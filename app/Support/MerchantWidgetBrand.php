<?php

namespace App\Support;

/**
 * Colour helpers for the hosted widget pages (MASTER-ONLY). The merchant's accent is chosen by them, so the text that sits on it must be
 * worked out, never assumed: a pale yellow accent with white text would be unreadable on a payment page.
 */
class MerchantWidgetBrand
{
    public const DEFAULT = '#0A6E6E';

    public static function color(?string $hex): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? (string) $hex : self::DEFAULT;
    }

    /** Black or white, whichever reads better on this colour (WCAG relative luminance). */
    public static function ink(string $hex): string
    {
        $hex = ltrim(self::color($hex), '#');
        $lin = fn (int $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lin(hexdec(substr($hex, 0, 2))) + 0.7152 * $lin(hexdec(substr($hex, 2, 2))) + 0.0722 * $lin(hexdec(substr($hex, 4, 2)));

        return $l > 0.4 ? '#0d1b2a' : '#ffffff';
    }

    /** Best-effort shopper currency from the browser's language region (en-NG -> NGN); null when there is no clear signal. */
    public static function currencyGuess(?string $acceptLanguage): ?string
    {
        if (! preg_match('/-([A-Za-z]{2})\b/', (string) $acceptLanguage, $m)) {
            return null;
        }
        $region = strtoupper($m[1]);
        $eur = ['DE', 'FR', 'ES', 'IT', 'NL', 'PT', 'IE', 'BE', 'AT', 'FI', 'GR'];

        return in_array($region, $eur, true) ? 'EUR' : ['NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR', 'GB' => 'GBP', 'CA' => 'CAD', 'IN' => 'INR', 'US' => 'USD'][$region] ?? null;
    }
}
