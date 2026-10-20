<?php

namespace App\Console\Commands;

use App\Services\N2N\Realtime\Realtime;
use Illuminate\Console\Command;

/** Deletes realtime events past their TTL (N2N, MASTER-ONLY). Registered with the scheduler heartbeat by the N2N provider. */
class N2NPruneRealtime extends Command
{
    protected $signature = 'n2n:prune-realtime';

    protected $description = 'Prune expired N2N realtime events (master only)';

    public function handle(): int
    {
        $this->info('Pruned '.Realtime::prune().' event(s).');

        return self::SUCCESS;
    }
}
