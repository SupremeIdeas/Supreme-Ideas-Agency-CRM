<?php

namespace App\Support\Business;

use App\Models\MoreApp;
use App\Models\User;
use App\Support\Brain\BrainAccess;
use App\Support\Brain\BrainSettings;
use App\Support\ComingSoon;
use App\Support\MoreApps;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER-ONLY. The three built-in apps of the "More" carousel: NaaraBusiness Suite, NaaraBrain and Naara Chat. Idempotent: `ensure()` creates
 * a missing row but never overwrites an admin's edits (name, logo, order, visibility). Rows are `is_system` (never deletable). Visibility is
 * decided per person at render time, so an app the person cannot open is not shown to them.
 */
final class SystemMoreApps
{
    /** key => [name, route, logo, order] */
    public const APPS = [
        'business_suite' => ['NaaraBusiness Suite', 'business', 'images/more-apps/naara-business-suite.webp', 10],
        'brain' => ['NaaraBrain', 'brain.chat', 'images/more-apps/naara-brain.webp', 20],
        'naara_chat' => ['Naara Chat', 'n2n.chat', 'images/more-apps/naara-chat.webp', 30],
    ];

    public static function ensure(): int
    {
        if (! Schema::hasTable('more_apps') || ! Schema::hasColumn('more_apps', 'system_key')) {
            return 0;
        }
        $made = 0;
        foreach (self::APPS as $key => [$name, $route, $logo, $order]) {
            if (MoreApp::where('system_key', $key)->exists()) {
                continue;
            }
            MoreApp::create(['name' => $name, 'icon_url' => '/'.$logo, 'link_url' => '/', 'sort_order' => $order, 'is_active' => true, 'is_system' => true, 'system_key' => $key, 'route_name' => $route]);
            $made++;
        }
        if ($made > 0) {
            MoreApps::flush();
        }

        return $made;
    }

    /** May this person see (and open) the built-in app right now? */
    public static function visibleTo(string $key, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match ($key) {
            'business_suite' => ComingSoon::isLive('business_suite') && Route::has('business'),
            'brain' => class_exists(BrainSettings::class) && BrainSettings::enabled() && BrainAccess::allowed($user) && Route::has('brain.chat'),
            'naara_chat' => class_exists(\App\Support\N2N\N2NSettings::class) && \App\Support\N2N\N2NSettings::enabled() && Route::has('n2n.chat'),
            default => false,
        };
    }
}
