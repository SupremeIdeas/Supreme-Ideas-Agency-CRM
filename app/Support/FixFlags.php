<?php

namespace App\Support;

/**
 * Switches for the risky rewrites in the engineering blueprint (0.5). Each one ships OFF, is verified, then turned on from Admin settings.
 * Stored in the normal settings table under `fix.<name>`; nothing here changes behaviour until a feature asks `FixFlags::on()`.
 */
final class FixFlags
{
    public const FLAGS = [
        'wallet_v2' => 'Wallet tabs and quick amounts rebuilt',
        'chat_profile_v2' => 'Naara Chat profile and discovery',
        'chat_calls_v2' => 'In-call and incoming-call screens',
        'messages_screen' => 'Dedicated NaaraLine Messages screen',
        'liquid_upload' => 'Liquid-fill upload progress everywhere',
        'brand_trial' => 'Brand Naara Pro free trial',
        'brand_links' => 'Brand custom links',
        'business_card' => 'Shareable Business Card',
        'connect_journey' => 'Connect and referral journey',
        'unified_router' => 'Unified messaging and calling router',
    ];

    public static function on(string $flag): bool
    {
        if (! isset(self::FLAGS[$flag])) {
            throw new \InvalidArgumentException("Unknown fix flag [{$flag}].");
        }

        try {
            return (bool) \App\Models\Setting::getValue('fix.'.$flag, false);
        } catch (\Throwable) {
            return false; // before the settings table exists, everything risky stays off
        }
    }
}
