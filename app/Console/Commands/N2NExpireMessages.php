<?php

namespace App\Console\Commands;

use App\Services\N2N\Messaging\Disappearing;
use Illuminate\Console\Command;

/** Delete disappearing Naara chat messages that have expired (MASTER-ONLY). Scheduled every minute by N2NServiceProvider. */
class N2NExpireMessages extends Command
{
    protected $signature = 'n2n:expire-messages';

    protected $description = 'Delete expired disappearing messages and their stored media';

    public function handle(Disappearing $svc): int
    {
        $total = 0;
        do {
            $n = $svc->purge(500);
            $total += $n;
        } while ($n === 500);
        $wiped = app(\App\Services\N2N\Messaging\LiveLocations::class)->purgeEnded();   // positions of ended live shares
        $this->info("Deleted {$total} expired message(s); cleared {$wiped} ended live location(s).");

        return self::SUCCESS;
    }
}
