<?php

namespace App\Services\Omni\Broadcast;

use App\Jobs\Omni\SendOmniBroadcast;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniBroadcast;
use App\Models\Omni\OmniBroadcastRecipient;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniIdentity;
use App\Services\Omni\AdapterRegistry;
use App\Services\Omni\Outbox;
use App\Services\Omni\WindowClosedException;

/**
 * Broadcasts (MASTER-ONLY, blueprint section 10). Only people who opted in and have not opted out are ever messaged; STOP removes a person at once. A channel
 * that needs a 24-hour window (WhatsApp, Instagram, Messenger) is refused until approved templates exist, so a broadcast can never break the window rule.
 * Sending is throttled (per-minute cap) and idempotent per recipient. A dry run shows exactly who would receive it without touching anyone.
 */
class BroadcastService
{
    public function __construct(private readonly AdapterRegistry $registry, private readonly Outbox $outbox)
    {
    }

    /** @return \Illuminate\Support\Collection<int,OmniContact> */
    public function audience(OmniBroadcast $b)
    {
        $ws = (int) $b->workspace_id;
        $conn = ChannelConnection::query()->where('wallet_group_id', $ws)->find($b->connection_id);
        if (! $conn) {
            return collect();
        }
        $q = OmniContact::query()->forWorkspace($ws)->whereNull('merged_into_id')->where('marketing_opt_in', true)->whereNull('opted_out_at')
            ->whereIn('id', OmniIdentity::query()->where('workspace_id', $ws)->where('channel', $conn->channel)->select('contact_id'));
        $seg = (array) $b->segment;
        if (! empty($seg['stage'])) {
            $q->where('stage', (string) $seg['stage']);
        }
        $people = $q->limit(5000)->get();
        if (! empty($seg['tag'])) {
            $people = $people->filter(fn ($c) => in_array((string) $seg['tag'], (array) $c->tags, true))->values();
        }

        return $people;
    }

    /** @return array{recipients:int,cost:float,blocked:?string,sample:list<string>} */
    public function dryRun(OmniBroadcast $b): array
    {
        $blocked = $this->blocker($b);
        $people = $blocked ? collect() : $this->audience($b);

        return ['recipients' => $people->count(), 'cost' => round($people->count() * (float) config('omni.broadcast_cost_per_message', 0.0), 4), 'blocked' => $blocked,
            'sample' => $people->take(5)->map(fn ($c) => (string) ($c->name ?: $c->email ?: $c->phone ?: 'Contact #'.$c->id))->all()];
    }

    /** Why this broadcast cannot be sent, in plain words; null when it can. */
    public function blocker(OmniBroadcast $b): ?string
    {
        $conn = ChannelConnection::query()->where('wallet_group_id', $b->workspace_id)->find($b->connection_id);
        if (! $conn || ! $conn->isActive()) {
            return 'Pick an active channel connection.';
        }
        $adapter = $this->registry->for((string) $conn->channel);
        if (! $adapter) {
            return 'That channel is not available.';
        }
        if ($adapter->requiresWindow()) {
            return 'This channel needs approved message templates for broadcasts. Use Telegram or web chat for now.';
        }
        if (trim((string) $b->body) === '') {
            return 'Write the message first.';
        }

        return null;
    }

    /** @throws \RuntimeException */
    public function start(OmniBroadcast $b, ?\DateTimeInterface $at = null): OmniBroadcast
    {
        if (! in_array($b->status, ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('This broadcast has already been sent or cancelled.');
        }
        if ($why = $this->blocker($b)) {
            throw new \RuntimeException($why);
        }
        $people = $this->audience($b);
        if ($people->isEmpty()) {
            throw new \RuntimeException('Nobody matches: only contacts who opted in on this channel can receive a broadcast.');
        }
        foreach ($people as $c) {
            OmniBroadcastRecipient::firstOrCreate(['broadcast_id' => $b->id, 'contact_id' => $c->id], ['workspace_id' => $b->workspace_id, 'status' => 'queued']);
        }
        if ($at && $at > now()) {
            $b->forceFill(['status' => 'scheduled', 'scheduled_at' => $at, 'stats' => ['total' => $people->count()]])->save();
        } else {
            $b->forceFill(['status' => 'sending', 'scheduled_at' => null, 'stats' => ['total' => $people->count()]])->save();
            SendOmniBroadcast::dispatch($b->id);
        }

        return $b;
    }

    public function cancel(OmniBroadcast $b): void
    {
        if (in_array($b->status, ['draft', 'scheduled', 'sending'], true)) {
            $b->forceFill(['status' => 'cancelled'])->save();
            OmniBroadcastRecipient::query()->forWorkspace((int) $b->workspace_id)->where('broadcast_id', $b->id)->where('status', 'queued')->update(['status' => 'skipped', 'reason' => 'Broadcast cancelled']);
        }
    }

    /** One throttled batch. Returns true when more remain. */
    public function sendBatch(OmniBroadcast $b): bool
    {
        if ($b->status !== 'sending') {
            return false;
        }
        $ws = (int) $b->workspace_id;
        $conn = ChannelConnection::query()->where('wallet_group_id', $ws)->find($b->connection_id);
        $rows = OmniBroadcastRecipient::query()->forWorkspace($ws)->where('broadcast_id', $b->id)->where('status', 'queued')->orderBy('id')->limit(max(1, (int) $b->per_minute))->get();
        foreach ($rows as $r) {
            $c = OmniContact::query()->forWorkspace($ws)->find($r->contact_id);
            if (! $c || ! $c->marketing_opt_in || $c->opted_out_at || ! $conn || ! $conn->isActive()) {
                $r->forceFill(['status' => 'skipped', 'reason' => 'Opted out or channel off'])->save();

                continue;
            }
            $conv = OmniConversation::query()->forWorkspace($ws)->where('contact_id', $c->id)->where('connection_id', $conn->id)->first()
                ?? OmniConversation::create(['workspace_id' => $ws, 'contact_id' => $c->id, 'connection_id' => $conn->id, 'channel' => $conn->channel, 'status' => 'open', 'unread' => 0]);
            try {
                $this->outbox->queue($ws, $conv, (string) $b->body, [], null, 'bc-'.$b->id.'-'.$c->id);
                $r->forceFill(['status' => 'sent', 'reason' => null])->save();
            } catch (WindowClosedException $e) {
                $r->forceFill(['status' => 'failed', 'reason' => 'Window closed'])->save();
            } catch (\Throwable $e) {
                $r->forceFill(['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 150)])->save();
            }
        }
        $left = OmniBroadcastRecipient::query()->forWorkspace($ws)->where('broadcast_id', $b->id)->where('status', 'queued')->exists();
        if (! $left) {
            $counts = OmniBroadcastRecipient::query()->forWorkspace($ws)->where('broadcast_id', $b->id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
            $b->forceFill(['status' => 'done', 'stats' => ['total' => array_sum($counts)] + array_map('intval', $counts)])->save();
        }

        return $left;
    }
}
