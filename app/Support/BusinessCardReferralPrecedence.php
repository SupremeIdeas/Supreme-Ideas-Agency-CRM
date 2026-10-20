<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Which referral link wins when a visitor has met more than one (Brand-Growth Phase 6, MASTER-ONLY). The cookie is single-valued, so the admin chooses:
 *   last  - the newest ?ref link replaces the older one (the long-standing behaviour, and the default);
 *   first - the first link a visitor ever followed keeps the credit;
 *   brand - a Business Card link always beats a personal referral link (between two of the same kind the newer wins).
 */
class BusinessCardReferralPrecedence
{
    public const MODES = ['last' => 'The newest link wins', 'first' => 'The first link wins', 'brand' => 'A brand card link beats a personal link'];

    public static function mode(): string
    {
        $m = (string) Setting::getValue('referral.precedence', 'last');

        return array_key_exists($m, self::MODES) ? $m : 'last';
    }

    /** Should a new referral link replace the one already remembered? @param 'brand'|'personal'|null $existingKind null when nothing is remembered */
    public static function replaces(bool $hasExisting, ?string $existingKind, string $newKind): bool
    {
        if (! $hasExisting) {
            return true;
        }

        return match (self::mode()) {
            'first' => false,
            'brand' => ! ($existingKind === 'brand' && $newKind === 'personal'),
            default => true,
        };
    }
}
