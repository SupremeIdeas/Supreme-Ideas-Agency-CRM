<?php

namespace App\Services\Omni;

use App\Models\ChannelConnection;
use App\Models\Omni\OmniAgentRun;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniRawEvent;

/** Respond numbers for one workspace (MASTER-ONLY, blueprint sections 12 and 13): volume, speed, what the AI handled, and channel health. All scoped by workspace. */
class OmniAnalytics
{
    /** @return array<string,mixed> */
    public function summary(int $ws, int $days = 7): array
    {
        $since = now()->subDays(max(1, min(90, $days)));
        $conv = OmniConversation::query()->forWorkspace($ws)->where('created_at', '>=', $since);
        $in = OmniMessage::query()->forWorkspace($ws)->where('direction', 'in')->where('created_at', '>=', $since)->count();
        $out = OmniMessage::query()->forWorkspace($ws)->where('direction', 'out')->where('created_at', '>=', $since);
        $runs = OmniAgentRun::query()->forWorkspace($ws)->where('created_at', '>=', $since)->selectRaw('outcome, count(*) n, sum(tokens_in) ti, sum(tokens_out) tout')->groupBy('outcome')->get()->keyBy('outcome');
        $ai = fn (string $k) => (int) ($runs[$k]->n ?? 0);

        return [
            'days' => $days,
            'conversations' => (clone $conv)->count(),
            'resolved' => OmniConversation::query()->forWorkspace($ws)->where('status', 'resolved')->where('updated_at', '>=', $since)->count(),
            'messages_in' => $in,
            'messages_out' => (clone $out)->count(),
            'failed_out' => (clone $out)->where('status', 'failed')->count(),
            'median_first_response_min' => $this->medianFirstResponse($ws, $since),
            'by_channel' => (clone $conv)->selectRaw('channel, count(*) n')->groupBy('channel')->pluck('n', 'channel')->all(),
            'ai' => ['answered' => $ai('answered'), 'suggested' => $ai('suggested'), 'handoff' => $ai('handoff') + $ai('refused'), 'capped' => $ai('capped'), 'errors' => $ai('error'),
                'tokens' => (int) $runs->sum('ti') + (int) $runs->sum('tout')],
        ];
    }

    /** Median minutes from a customer's first message to the first reply, over conversations created in the window (capped at 1,000 for speed). */
    private function medianFirstResponse(int $ws, $since): ?float
    {
        $ids = OmniConversation::query()->forWorkspace($ws)->where('created_at', '>=', $since)->orderByDesc('id')->limit(1000)->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }
        $rows = OmniMessage::query()->forWorkspace($ws)->whereIn('conversation_id', $ids)->orderBy('id')->get(['conversation_id', 'direction', 'created_at']);
        $first = [];
        $deltas = [];
        foreach ($rows as $m) {
            $k = $m->conversation_id;
            if ($m->direction === 'in' && ! isset($first[$k])) {
                $first[$k] = $m->created_at;
            } elseif ($m->direction === 'out' && isset($first[$k]) && $first[$k] !== true) {
                $deltas[] = max(0, $first[$k]->diffInSeconds($m->created_at, false)) / 60;
                $first[$k] = true;
            }
        }
        if ($deltas === []) {
            return null;
        }
        sort($deltas);
        $mid = intdiv(count($deltas), 2);

        return round(count($deltas) % 2 ? $deltas[$mid] : ($deltas[$mid - 1] + $deltas[$mid]) / 2, 1);
    }

    /** One row per connection for the health console: is it on, when did we last hear from it, how many sends failed, is the intake queue backing up. */
    public function health(int $ws): array
    {
        $rows = [];
        foreach (ChannelConnection::query()->where('wallet_group_id', $ws)->orderBy('channel')->get() as $c) {
            $convIds = OmniConversation::query()->forWorkspace($ws)->where('connection_id', $c->id)->select('id');
            $failed = OmniMessage::query()->forWorkspace($ws)->where('direction', 'out')->where('status', 'failed')->where('created_at', '>=', now()->subDay())->whereIn('conversation_id', $convIds)->count();
            $backlog = OmniRawEvent::query()->where('connection_id', $c->id)->where('status', 'pending')->where('received_at', '<', now()->subMinutes(5))->count();
            $stuckFailed = OmniRawEvent::query()->where('connection_id', $c->id)->where('status', 'failed')->where('received_at', '>=', now()->subDay())->count();
            $level = match (true) {
                $c->status !== ChannelConnection::ACTIVE || $backlog > 0 => 'red',
                $failed > 0 || $stuckFailed > 0 || $c->last_error => 'amber',
                default => 'green',
            };
            $rows[] = ['id' => $c->id, 'name' => ChannelConnection::label($c->channel), 'label' => $c->label, 'status' => $c->status, 'last_event_at' => $c->last_event_at, 'failed_sends_24h' => $failed,
                'backlog' => $backlog, 'level' => $level, 'note' => match ($level) {
                    'red' => $c->status !== ChannelConnection::ACTIVE ? 'Switched off or paused.' : 'Incoming messages are waiting to be processed. Check that the queue worker is running.',
                    'amber' => $failed > 0 ? $failed.' message(s) could not be sent in the last day.' : ($c->last_error ?: 'Some events could not be read.'),
                    default => 'Working.',
                }];
        }

        return $rows;
    }
}
