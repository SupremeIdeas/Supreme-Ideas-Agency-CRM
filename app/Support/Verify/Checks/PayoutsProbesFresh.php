<?php

namespace App\Support\Verify\Checks;

use App\Models\PayoutCorridor;
use App\Models\PayoutProbeRun;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Blueprint 2.3: a rail that carries real payouts should have been probed lately, and its last probe should have passed. */
class PayoutsProbesFresh implements Check
{
    public function area(): string
    {
        return 'payouts';
    }

    public function name(): string
    {
        return 'Payout gateway probes are recent and passing';
    }

    public function run(): CheckResult
    {
        $providers = PayoutCorridor::query()->enabled()->distinct()->pluck('provider')->all();
        if ($providers === []) {
            return CheckResult::warn('No enabled corridor to probe yet.');
        }
        $stale = [];
        $failing = [];
        foreach ($providers as $p) {
            $last = PayoutProbeRun::query()->where(['provider' => $p, 'tier' => 'auth'])->latest('id')->first();
            if ($last === null || $last->created_at->lt(now()->subDays(7))) {
                $stale[] = $p;
            } elseif ($last->status === 'fail') {
                $failing[] = $p;
            }
        }
        if ($failing !== []) {
            return CheckResult::fail('The latest probe failed for: '.implode(', ', $failing).'.', ['failing' => $failing]);
        }

        return $stale === []
            ? CheckResult::pass('Every payout rail has a passing probe from the last 7 days.')
            : CheckResult::warn('Not probed in the last 7 days: '.implode(', ', $stale).'. Open Payouts, Health and press Run all probes.', ['stale' => $stale]);
    }
}
