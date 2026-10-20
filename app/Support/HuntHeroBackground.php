<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Optional premium hero for the The Hunt (brand directory) (owner request: same
 * visual system as the customer dashboard home hero, so it can be re-themed
 * independently later). The admin uploads a LIGHT and a DARK image; the gift
 * hero renders whichever matches the active theme, under the same bleed +
 * mask treatment as the dashboard hero. When nothing is uploaded the hero
 * still shows its title/description with no image — purely additive.
 *
 * Deliberately a SEPARATE class/setting-namespace from `HeroBackground`
 * (dashboard.hero.*) rather than a shared one, so the two heroes can be
 * customised independently without one save clobbering the other.
 *
 * Recommended asset: 1600×500 (16:5), WebP q75-80 (~60-140 KB) — identical
 * spec to the dashboard hero, so the same source art can be reused if wanted.
 */
class HuntHeroBackground
{
    private const CACHE_KEY = 'hunt.hero.v2';

    public const LIGHT_KEY = 'hunt.hero.image_light';

    public const DARK_KEY = 'hunt.hero.image_dark';

    public const DESC_KEY = 'hunt.hero.description';

    /** Admin on/off switch for the gift hero image. */
    public const ENABLED_KEY = 'hunt.hero.enabled';

    public const TITLE_KEY = 'hunt.hero.title';

    public const TITLE_SIZE_KEY = 'hunt.hero.title_size';

    public const DEFAULT_DESCRIPTION = 'Follow real brands, earn surprise NaaraCredits. Every follow is a one-time reward, server-confirmed the moment you claim it.';

    public const DEFAULT_TITLE = 'Connect With Brands: Build Good Business Relationships';

    public const DEFAULT_TITLE_SIZE = 'sm';

    /** Committed starter artwork (1600x500 WebP). Used until the admin uploads their own, so the hero is never an empty slot. */
    public const DEFAULT_LIGHT = 'images/themes/shared/hunt-hero-light.webp';

    public const DEFAULT_DARK = 'images/themes/shared/hunt-hero-dark.webp';

    /** Get-listed (business page) starter artwork; it follows the Hunt upload if the admin sets one. */
    public const LISTED_DEFAULT_LIGHT = 'images/themes/shared/listed-hero-light.webp';

    public const LISTED_DEFAULT_DARK = 'images/themes/shared/listed-hero-dark.webp';

    /** Same preset keys as the dashboard hero — literal classes live in the
     *  gift-cards hero partial (Tailwind only scans resources/**\/*.blade.php). */
    public const TITLE_SIZES = HeroBackground::TITLE_SIZES;

    /** @return array{light: ?string, dark: ?string, uploaded_light: ?string, uploaded_dark: ?string, description: string, enabled: bool, title: string, title_size: string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $desc = trim((string) Setting::getValue(self::DESC_KEY, ''));
                $title = trim((string) Setting::getValue(self::TITLE_KEY, ''));
                $titleSize = (string) Setting::getValue(self::TITLE_SIZE_KEY, '');

                return [
                    'light' => Setting::getValue(self::LIGHT_KEY) ?: HeroBackground::light() ?: asset(self::DEFAULT_LIGHT),
                    'dark' => Setting::getValue(self::DARK_KEY) ?: HeroBackground::dark() ?: asset(self::DEFAULT_DARK),
                    'uploaded_light' => Setting::getValue(self::LIGHT_KEY) ?: null,
                    'uploaded_dark' => Setting::getValue(self::DARK_KEY) ?: null,
                    'description' => $desc !== '' ? $desc : self::DEFAULT_DESCRIPTION,
                    'enabled' => (bool) Setting::getValue(self::ENABLED_KEY, true),
                    'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
                    'title_size' => array_key_exists($titleSize, self::TITLE_SIZES) ? $titleSize : self::DEFAULT_TITLE_SIZE,
                ];
            } catch (\Throwable) {
                return [
                    'light' => null, 'dark' => null, 'uploaded_light' => null, 'uploaded_dark' => null,
                    'description' => self::DEFAULT_DESCRIPTION, 'enabled' => true,
                    'title' => self::DEFAULT_TITLE, 'title_size' => self::DEFAULT_TITLE_SIZE,
                ];
            }
        });
    }

    public static function light(): ?string
    {
        return self::current()['light'];
    }

    public static function dark(): ?string
    {
        return self::current()['dark'];
    }

    /** Get-listed hero: an admin's Hunt upload wins, otherwise its own business-owner artwork. */
    public static function listedLight(): ?string
    {
        return (self::current()['uploaded_light'] ?? null) ?: asset(self::LISTED_DEFAULT_LIGHT);
    }

    public static function listedDark(): ?string
    {
        return (self::current()['uploaded_dark'] ?? null) ?: asset(self::LISTED_DEFAULT_DARK);
    }

    public static function description(): string
    {
        return self::current()['description'];
    }

    public static function enabled(): bool
    {
        return self::current()['enabled'];
    }

    public static function isSet(): bool
    {
        $c = self::current();

        return filled($c['light']) || filled($c['dark']);
    }

    public static function title(): string
    {
        return self::current()['title'];
    }

    public static function titleSize(): string
    {
        return self::current()['title_size'];
    }

    /** First word plain, the rest gradient — same two-tone split as the dashboard hero. */
    public static function titleFirstWord(): string
    {
        return explode(' ', self::title(), 2)[0];
    }

    public static function titleRestWords(): string
    {
        return explode(' ', self::title(), 2)[1] ?? '';
    }

    /** Show the hero image when one is uploaded AND the admin switch is on. */
    public static function showsImage(): bool
    {
        return self::isSet() && self::enabled();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isHeroKey(string $key): bool
    {
        return in_array($key, [
            self::LIGHT_KEY, self::DARK_KEY, self::DESC_KEY, self::ENABLED_KEY,
            self::TITLE_KEY, self::TITLE_SIZE_KEY,
        ], true);
    }
}
