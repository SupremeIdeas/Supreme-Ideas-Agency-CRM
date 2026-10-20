<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\BusinessHoursSchedule;
use App\Models\CallEvent;
use App\Models\CallFlow;
use App\Models\CallFlowNode;
use App\Models\CallForwardingRule;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Business Suite Prompt 14 — Business Phone Core: the CallFlow webhook path,
 * TwiML per node type, ring groups (simultaneous + sequential), business
 * hours, and the guarantee that a number WITHOUT a flow behaves exactly as
 * before.
 */
class CallFlowTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    private const NUMBER = '+15005550006';

    private WalletGroup $group;

    private VirtualNumber $number;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);

        $owner = User::factory()->create();
        $this->group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        $this->number = $this->numberFor($this->group, self::NUMBER);
    }

    private function numberFor(WalletGroup $group, string $phone, string $provider = 'twilio'): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $group->owner_user_id, 'wallet_group_id' => $group->id, 'provider' => $provider,
            'phone_number' => $phone, 'sid' => 'PN'.uniqid(), 'status' => 'active',
        ]);
    }

    private function flow(string $status = CallFlow::ACTIVE): CallFlow
    {
        return CallFlow::create([
            'wallet_group_id' => $this->group->id, 'virtual_number_id' => $this->number->id, 'status' => $status,
        ]);
    }

    private function node(CallFlow $flow, string $type, array $config = []): CallFlowNode
    {
        return CallFlowNode::create(['call_flow_id' => $flow->id, 'type' => $type, 'config' => $config]);
    }

    private function root(CallFlow $flow, CallFlowNode $node): void
    {
        $flow->update(['root_node_id' => $node->id]);
    }

    private function seat(string $phone, int $priority = 0, bool $browser = false): RingGroupMember
    {
        $user = User::factory()->create();
        $member = WalletGroupMember::create([
            'wallet_group_id' => $this->group->id, 'user_id' => $user->id, 'role' => 'agent',
            'status' => 'active', 'invited_at' => now(), 'accepted_at' => now(),
        ]);

        return new RingGroupMember(['wallet_group_member_id' => $member->id, 'priority' => $priority, 'destination_number' => $phone, 'ring_in_browser' => $browser]);
    }

    private function ringGroup(string $strategy, array $seats): RingGroup
    {
        $group = RingGroup::create(['wallet_group_id' => $this->group->id, 'name' => 'Sales', 'strategy' => $strategy]);
        foreach ($seats as $seat) {
            $seat->ring_group_id = $group->id;
            $seat->save();
        }

        return $group;
    }

    /** Twilio's X-Twilio-Signature over the URL (as given, query and all) + sorted POST params. */
    private function signedPost(string $url, array $params = [])
    {
        $params += ['CallSid' => 'CA1', 'From' => '+15551112222', 'To' => self::NUMBER];
        $sorted = $params;
        ksort($sorted);
        $data = $url;
        foreach ($sorted as $k => $v) {
            $data .= $k.$v;
        }
        $sig = base64_encode(hash_hmac('sha1', $data, 'secret_token', true));

        return $this->withHeaders(['X-Twilio-Signature' => $sig])->post($url, $params);
    }

    private function enter(): \Illuminate\Testing\TestResponse
    {
        return $this->signedPost(route('webhooks.twilio.voice'), ['CallStatus' => 'ringing']);
    }

    // ── model rules ─────────────────────────────────────────────────────────

    public function test_a_flow_cannot_point_at_a_number_from_another_workspace(): void
    {
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        $foreign = $this->numberFor($other, '+15005550007');

        $this->expectException(ValidationException::class);
        CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $foreign->id]);
    }

    public function test_a_telnyx_number_is_refused_not_silently_broken(): void
    {
        $telnyx = $this->numberFor($this->group, '+15005550008', 'telnyx');

        $this->expectException(ValidationException::class);
        CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $telnyx->id]);
    }

    public function test_the_root_node_must_belong_to_the_same_flow(): void
    {
        $flow = $this->flow();
        $otherNumber = $this->numberFor($this->group, '+15005550009');
        $otherFlow = CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $otherNumber->id]);
        $foreignNode = $this->node($otherFlow, CallFlowNode::VOICEMAIL);

        $this->expectException(ValidationException::class);
        $flow->update(['root_node_id' => $foreignNode->id]);
    }

    // ── webhook resolution ──────────────────────────────────────────────────

    public function test_an_unsigned_request_is_rejected_before_any_flow_lookup(): void
    {
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::VOICEMAIL));

        $this->post(route('webhooks.twilio.voice'), ['To' => self::NUMBER, 'From' => '+1555', 'CallSid' => 'CA1'])
            ->assertStatus(403);
    }

    public function test_an_active_flow_wins_over_a_forwarding_rule_on_the_same_number(): void
    {
        CallForwardingRule::create([
            'user_id' => $this->group->owner_user_id, 'twilio_number' => self::NUMBER,
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::VOICEMAIL));

        $res = $this->enter();

        $res->assertOk()->assertSee('<Record', false)->assertDontSee('+2348012345678', false);
    }

    public function test_a_paused_flow_falls_through_to_the_forwarding_rule_unchanged(): void
    {
        CallForwardingRule::create([
            'user_id' => $this->group->owner_user_id, 'twilio_number' => self::NUMBER,
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);
        $flow = $this->flow(CallFlow::PAUSED);
        $this->root($flow, $this->node($flow, CallFlowNode::VOICEMAIL));

        $this->enter()->assertOk()->assertSee('<Number>+2348012345678</Number>', false);
    }

    public function test_a_number_with_no_flow_and_no_rule_is_still_rejected_gracefully(): void
    {
        $this->enter()->assertOk()->assertSee('<Reject', false);
    }

    public function test_an_active_flow_without_a_root_node_says_goodbye(): void
    {
        $this->flow();

        $this->enter()->assertOk()->assertSee('<Hangup/>', false);
    }

    public function test_entering_a_flow_logs_a_call_event_against_the_flow_not_a_rule(): void
    {
        $flow = $this->flow();
        $node = $this->node($flow, CallFlowNode::VOICEMAIL);
        $this->root($flow, $node);

        $this->enter();

        $event = CallEvent::first();
        $this->assertNotNull($event);
        $this->assertNull($event->call_forwarding_rule_id);
        $this->assertSame($flow->id, $event->call_flow_id);
        $this->assertSame($node->id, $event->call_flow_node_id);
        $this->assertSame($this->group->owner_user_id, $event->user_id);
    }

    // ── menu ────────────────────────────────────────────────────────────────

    public function test_a_menu_gathers_a_digit_and_routes_to_the_chosen_child(): void
    {
        $flow = $this->flow();
        $sales = $this->node($flow, CallFlowNode::FORWARD, ['target_number' => '+2348011112222']);
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Press 1 for sales', 'digits' => ['1' => $sales->id], 'timeout_seconds' => 6]);
        $this->root($flow, $menu);

        $this->enter()->assertOk()
            ->assertSee('<Gather input="dtmf" numDigits="1" timeout="6"', false)
            ->assertSee('Press 1 for sales', false)
            ->assertSee('/digits?h=0&amp;a=0', false);

        $url = route('webhooks.twilio.call-flow.digits', ['callFlow' => $flow->id, 'node' => $menu->id, 'h' => 0, 'a' => 0]);
        $this->signedPost($url, ['Digits' => '1'])->assertOk()->assertSee('<Number>+2348011112222</Number>', false);
    }

    public function test_an_invalid_digit_replays_the_retry_node_and_gives_up_after_three_attempts(): void
    {
        $flow = $this->flow();
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick', 'digits' => []]);
        $this->root($flow, $menu);

        $digits = fn (int $a) => route('webhooks.twilio.call-flow.digits', ['callFlow' => $flow->id, 'node' => $menu->id, 'h' => 0, 'a' => $a]);

        $this->signedPost($digits(0), ['Digits' => '9'])->assertOk()
            ->assertSee('not a valid choice', false)->assertSee('<Redirect', false);

        // Third strike: no more replays.
        $this->signedPost($digits(2), ['Digits' => '9'])->assertOk()
            ->assertSee('Goodbye', false)->assertSee('<Hangup/>', false)->assertDontSee('<Redirect', false);
    }

    public function test_a_menu_entered_past_the_attempt_cap_hangs_up_instead_of_looping(): void
    {
        $flow = $this->flow();
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick', 'invalid_retry_node_id' => null]);
        $this->root($flow, $menu);

        $url = route('webhooks.twilio.call-flow', ['callFlow' => $flow->id, 'node' => $menu->id, 'h' => 5, 'a' => 3]);
        $this->signedPost($url)->assertOk()->assertSee('<Hangup/>', false)->assertDontSee('<Gather', false);
    }

    public function test_the_hop_cap_stops_a_node_cycle(): void
    {
        $flow = $this->flow();
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick']);
        $this->root($flow, $menu);

        $url = route('webhooks.twilio.call-flow', ['callFlow' => $flow->id, 'node' => $menu->id, 'h' => 13, 'a' => 0]);
        $this->signedPost($url)->assertOk()->assertSee('<Hangup/>', false)->assertDontSee('<Gather', false);
    }

    public function test_a_digit_mapped_to_a_node_of_another_flow_is_ignored(): void
    {
        $flow = $this->flow();
        $otherNumber = $this->numberFor($this->group, '+15005550009');
        $otherFlow = CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $otherNumber->id]);
        $foreign = $this->node($otherFlow, CallFlowNode::FORWARD, ['target_number' => '+2347000000000']);
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick', 'digits' => ['1' => $foreign->id]]);
        $this->root($flow, $menu);

        $url = route('webhooks.twilio.call-flow.digits', ['callFlow' => $flow->id, 'node' => $menu->id, 'h' => 0, 'a' => 0]);
        $this->signedPost($url, ['Digits' => '1'])->assertOk()->assertDontSee('+2347000000000', false);
    }

    public function test_signature_verifies_when_query_params_are_not_alphabetical(): void
    {
        // h before a — Request::fullUrl() would re-sort these and break the HMAC.
        $flow = $this->flow();
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick']);
        $this->root($flow, $menu);

        $url = route('webhooks.twilio.call-flow.digits', ['callFlow' => $flow->id, 'node' => $menu->id]).'?h=2&a=0';
        $this->signedPost($url, ['Digits' => '7'])->assertOk();
    }

    public function test_a_continuation_with_a_bad_signature_is_rejected(): void
    {
        $flow = $this->flow();
        $menu = $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Pick']);
        $this->root($flow, $menu);

        $this->post(route('webhooks.twilio.call-flow', ['callFlow' => $flow->id, 'node' => $menu->id]), ['Digits' => '1'])
            ->assertStatus(403);
    }

    // ── business hours ──────────────────────────────────────────────────────

    public function test_a_menu_branches_to_its_closed_node_outside_business_hours(): void
    {
        $flow = $this->flow();
        $vm = $this->node($flow, CallFlowNode::VOICEMAIL);
        $schedule = BusinessHoursSchedule::create([
            'wallet_group_id' => $this->group->id, 'timezone' => 'UTC',
            'windows' => [], // never open
        ]);
        $menu = $this->node($flow, CallFlowNode::MENU, [
            'prompt_text' => 'Pick', 'business_hours_schedule_id' => $schedule->id, 'closed_node_id' => $vm->id,
        ]);
        $this->root($flow, $menu);

        $this->enter()->assertOk()->assertSee('<Record', false)->assertDontSee('<Gather', false);
    }

    public function test_a_menu_stays_open_inside_business_hours(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00'); // Wednesday
        $flow = $this->flow();
        $vm = $this->node($flow, CallFlowNode::VOICEMAIL);
        $schedule = BusinessHoursSchedule::create([
            'wallet_group_id' => $this->group->id, 'timezone' => 'UTC',
            'windows' => [['day' => 'wed', 'start' => '09:00', 'end' => '17:00']],
        ]);
        $menu = $this->node($flow, CallFlowNode::MENU, [
            'prompt_text' => 'Pick', 'business_hours_schedule_id' => $schedule->id, 'closed_node_id' => $vm->id,
        ]);
        $this->root($flow, $menu);

        $this->enter()->assertOk()->assertSee('<Gather', false);
        Carbon::setTestNow();
    }

    public function test_is_open_now_honours_the_schedule_timezone(): void
    {
        // 2026-10-07 is a Wednesday. 15:30 UTC is 16:30 in Lagos (UTC+1) and 08:30 in Los Angeles (UTC-7).
        $at = Carbon::parse('2026-10-07 15:30:00', 'UTC');
        $window = [['day' => 'wed', 'start' => '09:00', 'end' => '17:00']];

        $lagos = new BusinessHoursSchedule(['timezone' => 'Africa/Lagos', 'windows' => $window]);
        $la = new BusinessHoursSchedule(['timezone' => 'America/Los_Angeles', 'windows' => $window]);

        $this->assertTrue($lagos->isOpenNow($at));
        $this->assertFalse($la->isOpenNow($at));
    }

    public function test_is_open_now_handles_a_window_that_runs_past_midnight(): void
    {
        $night = new BusinessHoursSchedule(['timezone' => 'UTC', 'windows' => [['day' => 'fri', 'start' => '22:00', 'end' => '02:00']]]);

        $this->assertTrue($night->isOpenNow(Carbon::parse('2026-10-09 23:00:00', 'UTC')));  // Fri 23:00
        $this->assertTrue($night->isOpenNow(Carbon::parse('2026-10-10 01:00:00', 'UTC')));  // Sat 01:00 — spill-over
        $this->assertFalse($night->isOpenNow(Carbon::parse('2026-10-10 03:00:00', 'UTC'))); // Sat 03:00
        $this->assertFalse($night->isOpenNow(Carbon::parse('2026-10-09 21:00:00', 'UTC'))); // Fri 21:00
    }

    public function test_a_holiday_override_beats_the_weekly_pattern(): void
    {
        $weekly = [['day' => 'wed', 'start' => '09:00', 'end' => '17:00']];
        $wed = Carbon::parse('2026-10-07 10:00:00', 'UTC');

        $closed = new BusinessHoursSchedule(['timezone' => 'UTC', 'windows' => $weekly, 'holiday_overrides' => [['date' => '2026-10-07', 'closed' => true]]]);
        $this->assertFalse($closed->isOpenNow($wed));

        $short = new BusinessHoursSchedule(['timezone' => 'UTC', 'windows' => $weekly, 'holiday_overrides' => [['date' => '2026-10-07', 'closed' => false, 'windows' => [['start' => '11:00', 'end' => '13:00']]]]]);
        $this->assertFalse($short->isOpenNow($wed));
        $this->assertTrue($short->isOpenNow(Carbon::parse('2026-10-07 12:00:00', 'UTC')));
    }

    // ── forward + voicemail ─────────────────────────────────────────────────

    public function test_a_forward_node_reuses_the_forwarding_rule_twiml_builder(): void
    {
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::FORWARD, ['target_number' => '+2348011112222', 'fallback_number' => '+2348033334444']));

        $flowBody = $this->enter()->assertOk()->getContent();

        // Byte-compare against the single shared builder the rule path calls.
        $expected = app('number.twilio')->forwardTwiml('+2348011112222', '+15551112222', '+2348033334444', route('webhooks.twilio.recording'));
        $this->assertSame($expected, $flowBody);
    }

    public function test_a_voicemail_node_plays_a_greeting_then_records(): void
    {
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::VOICEMAIL, ['greeting_url' => 'https://cdn.example.com/hello.mp3']));

        $this->enter()->assertOk()
            ->assertSee('<Play>https://cdn.example.com/hello.mp3</Play>', false)
            ->assertSee('<Record', false);
    }

    public function test_a_non_https_greeting_falls_back_to_spoken_text(): void
    {
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::VOICEMAIL, ['greeting_url' => 'javascript:alert(1)']));

        $this->enter()->assertOk()->assertDontSee('<Play>', false)->assertSee('leave a message', false);
    }

    public function test_menu_prompt_text_is_xml_escaped(): void
    {
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::MENU, ['prompt_text' => 'Sales </Say><Hangup/> & more']));

        $this->enter()->assertOk()->assertSee('Sales &lt;/Say&gt;&lt;Hangup/&gt; &amp; more', false);
    }

    // ── ring groups ─────────────────────────────────────────────────────────

    public function test_a_simultaneous_queue_is_one_dial_with_every_seat_as_a_child(): void
    {
        $group = $this->ringGroup(RingGroup::SIMULTANEOUS, [$this->seat('+2348011110001'), $this->seat('+2348011110002', browser: true)]);
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id, 'max_wait_seconds' => 45]));

        $body = $this->enter()->assertOk()->getContent();

        $this->assertSame(1, substr_count($body, '<Dial '));
        $this->assertStringContainsString('timeout="45"', $body);
        $this->assertStringContainsString('<Number>+2348011110001</Number>', $body);
        $this->assertStringContainsString('<Number>+2348011110002</Number>', $body);
        $this->assertMatchesRegularExpression('~<Client>naara_user_\d+</Client>~', $body);
        $this->assertStringContainsString('callerId="+15551112222"', $body);
    }

    public function test_a_queue_with_no_reachable_seat_goes_straight_to_overflow(): void
    {
        $empty = RingGroup::create(['wallet_group_id' => $this->group->id, 'name' => 'Empty', 'strategy' => RingGroup::SIMULTANEOUS]);
        $flow = $this->flow();
        $vm = $this->node($flow, CallFlowNode::VOICEMAIL);
        $this->root($flow, $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $empty->id, 'overflow_node_id' => $vm->id]));

        $this->enter()->assertOk()->assertDontSee('<Dial', false)->assertSee('<Record', false);
    }

    public function test_a_ring_group_from_another_workspace_is_never_dialled(): void
    {
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        $foreign = RingGroup::create(['wallet_group_id' => $other->id, 'name' => 'Theirs', 'strategy' => RingGroup::SIMULTANEOUS]);
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $foreign->id]));

        $this->enter()->assertOk()->assertDontSee('<Dial', false);
    }

    public function test_a_removed_seat_is_not_rung(): void
    {
        $seat = $this->seat('+2348011110001');
        $group = $this->ringGroup(RingGroup::SIMULTANEOUS, [$seat, $this->seat('+2348011110002')]);
        WalletGroupMember::whereKey($seat->wallet_group_member_id)->update(['status' => 'removed']);
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id]));

        $this->enter()->assertOk()->assertDontSee('+2348011110001', false)->assertSee('+2348011110002', false);
    }

    public function test_a_seat_without_an_override_rings_its_own_personal_line(): void
    {
        $user = User::factory()->create();
        VirtualNumber::create(['user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15005559999', 'sid' => 'PN1', 'status' => 'active']);
        $member = WalletGroupMember::create(['wallet_group_id' => $this->group->id, 'user_id' => $user->id, 'role' => 'agent', 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        $group = $this->ringGroup(RingGroup::SIMULTANEOUS, [new RingGroupMember(['wallet_group_member_id' => $member->id])]);
        $flow = $this->flow();
        $this->root($flow, $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id]));

        $this->enter()->assertOk()->assertSee('<Number>+15005559999</Number>', false);
    }

    public function test_a_sequential_queue_dials_one_seat_at_a_time_in_priority_order(): void
    {
        $group = $this->ringGroup(RingGroup::SEQUENTIAL, [$this->seat('+2348011110002', priority: 2), $this->seat('+2348011110001', priority: 1)]);
        $flow = $this->flow();
        $queue = $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id, 'max_wait_seconds' => 120]);
        $this->root($flow, $queue);

        // Leg 1: priority 1 only, with a short per-leg timeout.
        $first = $this->enter()->assertOk()->getContent();
        $this->assertStringContainsString('<Number>+2348011110001</Number>', $first);
        $this->assertStringNotContainsString('+2348011110002', $first);
        $this->assertStringContainsString('timeout="20"', $first);
        $this->assertStringContainsString('step=0', $first);

        // Leg 1 not answered → leg 2.
        $dial = fn (int $step) => route('webhooks.twilio.call-flow.dial', ['callFlow' => $flow->id, 'node' => $queue->id, 'step' => $step, 'h' => 0]);
        $second = $this->signedPost($dial(0), ['DialCallStatus' => 'no-answer'])->assertOk()->getContent();
        $this->assertStringContainsString('<Number>+2348011110002</Number>', $second);
        $this->assertStringContainsString('step=1', $second);

        // Leg 2 not answered → no more seats → goodbye.
        $this->signedPost($dial(1), ['DialCallStatus' => 'busy'])->assertOk()
            ->assertDontSee('<Dial', false)->assertSee('<Hangup/>', false);
    }

    public function test_an_answered_leg_just_hangs_up_when_the_bridged_call_ends(): void
    {
        $group = $this->ringGroup(RingGroup::SEQUENTIAL, [$this->seat('+2348011110001')]);
        $flow = $this->flow();
        $queue = $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id]);
        $this->root($flow, $queue);

        $url = route('webhooks.twilio.call-flow.dial', ['callFlow' => $flow->id, 'node' => $queue->id, 'step' => 0, 'h' => 0]);
        $this->signedPost($url, ['DialCallStatus' => 'completed'])->assertOk()
            ->assertSee('<Hangup/>', false)->assertDontSee('<Dial', false)->assertDontSee('<Say>', false);

        $this->assertSame('answered', CallEvent::where('call_flow_node_id', $queue->id)->latest('id')->value('status'));
    }

    public function test_a_simultaneous_no_answer_falls_to_the_overflow_node(): void
    {
        $group = $this->ringGroup(RingGroup::SIMULTANEOUS, [$this->seat('+2348011110001')]);
        $flow = $this->flow();
        $vm = $this->node($flow, CallFlowNode::VOICEMAIL);
        $queue = $this->node($flow, CallFlowNode::QUEUE, ['ring_group_id' => $group->id, 'overflow_node_id' => $vm->id]);
        $this->root($flow, $queue);

        $url = route('webhooks.twilio.call-flow.dial', ['callFlow' => $flow->id, 'node' => $queue->id, 'step' => 0, 'h' => 0]);
        $this->signedPost($url, ['DialCallStatus' => 'no-answer'])->assertOk()->assertSee('<Record', false);
    }
}
