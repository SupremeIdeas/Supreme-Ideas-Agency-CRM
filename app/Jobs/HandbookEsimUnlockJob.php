<?php

namespace App\Jobs;

use App\Models\EsimOrder;
use App\Services\Handbooks\HandbookEsimUnlockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Unlock the handbook(s) an eSIM order earns (MASTER-ONLY). Idempotent, off the order's path; it can never fail or delay the order itself. A repeated failure alerts the admin. */
class HandbookEsimUnlockJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public function __construct(public int $esimOrderId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(HandbookEsimUnlockService $svc): void
    {
        $order = EsimOrder::query()->with('plan')->find($this->esimOrderId);
        if ($order) {
            $svc->onOrder($order);
        }
    }

    public function failed(\Throwable $e): void
    {
        AlertAdminJob::dispatch(code: 'handbook_esim_unlock_failed', message: 'Could not unlock the travel handbook for eSIM order #'.$this->esimOrderId.': '.$e->getMessage(), context: ['esim_order_id' => $this->esimOrderId]);
    }
}
