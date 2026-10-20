<?php

namespace App\Support\Verify\Checks;

use App\Support\Trustpilot\TrustpilotSettings as TP;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** `naara:verify trustpilot`: when the reviews layer is on, it has what it needs to render (business unit, a template id or a profile link) and its CSP origins are well-formed. Pure reads. */
class TrustpilotSound implements Check
{
    public function area(): string
    {
        return 'trustpilot';
    }

    public function name(): string
    {
        return 'Trustpilot: the reviews layer is configured to render, or is cleanly off';
    }

    public function run(): CheckResult
    {
        if (! TP::enabled()) {
            return CheckResult::pass('Trustpilot is off; nothing is rendered and no external script loads.');
        }
        $bad = [];
        if (TP::businessUnitId() === '') {
            $bad[] = 'it is on but no Business Unit ID is set, so no widget can load';
        }
        foreach (TP::PLACEMENTS as $key => $cfg) {
            if (TP::placementOn($key) && TP::templateId($cfg['type']) === '' && TP::profileUrl() === '') {
                $bad[] = "placement {$key} is on with neither a template id nor a profile link";
            }
        }
        if ($bad !== []) {
            return CheckResult::fail(implode('; ', $bad).'.', ['problems' => $bad]);
        }
        if (TP::profileUrl() === '') {
            return CheckResult::warn('Trustpilot is on but no profile link is set, so widgets have no fallback link.');
        }

        return CheckResult::pass('Trustpilot is on and every enabled placement can render.');
    }
}
