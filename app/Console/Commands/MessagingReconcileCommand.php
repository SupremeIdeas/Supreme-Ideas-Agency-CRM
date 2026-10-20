<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\ConversationEvent;
use App\Models\InboundMessage;
use App\Models\N2N\Message as N2NMessage;
use App\Models\OutboundMessage;
use App\Models\VoiceCall;
use App\Services\Messaging\ConversationTimeline;
use App\Services\N2N\Messaging\ConversationEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * messaging:reconcile (Brand-Growth Phase 9, MASTER-ONLY). The safety net under the unified conversation timeline: rebuilds any timeline line that is missing (a hook was
 * skipped or failed) or whose status drifted from its source row, and flags SMS stuck in `queued`. Pure bookkeeping: it sends nothing, charges nothing and refunds nothing
 * (a stuck message may or may not have left the provider, so a person decides). Safe to run any time and as often as you like.
 */
class MessagingReconcileCommand extends Command
{
    protected $signature = 'messaging:reconcile {--dry : Report what is missing without writing}';

    protected $description = 'Rebuild missing or stale conversation timeline lines and flag SMS stuck in queued';

    public function handle(ConversationTimeline $timeline): int
    {
        $dry = (bool) $this->option('dry');
        $fixed = 0;

        $fixed += $this->sweep('outbound_sms', OutboundMessage::query(), fn ($m) => $timeline->fromOutboundSms($m), $dry);
        $fixed += $this->sweep('inbound_sms', InboundMessage::query(), fn ($m) => $timeline->fromInboundSms($m), $dry);
        $fixed += $this->sweep('voice_call', VoiceCall::query(), fn ($c) => $timeline->fromCall($c), $dry);
        $fixed += $this->sweep('n2n_message', N2NMessage::query(), function ($m) { ConversationEventRecorder::n2n($m); }, $dry);

        // A status that drifted from its source (e.g. a delivery report landed while a hook was skipped).
        $drift = 0;
        OutboundMessage::query()->whereIn('id', ConversationEvent::query()->where('source_type', 'outbound_sms')->select('source_id'))->orderBy('id')->chunkById(500, function ($rows) use ($timeline, $dry, &$drift) {
            $events = ConversationEvent::query()->where('source_type', 'outbound_sms')->whereIn('source_id', $rows->pluck('id'))->get()->keyBy('source_id');
            foreach ($rows as $m) {
                if (($events[$m->id]->status ?? $m->status) !== $m->status) {
                    $drift++;
                    $dry || $timeline->fromOutboundSms($m);
                }
            }
        });

        // Stuck in queued for 30+ minutes: the job never finished. Alert once per message; never refund blindly.
        $stuck = OutboundMessage::query()->where('status', 'queued')->where('created_at', '<', now()->subMinutes(30))->get();
        foreach ($stuck as $m) {
            if (! $dry && Cache::add('sms-stale-queued:'.$m->id, 1, now()->addDay())) {
                AlertAdminJob::dispatch(code: 'sms_stale_queued', message: "SMS {$m->reference} has been queued for over 30 minutes. Check whether it left the provider before refunding.", context: ['message_id' => $m->id]);
            }
        }

        $this->info(($dry ? '[dry] ' : '')."Timeline lines rebuilt: {$fixed}. Statuses corrected: {$drift}. SMS stuck in queued: {$stuck->count()}.");

        return self::SUCCESS;
    }

    /** Re-record every source row that has no timeline line for its person yet. Returns how many were missing. */
    private function sweep(string $type, $query, callable $record, bool $dry): int
    {
        $missing = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use ($type, $record, $dry, &$missing) {
            $have = ConversationEvent::query()->where('source_type', $type)->whereIn('source_id', $rows->pluck('id'))->pluck('source_id')->flip();
            foreach ($rows as $m) {
                if (! isset($have[$m->id])) {
                    $missing++;
                    $dry || $record($m);
                }
            }
        });

        return $missing;
    }
}
