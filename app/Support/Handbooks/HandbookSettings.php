<?php

namespace App\Support\Handbooks;

use App\Models\Setting;

/**
 * Admin-controlled behaviour of the Travel Handbook Store (MASTER-ONLY): the unlock rules (what an eSIM, a Naara Pro term or a purchase does) and the storefront copy.
 * Every value has a safe default so the store works before an admin touches a switch. Read through here, never from Setting directly.
 */
final class HandbookSettings
{
    public const COVERAGE = ['local' => 'Single-country eSIM', 'regional' => 'Regional eSIM', 'global' => 'Global eSIM'];

    public const DEFAULTS = [
        'store_enabled' => true,                 // the whole storefront (landing, directory, book pages)
        'esim_unlock_enabled' => true,           // buying a Naara eSIM unlocks a handbook
        'esim_unlock_coverage' => ['local', 'regional', 'global'],
        'esim_regional_picks' => 1,              // how many handbooks a regional/global eSIM lets the buyer choose
        'esim_min_price_usd' => 0.0,             // the eSIM must have cost at least this to qualify
        'esim_unlock_flagship_only' => true,     // local eSIM: only the country's flagship (S1), not every title for the country
        'pro_unlock_enabled' => true,
        'pro_slots' => 3,
        'purchase_enabled' => true,              // direct purchase from the store
        'upgrade_enabled' => true,               // unlocked -> owned (download) upgrade
        'esim_banner_enabled' => true,           // the extra banner in the eSIM hero
        'image_max_width' => 1400,               // the manuscript studio scales pictures wider than this (pixels)
        'image_quality' => 82,                   // JPEG / WebP quality when a picture is re-encoded (50-95)
        'image_flag_kb' => 400,                  // a picture heavier than this is flagged as too big even if it is narrow enough
        'popular_count' => 8,                    // titles on the landing page's Popular shelf
        'hero_eyebrow' => 'Naara Travel Handbooks',
        'hero_headline' => 'Know before you go.',
        'hero_accent' => 'Read it anywhere, even offline.',
        'hero_subtext' => 'Practical, up-to-date guides to living, studying and working abroad. Buy a Naara eSIM for a country and its handbook unlocks for you, free.',
        'cta_primary' => 'Browse all handbooks',
        'cta_secondary' => 'How it works',
    ];

    public static function get(string $key): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;
        try {
            $v = Setting::getValue('handbooks.'.$key, $default);
        } catch (\Throwable) {
            return $default;
        }
        if ($v === null || $v === '') {
            return $default;
        }

        return match (true) {
            is_bool($default) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            is_int($default) => (int) $v,
            is_float($default) => (float) $v,
            is_array($default) => is_array($v) ? array_values($v) : $default,
            default => (string) $v,
        };
    }

    public static function put(string $key, mixed $value): void
    {
        abort_unless(array_key_exists($key, self::DEFAULTS), 422);
        Setting::setValue('handbooks.'.$key, $value, 'handbooks');
    }

    /** @return array<string,mixed> every setting, defaults filled in */
    public static function all(): array
    {
        return collect(array_keys(self::DEFAULTS))->mapWithKeys(fn ($k) => [$k => self::get($k)])->all();
    }

    public static function proSlots(): int
    {
        return max(0, (int) self::get('pro_slots'));
    }
}
