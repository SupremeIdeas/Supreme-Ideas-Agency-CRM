<?php

namespace App\Support\Bootstrap;

use App\Support\FeatureFlags;
use App\Support\MailTemplates;

/**
 * Bootstrap Phase 0: a read-only census of what the code actually uses, so the bootstrap defaults are generated from the repo and not from anyone's memory.
 * Everything here is a static scan of source files plus the live registries (FeatureFlags, MailTemplates). It never touches the database.
 */
final class Census
{
    /** Source roots scanned for Setting reads. */
    private const ROOTS = ['app', 'resources/views', 'routes', 'database/seeders', 'config'];

    /**
     * Every literal `Setting::getValue('key', default)` / `Setting::get(...)` in the code.
     *
     * @return array<string, array{key: string, group: string, defaults: list<string>, literal: mixed, has_literal: bool, files: list<string>, seeded: bool, risk: string}>
     */
    public static function settings(): array
    {
        $found = [];
        $pattern = "/(?<![A-Za-z\\\\])Setting::getValue\(\s*'([a-zA-Z0-9_.\-]+)'\s*(?:,\s*((?:[^,()\[\]]|\((?:[^()]*)\)|\[[^\]]*\])*?))?\)/";
        foreach (self::files(self::ROOTS) as $path) {
            $src = (string) file_get_contents($path);
            if (! str_contains($src, 'Setting::')) {
                continue;
            }
            if (preg_match_all($pattern, $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $key = $hit[1];
                    $found[$key]['files'][$path] = true;
                    $found[$key]['defaults'][trim($hit[2] ?? '')] = true;
                }
            }
        }

        $seeded = self::seededKeys();
        $rows = [];
        ksort($found);
        foreach ($found as $key => $info) {
            $defaults = array_keys($info['defaults']);
            [$has, $literal] = self::literal($defaults);
            $rows[$key] = [
                'key' => $key,
                'group' => strtok($key, '.') ?: 'misc',
                'defaults' => $defaults,
                'literal' => $literal,
                'has_literal' => $has,
                'files' => array_slice(array_keys($info['files']), 0, 3),
                'seeded' => in_array($key, $seeded, true),
                'risk' => self::risk($key),
            ];
        }

        return $rows;
    }

    /**
     * Dynamic reads we cannot list literally (a key built from a variable). Reported so nobody assumes the literal list is complete.
     *
     * @return list<string> "file:line  snippet"
     */
    public static function dynamicSettingReads(): array
    {
        $out = [];
        foreach (self::files(['app']) as $path) {
            foreach (file($path) ?: [] as $i => $line) {
                if (preg_match('/(?<![A-Za-z])Setting::getValue\(\s*(?:"|\$|\'[^\']*\'\s*\.)/', $line)) {
                    $out[] = str_replace(base_path().'/', '', $path).':'.($i + 1).'  '.trim(mb_substr($line, 0, 110));
                }
            }
        }

        return $out;
    }

    /**
     * Notification classes and how they are delivered, so the notification catalog can be checked for completeness.
     *
     * @return array<string, array{class: string, channels: list<string>, master_only: bool}>
     */
    public static function notifications(): array
    {
        $rows = [];
        foreach (glob(app_path('Notifications/*.php')) ?: [] as $file) {
            $name = basename($file, '.php');
            $src = (string) file_get_contents($file);
            $channels = [];
            if (preg_match('/function via\([^)]*\)[^{]*\{(.*?)\}/s', $src, $m)) {
                preg_match_all("/'(mail|database|webpush|broadcast|sms|whatsapp)'/", $m[1], $c);
                $channels = array_values(array_unique($c[1]));
            }
            $rows[$name] = [
                'class' => 'App\\Notifications\\'.$name,
                'channels' => $channels,
                'master_only' => (bool) preg_match('/N2N|WhiteLabel|MerchantWidget|Brain/i', $name),
            ];
        }
        ksort($rows);

        return $rows;
    }

    /**
     * Scheduled commands parsed from routes/console.php.
     *
     * @return list<array{command: string, frequency: string, overlap_guard: bool}>
     */
    public static function scheduler(): array
    {
        $src = (string) file_get_contents(base_path('routes/console.php'));
        $rows = [];
        // One Schedule:: statement may span several chained lines; split on the statement terminator.
        foreach (preg_split('/;\s*\n/', $src) ?: [] as $stmt) {
            if (! preg_match("/Schedule::(command|job)\(\s*([^,)]+)/", $stmt, $m)) {
                continue;
            }
            $cmd = trim($m[2], " '\"\t\n");
            $freq = 'custom';
            if (preg_match('/->(everyMinute|everyTwoMinutes|everyFiveMinutes|everyTenMinutes|everyFifteenMinutes|everyThirtyMinutes|hourly|hourlyAt|daily|dailyAt|weekly|weeklyOn|monthly|monthlyOn|twiceDaily)\(([^)]*)\)/', $stmt, $f)) {
                $freq = $f[1].($f[2] !== '' ? '('.trim($f[2], " '\"").')' : '');
            }
            $rows[] = ['command' => $cmd, 'frequency' => $freq, 'overlap_guard' => str_contains($stmt, 'withoutOverlapping')];
        }

        return $rows;
    }

    /** @return array<string, array{name: string, group: string, requires: list<string>, default: bool}> */
    public static function features(): array
    {
        $rows = [];
        foreach (FeatureFlags::FEATURES as $key => $f) {
            $rows[$key] = ['name' => $f['name'], 'group' => $f['group'], 'requires' => $f['requires'], 'default' => (bool) $f['default']];
        }

        return $rows;
    }

    /** @return array<string, array{label: string, view: string}> */
    public static function mailTemplates(): array
    {
        $rows = [];
        foreach (MailTemplates::templates() as $key => $t) {
            $rows[$key] = ['label' => $t['label'], 'view' => $t['view']];
        }

        return $rows;
    }

    /**
     * Provider blocks in config/services.php with the env variables they read: the raw material for the credential slot registry.
     *
     * @return array<string, list<string>> provider => env var names
     */
    public static function serviceEnv(): array
    {
        $src = (string) file_get_contents(config_path('services.php'));
        $out = [];
        $current = null;
        foreach (preg_split('/\R/', $src) ?: [] as $line) {
            if (preg_match("/^    '([a-z0-9_]+)' => \[/", $line, $m)) {
                $current = $m[1];
                $out[$current] = [];

                continue;
            }
            if ($current !== null && preg_match_all("/env\('([A-Z0-9_]+)'/", $line, $e)) {
                foreach ($e[1] as $v) {
                    $out[$current][] = $v;
                }
            }
            if (preg_match('/^    \],?\s*$/', $line)) {
                $current = null;
            }
        }

        return array_filter($out, fn ($v) => $v !== []);
    }

    // ---------------------------------------------------------------------------------------------------------------------------------

    /** @return list<string> */
    private static function seededKeys(): array
    {
        $keys = [];
        foreach (glob(database_path('seeders/*.php')) ?: [] as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match_all("/\[\s*'([a-z0-9_]+(?:\.[a-zA-Z0-9_\-]+)+)'\s*,/", $src, $m)) {
                array_push($keys, ...$m[1]);
            }
            if (preg_match_all("/Setting::setValue\(\s*'([a-zA-Z0-9_.\-]+)'/", $src, $m)) {
                array_push($keys, ...$m[1]);
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Try to read the default as a plain value. Constants, method calls and expressions are NOT guessed at: they come back as "no literal".
     *
     * @param  list<string>  $defaults
     * @return array{0: bool, 1: mixed}
     */
    private static function literal(array $defaults): array
    {
        $defaults = array_values(array_filter($defaults, fn ($d) => $d !== ''));
        if (count($defaults) !== 1) {
            return [false, null];
        }
        $d = $defaults[0];
        if (preg_match('/^-?\d+$/', $d)) {
            return [true, (int) $d];
        }
        if (preg_match('/^-?\d*\.\d+$/', $d)) {
            return [true, (float) $d];
        }
        if (in_array($d, ['true', 'false'], true)) {
            return [true, $d === 'true'];
        }
        if (preg_match("/^'((?:[^'\\\\]|\\\\.)*)'$/", $d, $m)) {
            return [true, stripcslashes($m[1])];
        }
        if (preg_match('/^"([^"$]*)"$/', $d, $m)) {
            return [true, $m[1]];
        }

        return [false, null];
    }

    /** Where a wrong default hurts most: money first, then anything public or that spends. */
    private static function risk(string $key): string
    {
        $g = strtok($key, '.');
        if (in_array($g, ['pricing', 'credits', 'payouts', 'payout', 'wallet', 'referral', 'giftcards', 'merchant', 'developer_api', 'sms', 'tax'], true)) {
            return 'money';
        }
        if (in_array($g, ['marketing', 'site', 'home', 'splash', 'brand', 'nav', 'ui', 'dashboard', 'legal', 'support', 'convai', 'wizard'], true)) {
            return 'public';
        }
        if (in_array($g, ['routing', 'nci', 'spam', 'contacts', 'kyc', 'account_erasure', 'appexport', 'media', 'mail', 'api'], true)) {
            return 'safety';
        }

        return 'low';
    }

    /** @return \Generator<string> */
    private static function files(array $roots): \Generator
    {
        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                    yield $f->getPathname();
                }
            }
        }
    }
}
