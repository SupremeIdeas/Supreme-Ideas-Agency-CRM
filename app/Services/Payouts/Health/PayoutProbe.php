<?php

namespace App\Services\Payouts\Health;

use App\Models\PayoutCorridor;
use App\Models\PayoutProbeRun;
use App\Services\Payouts\PayoutService;
use App\Support\Bootstrap\ProviderTester;

/**
 * "Test function / endpoint" per payout gateway (Engineering v3, Phase 2.3), so nobody has to guess whether a rail works.
 * Two safe tiers, both read-only (no money moves, nothing is sent):
 *   auth        a free authenticated read (balance / account / token) proves the credentials and the network path
 *   capability  asks the provider what it supports and compares it with the corridors the admin enabled; every disagreement is a mismatch
 * Each run is stored (summary and check names only: never a secret, a balance or personal data) and is what Payout Health shows.
 */
class PayoutProbe
{
    public const TIERS = ['auth', 'capability'];

    public function __construct(
        private readonly PayoutService $payouts,
        private readonly GatewayStatusResolver $resolver,
    ) {
    }

    /** @return array{provider: string, tier: string, status: string, latency_ms: ?int, summary: string, checks: list<array{label: string, ok: bool}>, mismatches: list<string>} */
    public function run(string $provider, string $tier = 'auth', ?int $userId = null): array
    {
        $tier = in_array($tier, self::TIERS, true) ? $tier : 'auth';
        $status = $this->resolver->statusOf($provider);
        $started = microtime(true);

        if (! $status->usable() && ! ($tier === 'auth' && in_array($status->reason, [GatewayStatus::R_BREAKER], true))) {
            // Nothing to probe: say exactly why, and do not call the provider with credentials that cannot work.
            $result = ['status' => 'skipped', 'summary' => $status->adminMessage, 'checks' => [['label' => 'Gateway is configured', 'ok' => false]], 'mismatches' => []];
        } else {
            $result = $tier === 'auth' ? $this->auth($provider) : $this->capability($provider);
        }
        $latency = (int) round((microtime(true) - $started) * 1000);

        $run = ['provider' => $provider, 'tier' => $tier, 'latency_ms' => $latency] + $result;
        PayoutProbeRun::create($run + ['ran_by' => $userId, 'created_at' => now()]);

        return $run;
    }

    /** @return list<array<string, mixed>> */
    public function runAll(string $tier = 'auth', ?int $userId = null): array
    {
        $out = [];
        foreach ($this->resolver->all() as $s) {
            if ($s->provider === 'manual_external' || $s->reason === GatewayStatus::R_NOT_INSTALLED) {
                continue;
            }
            $out[] = $this->run($s->provider, $tier, $userId);
        }

        return $out;
    }

    public function latest(string $provider, string $tier): ?PayoutProbeRun
    {
        return PayoutProbeRun::query()->where(['provider' => $provider, 'tier' => $tier])->latest('id')->first();
    }

    /** @return array{status: string, summary: string, checks: list<array{label: string, ok: bool}>, mismatches: list<string>} */
    private function auth(string $provider): array
    {
        if (! ProviderTester::canTest($provider)) {
            return ['status' => 'skipped', 'summary' => 'This provider has no free read call, so the first small real payout is the test.', 'checks' => [], 'mismatches' => []];
        }
        $r = ProviderTester::test($provider);
        $ok = $r['status'] === 'ok';

        return [
            'status' => match ($r['status']) { 'ok' => 'ok', 'manual', 'empty' => 'skipped', default => 'fail' },
            'summary' => $r['message'],
            'checks' => [['label' => 'Credentials accepted by the provider', 'ok' => $ok]],
            'mismatches' => [],
        ];
    }

    /** @return array{status: string, summary: string, checks: list<array{label: string, ok: bool}>, mismatches: list<string>} */
    private function capability(string $provider): array
    {
        $corridors = PayoutCorridor::query()->enabled()->where('provider', $provider)->get();
        if ($corridors->isEmpty()) {
            return ['status' => 'skipped', 'summary' => 'No corridor is enabled for this rail, so there is nothing to compare yet.', 'checks' => [], 'mismatches' => []];
        }

        $checks = [];
        $mismatches = [];
        foreach ($corridors->groupBy('country') as $country => $rows) {
            $country = (string) $country;
            if (in_array($provider, ['paystack', 'flutterwave'], true) && $rows->contains(fn ($c) => $c->method === 'bank')) {
                $resolver = collect($this->bankResolvers())->first(fn ($r) => $r->name() === $provider);
                $count = $resolver && $resolver->supports($country) ? count($resolver->banks($country)) : 0;
                $ok = $count > 0;
                $checks[] = ['label' => "{$country}: bank list from {$provider}", 'ok' => $ok];
                $ok || $mismatches[] = "{$country} is enabled for {$provider}, but {$provider} returned no banks for it.";
            } elseif ($provider === 'grey' && class_exists(\App\PayoutRails\Grey\GreyClient::class)) {
                $methods = app(\App\PayoutRails\Grey\GreyClient::class)->payoutMethods(true);
                foreach ($rows as $c) {
                    $m = app(\App\PayoutRails\Grey\GreyBeneficiaryMapper::class)->methodFor($methods, (string) $c->currency, $country, $c->method === 'mobile_money');
                    $ok = $methods !== [] && $m !== null;
                    $checks[] = ['label' => "{$country} {$c->currency} ({$c->method}) is on Grey's payout list", 'ok' => $ok];
                    $ok || $mismatches[] = $methods === [] ? 'Grey could not be read, so '.$country.' could not be confirmed.' : "{$country} {$c->currency} ({$c->method}) is enabled here but Grey does not list it.";
                }
            }
        }
        if ($checks === []) {
            return ['status' => 'skipped', 'summary' => 'This provider has no capability list we can compare against yet.', 'checks' => [], 'mismatches' => []];
        }

        return [
            'status' => $mismatches === [] ? 'ok' : 'fail',
            'summary' => $mismatches === [] ? 'Every enabled corridor is confirmed by the provider.' : count($mismatches).' enabled corridor(s) the provider does not confirm. Disable them or fix the provider setup.',
            'checks' => $checks,
            'mismatches' => $mismatches,
        ];
    }

    /** @return list<\App\Services\Payouts\BankResolverInterface> */
    private function bankResolvers(): array
    {
        return [app(\App\Services\Payouts\PaystackBankResolver::class), app(\App\Services\Payouts\FlutterwaveBankResolver::class)];
    }
}
