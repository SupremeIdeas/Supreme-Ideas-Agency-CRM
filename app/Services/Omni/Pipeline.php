<?php

namespace App\Services\Omni;

use App\Models\ChannelConnection;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniIdentity;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniRawEvent;
use App\Services\Omni\Dto\NormalizedEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Raw event to inbox (MASTER-ONLY, blueprint Phase 1). Every step is idempotent: a replayed webhook, a duplicated message id or a status that arrives
 * twice changes nothing. A status can never move a message backwards (read stays read). Everything is scoped by the connection's workspace, so one
 * workspace's data can never be written into, or read from, another's.
 */
class Pipeline
{
    public function __construct(private readonly AdapterRegistry $registry)
    {
    }

    /** @return bool false when a status arrived before the message it is about (the job retries shortly); true otherwise */
    public function process(OmniRawEvent $raw): bool
    {
        $connection = ChannelConnection::query()->find($raw->connection_id);
        $adapter = $connection ? $this->registry->for((string) $connection->channel) : null;
        if (! $connection || ! $adapter) {
            $raw->forceFill(['status' => 'failed', 'error' => 'unknown connection or channel', 'processed_at' => now()])->save();

            return true;
        }
        $workspace = (int) $connection->wallet_group_id;
        $complete = true;
        foreach ($adapter->events((array) $raw->payload, $connection) as $event) {
            if ($event->externalId === '') {
                continue;
            }
            $ok = $event->type === NormalizedEvent::STATUS ? $this->status($workspace, $connection, $event) : $this->message($workspace, $connection, $event);
            $complete = $complete && $ok;
        }
        if ($complete) {
            $raw->forceFill(['status' => 'done', 'error' => null, 'processed_at' => now()])->save();
        }

        return $complete;
    }

    private function message(int $workspace, ChannelConnection $connection, NormalizedEvent $e): bool
    {
        $filed = null;
        $ok = (bool) DB::transaction(function () use ($workspace, $connection, $e, &$filed) {
            $contact = $this->contactFor($workspace, $connection->channel, $e);
            $conversation = $this->conversationFor($workspace, $connection, $contact);
            $at = $e->at ?? now();
            try {
                $filed = OmniMessage::query()->create([
                    'workspace_id' => $workspace, 'conversation_id' => $conversation->id, 'direction' => 'in', 'status' => 'received',
                    'provider_message_id' => $e->providerMessageId, 'body' => $e->text, 'media' => $e->media ?: null, 'sent_at' => $at,
                ]);
            } catch (UniqueConstraintViolationException) {
                return true;   // the same customer message again: nothing to do
            }
            $conversation->forceFill([
                'last_preview' => mb_substr((string) ($e->text ?? ($e->media ? '[attachment]' : '')), 0, 160), 'last_message_at' => $at, 'last_inbound_at' => $at,
                'unread' => $conversation->unread + 1, 'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
            ])->save();
            $contact->forceFill(['last_seen_at' => $at])->save();

            return true;
        });
        if ($filed instanceof OmniMessage && class_exists(\App\Jobs\Omni\RunOmniAutomation::class)) {
            \App\Jobs\Omni\RunOmniAutomation::dispatch($filed->id)->afterCommit();   // flows, AI agent and webhooks run in the queue, never in the webhook request
        }

        return $ok;
    }

    private function status(int $workspace, ChannelConnection $connection, NormalizedEvent $e): bool
    {
        if ($e->providerMessageId === null || ! in_array($e->status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return true;
        }
        $message = OmniMessage::query()->forWorkspace($workspace)->where('direction', 'out')->where('provider_message_id', $e->providerMessageId)
            ->whereIn('conversation_id', OmniConversation::query()->forWorkspace($workspace)->where('connection_id', $connection->id)->select('id'))->first();
        if (! $message) {
            return false;   // the status beat our own bookkeeping: retry shortly
        }
        if ($e->status === 'failed') {
            if (in_array($message->status, ['queued', 'sent'], true)) {
                $message->forceFill(['status' => 'failed', 'error' => mb_substr((string) $e->error, 0, 240) ?: 'failed at the provider'])->save();
            }

            return true;
        }
        if ((OmniMessage::RANK[$e->status] ?? 0) > (OmniMessage::RANK[$message->status] ?? 0)) {
            $message->forceFill(['status' => $e->status])->save();
        }

        return true;
    }

    /** Same person across channels: a known identity wins, then a matching phone or email in this workspace, else a new contact. */
    private function contactFor(int $workspace, string $channel, NormalizedEvent $e): OmniContact
    {
        $identity = OmniIdentity::query()->where('workspace_id', $workspace)->where('channel', $channel)->where('external_id', $e->externalId)->first();
        if ($identity) {
            return $this->root(OmniContact::query()->forWorkspace($workspace)->findOrFail($identity->contact_id));
        }
        $contact = null;
        if ($e->phone) {
            $contact = OmniContact::query()->forWorkspace($workspace)->whereNull('merged_into_id')->where('phone', $e->phone)->first();
        }
        if (! $contact && $e->email) {
            $contact = OmniContact::query()->forWorkspace($workspace)->whereNull('merged_into_id')->where('email', strtolower($e->email))->first();
        }
        $contact ??= OmniContact::query()->create(['workspace_id' => $workspace, 'name' => $e->name, 'phone' => $e->phone, 'email' => $e->email ? strtolower($e->email) : null]);
        $contact->fill(array_filter(['name' => $contact->name ?: $e->name, 'phone' => $contact->phone ?: $e->phone, 'email' => $contact->email ?: ($e->email ? strtolower($e->email) : null)]))->save();
        try {
            OmniIdentity::query()->create(['workspace_id' => $workspace, 'contact_id' => $contact->id, 'channel' => $channel, 'external_id' => $e->externalId]);
        } catch (UniqueConstraintViolationException) {
            // a concurrent worker created it first: use that one
            $identity = OmniIdentity::query()->where('workspace_id', $workspace)->where('channel', $channel)->where('external_id', $e->externalId)->firstOrFail();

            return $this->root(OmniContact::query()->forWorkspace($workspace)->findOrFail($identity->contact_id));
        }

        return $contact;
    }

    private function root(OmniContact $c): OmniContact
    {
        $guard = 0;
        while ($c->merged_into_id && $guard++ < 10) {
            $c = OmniContact::query()->forWorkspace((int) $c->workspace_id)->findOrFail($c->merged_into_id);
        }

        return $c;
    }

    private function conversationFor(int $workspace, ChannelConnection $connection, OmniContact $contact): OmniConversation
    {
        return OmniConversation::query()->forWorkspace($workspace)->where('contact_id', $contact->id)->where('connection_id', $connection->id)->orderByDesc('id')->first()
            ?? OmniConversation::query()->create(['workspace_id' => $workspace, 'contact_id' => $contact->id, 'connection_id' => $connection->id, 'channel' => $connection->channel])->refresh();
    }
}
