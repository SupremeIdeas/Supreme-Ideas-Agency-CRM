<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * White-label custom-icon overrides (blueprint Section 16.3). The admin stores
 * a `name = image_url` map (one per line) in the `ui.icon_overrides` setting;
 * this parses it into a slug => URL map, cached for an hour. When a name has an
 * override, <x-icon> renders the custom image instead of the built-in sprite.
 */
class IconOverrides
{
    public const CACHE_KEY = 'ui.icon_overrides';

    /** @return array<string, string> slug => url */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, function () {
                $raw = (string) Setting::getValue('ui.icon_overrides', '');
                $map = [];

                foreach (preg_split('/\r?\n/', $raw) as $line) {
                    if (! str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = array_map('trim', explode('=', $line, 2));
                    if ($key !== '' && filter_var($value, FILTER_VALIDATE_URL)) {
                        $map[Str::slug($key)] = $value;
                    }
                }

                return $map;
            });
        } catch (\Throwable $e) {
            // Settings unavailable (e.g. pre-install / DB hiccup) — fall back to
            // the built-in sprite rather than blanking the page. Not cached.
            return [];
        }
    }

    public static function for(string $name): ?string
    {
        return self::all()[Str::slug($name)] ?? null;
    }

    /**
     * Set (or replace) one override, rewriting the same `name = url` textarea
     * setting this class already reads from — the admin UI for this (Admin →
     * Brand icons) writes through here rather than inventing a second storage
     * mechanism.
     */
    public static function set(string $name, string $url): void
    {
        $map = self::all();
        $map[Str::slug($name)] = $url;
        self::persist($map);
    }

    public static function remove(string $name): void
    {
        $map = self::all();
        unset($map[Str::slug($name)]);
        self::persist($map);
    }

    /** @param array<string, string> $map slug => url */
    private static function persist(array $map): void
    {
        $lines = [];
        foreach ($map as $slug => $url) {
            $lines[] = "{$slug} = {$url}";
        }

        Setting::setValue('ui.icon_overrides', implode("\n", $lines), 'ui');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
