<?php

namespace App\Services\N2N\Identity;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** Any local or international phone format -> E.164, via libphonenumber (never hand-rolled per country). N2N, MASTER-ONLY. */
class PhoneNormalizer
{
    /** @return array{e164: string, region: ?string}|null null when the input is not a valid number */
    public static function normalise(string $input, ?string $defaultRegion = null): ?array
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 32) {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $region = $defaultRegion !== null && $defaultRegion !== '' ? strtoupper($defaultRegion) : null;
            $number = $util->parse($input, str_starts_with($input, '+') ? null : $region);
            if (! $util->isValidNumber($number)) {
                return null;
            }

            return ['e164' => $util->format($number, PhoneNumberFormat::E164), 'region' => $util->getRegionCodeForNumber($number)];
        } catch (NumberParseException) {
            return null;
        }
    }
}
