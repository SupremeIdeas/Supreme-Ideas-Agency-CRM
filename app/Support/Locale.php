<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves the language a user READS the UI in (localization Phase A),
 * mirroring LocaleCurrency's shape exactly. Reuses the existing
 * users.language column (added 2026-07-21 for profile fields but never
 * wired to anything — no reads, no UI, no business logic referenced it
 * anywhere in the app) rather than adding a redundant one. Priority order:
 *
 *   1. an explicit choice this session (the language switcher),
 *   2. the user's saved language,
 *   3. their profile country_code -> a best-guess language,
 *   4. config('app.locale') ('en').
 *
 * Only locales with real, reviewed translation files ship as `available` —
 * Phase C/D adds French/Portuguese/Arabic/Swahili once a native reviewer
 * checks tone and terminology (Arabic also needs RTL verification). Listing
 * them here as unavailable lets the switcher show the roadmap honestly
 * ("coming soon") without ever serving machine-guessed or half-finished
 * copy to a real user.
 */
class Locale
{
    public const SESSION_KEY = 'app_locale';

    public const COOKIE_KEY = 'app_locale';

    /**
     * Every locale on the roadmap, with its real availability. `beta` marks
     * a language whose copy is machine-assisted and awaiting native review:
     * it is selectable (the switcher tags it "Beta") but never the default
     * guess for any country.
     */
    public const SUPPORTED = [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false, 'available' => true],
        // UX/localization/platform-reach blueprint Phase H, Track 1: real,
        // curated translations for lang/{fr,ar,sw}. Arabic's RTL flag is
        // wired end-to-end: SetLocale middleware -> $htmlDir -> <html dir>.
        'fr' => ['name' => 'French', 'native' => 'Français', 'rtl' => false, 'available' => true],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true, 'available' => true],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'rtl' => false, 'available' => true],
        // Language pack 1 (owner request, 2026-10-03): the roadmap entries
        // plus the next wave. All flagged beta until a native reviewer signs off.
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'rtl' => false, 'available' => true, 'beta' => true],
        'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'rtl' => false, 'available' => true, 'beta' => true],
        'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'rtl' => false, 'available' => true, 'beta' => true],
        'ig' => ['name' => 'Igbo', 'native' => 'Igbo', 'rtl' => false, 'available' => true, 'beta' => true],
        'zu' => ['name' => 'Zulu', 'native' => 'isiZulu', 'rtl' => false, 'available' => true, 'beta' => true],
        'am' => ['name' => 'Amharic', 'native' => 'አማርኛ', 'rtl' => false, 'available' => true, 'beta' => true],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'rtl' => false, 'available' => true, 'beta' => true],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'rtl' => false, 'available' => true, 'beta' => true],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'rtl' => false, 'available' => true, 'beta' => true],
        'zh' => ['name' => 'Chinese (Simplified)', 'native' => '简体中文', 'rtl' => false, 'available' => true, 'beta' => true],
    ];

    /** ISO-3166 alpha-2 country -> the language we'd resolve to once it ships. */
    private const COUNTRY_LOCALE = [
        // Francophone Africa
        'SN' => 'fr', 'CI' => 'fr', 'ML' => 'fr', 'BF' => 'fr', 'NE' => 'fr',
        'TG' => 'fr', 'BJ' => 'fr', 'CM' => 'fr', 'CD' => 'fr', 'CG' => 'fr',
        'GA' => 'fr', 'GN' => 'fr', 'FR' => 'fr', 'BE' => 'fr',
        // Lusophone Africa
        'MZ' => 'pt', 'AO' => 'pt', 'CV' => 'pt', 'GW' => 'pt', 'PT' => 'pt', 'BR' => 'pt',
        // Arabic-speaking North Africa
        'EG' => 'ar', 'MA' => 'ar', 'DZ' => 'ar', 'TN' => 'ar', 'LY' => 'ar', 'SD' => 'ar',
        // Swahili East Africa
        'TZ' => 'sw',
        // Nigeria stays English by default: Hausa/Yoruba/Igbo are one tap away in the switcher, but guessing one for a whole country is wrong.
        'ES' => 'es', 'MX' => 'es', 'CO' => 'es', 'AR' => 'es', 'CL' => 'es', 'PE' => 'es',
        'DE' => 'de', 'AT' => 'de',
        'CN' => 'zh',
    ];

    /** Only the locales real enough to offer right now. */
    public static function available(): array
    {
        return array_filter(self::SUPPORTED, fn (array $meta) => $meta['available']);
    }

    /** The full roadmap, for a switcher that shows "coming soon" entries too. */
    public static function options(): array
    {
        return self::SUPPORTED;
    }

    public static function isRtl(string $locale): bool
    {
        return self::SUPPORTED[$locale]['rtl'] ?? false;
    }

    /** Map a country code to a locale, but only if it has actually shipped. */
    public static function forCountry(?string $iso): string
    {
        $iso = strtoupper((string) $iso);
        $guess = self::COUNTRY_LOCALE[$iso] ?? 'en';

        return ((self::SUPPORTED[$guess]['available'] ?? false) && ! (self::SUPPORTED[$guess]['beta'] ?? false)) ? $guess : 'en';
    }

    /** The short code a header chip shows ("EN", "FR", "ZH"). */
    public static function code(string $locale): string
    {
        return strtoupper($locale);
    }

    /**
     * Persist an explicit choice: session + a one-year cookie (so guests on
     * the marketing site keep their language) + the profile column when
     * signed in. Validated; an unknown code falls back to English.
     */
    public static function choose(?User $user, string $locale): string
    {
        if (! (self::SUPPORTED[$locale]['available'] ?? false)) {
            $locale = 'en';
        }
        session([self::SESSION_KEY => $locale]);
        \Illuminate\Support\Facades\Cookie::queue(self::COOKIE_KEY, $locale, 60 * 24 * 365);
        if ($user !== null) {
            $user->forceFill(['language' => $locale])->save();
        }

        return $locale;
    }

    /** The locale to render the UI in for this user (see class doc). */
    public static function resolve(?User $user = null): string
    {
        $session = session(self::SESSION_KEY);
        if (is_string($session) && (self::SUPPORTED[$session]['available'] ?? false)) {
            return $session;
        }

        if ($user !== null && $user->language && (self::SUPPORTED[$user->language]['available'] ?? false)) {
            return $user->language;
        }

        // A guest's earlier choice (marketing site, login page).
        $cookie = request()?->cookie(self::COOKIE_KEY);
        if (is_string($cookie) && (self::SUPPORTED[$cookie]['available'] ?? false)) {
            return $cookie;
        }

        if ($user !== null && $user->country_code) {
            return self::forCountry($user->country_code);
        }

        $fallback = config('app.locale', 'en');

        return (self::SUPPORTED[$fallback]['available'] ?? false) ? $fallback : 'en';
    }
}
