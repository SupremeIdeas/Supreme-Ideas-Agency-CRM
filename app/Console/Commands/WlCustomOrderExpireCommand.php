<?php

namespace App\Console\Commands;

use App\Services\WhiteLabel\Funnel\WlCustomOrders;
use Illuminate\Console\Command;

/** White Label custom orders (MASTER-ONLY): a quote nobody answered stops being payable once its validity has passed. */
class WlCustomOrderExpireCommand extends Command
{
    protected $signature = 'wl-custom-orders:expire';

    protected $description = 'Expire White Label custom-order quotes past their validity.';

    public function handle(WlCustomOrders $orders): int
    {
        $this->info('Expired '.$orders->expireStale().' quote(s).');

        return self::SUCCESS;
    }
}
