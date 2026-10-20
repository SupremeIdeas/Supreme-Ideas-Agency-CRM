<?php

namespace App\Services\N2N\Group;

use App\Models\Setting;
use App\Support\N2N\N2NSettings;

/** Are group calls on? (N2N Batch 11, MASTER-ONLY.) Off unless N2N is on, the admin switched them on, an SFU is configured AND its health check passes. */
class GroupAvailability
{
    /** @return array{enabled: bool, reasons: list<string>} */
    public static function status(): array
    {
        $reasons = [];
        if (! N2NSettings::enabled()) {
            $reasons[] = 'N2N is switched off.';
        }
        if (! (bool) Setting::getValue('n2n.group_calls_enabled', false)) {
            $reasons[] = 'Group calls are switched off in Admin > N2N.';
        }
        $health = SfuClient::health();
        if (! $health['ok']) {
            $reasons[] = $health['detail'];
        }

        return ['enabled' => $reasons === [], 'reasons' => $reasons];
    }

    public static function enabled(): bool
    {
        return self::status()['enabled'];
    }
}
