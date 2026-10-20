<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledChannelMessageJob;
use App\Models\ScheduledChannelMessage;
use Illuminate\Console\Command;

/** Queue every shared-inbox message whose time has come (MASTER-ONLY, Business Suite). Scheduled every minute by BusinessSuiteServiceProvider. */
class BusinessSendScheduled extends Command
{
    protected $signature = 'business:send-scheduled';

    protected $description = 'Deliver due scheduled messages in the Business shared inbox';

    public function handle(): int
    {
        $n = 0;
        ScheduledChannelMessage::query()->where('status', ScheduledChannelMessage::PENDING)->where('send_at', '<=', now())->orderBy('send_at')->limit(200)->pluck('id')
            ->each(function (int $id) use (&$n) {
                // claim first: two overlapping runs can never queue the same message twice
                if (ScheduledChannelMessage::query()->whereKey($id)->where('status', ScheduledChannelMessage::PENDING)->update(['status' => ScheduledChannelMessage::SENDING]) === 1) {
                    SendScheduledChannelMessageJob::dispatch($id);
                    $n++;
                }
            });
        // a message claimed but never finished (worker died) is failed after 30 minutes rather than left spinning
        ScheduledChannelMessage::query()->where('status', ScheduledChannelMessage::SENDING)->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => ScheduledChannelMessage::FAILED, 'error' => 'The send did not finish. Check the thread before sending again.']);
        $this->info("Queued {$n} scheduled message(s).");

        return self::SUCCESS;
    }
}
