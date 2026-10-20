<?php

namespace App\Console\Commands;

use App\Services\N2N\Credit\CreditAccounting;
use App\Services\N2N\Credit\GiftVelocity;
use Illuminate\Console\Command;

/** Nightly: proves the usage ledger equals the wallets and scans for gift abuse (N2N, MASTER-ONLY). Alerts an admin on any discrepancy. */
class N2NCreditReconcile extends Command
{
    protected $signature = 'n2n:credit-reconcile';

    protected $description = 'Reconcile N2N usage-credit ledger to wallets and scan gift velocity (master only)';

    public function handle(CreditAccounting $acc, GiftVelocity $velocity): int
    {
        $bad = $acc->reconcile();
        foreach ($bad as $line) {
            $this->error($line);
        }
        if ($bad !== []) {
            \App\Jobs\AlertAdminJob::dispatch('n2n_credit_ledger_mismatch', 'N2N usage-credit ledger does not reconcile with the wallets.', ['rows' => array_slice($bad, 0, 10)]);
        }
        $flags = $velocity->scan();
        $this->info('Ledger '.($bad === [] ? 'reconciled' : 'MISMATCH').'; '.count($flags).' velocity flag(s).');

        return $bad === [] ? self::SUCCESS : self::FAILURE;
    }
}
