<?php

namespace App\Support\Verify\Checks;

use App\Models\PayoutCorridor;
use App\Services\Payouts\Health\GatewayStatusResolver;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Blueprint 2.4: every corridor an admin enabled must be payable, i.e. its gateway is ready. Otherwise members are offered a rail that cannot pay. */
class PayoutsGatewaysReady implements Check
{
    public function area(): string
    {
        return 'payouts';
    }

    public function name(): string
    {
        return 'Every enabled corridor has a ready gateway';
    }

    public function run(): CheckResult
    {
        $providers = PayoutCorridor::query()->enabled()->distinct()->pluck('provider')->all();
        if ($providers === []) {
            return CheckResult::warn('No payout corridor is enabled, so nobody can withdraw yet.');
        }
        $resolver = app(GatewayStatusResolver::class);
        $bad = [];
        foreach ($providers as $p) {
            $s = $resolver->statusOf($p);
            if (! $s->usable()) {
                $bad[$p] = $s->adminMessage;
            }
        }

        return $bad === []
            ? CheckResult::pass('Every enabled corridor is served by a ready gateway.', ['providers' => $providers])
            : CheckResult::fail('Corridors are enabled for a gateway that cannot pay right now.', ['not_ready' => $bad]);
    }
}
