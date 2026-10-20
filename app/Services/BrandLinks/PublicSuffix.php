<?php

namespace App\Services\BrandLinks;

use Illuminate\Support\Facades\Cache;

/**
 * Public Suffix List lookups from the PINNED copy in resources/data/brand-links (no network, no Composer package). Implements the published
 * algorithm: the longest matching rule wins, a `*.` wildcard consumes one extra label, a `!` exception rule beats everything, and an unlisted TLD is
 * treated as a one-label suffix. The ICANN and PRIVATE sections are remembered so a shared host (blogspot.com, github.io) can be told from a registry.
 */
final class PublicSuffix
{
    /** @var array<string, string>|null rule => 'icann'|'private' */
    private static ?array $rules = null;

    public static function parse(string $host): ?DomainParts
    {
        $host = rtrim(strtolower($host), '.');
        if ($host === '' || str_contains($host, '..') || strlen($host) > 253) {
            return null;
        }
        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return null;
        }
        $rules = self::rules();
        $n = count($labels);
        $suffixLen = 1;            // default rule "*": an unlisted TLD is a one-label suffix
        $section = 'icann';
        $unlisted = true;

        for ($i = 0; $i < $n; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            $exception = $rules['!'.$candidate] ?? null;
            if ($exception !== null) {
                $suffixLen = $n - $i - 1;   // the exception's parent is the suffix
                $section = $exception;
                $unlisted = false;
                break;
            }
            $len = $n - $i;
            if (isset($rules[$candidate]) && $len >= $suffixLen) {
                if ($len > $suffixLen || $unlisted) {
                    $suffixLen = $len; $section = $rules[$candidate]; $unlisted = false;
                }
            }
            $wild = '*.'.implode('.', array_slice($labels, $i + 1));
            if ($i + 1 < $n && isset($rules[$wild]) && $len >= $suffixLen) {
                if ($len > $suffixLen || $unlisted) {
                    $suffixLen = $len; $section = $rules[$wild]; $unlisted = false;
                }
            }
        }

        if ($suffixLen >= $n) {
            return null;   // the host IS a public suffix: nobody can own it
        }
        $suffix = implode('.', array_slice($labels, $n - $suffixLen));
        $label = $labels[$n - $suffixLen - 1];
        $registrable = $label.'.'.$suffix;
        $sub = $n - $suffixLen - 1 > 0 ? implode('.', array_slice($labels, 0, $n - $suffixLen - 1)) : '';

        return new DomainParts($host, $suffix, $registrable, $label, $sub, $labels[$n - 1], $section === 'private', $section === 'icann' && ! $unlisted);
    }

    /** True when the last label is a delegated TLD in the ICANN section (i.e. a real, registrable ending, not a made-up one). */
    public static function isKnownTld(string $tld): bool
    {
        $rules = self::rules();

        return ($rules[strtolower($tld)] ?? null) === 'icann';
    }

    /** @return array<string, string> */
    private static function rules(): array
    {
        if (self::$rules !== null) {
            return self::$rules;
        }
        $file = base_path((string) config('brand_links.psl.file', 'resources/data/brand-links/public_suffix_list.dat'));
        $key = 'brand_links.psl.'.(is_file($file) ? filemtime($file) : 0);
        try {
            return self::$rules = Cache::rememberForever($key, fn () => self::load($file));
        } catch (\Throwable) {
            return self::$rules = self::load($file);
        }
    }

    /** @return array<string, string> */
    private static function load(string $file): array
    {
        $out = [];
        if (! is_file($file)) {
            return $out;
        }
        $section = 'icann';
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, '// ===BEGIN PRIVATE DOMAINS===')) {
                $section = 'private';
            }
            if ($line === '' || str_starts_with($line, '//')) {
                continue;
            }
            $rule = strtolower(explode(' ', $line)[0]);
            if (preg_match('/[^\x00-\x7F]/', $rule) === 1) {
                $ascii = function_exists('idn_to_ascii') ? idn_to_ascii(ltrim($rule, '!*.'), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
                if (! is_string($ascii)) {
                    continue;
                }
                $rule = (str_starts_with($rule, '!') ? '!' : '').(str_starts_with($rule, '*.') ? '*.' : '').$ascii;
            }
            $out[$rule] = $section;
        }

        return $out;
    }

    public static function flush(): void
    {
        self::$rules = null;
    }
}
