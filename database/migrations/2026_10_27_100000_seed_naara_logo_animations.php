<?php

use App\Support\PreloaderSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the Naara logo animations as the DEFAULT preloaders (owner request, 2026-10-04) through the existing Preloader Studio, so every value
 * stays editable there (animation, daily cap, minimum display time). It only fills page types that have no assignment yet, so it never
 * overrides something an admin already configured.
 *
 *   dashboard_home  Naara mark          first visits of the day: 3
 *   marketing       Naara mark          2 per day
 *   numbers         Numbers animation   4 per day
 *   n2n             Naara Chat intro    4 per day (only where the chat module exists)
 */
return new class extends Migration
{
    public function up(): void
    {
        $custom = fn (string $file) => ['light' => ['kind' => 'webp', 'asset' => 'images/brand/animations/'.$file], 'dark' => ['kind' => 'webp', 'asset' => 'images/brand/animations/'.$file]];
        // size 150 (Studio custom size): the logo animations are drawn with padding, so they read well at about 260 px.
        $base = ['preset' => 'custom', 'enabled' => true, 'bg_color' => null, 'bg_opacity' => 1.0, 'size' => 150, 'speed' => 1.0, 'opacity' => 1.0, 'blur' => false];

        PreloaderSettings::registerPageType('dashboard_home', 'Dashboard home (first visits)');
        $seeds = [
            'dashboard_home' => $base + ['custom' => $custom('naara-preloader.webp'), 'max_per_day' => 3, 'min_display_ms' => 1500],
            'marketing' => $base + ['custom' => $custom('naara-preloader.webp'), 'max_per_day' => 2, 'min_display_ms' => 1500],
            'numbers' => $base + ['custom' => $custom('naara-numbers-intro.webp'), 'max_per_day' => 4, 'min_display_ms' => 1800],
        ];
        if (class_exists(\App\Providers\N2NServiceProvider::class)) {
            PreloaderSettings::registerPageType('n2n', 'Naara Chat');
            $seeds['n2n'] = $base + ['custom' => $custom('naara-chat-intro.webp'), 'max_per_day' => 4, 'min_display_ms' => 1500];
        }
        foreach ($seeds as $type => $cfg) {
            if (! PreloaderSettings::hasAssignment($type)) {
                PreloaderSettings::saveAssignment($type, $cfg);
            }
        }
    }

    public function down(): void
    {
        // Seeded defaults are ordinary Studio assignments; an admin removes or changes them there.
    }
};
