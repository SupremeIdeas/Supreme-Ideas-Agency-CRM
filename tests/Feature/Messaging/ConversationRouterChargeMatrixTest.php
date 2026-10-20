<?php

namespace Tests\Feature\Messaging;

use App\Models\User;
use App\Services\Messaging\ConversationRouter;
use App\Services\Messaging\ConversationRouterPlan;
use App\Services\Messaging\ConversationRouterFacts;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveResult;
use App\Services\N2N\Identity\ResolveThrottled;
use Tests\TestCase;

/** Brand-Growth Phase 9: the charge matrix, proven with property tests. No provider, no database money: the router only decides. */
class ConversationRouterChargeMatrixTest extends TestCase
{
    private const MY_ID = 'NR-ME00000001';

    private function user(): User
    {
        $u = new User(['name' => 'Me', 'country_code' => 'NG']);
        $u->id = 1;

        return $u;
    }

    private function resolver(array $known = [], bool $throttle = false): IdentityResolver
    {
        return new class($known, $throttle) extends IdentityResolver {
            public function __construct(private array $known, private bool $throttle)
            {
            }

            public function resolve(string $input, string $purpose = 'search', ?User $actor = null, ?string $ip = null): ResolveResult
            {
                if ($this->throttle) {
                    throw new ResolveThrottled('slow');
                }
                $hit = $this->known[$input] ?? $this->known[strtoupper($input)] ?? null;

                return $hit ? new ResolveResult(true, $hit, 'NR-OTHER', null, 'Other', null, 'x') : ResolveResult::miss();
            }
        };
    }

    private function facts(array $o = []): ConversationRouterFacts
    {
        return new class($o) extends ConversationRouterFacts {
            public function __construct(private array $o)
            {
            }

            private function g(string $k, mixed $d): mixed
            {
                return array_key_exists($k, $this->o) ? $this->o[$k] : $d;
            }

            public function creditBalance(User $u): float
            {
                return $this->g('credit', 100.0);
            }

            public function walletUsd(User $u): float
            {
                return $this->g('wallet', 100.0);
            }

            public function isPro(User $u): bool
            {
                return $this->g('pro', false);
            }

            public function n2nMessageCredits(): float
            {
                return 0.5;
            }

            public function n2nCallCreditsPerMinute(): float
            {
                return 2.0;
            }

            public function smsLineAndPrice(User $u, string $e164): ?array
            {
                return $this->g('line', true) ? ['line' => null, 'usd' => $this->g('sms_usd', 0.05)] : null;
            }

            public function voiceUsdPerMinute(User $u, string $e164): ?float
            {
                return $this->g('voice_usd', 0.12);
            }

            public function numberBlocked(string $e164): bool
            {
                return $this->g('blocked', false);
            }

            public function countryAllowed(?string $region): bool
            {
                return $this->g('country_ok', true);
            }
        };
    }

    private function router(array $known = [], array $facts = [], bool $throttle = false): ConversationRouter
    {
        return new ConversationRouter($this->resolver($known, $throttle), $this->facts($facts));
    }

    private const NAARA_NUM = '+2348012345678';

    private const OTHER_NUM = '+14155550123';

    private const HANDLE = 'NR-ZKX38FRWM1Z';

    public function test_naara_handle_message_is_n2n_paid_by_credit(): void
    {
        $p = $this->router([self::HANDLE => 2])->resolve($this->user(), self::HANDLE, 'message');
        $this->assertSame([ConversationRouterPlan::N2N, ConversationRouterPlan::CREDIT, 0.5, 'message'], [$p->channel, $p->payer, $p->price, $p->unit]);
        $this->assertTrue($p->allowed());
    }

    public function test_naara_handle_is_never_sent_by_sms_even_if_asked(): void
    {
        $p = $this->router([self::HANDLE => 2])->resolve($this->user(), self::HANDLE, 'message', ['via_sms' => true]);
        $this->assertSame(ConversationRouterPlan::NONE, $p->channel);
        $this->assertContains('naara_id_never_sms', $p->blockers);
        $c = $this->router([self::HANDLE => 2])->resolve($this->user(), self::HANDLE, 'call', ['via_pstn' => true]);
        $this->assertSame(ConversationRouterPlan::NONE, $c->channel);
    }

    public function test_number_on_naara_defaults_to_n2n_and_may_explicitly_choose_sms_on_the_wallet(): void
    {
        $r = $this->router([self::NAARA_NUM => 2]);
        $d = $r->resolve($this->user(), self::NAARA_NUM, 'message');
        $this->assertSame([ConversationRouterPlan::N2N, ConversationRouterPlan::CREDIT], [$d->channel, $d->payer]);
        $this->assertTrue($d->target['on_naara']);
        $s = $r->resolve($this->user(), self::NAARA_NUM, 'message', ['via_sms' => true]);
        $this->assertSame([ConversationRouterPlan::PSTN_SMS, ConversationRouterPlan::WALLET], [$s->channel, $s->payer]);
        $this->assertTrue($s->allowed());
    }

    public function test_text_to_a_non_naara_number_needs_the_sms_opt_in_and_a_line(): void
    {
        $r = $this->router();
        $p = $r->resolve($this->user(), self::OTHER_NUM, 'message');
        $this->assertSame([ConversationRouterPlan::PSTN_SMS, ConversationRouterPlan::WALLET], [$p->channel, $p->payer]);
        $this->assertTrue($p->requiresUserChoice);
        $this->assertContains('sms_opt_in_required', $p->blockers);
        $this->assertFalse($p->allowed());

        $ok = $r->resolve($this->user(), self::OTHER_NUM, 'message', ['via_sms' => true]);
        $this->assertTrue($ok->allowed());
        $this->assertSame(0.05, $ok->price);

        $noLine = $this->router([], ['line' => false])->resolve($this->user(), self::OTHER_NUM, 'message', ['via_sms' => true]);
        $this->assertContains('no_line', $noLine->blockers);
    }

    public function test_call_to_a_non_naara_number_is_voice_on_the_wallet_with_a_per_minute_quote(): void
    {
        $p = $this->router()->resolve($this->user(), self::OTHER_NUM, 'call');
        $this->assertSame([ConversationRouterPlan::PSTN_VOICE, ConversationRouterPlan::WALLET, 0.12, 'minute'], [$p->channel, $p->payer, $p->price, $p->unit]);
        $this->assertTrue($p->allowed());
    }

    public function test_insufficient_funds_block_without_falling_back_to_the_other_pot(): void
    {
        $n2n = $this->router([self::HANDLE => 2], ['credit' => 0.1, 'wallet' => 999.0])->resolve($this->user(), self::HANDLE, 'message');
        $this->assertSame(ConversationRouterPlan::N2N, $n2n->channel);
        $this->assertContains('insufficient_credit', $n2n->blockers);
        $this->assertFalse($n2n->allowed());

        $sms = $this->router([], ['credit' => 999.0, 'wallet' => 0.01])->resolve($this->user(), self::OTHER_NUM, 'message', ['via_sms' => true]);
        $this->assertSame(ConversationRouterPlan::PSTN_SMS, $sms->channel);
        $this->assertContains('insufficient_wallet', $sms->blockers);
        $this->assertFalse($sms->allowed());
    }

    public function test_media_needs_pro_on_n2n_only(): void
    {
        $r = $this->router([self::HANDLE => 2]);
        $this->assertContains('pro_required', $r->resolve($this->user(), self::HANDLE, 'message', ['media' => true])->blockers);
        $this->assertTrue($this->router([self::HANDLE => 2], ['pro' => true])->resolve($this->user(), self::HANDLE, 'message', ['media' => true])->allowed());
    }

    public function test_blocked_country_blocked_number_unknown_target_throttle_and_self(): void
    {
        $this->assertContains('number_blocked', $this->router([], ['blocked' => true])->resolve($this->user(), self::OTHER_NUM, 'call')->blockers);
        $this->assertContains('country_not_supported', $this->router([], ['country_ok' => false])->resolve($this->user(), self::OTHER_NUM, 'call')->blockers);
        $this->assertContains('unknown_target', $this->router()->resolve($this->user(), 'not a number', 'message')->blockers);
        $this->assertContains('unknown_target', $this->router()->resolve($this->user(), self::HANDLE, 'message')->blockers);
        $this->assertContains('rate_limited', $this->router([], [], true)->resolve($this->user(), self::OTHER_NUM, 'message')->blockers);
        $this->assertContains('self', $this->router([self::HANDLE => 1])->resolve($this->user(), self::HANDLE, 'message')->blockers);
    }

    /** The property tests: for many random worlds the two pots can never cross and the SMS opt-in is never skipped. */
    public function test_property_pots_never_cross_across_random_scenarios(): void
    {
        mt_srand(20261012);
        $targets = [self::HANDLE, self::NAARA_NUM, self::OTHER_NUM, '+2348099999999', 'garbage', '@someone'];
        $known = [self::HANDLE => 2, self::NAARA_NUM => 2, '@someone' => 2];
        for ($i = 0; $i < 400; $i++) {
            $facts = [
                'credit' => mt_rand(0, 3) === 0 ? 0.0 : mt_rand(1, 1000) / 10, 'wallet' => mt_rand(0, 3) === 0 ? 0.0 : mt_rand(1, 1000) / 10,
                'pro' => (bool) mt_rand(0, 1), 'line' => (bool) mt_rand(0, 1), 'blocked' => mt_rand(0, 9) === 0, 'country_ok' => mt_rand(0, 9) !== 0,
                'sms_usd' => mt_rand(1, 20) / 100, 'voice_usd' => mt_rand(1, 50) / 100,
            ];
            $target = $targets[mt_rand(0, count($targets) - 1)];
            $intent = mt_rand(0, 1) ? 'call' : 'message';
            $opts = ['via_sms' => (bool) mt_rand(0, 1), 'via_pstn' => (bool) mt_rand(0, 1), 'media' => (bool) mt_rand(0, 1)];
            $p = $this->router($known, $facts)->resolve($this->user(), $target, $intent, $opts);
            $ctx = json_encode(compact('facts', 'target', 'intent', 'opts'));

            if ($p->channel === ConversationRouterPlan::N2N) {
                $this->assertSame(ConversationRouterPlan::CREDIT, $p->payer, "N2N must be paid by credit: $ctx");
                $this->assertNotContains('insufficient_wallet', $p->blockers, "wallet is never consulted for N2N: $ctx");
            }
            if (in_array($p->channel, [ConversationRouterPlan::PSTN_SMS, ConversationRouterPlan::PSTN_VOICE], true)) {
                $this->assertSame(ConversationRouterPlan::WALLET, $p->payer, "PSTN must be paid by the wallet: $ctx");
                $this->assertNotContains('insufficient_credit', $p->blockers, "credit is never consulted for PSTN: $ctx");
                $this->assertNotSame(self::HANDLE, $target, "a Naara ID is never routed to PSTN: $ctx");
                $this->assertNotSame('@someone', $target, "a Naara username is never routed to PSTN: $ctx");
            }
            if ($p->channel === ConversationRouterPlan::PSTN_SMS && $p->allowed()) {
                $this->assertTrue($opts['via_sms'], "SMS is never sent without the explicit opt-in: $ctx");
            }
            if ($p->allowed()) {
                $this->assertSame([], $p->blockers, $ctx);
                $this->assertNotNull($p->price, "an allowed route always shows its price first: $ctx");
            }
            if ($p->channel === ConversationRouterPlan::NONE) {
                $this->assertFalse($p->allowed(), $ctx);
            }
        }
    }
}
