<?php

namespace App\Services\BrandLinks;

use App\Models\Setting;

/** Admin overrides for the Business Links policy: a stored value wins, config/brand_links.php is the default. Resilient to a missing settings table. */
final class BrandLinkSettings
{
    /** @return list<string> */
    public static function list(string $key): array
    {
        $v = self::raw($key);

        return array_values(array_unique(array_filter(array_map(fn ($x) => strtolower(trim((string) $x)), is_array($v) ? $v : (array) config("brand_links.$key", [])), fn ($x) => $x !== '')));
    }

    public static function int(string $key, int $default): int
    {
        $v = self::raw($key);

        return is_numeric($v) ? (int) $v : (int) config("brand_links.$key", $default);
    }

    public static function float(string $key, float $default): float
    {
        $v = self::raw($key);

        return is_numeric($v) ? (float) $v : (float) config("brand_links.$key", $default);
    }

    public static function save(string $key, mixed $value): void
    {
        Setting::setValue('brand_links.'.$key, $value, 'brand_links');
    }

    private static function raw(string $key): mixed
    {
        try {
            return Setting::getValue('brand_links.'.$key, null);
        } catch (\Throwable) {
            return null;
        }
    }
}
