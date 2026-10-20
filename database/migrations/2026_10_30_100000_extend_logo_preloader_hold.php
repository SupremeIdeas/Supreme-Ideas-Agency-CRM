<?php

use App\Support\PreloaderSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The seeded logo animations run about 2 to 2.5 seconds, but they were seeded with a 1.5 to 1.8 second minimum hold, so a fast page cut the logo
 * off mid-animation. Raise the hold to the full animation plus a short rest (owner request, 2026-10-04). Only rows still holding the ORIGINAL seeded
 * value are touched, so anything an admin already changed in Preloader Studio is left alone; every value stays editable there.
 */
return new class extends Migration
{
    public function up(): void
    {
        // page type => [old seeded hold, new hold], measured from the animation files (1944 / 2476 / 2049 ms).
        $bumps = ['dashboard_home' => [1500, 2800], 'marketing' => [1500, 2800], 'numbers' => [1800, 3300], 'n2n' => [1500, 2900]];
        foreach ($bumps as $type => [$old, $new]) {
            if (! PreloaderSettings::hasAssignment($type)) {
                continue;
            }
            $cfg = PreloaderSettings::forPageType($type);
            if ((int) ($cfg['min_display_ms'] ?? 0) === $old) {
                $cfg['min_display_ms'] = $new;
                PreloaderSettings::saveAssignment($type, $cfg);
            }
        }
    }

    public function down(): void
    {
    }
};
