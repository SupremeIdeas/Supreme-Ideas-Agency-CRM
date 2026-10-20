<?php

namespace App\Services\N2N\Brands;

use App\Models\BrandPartner;
use App\Support\N2N\N2NSettings;

/**
 * Profile limits (N2N Batch 8, MASTER-ONLY). Every number is an admin Setting (n2n.brand_limits.*), with the blueprint values as defaults.
 * Music limits apply ONLY after an admin confirmed the profile (music_verified_at): asking for "music" unlocks nothing by itself.
 */
class BrandLimits
{
    public const DEFAULTS = [
        'general' => ['youtube' => 5, 'vimeo' => 5, 'images' => 5, 'music_links' => 0, 'embeds' => 0, 'albums' => 0],
        'music' => ['youtube' => 20, 'vimeo' => 20, 'images' => 10, 'music_links' => 20, 'embeds' => 8, 'albums' => 12],
    ];

    public static function tier(BrandPartner $brand): string
    {
        return $brand->profile_type === 'music' && $brand->music_verified_at !== null ? 'music' : 'general';
    }

    public static function for(BrandPartner $brand, string $what): int
    {
        $tier = self::tier($brand);

        return N2NSettings::int("brand_limits.{$tier}.{$what}", self::DEFAULTS[$tier][$what] ?? 0, 0);
    }

    /** Albums an artist may keep: the admin number, or no limit at all for a brand on the highest plan (the setting `n2n.brand_limits.albums_unlimited_top_plan`, on by default). */
    public static function albumCap(BrandPartner $brand): int
    {
        if (self::tier($brand) !== 'music') {
            return 0;
        }
        if ((bool) \App\Models\Setting::getValue('n2n.brand_limits.albums_unlimited_top_plan', true) && self::onTopPlan($brand)) {
            return PHP_INT_MAX;
        }

        return self::for($brand, 'albums');
    }

    public static function onTopPlan(BrandPartner $brand): bool
    {
        try {
            $top = \App\Models\BrandSubscriptionPlan::query()->where('is_active', true)->orderByDesc('sort_order')->orderByDesc('id')->value('id');

            return $top !== null && (int) $brand->current_plan_id === (int) $top && $brand->isNaaraPro();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @throws BrandLimitException when adding one more of $what would exceed the cap */
    public static function assertRoom(BrandPartner $brand, string $what, int $current, string $noun): void
    {
        $cap = self::for($brand, $what);
        if ($current >= $cap) {
            throw new BrandLimitException('limit', $cap === 0
                ? "Your profile type does not include {$noun}."
                : "Your profile allows at most {$cap} {$noun}.");
        }
    }
}
