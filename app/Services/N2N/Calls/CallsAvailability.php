<?php

namespace App\Services\N2N\Calls;

use App\Models\Setting;
use App\Services\N2N\Realtime\Realtime;
use App\Support\N2N\N2NSettings;

/**
 * Are N2N voice calls on? (MASTER-ONLY.) Calls need a VPS: realtime must be Reverb and a TURN relay must be configured, because many mobile
 * networks sit behind carrier-grade NAT. cPanel / poll-only installs keep the feature OFF. A clearly labelled dev switch skips both checks for
 * local testing (STUN only, polling signalling): never for production.
 */
class CallsAvailability
{
    public static function devMode(): bool
    {
        return (bool) Setting::getValue('n2n.calls_dev_mode', false);
    }

    public static function turnConfigured(): bool
    {
        return TurnCredentials::secret() !== '' && TurnCredentials::turnUrls() !== [];
    }

    /** @return array{enabled: bool, reasons: list<string>} */
    public static function status(): array
    {
        $reasons = [];
        if (! N2NSettings::enabled()) {
            $reasons[] = 'N2N is switched off.';
        }
        if (! (bool) Setting::getValue('n2n.calls_enabled', false)) {
            $reasons[] = 'Calls are switched off in Admin > N2N.';
        }
        if (! self::devMode()) {
            if (Realtime::driver() !== 'reverb') {
                $reasons[] = 'Calls need the Reverb realtime driver (a VPS).';
            }
            if (! self::turnConfigured()) {
                $reasons[] = 'No TURN relay is configured (N2N_TURN_URLS and N2N_TURN_SECRET).';
            }
        }

        return ['enabled' => $reasons === [], 'reasons' => $reasons];
    }

    /** Video is an add-on to calls: it needs calls to be on and the admin's video switch (default on once calls are on). */
    public static function videoEnabled(): bool
    {
        return self::enabled() && (bool) Setting::getValue('n2n.calls_video_enabled', true);
    }

    public static function enabled(): bool
    {
        return self::status()['enabled'];
    }
}
