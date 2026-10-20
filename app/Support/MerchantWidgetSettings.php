<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-owned knobs for Naara Widgets (MASTER-ONLY, blueprint §12). Off by default; every limit is a setting, never a literal.
 */
class MerchantWidgetSettings
{
    public const FLAG = 'merchant_widgets.enabled';

    public const MAX_KEYS = 'merchant_widgets.max_keys_per_merchant';

    public const CATALOGUE_RATE = 'merchant_widgets.catalogue_rate_per_min';

    public const CHECKOUT_RATE = 'merchant_widgets.checkout_rate_per_min';

    public const FIRST_TIME_GUEST_CAP_USD = 'merchant_widgets.first_time_guest_cap_usd';

    public const SESSION_TTL_MINUTES = 'merchant_widgets.session_ttl_minutes';

    public const WP_PLUGIN_URL = 'merchant_widgets.wp_plugin_download_url';

    public const VELOCITY_PER_KEY_HOUR = 'merchant_widgets.velocity_sessions_per_key_hour';

    public const NUMBERS_ENABLED = 'merchant_widgets.numbers_enabled';

    public const NUMBER_SERVICES = 'merchant_widgets.number_services';

    public const ACCOUNT_ENABLED = 'merchant_widgets.account_enabled';

    public const SHOPPER_SESSION_DAYS = 'merchant_widgets.shopper_session_days';

    public const SHOPPER_CODE_MINUTES = 'merchant_widgets.shopper_code_minutes';

    public const PAID_ACCESS = 'merchant_widgets.paid_access';

    public const ACCESS_PRICE_USD = 'merchant_widgets.access_price_usd';

    /** The verification-number services a widget offers by default (provider product slugs): the ones people actually buy numbers for. */
    public const DEFAULT_NUMBER_SERVICES = ['whatsapp', 'telegram', 'google', 'facebook', 'instagram', 'tiktok', 'twitter', 'openai', 'uber', 'paypal', 'amazon', 'microsoft', 'discord', 'snapchat', 'linkedin', 'netflix', 'apple', 'viber', 'wechat', 'signal'];

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    public static function maxKeys(): int
    {
        return max(1, (int) Setting::getValue(self::MAX_KEYS, 5));
    }

    public static function catalogueRate(): int
    {
        return max(1, (int) Setting::getValue(self::CATALOGUE_RATE, 120));
    }

    public static function checkoutRate(): int
    {
        return max(1, (int) Setting::getValue(self::CHECKOUT_RATE, 10));
    }

    public static function firstTimeGuestCapUsd(): float
    {
        return (float) Setting::getValue(self::FIRST_TIME_GUEST_CAP_USD, 100);
    }

    public static function sessionTtlMinutes(): int
    {
        return max(1, (int) Setting::getValue(self::SESSION_TTL_MINUTES, 15));
    }

    public static function velocityPerKeyHour(): int
    {
        return max(1, (int) Setting::getValue(self::VELOCITY_PER_KEY_HOUR, 60));
    }

    public static function wpPluginUrl(): ?string
    {
        return ((string) Setting::getValue(self::WP_PLUGIN_URL, '')) ?: null;
    }

    /** Verification numbers through widgets: OFF until the admin switches it on (it moves real provider money). */
    public static function numbersEnabled(): bool
    {
        return (bool) Setting::getValue(self::NUMBERS_ENABLED, false);
    }

    /** @return list<string> */
    public static function numberServices(): array
    {
        $raw = Setting::getValue(self::NUMBER_SERVICES, null);
        $list = is_string($raw) ? preg_split('/[\s,]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) : (is_array($raw) ? array_map('strtolower', $raw) : []);

        return array_values(array_unique($list ?: self::DEFAULT_NUMBER_SERVICES));
    }

    /** The shopper "my orders" area on the merchant's site. On by default; it only ever shows a shopper their own orders. */
    public static function accountEnabled(): bool
    {
        return (bool) Setting::getValue(self::ACCOUNT_ENABLED, true);
    }

    public static function shopperSessionDays(): int
    {
        return max(1, min(90, (int) Setting::getValue(self::SHOPPER_SESSION_DAYS, 30)));
    }

    public static function shopperCodeMinutes(): int
    {
        return max(3, min(30, (int) Setting::getValue(self::SHOPPER_CODE_MINUTES, 10)));
    }

    /** Admin switch: merchants must buy access before they can create keys, copy the snippet or download the plugin. Off = free for everyone. */
    public static function paidAccess(): bool
    {
        return (bool) Setting::getValue(self::PAID_ACCESS, false);
    }

    public static function accessPriceUsd(): float
    {
        return max(0.0, round((float) Setting::getValue(self::ACCESS_PRICE_USD, 0), 2));
    }

    /** Paid only when the switch is on AND a price is set; a zero price is the same as free. */
    public static function isPaid(): bool
    {
        return self::paidAccess() && self::accessPriceUsd() > 0;
    }
}
