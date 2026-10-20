<?php

namespace App\Services\Omni;

use App\Jobs\Omni\SendOmniMessage;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Outbound messages (MASTER-ONLY). queue() writes the row first and sends from a queued job (nothing external runs inside a request); the idempotency key
 * means a double click or a retried request creates one message and one send. A refused send is recorded as failed and is never retried on its own.
 */
class Outbox
{
    public function __construct(private readonly AdapterRegistry $registry)
    {
    }

    /** @throws WindowClosedException when the channel needs an open 24-hour window and there is none */
    public function queue(int $workspaceId, OmniConversation $conversation, ?string $text, array $media, ?int $authorId, string $idempotencyKey): OmniMessage
    {
        abort_unless((int) $conversation->workspace_id === $workspaceId, 404);   // another workspace's conversation does not exist here
        $adapter = $this->registry->for($conversation->channel);
        abort_unless($adapter !== null, 422, 'This channel is not available.');
        if ($adapter->requiresWindow() && ! $conversation->windowOpen()) {
            throw new WindowClosedException('The customer has not written in the last 24 hours. Send an approved template instead.');
        }
        try {
            $message = DB::transaction(function () use ($workspaceId, $conversation, $text, $media, $authorId, $idempotencyKey) {
                $m = OmniMessage::query()->create([
                    'workspace_id' => $workspaceId, 'conversation_id' => $conversation->id, 'direction' => 'out', 'status' => 'queued',
                    'idempotency_key' => substr($idempotencyKey, 0, 64), 'body' => $text, 'media' => $media ?: null, 'author_id' => $authorId,
                ]);
                $conversation->forceFill(['last_preview' => mb_substr((string) $text, 0, 160), 'last_message_at' => now(), 'unread' => 0])->save();

                return $m;
            });
        } catch (UniqueConstraintViolationException) {
            return OmniMessage::query()->forWorkspace($workspaceId)->where('conversation_id', $conversation->id)->where('idempotency_key', substr($idempotencyKey, 0, 64))->firstOrFail();
        }
        SendOmniMessage::dispatch($message->id);

        return $message;
    }

    /**
     * Send an approved WhatsApp template. A template is the one message WhatsApp allows outside the 24-hour window, so the window rule does not apply; it still
     * needs the same idempotency key and queued send as any other message. `$rendered` is the readable text shown in the thread.
     *
     * @param list<string> $params body parameters in order ({{1}}, {{2}}, ...)
     */
    public function queueTemplate(int $workspaceId, OmniConversation $conversation, string $name, string $language, array $params, string $rendered, ?int $authorId, string $idempotencyKey): OmniMessage
    {
        abort_unless((int) $conversation->workspace_id === $workspaceId, 404);
        abort_unless($conversation->channel === \App\Models\ChannelConnection::WHATSAPP, 422, 'Templates are only for WhatsApp.');
        try {
            $message = DB::transaction(function () use ($workspaceId, $conversation, $name, $language, $params, $rendered, $authorId, $idempotencyKey) {
                $m = OmniMessage::query()->create([
                    'workspace_id' => $workspaceId, 'conversation_id' => $conversation->id, 'direction' => 'out', 'status' => 'queued',
                    'idempotency_key' => substr($idempotencyKey, 0, 64), 'body' => $rendered, 'template' => ['name' => $name, 'language' => $language, 'params' => array_values(array_map('strval', $params))], 'author_id' => $authorId,
                ]);
                $conversation->forceFill(['last_preview' => mb_substr($rendered, 0, 160), 'last_message_at' => now(), 'unread' => 0])->save();

                return $m;
            });
        } catch (UniqueConstraintViolationException) {
            return OmniMessage::query()->forWorkspace($workspaceId)->where('conversation_id', $conversation->id)->where('idempotency_key', substr($idempotencyKey, 0, 64))->firstOrFail();
        }
        SendOmniMessage::dispatch($message->id);

        return $message;
    }

    public function deliver(int $messageId): void
    {
        DB::transaction(function () use ($messageId) {
            $message = OmniMessage::query()->lockForUpdate()->find($messageId);
            if (! $message || $message->status !== 'queued') {
                return;   // already sent, failed or gone: never send twice
            }
            $conversation = OmniConversation::query()->forWorkspace((int) $message->workspace_id)->find($message->conversation_id);
            $connection = $conversation?->connection_id ? ChannelConnection::query()->find($conversation->connection_id) : null;
            $adapter = $conversation ? $this->registry->for($conversation->channel) : null;
            $identity = $conversation ? \App\Models\Omni\OmniIdentity::query()->where('workspace_id', $message->workspace_id)->where('contact_id', $conversation->contact_id)->where('channel', $conversation->channel)->first() : null;
            if (! $connection || ! $adapter || ! $identity || (int) $connection->wallet_group_id !== (int) $message->workspace_id) {
                $message->forceFill(['status' => 'failed', 'error' => 'The channel connection is missing.'])->save();

                return;
            }
            try {
                $providerId = $adapter->send($connection, new OutboundDraft($identity->external_id, $message->body, (array) $message->media, $message->idempotency_key, $message->template));
                $message->forceFill(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'error' => null])->save();
            } catch (ChannelSendException $e) {
                $message->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 240)])->save();
            }
        });
    }
}
