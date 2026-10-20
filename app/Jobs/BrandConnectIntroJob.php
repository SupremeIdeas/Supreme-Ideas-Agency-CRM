<?php

namespace App\Jobs;

use App\Services\Brands\BrandConnectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one brand welcome message after a person connects (Brand-Growth Phase 6, MASTER-ONLY). Runs once; a failure is recorded on the request, never retried (it costs credit). */
class BrandConnectIntroJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $requestId)
    {
    }

    public function handle(BrandConnectService $svc): void
    {
        $svc->deliverIntro($this->requestId);
    }
}
