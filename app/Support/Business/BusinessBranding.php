<?php

namespace App\Support\Business;

/** The name the Suite's subscription goes by in shared surfaces (MASTER-ONLY). Where the Suite is off or absent, callers keep the plain "List my brand" wording. */
class BusinessBranding
{
    public const NAME = 'NaaraBusiness Suite';

    public static function listedLabel(string $fallback = 'List my brand'): string
    {
        return BusinessSuiteSettings::enabled() ? self::NAME : $fallback;
    }

    /** Safe to call from a file that also ships in a white-label install. */
    public static function label(string $fallback = 'List my brand'): string
    {
        return class_exists(self::class) ? self::listedLabel($fallback) : $fallback;
    }
}
