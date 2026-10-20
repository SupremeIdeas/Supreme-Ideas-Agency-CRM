<?php

namespace App\Services\N2N\Media;

use App\Models\BrandPartner;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Primitive P4 (N2N, MASTER-ONLY): Naara Pro = the user's brand is on an active paid subscription, or has an admin grant. This reuses
 * BrandPartner::isNaaraPro(), so a lapsed or cancelled subscription (including the past-due grace period) is NOT Pro. The answer is cached
 * for a short time and busted the instant a subscription or grant changes, so a lapse can never leave Pro on longer than the TTL.
 */
class NaaraPro
{
    private const TTL_SECONDS = 60;

    public static function active(User|int $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return (bool) Cache::remember(self::key($id), self::TTL_SECONDS, function () use ($id) {
            $brand = BrandPartner::query()->where('owner_user_id', $id)->with('subscription')->first();

            return $brand !== null && $brand->is_active && $brand->isNaaraPro();
        });
    }

    public static function bust(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    private static function key(int $id): string
    {
        return 'n2n:pro:'.$id;
    }
}
