<?php

namespace App\Support;

use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\User;
use RuntimeException;

/**
 * Narrow velocity + first-time-guest guard for Naara Widgets (MASTER-ONLY, blueprint §5.6). Modelled on GiftCardFraud: flag and
 * refuse (never silently block), every threshold an admin setting.
 */
class MerchantWidgetFraud
{
    /** Too many checkout sessions on one key in the last hour (a scraped or abused key). */
    public static function assertSessionAllowed(MerchantWidgetKey $key): void
    {
        $recent = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= MerchantWidgetSettings::velocityPerKeyHour()) {
            Auditor::log('merchant_widget.velocity_blocked', MerchantWidgetKey::class, $key->id, ['sessions_last_hour' => $recent]);

            throw new RuntimeException('Too many checkouts right now. Please try again shortly.');
        }
    }

    /** A brand-new guest (no completed widget order yet) may not exceed the admin cap on a single order. */
    public static function assertGuestWithinCap(User $buyer, MerchantWidgetOrder $order): void
    {
        $hasHistory = MerchantWidgetOrder::query()->where('buyer_user_id', $buyer->id)->where('status', MerchantWidgetOrder::COMPLETED)->exists();
        if (! $hasHistory && (float) $order->merchant_price_usd > MerchantWidgetSettings::firstTimeGuestCapUsd()) {
            Auditor::log('merchant_widget.guest_cap_blocked', MerchantWidgetOrder::class, $order->id, ['amount' => (float) $order->merchant_price_usd]);

            throw new RuntimeException('This purchase is above the limit for a first-time buyer. Please contact the merchant.');
        }
    }
}
