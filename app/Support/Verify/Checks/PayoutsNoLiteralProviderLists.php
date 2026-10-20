<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/**
 * Blueprint 2.0b: a hand-written provider list in the payout code can never see a rail the Platform Updater delivers later, so Payout Health
 * cannot "detect" it. This finds such lists in the health/status layer; it turns green when they are derived from the gateway registry.
 */
class PayoutsNoLiteralProviderLists implements Check
{
    private const FILES = [
        'app/Services/Payouts/Hardening/AutomationStatus.php',
        'app/Livewire/Admin/PayoutHealth.php',
        'app/Services/Payouts/Health/GatewayStatusResolver.php',
        // RailAdvisor is deliberately not listed: its FAST_RAILS are the three user-facing local RAIL identifiers, not a gateway list.
    ];

    public function area(): string
    {
        return 'payouts';
    }

    public function name(): string
    {
        return 'Payout health lists come from the gateway registry';
    }

    public function run(): CheckResult
    {
        $hits = [];
        foreach (self::FILES as $rel) {
            $path = base_path($rel);
            if (! is_file($path)) {
                continue;
            }
            foreach (file($path) as $i => $line) {
                if (preg_match("/\\[\\s*'paystack'\\s*,\\s*'flutterwave'/", $line)) {
                    $hits[] = $rel.':'.($i + 1);
                }
            }
        }

        return $hits === []
            ? CheckResult::pass('No hand-written provider list in the payout health layer.')
            : CheckResult::warn('Hand-written provider lists found; a rail added later would not be evaluated.', ['at' => $hits]);
    }
}
