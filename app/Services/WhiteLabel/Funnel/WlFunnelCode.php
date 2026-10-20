<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\WhiteLabelLicensePlan;

/**
 * The funnel reference a buyer quotes to support (MASTER-ONLY): a plan prefix (BS for basic/medium, EX for extended/extended_v2), a dash, eight Crockford base32
 * characters and one check symbol, so a mistyped code is caught before a database lookup. Same style as the Naara ID but its own class: the funnel never imports
 * N2N code. Immutable once issued.
 */
final class WlFunnelCode
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const CHECK = '0123456789ABCDEFGHJKMNPQRSTVWXYZ*~$=U';

    public static function prefixFor(string $planKey): string
    {
        return in_array($planKey, [WhiteLabelLicensePlan::KEY_EXTENDED, WhiteLabelLicensePlan::KEY_EXTENDED_V2], true) ? 'EX' : 'BS';
    }

    public static function generate(string $planKey): string
    {
        $body = '';
        for ($i = 0; $i < 8; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return self::prefixFor($planKey).'-'.$body.self::check($body);
    }

    public static function valid(string $code): bool
    {
        $n = self::normalise($code);

        return $n !== null;
    }

    /** Canonical upper-case form (ambiguous letters mapped), or null when the shape or check symbol is wrong. */
    public static function normalise(string $input): ?string
    {
        $s = strtoupper(preg_replace('/[\s]+/', '', trim($input)) ?? '');
        if (preg_match('/^(BS|EX)-?([0-9A-Z*~$=]{9})$/', $s, $m) !== 1) {
            return null;
        }
        $rest = strtr($m[2], ['O' => '0', 'I' => '1', 'L' => '1']);
        $body = substr($rest, 0, 8);
        if (preg_match('/^['.self::ALPHABET.']{8}$/', $body) !== 1 || self::check($body) !== substr($rest, 8, 1)) {
            return null;
        }

        return $m[1].'-'.$rest;
    }

    private static function check(string $body): string
    {
        $v = 0;
        foreach (str_split($body) as $c) {
            $v = ($v * 32 + strpos(self::ALPHABET, $c)) % 37;
        }

        return self::CHECK[$v];
    }
}
