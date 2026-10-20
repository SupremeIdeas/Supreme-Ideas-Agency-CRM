<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard page transitions (MASTER-ONLY, owner request 2026-10-04): a soft fade, or a "fluid" spring with a staggered content reveal, when a member
 * moves between pages with wire:navigate. Admin on/off + style + speed. The motion is pure CSS (opacity/transform only, so it runs on the compositor), driven by
 * three attributes on <html>; with the switch off nothing at all is emitted. It never runs on /adminmaster, the marketing site, or for members who ask the OS for
 * reduced motion (that rule lives in the CSS and the JS).
 */
class PageMotion
{
    private const CACHE_KEY = 'platform.page_motion.v1';

    public const SETTING_KEY = 'platform.page_motion';

    public const STYLES = ['fade' => 'Soft fade', 'fluid' => 'Fluid (spring + staggered reveal)'];

    public const SPEEDS = ['fast' => 'Fast', 'normal' => 'Normal', 'slow' => 'Relaxed'];

    /** @return array{enabled: bool, style: string, speed: string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $cfg = Setting::getValue(self::SETTING_KEY);
            } catch (\Throwable) {
                $cfg = null;
            }
            $cfg = is_array($cfg) ? $cfg : [];

            return [
                'enabled' => (bool) ($cfg['enabled'] ?? true),
                'style' => array_key_exists((string) ($cfg['style'] ?? ''), self::STYLES) ? (string) $cfg['style'] : 'fluid',
                'speed' => array_key_exists((string) ($cfg['speed'] ?? ''), self::SPEEDS) ? (string) $cfg['speed'] : 'normal',
            ];
        });
    }

    public static function enabled(): bool
    {
        return self::current()['enabled'];
    }

    /** The <html> attributes for a page that should animate (empty string when it should not). */
    public static function htmlAttributes(bool $dashboardSurface): string
    {
        if (! $dashboardSurface || ! self::enabled()) {
            return '';
        }
        $c = self::current();

        return 'data-nx-pt="'.$c['style'].'" data-nx-pt-speed="'.$c['speed'].'"';
    }

    public static function save(bool $enabled, string $style, string $speed): void
    {
        Setting::setValue(self::SETTING_KEY, [
            'enabled' => $enabled,
            'style' => array_key_exists($style, self::STYLES) ? $style : 'fluid',
            'speed' => array_key_exists($speed, self::SPEEDS) ? $speed : 'normal',
        ], group: 'platform');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
