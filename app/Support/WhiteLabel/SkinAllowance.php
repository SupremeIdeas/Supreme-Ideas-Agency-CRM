<?php

namespace App\Support\WhiteLabel;

use App\Models\Setting;
use App\Models\WhiteLabelInstance;
use Illuminate\Support\Facades\Cache;

/**
 * BOUNDARY: master-only. This class decides how many skins a white-label licence unlocks and issues that number to forks. It must
 * NEVER exist in a white-label build (normal or Extended) — a child is a consumer + a leaf puller, never a hub; it receives the
 * number in the entitlement payload and obeys it. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md. CI fitness tests in
 * the child repos fail the build if this reappears there.
 *
 * Resolution (mirrors FeatureLocks): per-instance override > admin-saved tier default > config/white_label_skins.php tier default >
 * minimum. Always clamped to [minimum, catalogue size].
 */
class SkinAllowance
{
    public const SETTING_KEY = 'white_label.skins.allowance_by_tier';

    private const CACHE_KEY = 'white_label.skins.allowance.v1';

    /** The allowance for one licence tier. An unknown or null tier gets the minimum. */
    public static function forTier(?string $tier): int
    {
        if ($tier === null || ! in_array($tier, WhiteLabelInstance::TIERS, true)) {
            return self::minimum();
        }

        return self::clamp(self::tierMap()[$tier] ?? self::minimum());
    }

    /** The allowance one registered instance is told to enforce. */
    public static function forInstance(WhiteLabelInstance $instance): int
    {
        $override = $instance->skin_allowance_override ?? null;
        if (is_numeric($override) && (int) $override >= 1) {
            return self::clamp((int) $override);
        }

        return self::forTier($instance->tier);
    }

    /** @return array<string, int> tier => allowance (effective, after saved overrides) */
    public static function tierMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $map = (array) config('white_label_skins.allowance', []);
            try {
                $saved = Setting::getValue(self::SETTING_KEY, []);
            } catch (\Throwable) {
                $saved = [];
            }
            foreach (is_array($saved) ? $saved : [] as $tier => $n) {
                if (in_array($tier, WhiteLabelInstance::TIERS, true) && is_numeric($n)) {
                    $map[$tier] = (int) $n;
                }
            }

            return array_map(fn ($n) => self::clamp((int) $n), $map);
        });
    }

    /** Persist the tier defaults an admin edited (validated, clamped). */
    public static function saveTierDefaults(array $byTier): void
    {
        $clean = [];
        foreach (WhiteLabelInstance::TIERS as $tier) {
            if (isset($byTier[$tier]) && is_numeric($byTier[$tier])) {
                $clean[$tier] = self::clamp((int) $byTier[$tier]);
            }
        }
        Setting::setValue(self::SETTING_KEY, $clean, 'white_label');
        self::bust();
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function minimum(): int
    {
        return max(1, (int) config('white_label_skins.minimum', 1));
    }

    public static function catalogueSize(): int
    {
        return max(self::minimum(), count((array) config('appearance.skins', [])));
    }

    public static function clamp(int $n): int
    {
        return max(self::minimum(), min(self::catalogueSize(), $n));
    }
}
