<?php

namespace App\Console\Commands;

use App\Services\N2N\Credit\CreditGifts;
use Illuminate\Console\Command;

/** Refunds expired pending credit gifts exactly once each (N2N, MASTER-ONLY). Safe to run twice and from a 1-minute cPanel cron. */
class N2NExpireGifts extends Command
{
    protected $signature = 'n2n:expire-gifts';

    protected $description = 'Refund expired N2N credit gifts (master only)';

    public function handle(CreditGifts $gifts): int
    {
        $this->info('Refunded '.$gifts->expireDue().' expired gift(s).');

        return self::SUCCESS;
    }
}
