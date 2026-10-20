<?php

namespace App\Console\Commands;

use App\Services\N2N\Messaging\Planner;
use Illuminate\Console\Command;

/** Send due Naara Planner reminders, once per person per event (MASTER-ONLY). Scheduled every minute by N2NServiceProvider. */
class N2NEventReminders extends Command
{
    protected $signature = 'n2n:event-reminders';

    protected $description = 'Send Naara Planner event reminders (exactly once per recipient)';

    public function handle(Planner $planner): int
    {
        $n = $planner->remindDue();
        $this->info("Sent {$n} event reminder(s).");

        return self::SUCCESS;
    }
}
