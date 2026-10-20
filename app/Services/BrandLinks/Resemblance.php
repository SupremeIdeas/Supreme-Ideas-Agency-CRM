<?php

namespace App\Services\BrandLinks;

use Illuminate\Support\Str;

/**
 * How much does a domain look like the brand's own name? 0 to 100. The registrable-domain label (and the customer label of a shared host) is compared with
 * the brand name after both are normalised (accents, punctuation and stopwords like "ltd" removed). The score is the best of four signals:
 * text similarity, every brand word present, the initialism ("Supreme Ideas Agency" is `sia`), and one name containing the other.
 */
final class Resemblance
{
    /** @param list<string> $stopwords */
    public static function score(string $brand, string $label, array $stopwords = []): int
    {
        $tokens = self::tokens($brand, $stopwords);
        $joined = implode('', $tokens);
        $domain = self::flat($label);
        if ($joined === '' || $domain === '') {
            return 0;
        }
        $best = 0;

        // 1) similarity of the two strings
        similar_text($joined, $domain, $pct);
        $lev = max(strlen($joined), strlen($domain)) > 0 ? (1 - levenshtein(substr($joined, 0, 60), substr($domain, 0, 60)) / max(strlen(substr($joined, 0, 60)), strlen(substr($domain, 0, 60)))) * 100 : 0;
        $best = max($best, (int) round(max($pct, $lev)));

        // 2) every brand word is in the domain
        $all = $tokens !== [] && count(array_filter($tokens, fn ($t) => strlen($t) >= 2 && str_contains($domain, $t))) === count(array_filter($tokens, fn ($t) => strlen($t) >= 2)) && count(array_filter($tokens, fn ($t) => strlen($t) >= 2)) > 0;
        if ($all) {
            $best = max($best, 100);
        }

        // 3) initialism
        if (count($tokens) >= 2) {
            $initials = implode('', array_map(fn ($t) => $t[0], $tokens));
            if (strlen($initials) >= 3 && $initials === $domain) {
                $best = max($best, 55);   // an initialism is ambiguous (sia.com may be anyone): it never clears the auto-approve line, an admin looks at it
            }
        }

        // 4) one contains the other (supremeideas is inside supremeideasagency)
        $short = strlen($joined) <= strlen($domain) ? $joined : $domain;
        $long = $short === $joined ? $domain : $joined;
        if (strlen($short) >= 4 && str_contains($long, $short)) {
            $best = max($best, max(60, (int) round(100 * strlen($short) / strlen($long))));
        }

        return min(100, $best);
    }

    /** @param list<string> $stopwords @return list<string> */
    public static function tokens(string $name, array $stopwords = []): array
    {
        $ascii = Str::lower(Str::ascii($name));
        $parts = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, fn ($p) => ! in_array($p, $stopwords, true)));
    }

    public static function flat(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($s))) ?? '';
    }
}
