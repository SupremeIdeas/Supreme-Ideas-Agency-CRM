<?php

namespace App\Listeners;

use App\Events\OtpReceived;
use App\Models\MerchantWidgetOrder;
use App\Services\MerchantWidgets\MerchantWidgetFulfilmentService;

/** Naara Widgets (MASTER-ONLY): a verification number's code arrived, so the merchant's margin on that widget order is now earned. */
class MerchantWidgetAccrueNumberEarning
{
    public function handle(OtpReceived $event): void
    {
        $order = MerchantWidgetOrder::query()->with(['merchant', 'buyer'])->where('sms_order_id', $event->smsOrderId)->where('kind', 'number')->first();
        if ($order !== null) {
            app(MerchantWidgetFulfilmentService::class)->accrueNumber($order);
        }
    }
}
