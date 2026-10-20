<?php

namespace App\Console\Commands;

use App\Models\WlFunnelManualPayment;
use App\Services\WhiteLabel\Funnel\WlInvoiceIssuer;
use Illuminate\Console\Command;

/** Issue documents for any approved White Label payment that does not have them yet (MASTER-ONLY). Scheduled every ten minutes; safe to run any time. */
class WlInvoicesRetryCommand extends Command
{
    protected $signature = 'wl-invoices:retry';

    protected $description = 'Issue missing invoices and receipts for approved White Label payments';

    public function handle(WlInvoiceIssuer $issuer): int
    {
        $done = $failed = 0;
        WlFunnelManualPayment::query()->where('status', 'approved')->whereNotNull('white_label_instance_id')->orderBy('id')->limit(500)->get()
            ->reject(fn ($p) => \App\Models\WlInvoice::query()->where('source_key', 'mp:'.$p->id.($p->isBalance() ? ':receipt' : ':invoice'))->exists())
            ->each(function ($p) use ($issuer, &$done, &$failed) {
                try {
                    $p->isBalance() ? $issuer->forBalancePayment($p) : $issuer->forApprovedPayment($p);
                    $done++;
                } catch (\Throwable $e) {
                    report($e);
                    $failed++;
                }
            });
        $this->info("Issued documents for {$done} payment(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
