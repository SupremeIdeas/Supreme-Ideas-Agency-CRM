<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 codes of the African Union's 55 member states (UX/
 * localization/platform-reach blueprint Phase F — KYC provider routing).
 * Dojah and Smile ID are built for this market; everywhere else routes to a
 * global provider. This is deliberately the full AU membership, not just the
 * countries this platform currently has heavy traffic in, so routing stays
 * correct as the user base grows into new African markets without a code
 * change.
 */
class AfricanCountries
{
    /** @var list<string> */
    public const CODES = [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD',
        'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET',
        'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG',
        'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW',
        'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG',
        'TN', 'UG', 'ZM', 'ZW', 'EH',
    ];

    public static function is(?string $countryCode): bool
    {
        return in_array(strtoupper((string) $countryCode), self::CODES, true);
    }
}
