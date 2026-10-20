<?php

namespace Tests\Feature\N2N;

use App\Models\N2N\Block;
use App\Models\N2N\CallInterval;
use App\Models\N2N\CallLink;
use App\Models\N2N\CallParticipant;
use App\Models\N2N\GroupCall;
use App\Models\N2N\RealtimeEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Group\GroupAvailability;
use App\Services\N2N\Group\GroupCallException;
use App\Services\N2N\Group\GroupCallService;
use App\Services\N2N\Group\SfuClient;
use App\Services\N2N\Identity\NaaraId;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** N2N Batch 11: group calls. Join rules, speaker limits, SFU grants, billing by appointment and the sweeper (MASTER-ONLY). The SFU is faked here; see docs/N2N-GROUP-CALLS.md for the real-SFU run. */
class GroupCallsTest extends N2NTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('n2n.sfu', ['url' => 'http://sfu.test', 'ws_url' => 'wss://sfu.test', 'key' => 'devkey', 'secret' => str_repeat('s', 32)]);
        Setting::setValue('n2n.group_calls_enabled', true);
        Setting::setValue('n2n.call_usd_per_min', 0.10);          // a round rate so the arithmetic below is easy to read
        Cache::forget('n2n:sfu:health');
        Http::fake(['sfu.test/twirp/*' => Http::response(['rooms' => [], 'participants' => []]), 'sfu.test/*' => Http::response('OK')]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name = 'Member', float $usd = 5): User
    {
        $u = $this->user(['email_verified_at' => now(), 'name' => $name, 'referral_code' => strtoupper(substr(md5($name.uniqid()), 0, 8))]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        if ($usd > 0) {
            app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
            app(UsageCredit::class)->purchase($u, $usd, 'b'.uniqid());
        }

        return $u->fresh();
    }

    private function svc(): GroupCallService
    {
        return app(GroupCallService::class);
    }

    private function bal(User $u): int
    {
        return array_sum(app(UsageLedgerService::class)->balances($u->id));
    }

    /** @return array{0: GroupCall, 1: string, 2: User} */
    private function room(array $opts = []): array
    {
        $host = $opts['host'] ?? $this->member('Host');
        $r = $this->svc()->create($host, $opts['title'] ?? 'Team call', $opts['capacity'] ?? null, $opts['approval'] ?? false);

        return [$r['call'], $r['link']->token(), $host];
    }

    private function jwtGrants(string $jwt): array
    {
        [, $body] = explode('.', $jwt);

        return json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    }

    private function fails(callable $fn, string $reason): void
    {
        try {
            $fn();
            $this->fail("expected GroupCallException({$reason})");
        } catch (GroupCallException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
        }
    }

    // ---- availability
    public function test_group_calls_are_off_unless_every_switch_and_the_sfu_health_check_pass(): void
    {
        $this->assertTrue(GroupAvailability::enabled());
        Setting::setValue('n2n.group_calls_enabled', false);
        $this->assertFalse(GroupAvailability::enabled());
        Setting::setValue('n2n.group_calls_enabled', true);
        Cache::forget('n2n:sfu:health');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['sfu.test/*' => Http::response('down', 502)]);
        $this->assertFalse(GroupAvailability::enabled(), 'a failing SFU switches the feature off');
        $this->fails(fn () => $this->svc()->create($this->member(), 'x y', null), 'unavailable');
        config()->set('n2n.sfu.secret', '');
        Cache::forget('n2n:sfu:health');
        $this->assertFalse(SfuClient::configured());
    }

    // ---- creating and joining
    public function test_creating_a_room_makes_the_host_a_billed_speaker_and_stores_the_link_only_hashed(): void
    {
        [$call, $token, $host] = $this->room(['capacity' => 10]);
        $this->assertSame(10, $call->capacity);
        $this->assertSame(5, $call->max_speakers);
        $p = CallParticipant::query()->where('group_call_id', $call->id)->where('user_id', $host->id)->first();
        $this->assertTrue($p->speaking);
        $this->assertSame('host', $p->role);
        $this->assertNotNull(CallInterval::query()->where('user_id', $host->id)->whereNull('to_at')->first());
        $this->assertSame(CallLink::hash($token), CallLink::query()->first()->token_hash);
        $this->assertDatabaseMissing('n2n_call_links', ['token_hash' => $token]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'CreateRoom') && $r['name'] === $call->room);
    }

    public function test_capacity_is_capped_by_the_admin_setting_and_rooms_are_limited(): void
    {
        Setting::setValue('n2n.group_max_capacity', 8);
        [$call] = $this->room(['capacity' => 90]);
        $this->assertSame(8, $call->capacity);
        Setting::setValue('n2n.group_max_rooms', 1);
        $this->fails(fn () => $this->svc()->create($this->member('Other'), 'Second room', null), 'busy');
    }

    public function test_a_listener_gets_a_token_that_cannot_publish_and_the_room_fills_up(): void
    {
        [$call, $token] = $this->room(['capacity' => 3]);
        $a = $this->member('A');
        $r = $this->svc()->join($a, $token);
        $grants = $this->jwtGrants($r['connect']['token']);
        $this->assertFalse($grants['video']['canPublish'], 'a listener cannot publish: the SFU refuses it');
        $this->assertSame([], $grants['video']['canPublishSources']);
        $this->assertFalse($grants['video']['canPublishData']);
        $this->assertSame($call->room, $grants['video']['room']);
        $this->assertSame('u'.$a->id, $grants['sub']);
        $this->assertTrue($grants['exp'] - $grants['nbf'] <= 700, 'the join token is short-lived');
        $this->assertSame('wss://sfu.test', $r['connect']['url']);
        $this->assertStringNotContainsString(str_repeat('s', 32), json_encode($r), 'the API secret never reaches the browser');

        $this->svc()->join($this->member('B'), $token);                       // host + A + B = capacity 3
        $this->fails(fn () => $this->svc()->join($this->member('C'), $token), 'room_full');
        $this->assertSame($r['connect']['room'], $call->room);
    }

    public function test_dead_links_blocks_and_removed_people_all_fail_the_same_safe_way(): void
    {
        [$call, $token, $host] = $this->room();
        $this->fails(fn () => $this->svc()->join($this->member('X'), str_repeat('z', 40)), 'link_invalid');
        $this->fails(fn () => $this->svc()->join($this->member('X'), 'short'), 'link_invalid');

        $blocked = $this->member('Blocked');
        Block::create(['blocker_id' => $host->id, 'blocked_id' => $blocked->id, 'created_at' => now()]);
        $this->fails(fn () => $this->svc()->join($blocked, $token), 'link_invalid');   // looks exactly like an expired link

        CallLink::query()->update(['expires_at' => now()->subMinute()]);
        $this->fails(fn () => $this->svc()->join($this->member('Late'), $token), 'link_invalid');

        $newToken = $this->svc()->newLink($host, $call->id)->token();
        $bad = $this->member('Troll');
        $this->svc()->join($bad, $newToken);
        $this->svc()->remove($host, $call->id, $bad->id);
        $this->fails(fn () => $this->svc()->join($bad, $newToken), 'banned');
        $fresh = $this->svc()->newLink($host, $call->id)->token();
        $this->fails(fn () => $this->svc()->join($bad, $fresh), 'banned');             // a removed person cannot return with a NEW link either
        Http::assertSent(fn ($r) => str_contains($r->url(), 'RemoveParticipant') && $r['identity'] === 'u'.$bad->id);

        $this->svc()->revokeLinks($host, $call->id);
        $this->fails(fn () => $this->svc()->join($this->member('Rev'), $fresh), 'link_invalid');
    }

    public function test_someone_already_in_another_call_cannot_join_a_second_room(): void
    {
        [, $token1] = $this->room();
        [, $token2] = $this->room(['host' => $this->member('Host2')]);
        $u = $this->member('Busy');
        $this->svc()->join($u, $token1);
        $this->fails(fn () => $this->svc()->join($u, $token2), 'already_in_call');
    }

    public function test_the_approval_flow_holds_a_person_until_the_host_lets_them_in(): void
    {
        [$call, $token, $host] = $this->room(['approval' => true]);
        $u = $this->member('Waiting');
        $r = $this->svc()->join($u, $token);
        $this->assertTrue($r['pending']);
        $this->assertNull($r['connect'], 'no connection details until approved');
        $this->assertSame(1, count($this->svc()->state($host, $call->id)['waiting']));
        $this->assertTrue($this->svc()->myStatus($u, $call->id)['pending']);

        $this->svc()->approve($host, $call->id, $u->id);
        $this->assertNotNull($this->svc()->myStatus($u, $call->id)['connect']);

        $v = $this->member('Denied');
        $this->svc()->join($v, $token);
        $this->svc()->approve($host, $call->id, $v->id, false);
        $this->assertTrue($this->svc()->myStatus($v, $call->id)['denied']);
    }

    // ---- speakers
    public function test_the_host_appoints_up_to_five_speakers_and_the_sixth_is_refused(): void
    {
        [$call, $token, $host] = $this->room();
        $people = [];
        foreach (range(1, 5) as $i) {
            $people[$i] = $this->member('P'.$i);
            $this->svc()->join($people[$i], $token);
        }
        foreach ([1, 2, 3, 4] as $i) {
            $this->svc()->appoint($host, $call->id, $people[$i]->id);       // host + 4 = 5
        }
        $this->fails(fn () => $this->svc()->appoint($host, $call->id, $people[5]->id), 'speakers_full');
        $this->assertSame(5, CallParticipant::query()->where('group_call_id', $call->id)->where('speaking', true)->count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'UpdateParticipant') && $r['identity'] === 'u'.$people[1]->id && $r['permission']['can_publish'] === true && $r['permission']['can_publish_sources'] === ['MICROPHONE']);

        // Stepping one down frees a place; a speaker's fresh token now carries the publish grant.
        $this->svc()->demote($host, $call->id, $people[1]->id);
        $this->svc()->appoint($host, $call->id, $people[5]->id);
        $grants = $this->jwtGrants($this->svc()->myStatus($people[5], $call->id)['connect']['token']);
        $this->assertTrue($grants['video']['canPublish']);
        $this->assertSame(['microphone'], $grants['video']['canPublishSources']);
    }

    public function test_only_the_host_can_appoint_mute_or_remove_and_a_speaker_can_step_down(): void
    {
        [$call, $token, $host] = $this->room();
        $a = $this->member('A');
        $b = $this->member('B');
        $this->svc()->join($a, $token);
        $this->svc()->join($b, $token);
        $this->fails(fn () => $this->svc()->appoint($a, $call->id, $b->id), 'not_found');
        $this->fails(fn () => $this->svc()->mute($a, $call->id, $b->id), 'not_found');
        $this->fails(fn () => $this->svc()->remove($a, $call->id, $b->id), 'not_found');
        $this->fails(fn () => $this->svc()->close($a, $call->id), 'not_found');
        $this->svc()->appoint($host, $call->id, $a->id);
        $this->fails(fn () => $this->svc()->demote($b, $call->id, $a->id), 'forbidden');   // neither host nor that speaker
        $this->svc()->demote($a, $call->id, $a->id);                                        // a speaker may step down themselves
        $this->assertFalse(CallParticipant::query()->where('user_id', $a->id)->first()->speaking);
        $this->svc()->mute($host, $call->id, $a->id);                                       // no error even when nothing is published
    }

    public function test_a_person_without_credit_cannot_take_the_microphone_and_the_host_can_still_host(): void
    {
        [$call, $token, $host] = $this->room();
        $poor = $this->member('Poor', 0);
        $this->svc()->join($poor, $token);
        $this->fails(fn () => $this->svc()->appoint($host, $call->id, $poor->id), 'no_credit');
        $this->assertFalse(CallParticipant::query()->where('user_id', $poor->id)->first()->speaking);
        $this->assertSame(0, CallInterval::query()->where('user_id', $poor->id)->count(), 'a refused appointment leaves no interval behind');

        $brokeHost = $this->member('BrokeHost', 0);
        $r = $this->svc()->create($brokeHost, 'Free chat', null);
        $this->assertFalse(CallParticipant::query()->where('user_id', $brokeHost->id)->first()->speaking, 'a host without credit hosts as a listener');
        $this->assertTrue($r['call']->isOpen());
    }

    // ---- billing
    public function test_appoint_mute_unappoint_bills_exactly_the_appointed_interval_and_listeners_pay_nothing(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        [$call, $token, $host] = $this->room();
        $a = $this->member('Speaker', 5);
        $l = $this->member('Listener', 5);
        $this->svc()->join($a, $token);
        $this->svc()->join($l, $token);
        $startA = $this->bal($a);
        $startL = $this->bal($l);

        $this->svc()->appoint($host, $call->id, $a->id);
        Carbon::setTestNow('2026-10-04 10:01:10');
        $this->svc()->heartbeat($a, $call->id);                                            // the speaker's page beats every few seconds and extends the credit cover
        $this->svc()->mute($host, $call->id, $a->id);                                      // muting does not stop the clock
        Carbon::setTestNow('2026-10-04 10:02:05');                                         // 125 s appointed
        $this->svc()->heartbeat($a, $call->id);
        $this->svc()->heartbeat($host, $call->id);
        $this->svc()->demote($host, $call->id, $a->id);

        $rate = (int) \App\Services\N2N\Credit\N2NPricing::speakerRatePerMinuteCents();
        $iv = CallInterval::query()->where('user_id', $a->id)->first();
        $this->assertSame(125, (int) $iv->from_at->diffInSeconds($iv->to_at));
        $this->assertSame(180, $iv->billed_seconds, '125 s rounds UP to 3 whole minutes');
        $this->assertSame((int) ceil(180 * $rate / 60), $iv->billed_cents);
        $this->assertSame($startA - $iv->billed_cents, $this->bal($a), 'the speaker paid exactly that, unused holds returned');
        $this->assertSame($startL, $this->bal($l), 'a listener is never charged');
    }

    public function test_a_crash_mid_call_settles_at_the_last_heartbeat_not_at_the_sweep(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        [$call, $token, $host] = $this->room();
        $a = $this->member('Speaker', 5);
        $this->svc()->join($a, $token);
        $this->svc()->appoint($host, $call->id, $a->id);
        Carbon::setTestNow('2026-10-04 10:01:00');
        $this->svc()->heartbeat($host, $call->id);
        $this->svc()->heartbeat($a, $call->id);                                           // the speaker's last sign of life
        Carbon::setTestNow('2026-10-04 10:03:00');
        $this->svc()->heartbeat($host, $call->id);                                        // the host is still there
        $this->svc()->sweep();

        $iv = CallInterval::query()->where('user_id', $a->id)->first();
        $this->assertSame('lost', $iv->end_reason);
        $this->assertSame('2026-10-04 10:01:00', $iv->to_at->format('Y-m-d H:i:s'));
        $this->assertSame(60, $iv->billed_seconds);
        $this->assertNotNull(CallParticipant::query()->where('user_id', $a->id)->first()->left_at);
        $this->assertTrue($call->fresh()->isOpen(), 'the room goes on without them');
    }

    public function test_a_speaker_who_runs_out_of_credit_is_taken_back_to_the_audience_automatically(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        Setting::setValue('n2n.call_usd_per_min', 0.50);
        Setting::setValue('n2n.call_hold_minutes', 1);
        [$call, $token, $host] = $this->room();
        $thin = $this->member('Thin', 1);                       // $1 buys exactly two minutes at this rate
        $this->svc()->join($thin, $token);
        $this->svc()->appoint($host, $call->id, $thin->id);
        foreach (['10:00:40', '10:01:20', '10:01:50', '10:02:30'] as $t) {
            Carbon::setTestNow('2026-10-04 '.$t);
            $this->svc()->heartbeat($host, $call->id);
            $out = $this->svc()->heartbeat($thin, $call->id);
        }

        $this->assertFalse($out['speaking'], 'taken back to the audience');
        $this->assertTrue($out['present'], 'still in the room as a listener');
        $iv = CallInterval::query()->where('user_id', $thin->id)->first();
        $this->assertSame('credit', $iv->end_reason);
        $this->assertSame(120, $iv->billed_seconds, 'billed up to the moment the cover ran out, not beyond');
        $this->assertNotNull(RealtimeEvent::query()->where('user_id', $thin->id)->where('type', 'group.demoted')->first());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'UpdateParticipant') && $r['identity'] === 'u'.$thin->id && $r['permission']['can_publish'] === false);
    }

    public function test_closing_the_room_settles_everyone_revokes_links_and_deletes_the_sfu_room(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        [$call, $token, $host] = $this->room();
        $a = $this->member('Speaker');
        $this->svc()->join($a, $token);
        $this->svc()->appoint($host, $call->id, $a->id);
        Carbon::setTestNow('2026-10-04 10:00:30');
        $this->svc()->close($host, $call->id);

        $call->refresh();
        $this->assertSame(GroupCall::CLOSED, $call->state);
        $this->assertSame(0, CallInterval::query()->where('group_call_id', $call->id)->whereNull('to_at')->count(), 'every open interval was settled');
        $this->assertSame(0, CallLink::query()->where('group_call_id', $call->id)->whereNull('revoked_at')->count());
        $this->assertSame(0, CallParticipant::query()->where('group_call_id', $call->id)->whereNull('left_at')->count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'DeleteRoom') && $r['room'] === $call->room);
        $this->fails(fn () => $this->svc()->join($this->member('Late'), $token), 'link_invalid');
        $this->fails(fn () => $this->svc()->appoint($host, $call->id, $a->id), 'closed');
    }

    public function test_when_the_host_leaves_the_room_ends_and_an_empty_room_is_closed_by_the_sweeper(): void
    {
        [$call, , $host] = $this->room();
        $this->svc()->leave($host, $call->id);
        $this->assertSame('host_left', $call->fresh()->end_reason);

        Carbon::setTestNow('2026-10-04 10:00:00');
        [$idle] = $this->room(['host' => $this->member('Host3')]);
        CallParticipant::query()->where('group_call_id', $idle->id)->update(['left_at' => now()]);
        $idle->forceFill(['last_active_at' => now()])->save();
        Carbon::setTestNow('2026-10-04 10:10:00');
        $this->svc()->sweep();
        $this->assertSame('idle', $idle->fresh()->end_reason);
    }

    // ---- HTTP surface
    public function test_the_http_layer_does_not_leak_rooms_to_outsiders_and_the_landing_carries_the_hosts_referral_code(): void
    {
        [$call, $token, $host] = $this->room();
        $outsider = $this->member('Outsider');
        $this->actingAs($outsider)->get(route('n2n.group.room', $call->id))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('n2n.api.group.state', $call->id))->assertNotFound();
        $this->actingAs($outsider)->postJson(route('n2n.api.group.close', $call->id))->assertNotFound();
        $this->actingAs($outsider)->postJson(route('n2n.api.group.appoint', [$call->id, $host->id]))->assertNotFound();

        auth()->logout();
        $this->get(route('n2n.group.join', $token))->assertOk()->assertSee('Team call')->assertSee('ref='.$host->referral_code)->assertSessionHas('url.intended');
        $this->get(route('n2n.group.join', str_repeat('q', 40)))->assertNotFound();

        $r = $this->actingAs($outsider)->postJson(route('n2n.api.group.join'), ['token' => $token])->assertOk()->assertJsonPath('pending', false)->json();
        $this->assertNotEmpty($r['connect']['token']);
        $this->actingAs($outsider)->get(route('n2n.group.room', $call->id))->assertOk();
        $this->actingAs($outsider)->getJson(route('n2n.api.group.state', $call->id))->assertOk()->assertJsonPath('is_host', false)->assertJsonPath('link', null);
        $this->actingAs($host)->getJson(route('n2n.api.group.state', $call->id))->assertOk()->assertJsonPath('is_host', true)->assertJsonPath('speakers.0.name', $host->name);
        $this->actingAs($outsider)->postJson(route('n2n.api.group.join'), ['token' => 'nope'])->assertStatus(422);
    }
    public function test_the_admin_page_saves_validated_caps_and_non_admins_are_refused(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = $this->user();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\GroupCallSettings::class)
            ->set('maxSpeakers', 3)->set('maxCapacity', 12)->set('linkHours', 6)->call('save')->assertHasNoErrors()->assertSee('Group call settings saved.');
        $this->assertSame(3, \App\Support\N2N\N2NSettings::groupMaxSpeakers());
        $this->assertSame(12, \App\Support\N2N\N2NSettings::groupMaxCapacity());
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\GroupCallSettings::class)->set('maxSpeakers', 9)->call('save')->assertHasErrors(['maxSpeakers']);
        \Livewire\Livewire::actingAs($this->user())->test(\App\Livewire\Admin\N2N\GroupCallSettings::class)->assertForbidden();
    }

    public function test_the_calls_page_offers_a_new_group_call_only_while_group_calls_are_available(): void
    {
        $u = $this->member('Host');
        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\N2N\Calls::class)->assertSee('New group call');
        Setting::setValue('n2n.group_calls_enabled', false);
        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\N2N\Calls::class)->assertDontSee('New group call');
    }
}
