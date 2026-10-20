<?php

namespace App\Support\Business;

use App\Models\Setting;
use App\Support\FeatureEntitlements;

/**
 * The Business Suite master switch (MASTER-ONLY). Off by default and never on in a white-label install.
 *
 * "Off" means: every /business* page and every call-flow webhook is a plain 404 (or, for the shared Twilio voice webhook, is skipped),
 * org-owned numbers keep working exactly like personal numbers (forwarding rules, voicemail), and no nav entry mentions the Suite.
 * Nothing is deleted when it is switched off: workspaces, seats, flows and recordings stay in the database and come back when it is switched on.
 */
class BusinessSuiteSettings
{
    public const FLAG = 'business_suite.enabled';

    public static function enabled(): bool
    {
        return FeatureEntitlements::isMaster() && (bool) Setting::getValue(self::FLAG, false);
    }

    public static function set(bool $on): void
    {
        Setting::setValue(self::FLAG, $on ? '1' : '0');
    }
}
