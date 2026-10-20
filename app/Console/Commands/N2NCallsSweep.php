<?php

namespace App\Console\Commands;

use App\Services\N2N\Calls\CallService;
use App\Support\SchedulerHealth;
use Illuminate\Console\Command;

class N2NCallsSweep extends Command
{
    protected $signature = 'n2n:calls-sweep';

    protected $description = 'N2N (master only): turn unanswered rings into missed calls and settle calls whose side stopped sending heartbeats';

    public function handle(CallService $calls): int
    {
        $n = $calls->sweep();
        $this->info("Calls changed: {$n}");

        return self::SUCCESS;
    }
}
