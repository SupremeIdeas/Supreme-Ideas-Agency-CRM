<?php

namespace App\Support\Business;

use App\Models\Setting;

/**
 * The words and picture at the top of the Business workspace page (MASTER-ONLY). Every part is an admin-owned setting (Admin > Business Suite >
 * Workspace page) with a seeded default, so copy and the illustration change without a deploy. An empty value falls back to the default,
 * so the page can never render blank.
 */
class WorkspaceHero
{
    public const GROUP = 'business';

    public const KEY = 'business.workspace_page.';

    public const MAX_WORDS = 4;

    public const DEFAULT_TITLE = 'Business workspace';

    public const DEFAULT_WORDS = ['calls', 'messages', 'team', 'wallet'];

    public const DEFAULT_DESCRIPTION = 'One shared home for your numbers, inbox and call menus, with an AI receptionist for quiet hours. Invite teammates, give each a role, and spend from one wallet.';

    public const DEFAULT_IMAGE = 'images/business/workspace-hero.webp';

    public const DEFAULT_IMAGE_SMALL = 'images/business/workspace-hero-800.webp';

    public static function title(): string
    {
        return self::text('title', self::DEFAULT_TITLE);
    }

    /** @return list<string> 1 to 4 rotating words */
    public static function words(): array
    {
        $out = [];
        for ($i = 1; $i <= self::MAX_WORDS; $i++) {
            // A stored empty box means "not used" (fewer than four words); only a word that was never stored falls back to its default.
            $stored = Setting::getValue(self::KEY.'word_'.$i, null);
            $w = $stored === null ? (self::DEFAULT_WORDS[$i - 1] ?? '') : self::clean((string) $stored, 24);
            if ($w !== '') {
                $out[] = $w;
            }
        }

        return $out !== [] ? $out : self::DEFAULT_WORDS;
    }

    public static function description(): string
    {
        return self::text('description', self::DEFAULT_DESCRIPTION);
    }

    /** The uploaded picture URL, or null while the shipped illustration is in use. */
    public static function customImage(): ?string
    {
        $v = trim((string) Setting::getValue(self::KEY.'image', ''));

        return $v !== '' ? $v : null;
    }

    /** @return array{src: string, srcset: ?string, width: int, height: int} */
    public static function image(): array
    {
        $custom = self::customImage();
        if ($custom !== null) {
            return ['src' => $custom, 'srcset' => null, 'width' => 1400, 'height' => 406];
        }

        return ['src' => asset(self::DEFAULT_IMAGE_SMALL), 'srcset' => asset(self::DEFAULT_IMAGE_SMALL).' 800w, '.asset(self::DEFAULT_IMAGE).' 1400w', 'width' => 1400, 'height' => 406];
    }

    /** Trim, strip tags and cap, so an admin-written line can never inject markup. */
    public static function clean(string $v, int $max): string
    {
        return mb_substr(trim(strip_tags($v)), 0, $max);
    }

    private static function text(string $key, string $default): string
    {
        $v = self::clean((string) Setting::getValue(self::KEY.$key, $default), 400);

        return $v !== '' ? $v : $default;
    }
}
