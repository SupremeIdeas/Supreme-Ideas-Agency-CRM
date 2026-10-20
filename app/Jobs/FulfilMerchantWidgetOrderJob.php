<?php

namespace App\Jobs;

use App\Services\MerchantWidgets\MerchantWidgetFulfilmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Fulfil a paid Naara Widgets order (MASTER-ONLY). Unique per order; the service is itself idempotent. Never blind-retried. */
class FulfilMerchantWidgetOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return 'merchant-widget-fulfil:'.$this->orderId;
    }

    public function handle(MerchantWidgetFulfilmentService $service): void
    {
        $service->fulfil($this->orderId);
    }
}
