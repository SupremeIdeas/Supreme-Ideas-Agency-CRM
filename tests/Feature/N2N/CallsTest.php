<?php

namespace Tests\Feature\N2N;

use App\Models\BrandPartner;
use App\Models\N2N\Block;
use App\Models\N2N\Call;
use App\Models\N2N\RealtimeEvent;
use App\Models\N2N\UsageHold;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\N2NMissedCall;
use App\Services\N2N\Calls\CallException;
use App\Services\N2N\Calls\CallService;
use App\Services\N2N\Calls\CallsAvailability;
use App\Services\N2N\Calls\TurnCredentials;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Identity\NaaraId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/** N2N Batch 10: the call state machine, billing, policy, signalling auth and TURN credentials (MASTER-ONLY). WebRTC media itself: tests/visual/n2n/call-smoke.mjs. */
class CallsTest extends N2NTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('n2n.calls_enabled', true);
        Setting::setValue('n2n.calls_dev_mode', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(array $attrs = []): User
    {
        return $this->user(array_merge(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)], $attrs));
    }

    private function credit(User $u, int $cents = 100000): void
    {
        app(UsageLedgerService::class)->apply([[$u->id, 'shareable', $cents, 'seed', 'seed:'.$u->id.uniqid(), null, []]]);
    }

    private function connect(User $a, User $b): void
    {
        app(Connections::class)->follow($a, $b);
        app(Connections::class)->follow($b, $a);
    }

    /** @return array{0: User, 1: User} two connected members, the caller funded */
    private function pair(int $cents = 100000): array
    {
        [$a, $b] = [$this->member(), $this->member()];
        $this->connect($a, $b);
        $this->credit($a, $cents);

        return [$a, $b];
    }

    private function svc(): CallService
    {
        return app(CallService::class);
    }

    private function avail(User $u): int
    {
        return app(UsageMeter::class)->available($u);
    }

    /** Both browsers beating every 10 s from the answer time until `$until` (an absolute test time). */
    private function beatUntil(Call $call, User $a, User $b, string $from, string $until): void
    {
        for ($t = Carbon::parse($from)->addSeconds(10); $t->lte(Carbon::parse($until)); $t->addSeconds(10)) {
            Carbon::setTestNow($t->copy());
            $this->svc()->heartbeat($b, $call->id);
            $this->svc()->heartbeat($a, $call->id);
        }
    }

    private function events(User $u, string $type)
    {
        return RealtimeEvent::query()->where('user_id', $u->id)->where('type', $type)->get();
    }

    // ---- availability ---------------------------------------------------------------------------------------------------------

    public function test_calls_are_off_without_reverb_and_turn_unless_the_dev_switch_is_on(): void
    {
        Setting::setValue('n2n.calls_dev_mode', false);
        $s = CallsAvailability::status();
        $this->assertFalse($s['enabled']);
        $this->assertStringContainsString('Reverb', implode(' ', $s['reasons']));
        $this->assertStringContainsString('TURN', implode(' ', $s['reasons']));
        Setting::setValue('n2n.realtime_driver', 'reverb');
        config()->set('n2n.calls.turn', ['urls' => 'turn:t.example.com:3478', 'secret' => 's3cret', 'stun' => '', 'ttl' => 3600]);
        $this->assertTrue(CallsAvailability::enabled());
        Setting::setValue('n2n.calls_enabled', false);
        $this->assertFalse(CallsAvailability::enabled());
    }

    public function test_starting_a_call_while_unavailable_is_refused(): void
    {
        Setting::setValue('n2n.calls_enabled', false);
        [$a, $b] = $this->pair();
        $this->expectException(CallException::class);
        $this->svc()->start($a, $b->naara_id);
    }

    // ---- ring, answer, billing -------------------------------------------------------------------------------------------------

    public function test_a_call_rings_the_callee_and_holds_credit_for_the_caller_only(): void
    {
        [$a, $b] = $this->pair();
        $before = $this->avail($a);
        $call = $this->svc()->start($a, $b->naara_id);
        $this->assertSame('ringing', $call->state);
        $rate = N2NPricing::callRatePerMinuteCents();
        $this->assertSame($before - 2 * $rate, $this->avail($a), 'two minutes are held up front');
        $this->assertCount(1, $this->events($b, 'call.incoming'));
        $this->assertSame(0, $this->events($a, 'call.incoming')->count());
        $this->assertSame(0, $this->avail($b), 'the callee holds nothing and pays nothing');
    }

    public function test_billing_is_server_clock_rounded_up_and_the_unused_hold_returns(): void
    {
        [$a, $b] = $this->pair();
        $start = $this->avail($a);
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        $this->assertSame('2026-10-04 10:00:00', $call->fresh()->answered_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-10-04 10:01:10');                       // 70 s -> 2 billable minutes
        $this->svc()->hangup($a, $call->id);
        $c = $call->fresh();
        $rate = N2NPricing::callRatePerMinuteCents();
        $this->assertSame('ended', $c->state);
        $this->assertSame(120, $c->billed_seconds);
        $this->assertSame(2 * $rate, $c->billed_cents);
        $this->assertSame($start - 2 * $rate, $this->avail($a));
        $this->assertSame(0, UsageHold::query()->where('status', 'held')->count(), 'nothing stays held');
    }

    public function test_a_short_call_keeps_one_increment_and_a_zero_second_call_is_free(): void
    {
        [$a, $b] = $this->pair();
        $start = $this->avail($a);
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:05');
        $this->svc()->hangup($b, $call->id);                             // the callee may end it too
        $this->assertSame(60, $call->fresh()->billed_seconds);
        $this->assertSame($start - N2NPricing::callRatePerMinuteCents(), $this->avail($a));

        $call2 = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call2->id);
        $this->svc()->hangup($a, $call2->id);                            // same instant: zero seconds
        $this->assertSame(0, $call2->fresh()->billed_cents);
        $this->assertSame($start - N2NPricing::callRatePerMinuteCents(), $this->avail($a));
    }

    public function test_a_smaller_billing_increment_is_honoured(): void
    {
        Setting::setValue('n2n.call_increment_s', 10);
        [$a, $b] = $this->pair();
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:25');
        $this->svc()->hangup($a, $call->id);
        $c = $call->fresh();
        $this->assertSame(30, $c->billed_seconds);
        $this->assertSame((int) ceil(30 * N2NPricing::callRatePerMinuteCents() / 60), $c->billed_cents);
    }

    public function test_a_double_hangup_and_a_late_sweep_settle_exactly_once(): void
    {
        [$a, $b] = $this->pair();
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:30');
        $this->svc()->hangup($a, $call->id);
        $after = $this->avail($a);
        $this->svc()->hangup($a, $call->id);
        $this->svc()->hangup($b, $call->id);
        $this->svc()->sweep();
        $this->assertSame($after, $this->avail($a));
        $this->assertSame(60, $call->fresh()->billed_seconds);
    }

    public function test_insufficient_credit_blocks_the_call_and_leaves_nothing_behind(): void
    {
        [$a, $b] = $this->pair(1);
        try {
            $this->svc()->start($a, $b->naara_id);
            $this->fail('should be refused');
        } catch (InsufficientUsageCredit) {
        }
        $this->assertSame(0, Call::count());
        $this->assertSame(0, $this->events($b, 'call.incoming')->count(), 'the callee is never rung for a call that cannot be paid for');
    }

    public function test_the_credit_cover_is_extended_ahead_and_the_call_ends_when_credit_runs_out(): void
    {
        $rate = N2NPricing::callRatePerMinuteCents();
        [$a, $b] = $this->pair(3 * $rate + 5);                          // enough for 3 minutes in total, not for a 4th slice
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);                  // holds minutes 1-2
        $this->svc()->accept($b, $call->id);
        $this->beatUntil($call, $a, $b, '2026-10-04 10:00:00', '2026-10-04 10:01:40');   // inside the look-ahead: tries 2 more minutes, cannot afford them
        $this->assertSame('active', $call->fresh()->state);
        $this->assertNotNull($call->fresh()->ends_at, 'the moment the cover runs out is now fixed');
        $this->assertSame('2026-10-04 10:02:00', $call->fresh()->ends_at->format('Y-m-d H:i:s'));
        $r = $this->svc()->heartbeat($a, $call->id);
        $this->assertTrue($r['warn']);
        $this->assertLessThanOrEqual(30, $r['seconds_left']);
        $this->beatUntil($call, $a, $b, '2026-10-04 10:01:40', '2026-10-04 10:02:10');
        $c = $call->fresh();
        $this->assertSame('ended', $c->state);
        $this->assertSame('credit', $c->end_reason);
        $this->assertSame(120, $c->billed_seconds, 'billed up to the moment the cover ended, not beyond');
        $this->assertSame(0, UsageHold::query()->where('status', 'held')->count());
    }

    public function test_a_long_call_with_credit_keeps_extending_the_hold(): void
    {
        [$a, $b] = $this->pair();
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        $this->beatUntil($call, $a, $b, '2026-10-04 10:00:00', '2026-10-04 10:04:50');
        $c = $call->fresh();
        $this->assertSame('active', $c->state);
        $this->assertNull($c->ends_at);
        $this->assertGreaterThanOrEqual(3, $c->hold_count);
        Carbon::setTestNow('2026-10-04 10:05:00');
        $this->svc()->hangup($a, $call->id);
        $this->assertSame(300, $call->fresh()->billed_seconds);
        $this->assertSame(0, UsageHold::query()->where('status', 'held')->count());
    }

    // ---- abrupt close / lost peer ------------------------------------------------------------------------------------------------

    public function test_a_closed_tab_is_settled_at_the_last_good_heartbeat(): void
    {
        [$a, $b] = $this->pair();
        $start = $this->avail($a);
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:20');
        $this->svc()->heartbeat($a, $call->id);
        $this->svc()->heartbeat($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:28');
        $this->svc()->heartbeat($b, $call->id);                          // the caller's tab closed at 10:00:20; the callee keeps beating
        Carbon::setTestNow('2026-10-04 10:00:55');
        $this->svc()->heartbeat($b, $call->id);                          // caller stale for 35 s (> 30): ended at 10:00:20
        $c = $call->fresh();
        $this->assertSame('ended', $c->state);
        $this->assertSame('peer_lost', $c->end_reason);
        $this->assertSame('2026-10-04 10:00:20', $c->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame(60, $c->billed_seconds);
        $this->assertSame($start - N2NPricing::callRatePerMinuteCents(), $this->avail($a));
    }

    public function test_when_both_sides_vanish_the_sweeper_ends_it_at_the_later_beat(): void
    {
        [$a, $b] = $this->pair();
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        Carbon::setTestNow('2026-10-04 10:00:30');
        $this->svc()->heartbeat($a, $call->id);
        Carbon::setTestNow('2026-10-04 10:01:30');
        $this->assertSame(1, $this->svc()->sweep());
        $c = $call->fresh();
        $this->assertSame('peer_lost', $c->end_reason);
        $this->assertSame('2026-10-04 10:00:30', $c->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_client_supplied_time_cannot_change_billing(): void
    {
        [$a, $b] = $this->pair();
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);
        $this->beatUntil($call, $a, $b, '2026-10-04 10:00:00', '2026-10-04 10:02:20');
        Carbon::setTestNow('2026-10-04 10:02:30');
        $this->actingAs($b)->postJson(route('n2n.api.calls.heartbeat', $call->id))->assertOk();
        $this->actingAs($a)->postJson(route('n2n.api.calls.heartbeat', $call->id), ['elapsed' => 1, 'now' => '2020-01-01', 'duration' => 1])->assertOk();
        $this->actingAs($a)->postJson(route('n2n.api.calls.hangup', $call->id), ['duration' => 1, 'ended_at' => '2020-01-01'])->assertOk();
        $this->assertSame(180, $call->fresh()->billed_seconds);
    }

    // ---- ring outcomes ---------------------------------------------------------------------------------------------------------------

    public function test_decline_releases_the_hold_and_tells_the_caller(): void
    {
        [$a, $b] = $this->pair();
        $start = $this->avail($a);
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->decline($b, $call->id);
        $this->assertSame('declined', $call->fresh()->state);
        $this->assertSame($start, $this->avail($a));
        $this->assertSame('declined', $this->events($a, 'call.ended')->last()->payload['reason']);
    }

    public function test_no_answer_becomes_a_missed_call_with_a_notification_and_the_hold_back(): void
    {
        Notification::fake();
        [$a, $b] = $this->pair();
        $start = $this->avail($a);
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = $this->svc()->start($a, $b->naara_id);
        Carbon::setTestNow('2026-10-04 10:01:00');
        $this->assertSame(1, $this->svc()->sweep());
        $this->assertSame('missed', $call->fresh()->state);
        $this->assertSame('no_answer', $call->fresh()->end_reason);
        $this->assertSame($start, $this->avail($a));
        Notification::assertSentTo($b, N2NMissedCall::class);
        $this->expectException(CallException::class);
        $this->svc()->accept($b, $call->id);                             // too late
    }

    public function test_cancelling_while_ringing_is_a_missed_call_for_the_callee(): void
    {
        Notification::fake();
        [$a, $b] = $this->pair();
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->hangup($a, $call->id);
        $this->assertSame('missed', $call->fresh()->state);
        $this->assertSame('cancelled', $call->fresh()->end_reason);
        Notification::assertSentTo($b, N2NMissedCall::class);
    }

    public function test_busy_one_active_call_per_user_and_two_incoming_at_once(): void
    {
        [$a, $b] = $this->pair();
        $c = $this->member();
        $d = $this->member();
        foreach ([$c, $d] as $u) {
            $this->connect($u, $b);
            $this->credit($u);
        }
        $first = $this->svc()->start($c, $b->naara_id);                  // C rings B
        $second = $this->svc()->start($d, $b->naara_id);                 // D arrives while B is ringing: busy
        $this->assertSame('ringing', $first->state);
        $this->assertSame('busy', $second->state);
        $this->assertSame(0, $this->events($b, 'call.incoming')->where('payload.call_id', $second->id)->count());
        $this->svc()->accept($b, $first->id);
        $this->expectException(CallException::class);                    // B is now in a call and cannot place another
        $this->svc()->start($b, $a->naara_id);
    }

    public function test_the_caller_cannot_start_a_second_call_while_ringing(): void
    {
        [$a, $b] = $this->pair();
        $c = $this->member();
        $this->connect($a, $c);
        $this->svc()->start($a, $b->naara_id);
        try {
            $this->svc()->start($a, $c->naara_id);
            $this->fail('already in a call');
        } catch (CallException $e) {
            $this->assertSame('already_in_call', $e->reason);
        }
    }

    // ---- policy ----------------------------------------------------------------------------------------------------------------------

    public function test_blocked_not_allowed_and_dnd_calls_ring_for_the_caller_but_reach_nobody(): void
    {
        $caller = $this->member();
        $this->credit($caller);
        $stranger = $this->member();                                     // not connected: default policy
        $blocker = $this->member(); $this->connect($caller, $blocker); Block::create(['blocker_id' => $blocker->id, 'blocked_id' => $caller->id, 'created_at' => now()]);
        $dnd = $this->member(['n2n_dnd_until' => now()->addHour()]); $this->connect($caller, $dnd);
        $nobody = $this->member(['n2n_who_can_call' => 'nobody']); $this->connect($caller, $nobody);
        foreach ([$stranger, $blocker, $dnd, $nobody] as $callee) {
            $call = $this->svc()->start($caller, $callee->naara_id);
            $this->assertSame('ringing', $call->state, 'indistinguishable from a real ring');
            $this->assertTrue($call->suppressed);
            $this->assertSame(0, $this->events($callee, 'call.incoming')->count());
            $this->svc()->hangup($caller, $call->id);
            try {
                $this->svc()->accept($callee, $call->id);
                $this->fail('a suppressed call can never be answered');
            } catch (CallException) {
            }
        }
        Notification::fake();
        $call = $this->svc()->start($caller, $stranger->naara_id);
        Carbon::setTestNow(now()->addMinutes(2));
        $this->svc()->sweep();
        Notification::assertNothingSent();                               // and no missed-call notice for a call that never rang
    }

    public function test_everyone_and_pro_brand_owners_can_be_called_by_default(): void
    {
        $caller = $this->member(); $this->credit($caller);
        $open = $this->member(['n2n_who_can_call' => 'everyone']);
        $brandOwner = $this->member();
        BrandPartner::create(['owner_user_id' => $brandOwner->id, 'brand_name' => 'Pro Co', 'listing_status' => 'active', 'is_active' => true, 'pro_granted_at' => now()]);
        foreach ([$open, $brandOwner] as $callee) {
            $call = $this->svc()->start($caller, $callee->naara_id);
            $this->assertFalse($call->suppressed);
            $this->assertCount(1, $this->events($callee, 'call.incoming'));
            $this->svc()->hangup($caller, $call->id);
        }
    }

    public function test_call_attempts_are_rate_limited(): void
    {
        Setting::setValue('n2n.call_attempts_per_min', 2);
        RateLimiter::clear('n2n-call:1');
        [$a, $b] = $this->pair();
        RateLimiter::clear('n2n-call:'.$a->id);
        foreach ([1, 2] as $i) {
            $c = $this->svc()->start($a, $b->naara_id);
            $this->svc()->hangup($a, $c->id);
        }
        try {
            $this->svc()->start($a, $b->naara_id);
            $this->fail('third attempt in a minute');
        } catch (CallException $e) {
            $this->assertSame('rate_limited', $e->reason);
        }
    }

    // ---- signalling authorisation -------------------------------------------------------------------------------------------------------

    public function test_signals_reach_only_the_other_participant_and_only_when_valid(): void
    {
        [$a, $b] = $this->pair();
        $stranger = $this->member();
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->accept($b, $call->id);

        $this->svc()->signal($a, $call->id, 'offer', ['type' => 'offer', 'sdp' => 'v=0']);
        $got = $this->events($b, 'call.signal')->last();
        $this->assertSame('offer', $got->payload['kind']);
        $this->assertSame(0, $this->events($a, 'call.signal')->count(), 'never echoed to the sender');
        $this->svc()->signal($b, $call->id, 'answer', ['type' => 'answer', 'sdp' => 'v=0']);
        $this->svc()->signal($b, $call->id, 'candidate', ['candidate' => 'x']);

        foreach ([[$b, 'offer'], [$a, 'answer'], [$a, 'script'], [$a, '']] as [$who, $kind]) {
            try {
                $this->svc()->signal($who, $call->id, $kind, ['x' => 1]);
                $this->fail("refused: {$kind}");
            } catch (CallException $e) {
                $this->assertContains($e->reason, ['invalid']);
            }
        }
        try {
            $this->svc()->signal($a, $call->id, 'candidate', ['blob' => str_repeat('x', 20000)]);
            $this->fail('oversized');
        } catch (CallException) {
        }
        try {
            $this->svc()->signal($stranger, $call->id, 'candidate', ['x' => 1]);
            $this->fail('not a participant');
        } catch (CallException $e) {
            $this->assertSame('not_found', $e->reason);
        }
        $this->svc()->hangup($a, $call->id);
        $this->expectException(CallException::class);                    // an ended call carries no more signals
        $this->svc()->signal($a, $call->id, 'candidate', ['x' => 1]);
    }

    public function test_http_endpoints_need_login_and_answer_the_same_for_missing_and_not_yours(): void
    {
        [$a, $b] = $this->pair();
        $stranger = $this->member();
        $call = $this->svc()->start($a, $b->naara_id);
        $this->postJson(route('n2n.api.calls.heartbeat', $call->id))->assertUnauthorized();
        $missing = $this->actingAs($stranger)->postJson(route('n2n.api.calls.heartbeat', 999999));
        $foreign = $this->actingAs($stranger)->postJson(route('n2n.api.calls.heartbeat', $call->id));
        $this->assertSame($missing->status(), $foreign->status());
        $this->assertSame($missing->json('message'), $foreign->json('message'));
        $this->actingAs($stranger)->postJson(route('n2n.api.calls.accept', $call->id))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('n2n.api.calls.ice', $call->id))->assertNotFound();
        $this->actingAs($b)->postJson(route('n2n.api.calls.accept', $call->id))->assertOk()->assertJsonPath('call.state', 'active')->assertJsonPath('call.role', 'callee');
    }

    public function test_http_start_returns_buy_and_ask_actions_when_credit_is_short(): void
    {
        [$a, $b] = $this->pair(1);
        $this->actingAs($a)->postJson(route('n2n.api.calls.start'), ['to' => $b->naara_id])->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credit')->assertJsonStructure(['actions' => ['buy', 'ask']]);
    }

    public function test_http_start_never_leaks_cost_or_other_users_data(): void
    {
        [$a, $b] = $this->pair();
        $res = $this->actingAs($a)->postJson(route('n2n.api.calls.start'), ['to' => $b->naara_id])->assertOk();
        $body = json_encode($res->json());
        $this->assertStringNotContainsString($b->email, $body);
        $this->assertStringNotContainsString('rate_cents', $body);
        // A JSON KEY starting with hold (hold_id, hold_cents...). Checking the bare substring flaked whenever Faker produced a name like "Gerhold".
        $this->assertStringNotContainsString('"hold', $body);
        $this->assertSame('caller', $res->json('call.role'));
    }

    // ---- TURN ------------------------------------------------------------------------------------------------------------------------------

    public function test_turn_credentials_are_time_limited_hmac_and_never_static(): void
    {
        Setting::setValue('n2n.calls_dev_mode', false);
        config()->set('n2n.calls.turn', ['urls' => 'turn:t.example.com:3478?transport=udp,turns:t.example.com:5349?transport=tcp', 'secret' => 'topsecret', 'stun' => 'stun:t.example.com:3478', 'ttl' => 600]);
        Carbon::setTestNow('2026-10-04 10:00:00');
        $call = new Call(); $call->id = 77;
        $ice = TurnCredentials::iceServers(5, $call);
        $this->assertSame(['stun:t.example.com:3478'], $ice[0]['urls']);
        $turn = $ice[1];
        [$exp, $uid, $cid] = explode(':', $turn['username']);
        $this->assertSame(Carbon::now()->timestamp + 600, (int) $exp);
        $this->assertSame(['5', '77'], [$uid, $cid]);
        $this->assertSame(base64_encode(hash_hmac('sha1', $turn['username'], 'topsecret', true)), $turn['credential']);
        $this->assertCount(2, $turn['urls']);
        $this->assertStringNotContainsString('topsecret', json_encode($ice), 'the secret itself never leaves the server');
        config()->set('n2n.calls.turn.secret', '');
        $this->assertCount(1, TurnCredentials::iceServers(5, $call), 'no secret, no TURN entry');
    }

    // ---- pages ------------------------------------------------------------------------------------------------------------------------------

    public function test_the_calls_tab_is_hidden_while_off_and_the_recents_page_lists_my_calls_only(): void
    {
        [$a, $b] = $this->pair();
        $call = $this->svc()->start($a, $b->naara_id);
        $this->svc()->hangup($a, $call->id);
        $this->actingAs($b)->get(route('n2n.calls'))->assertOk()->assertSee('Missed');
        $this->actingAs($this->member())->get(route('n2n.calls'))->assertOk()->assertDontSee($a->name);
        $keys = array_column(\App\Support\N2N\N2NNav::items($a), 'key');
        $this->assertContains('calls', $keys);
        Setting::setValue('n2n.calls_enabled', false);
        $this->assertNotContains('calls', array_column(\App\Support\N2N\N2NNav::items($a), 'key'));
    }
}
