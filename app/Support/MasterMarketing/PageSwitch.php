<?php

namespace App\Support\MasterMarketing;

use App\Models\Setting;
use App\Support\FeatureEntitlements;

/**
 * MASTER-ONLY. The admin's on/off switch for the master's own sales pages (the White Label page, the NaaraChat page). One Setting row per
 * page (`site.page_live.{page}`), shown as a toggle at the top of that page in Admin > Marketing site. Default is ON: the page is built, the
 * admin can switch it off afterwards without a deploy. When it is off the route answers a friendly "not open right now" sheet (never a bare
 * 404 to a person) and every link to it disappears from the header and footer.
 */
final class PageSwitch
{
    /** page key => [route name, label shown beside the toggle] */
    public const PAGES = [
        'white-label' => ['white-label.landing', 'White Label page'],
        'naara-chat' => ['n2n.landing', 'Naara Chat page'],
        'naara-brain' => ['brain.landing', 'NaaraBrain page'],
        'naara-gift' => ['gift.landing', 'Naara Gift page'],
    ];

    /** Pages that ship switched OFF until the product they sell is live (the admin switches them on). Owner decision 2026-10-06: every page ships ON, so this is empty. */
    public const DEFAULT_OFF = [];

    public static function manages(string $page): bool
    {
        return isset(self::PAGES[$page]);
    }

    public static function label(string $page): string
    {
        return self::PAGES[$page][1] ?? $page;
    }

    public static function isLive(string $page): bool
    {
        if (! FeatureEntitlements::isMaster()) {
            return false;
        }
        try {
            $v = Setting::getValue(self::key($page), null);
        } catch (\Throwable) {
            return ! in_array($page, self::DEFAULT_OFF, true);
        }

        return $v === null ? ! in_array($page, self::DEFAULT_OFF, true) : (bool) $v;
    }

    public static function set(string $page, bool $live): void
    {
        if (self::manages($page)) {
            Setting::setValue(self::key($page), $live);
        }
    }

    private static function key(string $page): string
    {
        return 'site.page_live.'.$page;
    }
}
