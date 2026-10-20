<?php

namespace App\Support\Verify\Checks;

use App\Models\User;
use App\Services\Messaging\ConversationRouter;
use App\Services\Messaging\ConversationRouterFacts;
use App\Services\Messaging\ConversationRouterPlan as Plan;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveResult;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/**
 * MASTER-ONLY. `naara:verify messaging`: the Conversation Router's charge matrix holds on this install, run through the live router class with a stand-in
 * identity directory and stand-in balances (so it touches no provider and no money). Also checks the N2N prices the matrix leans on are set.
 */
class ConversationRouterMatrixSound implements Check
{
    public function area(): string
    {
        return 'messaging';
    }

    public function name(): string
    {
        return 'Messaging: the charge matrix holds (credit for Naara, wallet for SMS/calls, never crossed)';
    }

    public function run(): CheckResult
    {
        try {
            if (N2NPricing::messageCostCents() <= 0 || N2NPricing::callRatePerMinuteCents() <= 0) {
                return CheckResult::fail('N2N message or call prices are not set (Admin > Naara Chat > Pricing), so the router cannot quote a Naara chat.');
            }
            $me = new User(['name' => 'Verifier', 'country_code' => 'NG']);
            $me->id = 1;
            $handle = 'NR-ZKX38FRWM1Z';
            $resolver = new class extends IdentityResolver {
                public function __construct()
                {
                }

                public function resolve(string $input, string $purpose = 'search', ?User $actor = null, ?string $ip = null): ResolveResult
                {
                    return in_array($input, ['NR-ZKX38FRWM1Z', '+2348012345678'], true) ? new ResolveResult(true, 2, 'NR-ZKX38FRWM1Z', null, 'Other', null, 'x') : ResolveResult::miss();
                }
            };
            $rich = new class extends ConversationRouterFacts {
                public function creditBalance(User $u): float { return 1000.0; }
                public function walletUsd(User $u): float { return 1000.0; }
                public function isPro(User $u): bool { return false; }
                public function n2nMessageCredits(): float { return 0.5; }
                public function n2nCallCreditsPerMinute(): float { return 2.0; }
                public function smsLineAndPrice(User $u, string $e164): ?array { return ['line' => null, 'usd' => 0.05]; }
                public function voiceUsdPerMinute(User $u, string $e164): ?float { return 0.1; }
                public function numberBlocked(string $e164): bool { return false; }
                public function countryAllowed(?string $region): bool { return true; }
            };
            $noCredit = new class extends ConversationRouterFacts {
                public function creditBalance(User $u): float { return 0.0; }
                public function walletUsd(User $u): float { return 1000.0; }
                public function n2nMessageCredits(): float { return 0.5; }
                public function n2nCallCreditsPerMinute(): float { return 2.0; }
            };
            $r = new ConversationRouter($resolver, $rich);
            $bad = [];
            $expect = function (string $what, bool $ok) use (&$bad) {
                if (! $ok) {
                    $bad[] = $what;
                }
            };
            $a = $r->resolve($me, $handle, 'message');
            $expect('Naara ID message is N2N on credit', $a->channel === Plan::N2N && $a->payer === Plan::CREDIT && $a->allowed());
            $b = $r->resolve($me, $handle, 'message', ['via_sms' => true]);
            $expect('a Naara ID is never sent as SMS', $b->channel === Plan::NONE);
            $c = $r->resolve($me, '+2348012345678', 'message');
            $expect('a Naara number defaults to N2N on credit', $c->channel === Plan::N2N && $c->payer === Plan::CREDIT);
            $d = $r->resolve($me, '+14155550123', 'message');
            $expect('a text to a non-Naara number needs the SMS opt-in', $d->channel === Plan::PSTN_SMS && $d->payer === Plan::WALLET && ! $d->allowed());
            $e = $r->resolve($me, '+14155550123', 'message', ['via_sms' => true]);
            $expect('SMS with opt-in is wallet-paid and allowed', $e->payer === Plan::WALLET && $e->allowed() && $e->price !== null);
            $f = $r->resolve($me, '+14155550123', 'call');
            $expect('a call to a non-Naara number is voice on the wallet', $f->channel === Plan::PSTN_VOICE && $f->payer === Plan::WALLET && $f->allowed());
            $g = (new ConversationRouter($resolver, $noCredit))->resolve($me, $handle, 'message');
            $expect('no credit blocks N2N and never falls back to the wallet', $g->channel === Plan::N2N && in_array('insufficient_credit', $g->blockers, true) && ! $g->allowed());
        } catch (Throwable $t) {
            return CheckResult::fail('The router could not be exercised: '.$t->getMessage());
        }

        return $bad === [] ? CheckResult::pass('The charge matrix holds: Naara chats use credit, SMS and calls use the wallet, and the two never cross.') : CheckResult::fail('Charge matrix broken: '.implode('; ', $bad).'.', ['broken' => $bad]);
    }
}
