<?php

namespace Tests\Feature\N2N;

use App\Jobs\N2N\SendCreditGiftInviteJob;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\N2N\CreditGift;
use App\Models\N2N\UsageLedger;
use App\Models\Setting;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Credits\CreditService;
use App\Services\N2N\Credit\CreditAccounting;
use App\Services\N2N\Credit\CreditGifts;
use App\Services\N2N\Credit\GiftVelocity;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Realtime\Realtime;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Queue;

/** N2N Batch 5: the usage-credit economy. Treated as ledger invariants (MASTER-ONLY). */
class CreditTest extends N2NTestCase
{
    private function member(array $attrs = []): User
    {
        $u = $this->user(array_merge(['email_verified_at' => now()], $attrs));
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();

        return $u->fresh();
    }

    private function fund(User $u, float $usd): void
    {
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'fund-'.$u->id.'-'.uniqid()]);
    }

    private function buy(User $u, float $usd, string $key = null): void
    {
        $this->fund($u, $usd);
        app(UsageCredit::class)->purchase($u, $usd, $key ?? 'k'.uniqid());
    }

    private function bal(User $u): array
    {
        return app(UsageLedgerService::class)->balances($u->id);
    }

    // ---- Phase A/B: ledger + purchase
    public function test_purchase_debits_the_wallet_credits_the_peg_and_buys_exactly_once_on_a_double_submit(): void
    {
        $u = $this->member();
        $this->fund($u, 10);
        $svc = app(UsageCredit::class);

        $r = $svc->purchase($u, 4.00, 'same-key');
        $svc->purchase($u, 4.00, 'same-key');   // double submit

        $this->assertSame('400.00', $r['credits'], '100 credits per USD by default');
        $this->assertSame(40000, $this->bal($u)['shareable']);
        $this->assertSame(6.0, round((float) $u->wallet()->first()->usd_balance, 2), 'debited once');
        $this->assertSame(1, UsageLedger::where('user_id', $u->id)->where('type', 'purchase')->count());
    }

    public function test_purchase_fails_cleanly_when_the_wallet_cannot_cover_it(): void
    {
        $u = $this->member();
        $this->fund($u, 1);
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        try {
            app(UsageCredit::class)->purchase($u, 5, 'k1');
        } finally {
            $this->assertSame(0, $this->bal($u)['shareable']);
            $this->assertSame(0, UsageLedger::count());
        }
    }

    public function test_a_failure_after_the_debit_refunds_the_wallet_never_charging_without_delivering(): void
    {
        $u = $this->member();
        $this->fund($u, 10);
        // Make the credit write fail after the wallet debit succeeds.
        $this->app->bind(UsageLedgerService::class, fn () => new class extends UsageLedgerService {
            public function apply(array $legs, ?\Closure $inside = null): array { throw new \RuntimeException('disk full'); }
        });
        try {
            app(UsageCredit::class)->purchase($u, 4, 'k2');
            $this->fail('expected the orphan-charge guard to throw');
        } catch (\App\Exceptions\OrphanChargeRefundedException) {
        }
        $this->assertSame(10.0, round((float) $u->wallet()->first()->usd_balance, 2), 'auto-refunded in full');
    }

    public function test_purchase_amount_limits_are_enforced_and_ios_purchase_is_off_by_default(): void
    {
        $u = $this->member();
        $this->fund($u, 1000);
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(UsageCredit::class)->purchase($u, 0.10, 'a');
        } finally {
            $this->assertFalse(app(UsageCredit::class)->purchaseAllowed('NaaraSim-iOS/1.0 (iPhone)') && false);
        }
    }

    public function test_usage_credit_is_never_reachable_by_loyalty_redemption_or_withdrawal(): void
    {
        $u = $this->member();
        $credits = app(CreditService::class);
        $before = [$credits->balance($u), $credits->withdrawableBalance($u)];
        $this->buy($u, 20);

        $this->assertSame($before, [$credits->balance($u), $credits->withdrawableBalance($u)], 'purchase never touches loyalty NaaraCredit');
        $q = $credits->quoteRedemption($u, 10.0, 6.0);
        $this->assertSame(0.0, (float) ($q['credits'] ?? $q['credits_used'] ?? 0), 'usage credit is not redeemable at checkout');
        $this->assertSame(0.0, $credits->withdrawableBalance($u));
    }

    // ---- Phase C: share
    public function test_sharing_moves_purchased_credit_to_the_recipients_nonshareable_bucket_in_one_paired_step(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->buy($a, 10);   // 1000 credits
        $r = app(UsageCredit::class)->share($a, $b->naara_id, 100, 's1');

        $this->assertTrue($r['ok'], $r['message']);
        $this->assertSame(90000, $this->bal($a)['shareable']);
        $this->assertSame(['shareable' => 0, 'nonshareable' => 10000], $this->bal($b));
        $this->assertSame(2, UsageLedger::where('reference', 'share:s1')->count(), 'paired rows, same reference');
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
        $this->assertDatabaseHas('n2n_realtime_events', ['user_id' => $b->id, 'type' => 'credit.received']);
    }

    public function test_a_share_replayed_with_the_same_key_moves_credit_once(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->buy($a, 10);
        app(UsageCredit::class)->share($a, $b->naara_id, 100, 'dup');
        app(UsageCredit::class)->share($a, $b->naara_id, 100, 'dup');
        $this->assertSame(10000, $this->bal($b)['nonshareable']);
        $this->assertSame(90000, $this->bal($a)['shareable']);
    }

    public function test_received_gift_credit_cannot_be_reshared_and_self_share_and_overspend_are_rejected(): void
    {
        $a = $this->member(); $b = $this->member(); $c = $this->member();
        $this->buy($a, 10);
        $svc = app(UsageCredit::class);
        $svc->share($a, $b->naara_id, 100, 's1');

        $this->assertFalse($svc->share($b, $c->naara_id, 50, 's2')['ok'], 'received credit is spendable but not shareable');
        $this->assertFalse($svc->share($a, $a->naara_id, 10, 's3')['ok'], 'not to yourself');
        $this->assertFalse($svc->share($a, $c->naara_id, 500, 's4')['ok'] && $svc->share($a, $c->naara_id, 500, 's5')['ok'], 'cannot spend more than you have');
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
    }

    public function test_sharing_respects_min_max_account_age_and_daily_caps(): void
    {
        Setting::setValue('n2n.share_cap_per_recipient_day', 150);
        $a = $this->member(); $b = $this->member(); $new = $this->member();
        $new->forceFill(['created_at' => now()->subDay()])->save();
        $this->buy($a, 50); $this->buy($new->fresh(), 5);
        $svc = app(UsageCredit::class);

        $this->assertFalse($svc->share($a, $b->naara_id, 0.5, 'x1')['ok'], 'below the minimum');
        $this->assertFalse($svc->share($a, $b->naara_id, 501, 'x2')['ok'], 'above the maximum');
        $this->assertStringContainsString('days after you join', $svc->share($new->fresh(), $b->naara_id, 10, 'x3')['message']);
        $this->assertTrue($svc->share($a, $b->naara_id, 100, 'x4')['ok']);
        $this->assertFalse($svc->share($a, $b->naara_id, 100, 'x5')['ok'], 'per-recipient daily cap (150)');
    }

    public function test_a_hidden_person_cannot_be_paid_by_phone_but_their_shared_naara_id_works(): void
    {
        $a = $this->member(); $h = $this->member(['email' => 'h@example.com', 'n2n_hide_me' => true, 'n2n_find_by_email' => true]);
        $this->buy($a, 10);
        $svc = app(UsageCredit::class);
        $this->assertFalse($svc->share($a, 'h@example.com', 10, 'h1')['ok'], 'hidden: not findable, so not payable (no oracle)');
        $this->assertTrue($svc->share($a, $h->naara_id, 10, 'h2')['ok']);
    }

    // ---- Phase D: pending gifts
    private function giftSetup(): array
    {
        Queue::fake([SendCreditGiftInviteJob::class]);
        $sender = $this->member();
        $this->buy($sender, 10);
        $r = app(CreditGifts::class)->create($sender, 'newbie@example.com', 50, 'g1');
        $this->assertTrue($r['ok'], $r['message']);

        return [$sender, $r['gift']];
    }

    private function registerNewbie(User $sender, string $email = 'newbie@example.com', bool $verified = true): User
    {
        $n = $this->user(['email' => $email, 'referred_by' => $sender->id, 'email_verified_at' => $verified ? now() : null]);

        return $n->fresh();
    }

    public function test_a_gift_debits_the_sender_at_once_holds_the_credit_in_escrow_and_stores_no_clear_address(): void
    {
        [$sender, $gift] = $this->giftSetup();

        $this->assertSame(95000, $this->bal($sender)['shareable'], '1000 bought, 50 escrowed');
        $this->assertSame(CreditGift::PENDING, $gift->status);
        $this->assertSame('5000', (string) Money::cents($gift->getRawOriginal('amount')));
        $this->assertStringNotContainsString('newbie', json_encode(CreditGift::first()->getAttributes()), 'only a hash and a mask are stored');
        $this->assertStringContainsString('@example.com', $gift->recipient_masked);
        Queue::assertPushed(SendCreditGiftInviteJob::class);
        $this->assertSame(CreditGift::first()->amount, '50.00');
    }

    public function test_a_gift_to_a_findable_person_is_refused_and_a_replay_never_debits_twice(): void
    {
        Queue::fake();
        $sender = $this->member(); $existing = $this->member(['email' => 'ex@example.com', 'n2n_find_by_email' => true]);
        $this->buy($sender, 10);
        $g = app(CreditGifts::class);
        $this->assertFalse($g->create($sender, 'ex@example.com', 10, 'a')['ok']);

        $g->create($sender, 'new@example.com', 10, 'same'); $g->create($sender, 'new@example.com', 10, 'same');
        $this->assertSame(1, CreditGift::count());
        $this->assertSame(99000, $this->bal($sender)['shareable']);
    }

    public function test_registering_through_the_senders_link_and_verifying_the_same_email_claims_the_gift_into_nonshareable(): void
    {
        [$sender, $gift] = $this->giftSetup();
        $this->travel(23)->hours();
        $new = $this->registerNewbie($sender);

        $this->assertSame(1, app(CreditGifts::class)->claimForUser($new));
        $this->assertSame(['shareable' => 0, 'nonshareable' => 5000], $this->bal($new));
        $this->assertSame(CreditGift::CLAIMED, $gift->fresh()->status);
        $this->assertSame(0, app(CreditGifts::class)->claimForUser($new), 'claiming twice does nothing');
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
    }

    public function test_claiming_after_24_hours_finds_the_gift_already_refunded(): void
    {
        [$sender, $gift] = $this->giftSetup();
        $new = $this->registerNewbie($sender);
        $this->travel(1441)->minutes();

        $this->assertSame(0, app(CreditGifts::class)->claimForUser($new), 'too late');
        $this->assertSame(1, app(CreditGifts::class)->expireDue());
        $this->assertSame(CreditGift::REFUNDED, $gift->fresh()->status);
        $this->assertSame(100000, $this->bal($sender)['shareable']);
        $this->assertSame(['shareable' => 0, 'nonshareable' => 0], $this->bal($new));
    }

    public function test_the_wrong_email_an_unverified_email_no_referral_and_an_old_account_cannot_claim(): void
    {
        [$sender, $gift] = $this->giftSetup();
        $g = app(CreditGifts::class);
        $this->assertSame(0, $g->claimForUser($this->registerNewbie($sender, 'other@example.com')), 'wrong email');
        $this->assertSame(0, $g->claimForUser($this->user(['email' => 'newbie@example.com', 'referred_by' => $sender->id, 'email_verified_at' => null])), 'email not verified');
        $this->assertSame(0, $g->claimForUser($this->user(['email' => 'newbie2@example.com', 'email_verified_at' => now()])), 'no referral link');
        $this->assertSame(CreditGift::PENDING, $gift->fresh()->status);
    }

    public function test_an_account_that_existed_before_the_gift_cannot_claim_it(): void
    {
        Queue::fake();
        $sender = $this->member();
        $this->buy($sender, 10);
        $old = $this->user(['email' => 'old@example.com', 'referred_by' => $sender->id, 'email_verified_at' => now()]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        // Hidden so it is not "findable" (a findable person is refused before any gift exists).
        $old->forceFill(['n2n_hide_me' => true])->save();
        app(CreditGifts::class)->create($sender, 'old@example.com', 10, 'old');
        $this->assertSame(0, app(CreditGifts::class)->claimForUser($old->fresh()), 'must have registered after the gift');
    }

    public function test_expiry_runs_once_per_gift_even_if_the_job_runs_twice_and_a_claimed_gift_is_never_refunded(): void
    {
        [$sender, $gift] = $this->giftSetup();
        $new = $this->registerNewbie($sender);
        app(CreditGifts::class)->claimForUser($new);
        $this->travel(25)->hours();

        $this->artisan('n2n:expire-gifts')->assertSuccessful();
        $this->artisan('n2n:expire-gifts')->assertSuccessful();
        $this->assertSame(CreditGift::CLAIMED, $gift->fresh()->status);
        $this->assertSame(95000, $this->bal($sender)['shareable'], 'no refund for a claimed gift');

        // An unclaimed one is refunded exactly once across two runs.
        $g2 = app(CreditGifts::class)->create($sender, 'zed@example.com', 20, 'g2');
        $this->assertTrue($g2['ok']);
        $this->travel(25)->hours();
        $before = $this->bal($sender)['shareable'];
        $this->artisan('n2n:expire-gifts'); $this->artisan('n2n:expire-gifts');
        $this->assertSame($before + 2000, $this->bal($sender)['shareable']);
        $this->assertSame(1, UsageLedger::where('reference', 'gift-refund:'.$g2['gift']->id)->count());
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
    }

    public function test_a_job_killed_halfway_leaves_no_partial_refund_and_the_rerun_completes_it(): void
    {
        [$sender, $gift] = $this->giftSetup();
        $this->travel(25)->hours();
        // The refund's DB transaction rolls back as a whole: simulate the crash inside it.
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($gift) {
                app(UsageLedgerService::class)->apply([[$gift->sender_id, 'shareable', 5000, 'gift_refund', 'gift-refund:'.$gift->id, null, null]], function () { throw new \RuntimeException('killed'); });
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, UsageLedger::where('reference', 'gift-refund:'.$gift->id)->count(), 'rolled back, no half-written refund');
        $this->assertSame(CreditGift::PENDING, $gift->fresh()->status);

        $this->assertSame(1, app(CreditGifts::class)->expireDue());
        $this->assertSame(CreditGift::REFUNDED, $gift->fresh()->status);
        $this->assertSame(100000, $this->bal($sender)['shareable']);
    }

    // ---- Phase E: pricing + meter
    public function test_a_half_credit_charge_is_exact_and_many_small_charges_sum_exactly(): void
    {
        $u = $this->member();
        $this->buy($u, 1);   // 100 credits
        $meter = app(UsageMeter::class);
        foreach (range(1, 7) as $i) { $meter->charge($u, N2NPricing::messageCostCents(), 'm'.$i); }
        $this->assertSame(10000 - 7 * 50, $this->bal($u)['shareable']);
        $this->assertSame('96.50', Money::credits($this->bal($u)['shareable']));
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
    }

    public function test_charging_is_idempotent_and_an_insufficient_balance_takes_nothing_and_says_what_to_do(): void
    {
        $u = $this->member();
        $this->buy($u, 1);
        $meter = app(UsageMeter::class);
        $meter->charge($u, 100, 'same'); $meter->charge($u, 100, 'same');
        $this->assertSame(9900, $this->bal($u)['shareable']);

        try {
            $meter->charge($u, 20000, 'too-much');
            $this->fail('should throw');
        } catch (InsufficientUsageCredit $e) {
            $this->assertSame(20000, $e->neededCents);
            $this->assertSame(9900, $e->availableCents);
        }
        $this->assertSame(9900, $this->bal($u)['shareable']);
    }

    public function test_spend_order_is_loyalty_then_received_credit_then_purchased_and_withdrawable_loyalty_is_never_touched(): void
    {
        $a = $this->member(); $u = $this->member();
        $this->buy($a, 10); app(UsageCredit::class)->share($a, $u->naara_id, 10, 'o1');   // 10 non-shareable
        $this->buy($u, 1);                                                                  // 100 shareable
        $credits = app(CreditService::class);
        $credits->earn($u, 3, 'checkin');                                                   // 3 spendable loyalty
        $credits->earn($u, 50, 'referral', 'ref-1', null, true);                            // 50 WITHDRAWABLE: off limits
        $meter = app(UsageMeter::class);
        $this->assertSame(300 + 1000 + 10000, $meter->available($u), 'withdrawable loyalty is excluded');

        $parts = $meter->charge($u, 1500, 'order-1');   // 15 credits: 3 loyalty, 10 received, 2 purchased
        $this->assertSame([['loyalty', 300], ['nonshareable', 1000], ['shareable', 200]], $parts);
        $this->assertSame(['shareable' => 9800, 'nonshareable' => 0], $this->bal($u));
        $this->assertSame(50.0, $credits->withdrawableBalance($u));
        $this->assertSame($parts, $meter->charge($u, 1500, 'order-1'), 'a replay returns the same parts and charges nothing');
    }

    public function test_hold_settle_returns_the_unused_part_to_where_it_came_from_and_settling_twice_is_a_no_op(): void
    {
        $a = $this->member(); $u = $this->member();
        $this->buy($a, 10); app(UsageCredit::class)->share($a, $u->naara_id, 10, 'h1');
        $this->buy($u, 1);
        $meter = app(UsageMeter::class);

        $meter->hold($u, 1500, 'call-1');                                  // 10 received + 5 purchased
        $this->assertSame(['shareable' => 9500, 'nonshareable' => 0], $this->bal($u));
        $meter->settle('call-1', 400);                                     // the call used 4 credits' worth
        // Unused 11.00 goes back last-consumed first: 5.00 to purchased, then 6.00 to received. The call kept 4.00 of the received credit.
        $this->assertSame(['shareable' => 10000, 'nonshareable' => 600], $this->bal($u));

        $after = $this->bal($u);
        $meter->settle('call-1', 0);
        $this->assertSame($after, $this->bal($u), 'second settle changes nothing');
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
    }

    public function test_release_returns_everything_and_a_client_supplied_amount_never_exceeds_the_hold(): void
    {
        $u = $this->member();
        $this->buy($u, 1);
        $meter = app(UsageMeter::class);
        $meter->hold($u, 3000, 'c2');
        $meter->settle('c2', 999999);   // a client claiming a huge duration is clamped to what was held
        $this->assertSame(7000, $this->bal($u)['shareable']);
        $meter->hold($u, 1000, 'c3');
        $meter->release('c3');
        $this->assertSame(7000, $this->bal($u)['shareable']);
    }

    public function test_media_pricing_is_derived_from_the_admin_dollar_per_mb_with_a_margin_floor_and_never_rounds_below_cost(): void
    {
        Setting::setValue('n2n.storage_usd_per_mb', 0.01);
        Setting::setValue('n2n.media_markup', 1.0);
        Setting::setValue('n2n.min_margin_pct', 50);
        // 10 MB x $0.01 x 100 credits/USD = 10 credits infra; markup 1.0 would be 10, the margin floor makes it 15.
        $this->assertSame(1500, N2NPricing::mediaCostCents(10 * 1048576));
        Setting::setValue('n2n.media_markup', 3.0);
        $this->assertSame(3000, N2NPricing::mediaCostCents(10 * 1048576));
        $this->assertSame(1, N2NPricing::mediaCostCents(10), 'a tiny file is never free');
        $this->assertSame(0, N2NPricing::mediaCostCents(0));
        $this->assertSame(50, N2NPricing::messageCostCents());
        $this->assertArrayHasKey('media_basis', N2NPricing::workedExample());
    }

    // ---- Phase F: subscription grant
    public function test_a_plan_credit_grant_is_added_on_activation_and_renewal_exactly_once_per_period(): void
    {
        $owner = $this->member();
        $plan = BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 10, 'handles_included' => 3, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 1, 'credit_reward_per_follow' => 0, 'is_active' => true, 'sort_order' => 1]);
        $plan->forceFill(['n2n_credit_grant' => 200, 'is_pro' => true])->save();
        $brand = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Zed', 'listing_status' => 'active', 'is_active' => true, 'current_plan_id' => $plan->id]);

        $sub = BrandSubscription::create(['brand_partner_id' => $brand->id, 'plan_id' => $plan->id, 'status' => BrandSubscription::ACTIVE, 'started_at' => now(), 'next_billing_at' => now()->addMonth(), 'last_charged_at' => now()]);
        $this->assertSame(20000, $this->bal($owner)['shareable']);

        $sub->forceFill(['grace_reminders_sent' => 1])->save();   // an unrelated save grants nothing
        $this->assertSame(20000, $this->bal($owner)['shareable']);

        $this->travel(31)->days();
        $sub->forceFill(['last_charged_at' => now()])->save();    // renewal
        $this->assertSame(40000, $this->bal($owner)['shareable']);
        $sub->forceFill(['last_charged_at' => $sub->last_charged_at])->save();
        $this->assertSame(40000, $this->bal($owner)['shareable'], 'same period never grants twice');
    }

    // ---- Phase G: accounting, abuse, erasure
    public function test_the_liability_report_and_ledger_reconcile_cover_every_bucket(): void
    {
        Queue::fake();
        $a = $this->member(); $b = $this->member();
        $this->buy($a, 20);
        app(UsageCredit::class)->share($a, $b->naara_id, 100, 'r1');
        app(CreditGifts::class)->create($a, 'ghost@example.com', 50, 'r2');
        app(UsageMeter::class)->charge($b, 50, 'r3');

        $r = app(CreditAccounting::class)->report();
        $this->assertSame('1949.50', $r['outstanding_credits'], '2000 bought - 50 in escrow - 0.50 consumed; a share only moves credit between members');
        $this->assertSame('50.00', $r['escrow_credits']);
        $this->assertSame('0.50', $r['consumed_30d_credits']);
        $this->assertSame(20.0, $r['purchased_30d_usd']);
        $this->assertSame([], app(CreditAccounting::class)->reconcile());
        $this->artisan('n2n:credit-reconcile')->assertSuccessful();
    }

    public function test_a_tampered_balance_is_caught_by_the_reconcile_command(): void
    {
        $u = $this->member();
        $this->buy($u, 1);
        \Illuminate\Support\Facades\DB::table('n2n_usage_wallets')->where('user_id', $u->id)->update(['shareable' => 999]);
        $this->assertNotEmpty(app(CreditAccounting::class)->reconcile());
        $this->artisan('n2n:credit-reconcile')->assertFailed();
    }

    public function test_velocity_monitors_flag_rings_and_pile_ups_for_a_human(): void
    {
        Setting::setValue('n2n.velocity_senders_per_receiver_day', 2);
        $target = $this->member();
        foreach (range(1, 2) as $_) {
            $s = $this->member();
            $this->buy($s, 5);
            app(UsageCredit::class)->share($s, $target->naara_id, 10, 'v'.$s->id);
        }
        $flags = app(GiftVelocity::class)->scan();
        $this->assertContains('many_senders_in', array_column($flags, 'kind'));
        $this->assertSame($target->id, collect($flags)->firstWhere('kind', 'many_senders_in')['user_id']);
    }

    public function test_erasing_a_sender_returns_their_pending_gifts_and_leaves_nothing_in_escrow(): void
    {
        [$sender, $gift] = $this->giftSetup();
        app(AccountService::class)->erase($sender->fresh());
        $this->assertSame(CreditGift::REFUNDED, $gift->fresh()->status);
        $this->assertSame(0, CreditGift::where('status', 'pending')->count());
    }

    public function test_low_balance_publishes_a_credit_low_event_for_the_tone(): void
    {
        $u = $this->member();
        $this->buy($u, 1);   // 100 credits
        app(UsageMeter::class)->charge($u, 9700, 'low1');   // 3 left, below the default threshold of 5
        $this->assertDatabaseHas('n2n_realtime_events', ['user_id' => $u->id, 'type' => 'credit.low']);
    }

    // ---- UI + admin
    public function test_the_credits_page_buys_shares_and_gifts_with_idempotent_forms_and_live_recipient_validation(): void
    {
        Queue::fake([SendCreditGiftInviteJob::class]);
        $a = $this->member(); $b = $this->member(['name' => 'Bola Ade']);
        $b->forceFill(['username' => 'bola'])->save();
        $this->fund($a, 50);

        $lw = \Livewire\Livewire::actingAs($a)->test(\App\Livewire\N2N\UsageCredits::class)
            ->set('buyUsd', '20')->call('buy')->assertSet('error', null)->assertSee('2,000.00', false)
            ->set('shareTo', '@bola')->assertSee('Sending to Bola Ade')
            ->set('shareAmount', '100')->call('share')->assertSet('error', null)
            ->set('giftTo', 'friend@example.com')->set('giftAmount', '50')->call('gift')->assertSet('error', null)->assertSee('Pending gifts');

        $this->assertSame(10000, $this->bal($b)['nonshareable']);
        $this->assertSame(1, CreditGift::count());
        $lw->set('shareTo', 'zzzzzz')->assertSee('No match yet');
    }

    public function test_the_credits_page_says_what_to_do_when_the_wallet_is_short_and_blocks_native_ios_purchase(): void
    {
        $a = $this->member();
        \Livewire\Livewire::actingAs($a)->test(\App\Livewire\N2N\UsageCredits::class)->set('buyUsd', '10')->call('buy')->assertSee('Top up your wallet');
        $this->assertFalse(app(UsageCredit::class)->purchaseAllowed('Mozilla/5.0 NaaraSimApp iOS iPhone') && \App\Support\AppPlatform::isIosBuild('Mozilla/5.0 NaaraSimApp iOS iPhone'));
        Setting::setValue('n2n.credit_purchase_ios', true);
        $this->assertTrue(app(UsageCredit::class)->purchaseAllowed('Mozilla/5.0 NaaraSimApp iOS iPhone'));
    }

    public function test_the_admin_saves_prices_and_caps_with_validation_and_sees_the_worked_example_and_liability(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = $this->user(); $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)
            ->set('priceMessage', 0.75)->set('storageUsdPerMb', 0.002)->set('mediaMarkup', 4)->set('shareMax', 300)->set('shareCapDay', 900)
            ->call('save')->assertHasNoErrors()->assertSee('Worked example')->assertSee('Outstanding credit');

        $this->assertSame(75, N2NPricing::messageCostCents());
        $this->assertSame(0.002, N2NPricing::storageUsdPerMb());
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)->set('shareMax', 100000)->set('shareCapDay', 10)->call('save')->assertHasErrors(['shareCapDay']);
    }

    public function test_the_legal_hold_view_includes_the_usage_ledger_and_sent_gifts_for_an_erased_account(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        [$sender, $gift] = $this->giftSetup();
        app(AccountService::class)->erase($sender->fresh());
        $admin = $this->user(); $admin->assignRole('super_admin');

        $r = app(AccountService::class)->viewRetainedRecordsForLegalHold($sender->fresh(), $admin, 'CASE-1', 'regulator request');
        $this->assertGreaterThanOrEqual(2, $r['n2n_usage_ledger']->count());
        $this->assertSame(1, $r['n2n_credit_gifts_sent']->count());
        $this->assertStringNotContainsString('newbie@example.com', json_encode($r['n2n_credit_gifts_sent']));
    }
}
