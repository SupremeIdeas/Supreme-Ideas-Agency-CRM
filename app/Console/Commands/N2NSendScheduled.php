<?php

namespace App\Console\Commands;

use App\Services\N2N\Messaging\ScheduledSend;
use Illuminate\Console\Command;

/** Deliver due scheduled N2N messages (MASTER-ONLY). Scheduled every minute by N2NServiceProvider. */
class N2NSendScheduled extends Command
{
    protected $signature = 'n2n:send-scheduled';

    protected $description = 'Send scheduled Naara chat messages that are due';

    public function handle(ScheduledSend $svc): int
    {
        $n = $svc->dispatchDue();
        $this->info("Sent {$n} scheduled message(s).");

        return self::SUCCESS;
    }
}
