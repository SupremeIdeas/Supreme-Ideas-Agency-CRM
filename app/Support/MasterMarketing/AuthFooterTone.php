<?php

namespace App\Support\MasterMarketing;

use App\Support\AppExport;
use App\Support\ThemePreset;

/**
 * MASTER-ONLY. The one colour the sign-in screens share: the footer background, the feathered edge of the illustration above it and the browser chrome
 * (meta theme-color). It is the active theme's primary colour pulled toward the dark navy, so the default Deep Teal theme gives a deep teal footer and any
 * other theme gives its own tint, with no hard-coded hex in the stylesheet.
 */
final class AuthFooterTone
{
    private const NAVY = [6, 20, 31];

    /** How much of the theme colour stays in the footer (0-1). */
    private const KEEP = 0.40;

    public static function primaryHex(): string
    {
        $hex = ThemePreset::browserThemeColor() ?? (string) AppExport::get('theme_color', '#0A6E6E');

        return preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1 ? strtolower($hex) : '#0a6e6e';
    }

    /** The footer / browser-chrome colour as #rrggbb. */
    public static function hex(): string
    {
        [$r, $g, $b] = sscanf(self::primaryHex(), '#%02x%02x%02x');
        $mix = fn (int $c, int $n) => (int) round($c * self::KEEP + $n * (1 - self::KEEP));

        return sprintf('#%02x%02x%02x', $mix($r, self::NAVY[0]), $mix($g, self::NAVY[1]), $mix($b, self::NAVY[2]));
    }
}
