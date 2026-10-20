<?php

namespace App\Support;

use App\Models\MoreApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Cached lookup for the More Apps carousel (master-only — the mobile "More"
 * sheet cross-promotes companion Supreme Ideas apps). Gated by
 * FeatureEntitlements::isMaster() at every render point, not here, so a
 * white-label instance that later upgrades to master entitlement picks this
 * up with zero code changes.
 */
class MoreApps
{
    private const CACHE_KEY = 'more_apps.live.v1';

    /** @return \Illuminate\Support\Collection<int, MoreApp> */
    public static function active()
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            if (! Schema::hasTable('more_apps')) {
                return collect();
            }

            return MoreApp::query()->live()
                ->orderBy('sort_order')->orderBy('id')
                ->get();
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
