<?php

namespace App\Services\N2N\Credit;

use App\Models\Setting;
use App\Support\CreditSettings;

/**
 * N2N prices, server-authoritative (MASTER-ONLY). Every number is an admin setting. Media is NOT priced per "megabit sent": object storage
 * is billed per stored volume, so the admin enters an effective $/MB derived from the real bill, and the markup and a minimum-margin floor
 * are applied on top. The client never supplies a size or a duration that is trusted: callers pass sizes they measured server-side.
 */
class N2NPricing
{
    public static function messageCostCents(): int
    {
        return Money::cents(max(0.01, (float) Setting::getValue('n2n.price_message', 0.5)));
    }

    /** Admin $/MB, derived from the real storage bill and reviewed monthly. UNVERIFIED default: confirm against the actual Wasabi invoice. */
    public static function storageUsdPerMb(): float
    {
        return max(0.0, (float) Setting::getValue('n2n.storage_usd_per_mb', 0.00001));
    }

    public static function mediaMarkup(): float
    {
        return max(1.0, (float) Setting::getValue('n2n.media_markup', 3.0));
    }

    /** The cost can never be set below infra cost plus this margin, whatever the markup says. */
    public static function minMarginPct(): float
    {
        return max(0.0, (float) Setting::getValue('n2n.min_margin_pct', 30));
    }

    public static function mediaCostCents(int $bytes): int
    {
        $mb = max(0, $bytes) / 1048576;
        $perUsd = (float) CreditSettings::perUsd();
        $infra = $mb * self::storageUsdPerMb() * $perUsd;
        $priced = $infra * self::mediaMarkup();
        $floor = $infra * (1 + self::minMarginPct() / 100);

        return max(Money::ceilCents(max($priced, $floor)), $bytes > 0 ? 1 : 0);
    }

    /** USD per minute for a 1:1 call. Anchored by the owner to a reference rate; the floor is the measured infra cost times margin. */
    public static function callUsdPerMinute(): float
    {
        return max(0.0, (float) Setting::getValue('n2n.call_usd_per_min', 0.02));
    }

    public static function callRatePerMinuteCents(): int
    {
        return Money::ceilCents(self::callUsdPerMinute() * CreditSettings::perUsd());
    }

    /** Video costs more to relay than audio: the voice rate times an admin multiplier (never below the voice rate). */
    public static function videoMultiplier(): float
    {
        return max(1.0, (float) Setting::getValue('n2n.call_video_multiplier', 2.0));
    }

    public static function videoRatePerMinuteCents(): int
    {
        return Money::ceilCents(self::callUsdPerMinute() * self::videoMultiplier() * CreditSettings::perUsd());
    }

    public static function speakerUsdPerMinute(): float
    {
        return max(0.0, (float) Setting::getValue('n2n.speaker_usd_per_min', self::callUsdPerMinute()));
    }

    public static function speakerRatePerMinuteCents(): int
    {
        return Money::ceilCents(self::speakerUsdPerMinute() * CreditSettings::perUsd());
    }

    /** The worked example the admin panel shows, so the owner can see how a number comes about. */
    public static function workedExample(): array
    {
        $bytes = 5 * 1048576;
        $perUsd = CreditSettings::perUsd();

        return [
            'peg' => "{$perUsd} credits = 1 USD",
            'message' => Money::credits(self::messageCostCents()).' credits ($'.number_format(Money::cents(Money::credits(self::messageCostCents())) / 100 / $perUsd, 4).')',
            'media_5mb' => Money::credits(self::mediaCostCents($bytes)).' credits',
            'media_basis' => '5 MB x $'.rtrim(rtrim(number_format(self::storageUsdPerMb(), 8), '0'), '.').'/MB x '.self::mediaMarkup().' markup (floor: infra cost + '.self::minMarginPct().'%) x '.$perUsd.' credits/USD',
            'call_minute' => Money::credits(self::callRatePerMinuteCents()).' credits',
            'video_call_minute' => Money::credits(self::videoRatePerMinuteCents()).' credits',
        ];
    }
}
