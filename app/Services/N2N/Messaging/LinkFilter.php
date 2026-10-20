<?php

namespace App\Services\N2N\Messaging;

use App\Models\Setting;

/**
 * Best-effort link filter for N2N messages (MASTER-ONLY). It reduces phishing; it cannot be absolute, and the copy must never promise
 * more. It catches URLs, schemeless domains, markdown links, "dot" spellings, spaced or zero-width-character obfuscation and look-alike
 * (mixed-script) domains. Links that Naara itself issues (profile and call links on this host) are allowed.
 */
class LinkFilter
{
    private const TLDS = 'com|net|org|io|co|me|ng|gh|ke|za|uk|us|ca|in|info|biz|app|xyz|top|site|online|link|click|live|shop|store|tech|dev|ly|gl|gd|cc|tv|to|ws|vip|club|icu|cn|ru|tk|ml|ga|cf|gq|pw|fun|win|bid|loan|work|date|men|stream|download|party|review|trade|webcam|science|racing|accountant|cricket|faith|zip|mov';

    /** @return string|null why the text is refused, or null when it is allowed */
    public static function problem(string $text): ?string
    {
        $flat = self::flatten($text);
        if ($flat === '') {
            return null;
        }
        foreach (self::candidates($text, $flat) as $c) {
            if (! self::allowed($c)) {
                return 'Links are not allowed in messages.';
            }
        }

        return null;
    }

    /** Fold the common evasions into one comparable string. */
    private static function flatten(string $text): string
    {
        $t = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text) ?? $text;   // zero-width and bidi characters
        $t = mb_strtolower($t);
        $t = preg_replace('/\s*[\[\(\{<]\s*(?:dot|\.)\s*[\]\)\}>]\s*/u', '.', $t) ?? $t;                            // "example [dot] com", "(.)"
        $t = preg_replace('/\s+(?:dot|d0t)\s+/u', '.', $t) ?? $t;                                                    // "example dot com"
        $t = preg_replace('/\s*\.\s*/u', '.', $t) ?? $t;                                                              // "example . com", "example. com"
        $t = preg_replace('/(?<=\w)\s+(?=\.)/u', '', $t) ?? $t;
        $t = str_replace(['hxxp', 'h t t p', 'ｈｔｔｐ'], 'http', $t);
        $t = preg_replace('/\bw\s*w\s*w\b/u', 'www', $t) ?? $t;
        // "e x a m p l e": a run of single letters/digits spread out with spaces is joined back together (detection only; nothing is stored).
        for ($i = 0; $i < 3; $i++) {
            $t = preg_replace('/(?<![\p{L}\p{N}])([\p{L}\p{N}])\s+(?=[\p{L}\p{N}](?![\p{L}\p{N}]))/u', '$1', $t) ?? $t;
        }

        return $t;
    }

    /** @return list<string> */
    private static function candidates(string $original, string $flat): array
    {
        $out = [];
        foreach ([$original, $flat] as $t) {
            if (preg_match_all('~(?:https?://|ftp://|www\.)[^\s<>"\')\]]+~iu', $t, $m)) {
                array_push($out, ...$m[0]);
            }
            if (preg_match_all('~\[[^\]]*\]\(([^)]+)\)~u', $t, $m)) {
                array_push($out, ...$m[1]);                                    // markdown [label](url)
            }
            if (preg_match_all('~(?<![\w@.-])((?:[\p{L}\p{N}-]+\.)+(?:'.self::TLDS.'|xn--[a-z0-9-]+))(?![\p{L}\p{N}-])(?:[/:?#][^\s]*)?~iu', $t, $m)) {
                array_push($out, ...$m[0]);                                    // bare domains
            }
        }
        // A look-alike domain: a dotted token that mixes scripts (e.g. a Cyrillic letter inside a Latin name).
        foreach (preg_split('/\s+/u', $flat) ?: [] as $word) {
            if (str_contains($word, '.') && preg_match('/\p{Cyrillic}|\p{Greek}/u', $word) && preg_match('/[a-z]/i', $word)) {
                $out[] = $word;
            }
        }

        return array_values(array_unique($out));
    }

    private static function allowed(string $candidate): bool
    {
        $host = parse_url(str_contains($candidate, '://') ? $candidate : 'https://'.$candidate, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        $own = [strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))];
        $extra = array_filter(array_map('trim', explode(',', strtolower((string) Setting::getValue('n2n.allowed_link_hosts', '')))));
        foreach (array_merge($own, $extra) as $ok) {
            if ($ok !== '' && ($host === $ok || str_ends_with($host, '.'.$ok))) {
                return true;
            }
        }

        return false;
    }
}
