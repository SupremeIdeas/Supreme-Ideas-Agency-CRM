<?php

namespace App\Support\Brain;

use App\Models\Brain\BrainMessage;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Business\BusinessAccess;
use App\Support\Appearance\AppearanceResolver;

/**
 * Who may use NaaraBrain, how much, and at which depth (MASTER-ONLY). Tied to Naara Pro: a member with an active plan gets the Pro defaults unless the plan
 * carries its own NaaraBrain numbers (set per plan in Admin > Brand directory > plan), so a bigger plan can mean more questions and the deeper model. A member with
 * no plan is "free": allowed or not by the admin switch, at the free ceiling and the free depths. A lapsed plan simply falls back to free; nothing is deleted.
 * Admins are never limited.
 */
class BrainAccess
{
    public const ORDER = ['quick', 'balanced', 'deep'];

    public const TIER_ADMIN = 'admin';

    public const TIER_PRO = 'pro';

    public const TIER_FREE = 'free';

    public static function plan(User $user): ?BrandSubscriptionPlan
    {
        return class_exists(BusinessAccess::class) ? app(BusinessAccess::class)->planFor($user) : null;
    }

    public static function tier(User $user): string
    {
        if (AppearanceResolver::isAdmin($user)) {
            return self::TIER_ADMIN;
        }

        return self::plan($user) !== null ? self::TIER_PRO : self::TIER_FREE;
    }

    /** May this person ask NaaraBrain anything at all? Pro and admins always; free members only while the admin allows it. */
    public static function allowed(User $user): bool
    {
        return self::tier($user) !== self::TIER_FREE || (bool) Setting::getValue('brain.free_access', config('brain.free_access'));
    }

    /** Questions per day; null = unlimited (admins). */
    public static function dailyLimit(User $user): ?int
    {
        $tier = self::tier($user);
        if ($tier === self::TIER_ADMIN) {
            return null;
        }
        if ($tier === self::TIER_FREE) {
            return BrainSettings::int('daily_messages');
        }
        $own = (int) (self::plan($user)?->capabilities['brain']['daily'] ?? 0);

        return $own > 0 ? $own : BrainSettings::int('pro_daily');
    }

    /** @return list<string> depth keys, in quality order */
    public static function depths(User $user): array
    {
        $tier = self::tier($user);
        if ($tier === self::TIER_ADMIN) {
            return self::ORDER;
        }
        $own = $tier === self::TIER_PRO ? (array) (self::plan($user)?->capabilities['brain']['depths'] ?? []) : [];
        $list = $own !== [] ? $own : (array) Setting::getValue($tier === self::TIER_PRO ? 'brain.pro_depths' : 'brain.free_depths', config($tier === self::TIER_PRO ? 'brain.pro_depths' : 'brain.free_depths'));
        $ok = array_values(array_intersect(self::ORDER, array_map('strval', $list)));

        return $ok !== [] ? $ok : ['quick'];
    }

    /** The depth a question actually runs at: the requested one when allowed, else the best allowed depth at or below it (or the lightest allowed). */
    public static function effectiveDepth(User $user, ?string $requested): string
    {
        $allowed = self::depths($user);
        $want = BrainSettings::depth($requested);
        if (in_array($want, $allowed, true)) {
            return $want;
        }
        $rank = array_search($want, self::ORDER, true);
        $best = null;
        foreach ($allowed as $d) {
            if (array_search($d, self::ORDER, true) <= $rank) {
                $best = $d;
            }
        }

        return $best ?? $allowed[0];
    }

    public static function usedToday(User $user): int
    {
        return BrainMessage::query()->where('user_id', $user->id)->where('role', 'user')->where('created_at', '>=', now()->startOfDay())->count();
    }

    public static function remaining(User $user): ?int
    {
        $limit = self::dailyLimit($user);

        return $limit === null ? null : max(0, $limit - self::usedToday($user));
    }

    /** What the member sees as their plan: the Pro plan's name, "Naara Pro" for an admin grant, or "Free". */
    public static function label(User $user): string
    {
        return match (self::tier($user)) {
            self::TIER_ADMIN => 'Admin',
            self::TIER_PRO => (string) (self::plan($user)?->name ?: 'Naara Pro'),
            default => 'Free',
        };
    }

    /** Where to send someone who needs more: the Naara Pro page. */
    public static function upgradeUrl(): ?string
    {
        return \Illuminate\Support\Facades\Route::has('brand.get-listed') ? route('brand.get-listed') : null;
    }

    /** Clean the NaaraBrain numbers an admin enters on a plan. Blank/0 = use the Pro defaults. */
    public static function cleanPlanCaps(array $in): array
    {
        $out = [];
        $daily = (int) ($in['daily'] ?? 0);
        if ($daily > 0) {
            $out['daily'] = min($daily, 100000);
        }
        $depths = array_values(array_intersect(self::ORDER, array_map('strval', (array) ($in['depths'] ?? []))));
        if ($depths !== []) {
            $out['depths'] = $depths;
        }

        return $out;
    }
}
