<?php

namespace App\Services\Omni\Kb;

use App\Models\Setting;

/**
 * Admin-tunable rules for knowledge uploads (MASTER-ONLY). Stored in settings (Admin > Respond setup guide > Upload rules) and always clamped to safe bounds, so a typo
 * can never switch validation off or allow a file the reader cannot handle.
 */
final class DocumentLimits
{
    public const DEFAULTS = ['min_bytes' => 200, 'min_words' => 25, 'max_mb' => 2, 'max_files' => 10, 'max_sources' => 100];

    /** @return array{min_bytes:int,min_words:int,max_mb:int,max_files:int,max_sources:int} */
    public static function all(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $d) {
            $out[$k] = self::clamp($k, (int) Setting::getValue('omni.kb.'.$k, $d));
        }
        // a minimum above the maximum would refuse everything
        $out['min_bytes'] = min($out['min_bytes'], $out['max_mb'] * 1048576);

        return $out;
    }

    public static function minBytes(): int
    {
        return self::all()['min_bytes'];
    }

    public static function maxBytes(): int
    {
        return self::all()['max_mb'] * 1048576;
    }

    public static function minWords(): int
    {
        return self::all()['min_words'];
    }

    public static function maxFiles(): int
    {
        return self::all()['max_files'];
    }

    public static function maxSources(): int
    {
        return self::all()['max_sources'];
    }

    /** @param array<string,mixed> $v */
    public static function save(array $v): void
    {
        foreach (self::DEFAULTS as $k => $d) {
            Setting::setValue('omni.kb.'.$k, self::clamp($k, (int) ($v[$k] ?? $d)), 'omni', 'Respond knowledge upload rule: '.$k);
        }
    }

    private static function clamp(string $k, int $v): int
    {
        [$lo, $hi] = ['min_bytes' => [0, 50000], 'min_words' => [5, 500], 'max_mb' => [1, 5], 'max_files' => [1, 20], 'max_sources' => [10, 200]][$k];

        return max($lo, min($hi, $v));
    }
}
