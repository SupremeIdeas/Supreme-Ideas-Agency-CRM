<?php

namespace App\Support\Handbooks;

/** Handbook price maths (MASTER-ONLY). The wallet is USD. The price a person paid is snapshotted on the entitlement and never recomputed from the current list price. */
class HandbookPricing
{
    /** Download upgrade for an Unlocked book: 50% of list, rounded UP to the next cent, so 9.99 becomes 5.00 and 6.99 becomes 3.50. */
    public static function upgradeUsd(float|string $list): float
    {
        $list = round((float) $list, 2);

        return $list <= 0 ? 0.0 : ceil(round($list * (float) config('handbooks.upgrade_fraction', 0.5) * 100, 6)) / 100;
    }

    /** What a person pays for the full book when they have nothing yet. */
    public static function fullUsd(float|string $list): float
    {
        return max(0.0, round((float) $list, 2));
    }
}
