<?php

namespace App\Console\Commands;

use App\Jobs\Omni\SendOmniBroadcast;
use App\Models\Omni\OmniBroadcast;
use Illuminate\Console\Command;

/** Starts scheduled Respond broadcasts whose time has come (MASTER-ONLY). Runs every minute from the scheduler. */
class OmniStartDueBroadcasts extends Command
{
    protected $signature = 'omni:start-due-broadcasts';

    protected $description = 'Start scheduled Naara Respond broadcasts that are due';

    public function handle(): int
    {
        $n = 0;
        OmniBroadcast::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('id')->limit(50)->get()->each(function (OmniBroadcast $b) use (&$n) {
            if ($b->forceFill(['status' => 'sending'])->save()) {
                SendOmniBroadcast::dispatch($b->id);
                $n++;
            }
        });
        $this->info("Started $n broadcast(s).");

        return self::SUCCESS;
    }
}
