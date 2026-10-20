<?php

namespace App\Support\Business;

use App\Models\AppearancePreset;
use App\Models\User;
use App\Services\Business\BusinessAccess;
use App\Support\Appearance\AppearanceResolver;
use App\Support\FeatureEntitlements;

/**
 * Premium dashboard skins for Naara Pro (MASTER-ONLY). While the admin switch "Reserve premium skins for Naara Pro" is on, a skin whose admin access is
 * "pro" (Admin > Appearance) can only be used by a member whose plan includes premium skins. Admins are never locked out. A member whose plan lapses sees the
 * platform default again; their saved choice is kept, so renewing brings the skin straight back. Off (the default): every enabled skin is open, as before.
 */
class PremiumSkins
{
    /** @var array<int, array<string, bool>> per-request memo: user id => skin => locked */
    private static array $memo = [];

    public static function active(): bool
    {
        return FeatureEntitlements::isMaster() && BusinessSettings::premiumSkinsReserved();
    }

    /** @return list<string> the skin keys that need a plan right now */
    public static function reserved(): array
    {
        if (! self::active()) {
            return [];
        }

        return AppearancePreset::query()->where('kind', AppearancePreset::SKIN)->where('enabled', true)->where('access', 'pro')->pluck('key')->all();
    }

    public static function lockedFor(?User $user, string $skin): bool
    {
        if (! self::active() || $user === null || AppearanceResolver::isAdmin($user)) {
            return false;
        }
        if (isset(self::$memo[$user->id][$skin])) {
            return self::$memo[$user->id][$skin];
        }
        $needs = in_array($skin, self::reserved(), true);

        return self::$memo[$user->id][$skin] = $needs && ! app(BusinessAccess::class)->premiumSkins($user);
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
