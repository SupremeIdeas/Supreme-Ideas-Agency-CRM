<?php

namespace Tests\Feature\N2N;

use App\Events\N2N\RealtimeBroadcast;
use App\Models\N2N\RealtimeEvent;
use App\Models\Setting;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Realtime\ChannelAuth;
use App\Services\N2N\Realtime\Realtime;
use App\Services\N2N\Realtime\RealtimeHealth;
use Illuminate\Support\Facades\Event;

/** N2N Batch 4: realtime event log, poll driver, Reverb driver, channel auth, presence (MASTER-ONLY). */
class RealtimeTest extends N2NTestCase
{
    public function test_published_events_are_logged_in_order_and_replayed_after_a_gap_exactly_once(): void
    {
        $u = $this->user();
        $a = Realtime::publish($u->id, 'message.new', ['c' => 1]);
        $b = Realtime::publish($u->id, 'message.new', ['c' => 2]);
        $this->assertGreaterThan($a, $b);

        // The client connects, receives both, then "loses the connection" while two more events happen.
        $first = $this->actingAs($u)->getJson('/n2n/realtime/sync?cursor=0')->assertOk()->json();
        $this->assertSame([$a, $b], array_column($first['events'], 'id'));
        $cursor = $first['cursor'];

        Realtime::publish($u->id, 'message.new', ['c' => 3]);
        Realtime::publish($u->id, 'credit.low', []);

        $replay = $this->actingAs($u)->getJson('/n2n/realtime/sync?cursor='.$cursor)->assertOk()->json();
        $this->assertSame(['message.new', 'credit.low'], array_column($replay['events'], 'type'));
        $this->assertSame(3, $replay['events'][0]['payload']['c']);

        $again = $this->actingAs($u)->getJson('/n2n/realtime/sync?cursor='.$replay['cursor'])->assertOk()->json();
        $this->assertSame([], $again['events'], 'nothing is delivered twice');
        $this->assertSame($replay['cursor'], $again['cursor']);
    }

    public function test_a_user_only_ever_sees_their_own_events(): void
    {
        $a = $this->user(); $b = $this->user();
        Realtime::publish($a->id, 'message.new', ['for' => 'a']);
        Realtime::publish($b->id, 'message.new', ['for' => 'b']);

        $seen = $this->actingAs($a)->getJson('/n2n/realtime/sync?cursor=0')->json('events');
        $this->assertCount(1, $seen);
        $this->assertSame('a', $seen[0]['payload']['for']);
    }

    public function test_the_poll_endpoint_pages_a_backlog_and_flags_more(): void
    {
        $u = $this->user();
        foreach (range(1, 130) as $i) { Realtime::publish($u->id, 'x', ['i' => $i]); }
        $r = $this->actingAs($u)->getJson('/n2n/realtime/sync?cursor=0')->json();
        $this->assertCount(100, $r['events']);
        $this->assertTrue($r['has_more']);
        $r2 = $this->actingAs($u)->getJson('/n2n/realtime/sync?cursor='.$r['cursor'])->json();
        $this->assertCount(30, $r2['events']);
        $this->assertFalse($r2['has_more']);
    }

    public function test_typing_is_ephemeral_and_never_logged(): void
    {
        $u = $this->user();
        Realtime::publish($u->id, 'typing', ['from' => 'NR-X']);
        $this->assertSame(0, RealtimeEvent::count());
    }

    public function test_the_client_config_picks_the_driver_and_never_leaks_the_reverb_secret(): void
    {
        $u = $this->user();
        config(['n2n.realtime.reverb.key' => 'pubkey', 'n2n.realtime.reverb.secret' => 'SUPERSECRET', 'n2n.realtime.reverb.public_host' => 'ws.example.com']);

        $poll = $this->actingAs($u)->getJson('/n2n/realtime/config')->assertOk()->json();
        $this->assertSame('poll', $poll['driver']);
        $this->assertArrayNotHasKey('reverb', $poll);
        $this->assertGreaterThanOrEqual(1000, $poll['poll']['visible_ms']);

        Setting::setValue('n2n.realtime_driver', 'reverb');
        $res = $this->actingAs($u)->getJson('/n2n/realtime/config')->assertOk();
        $this->assertSame('reverb', $res->json('driver'));
        $this->assertSame('ws.example.com', $res->json('reverb.host'));
        $this->assertSame('n2n.user.'.$u->id, $res->json('reverb.channel'));
        $this->assertStringNotContainsString('SUPERSECRET', $res->getContent());
    }

    public function test_the_reverb_driver_broadcasts_to_the_private_channel_and_a_dead_server_never_breaks_a_publish(): void
    {
        Setting::setValue('n2n.realtime_driver', 'reverb');
        Event::fake([RealtimeBroadcast::class]);
        $u = $this->user();
        $id = Realtime::publish($u->id, 'message.new', ['c' => 1]);

        Event::assertDispatched(RealtimeBroadcast::class, function ($e) use ($u, $id) {
            return $e->userId === $u->id && $e->envelope['id'] === $id && $e->broadcastOn()[0]->name === 'private-n2n.user.'.$u->id && $e->broadcastAs() === 'n2n.event';
        });
        $this->assertSame(1, RealtimeEvent::count(), 'the log row is the poll/replay fallback');

        Event::fake([]);   // real dispatch now: there is no Reverb server in tests, so the broadcast fails
        config(['n2n.realtime.reverb.port' => 1]);
        $this->assertNotNull(Realtime::publish($u->id, 'message.new', ['c' => 2]), 'a failed broadcast is swallowed and logged');
    }

    public function test_the_null_driver_publishes_nothing(): void
    {
        Setting::setValue('n2n.realtime_driver', 'null');
        $this->assertNull(Realtime::publish($this->user()->id, 'message.new'));
        $this->assertSame(0, RealtimeEvent::count());
    }

    public function test_channel_authorisation_allows_only_the_owner_and_denies_unregistered_kinds(): void
    {
        $a = $this->user(); $b = $this->user();
        $this->assertTrue(ChannelAuth::can($a, 'user', $a->id));
        $this->assertFalse(ChannelAuth::can($a, 'user', $b->id), 'another user\'s personal channel');
        $this->assertFalse(ChannelAuth::can($a, 'conv', 1), 'no authoriser registered yet = denied');
        $this->assertFalse(ChannelAuth::can($a, 'call', 1));
        $this->assertFalse(ChannelAuth::can(null, 'user', 1));
        $this->assertFalse(ChannelAuth::can($this->user(['is_active' => false]), 'user', 999) );

        ChannelAuth::register('conv', fn ($u, $id) => $id === 7 && $u->id === $a->id);
        $this->assertTrue(ChannelAuth::can($a, 'conv', 7));
        $this->assertFalse(ChannelAuth::can($b, 'conv', 7));
    }

    public function test_the_broadcast_auth_endpoint_rejects_a_foreign_private_channel(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
        app(\Illuminate\Broadcasting\BroadcastManager::class)->purge('reverb');
        \App\Providers\N2NServiceProvider::registerChannels();   // channel definitions belong to the active broadcaster
        $a = $this->user(); $b = $this->user();

        $this->actingAs($a)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-n2n.user.'.$b->id])->assertForbidden();
        $this->actingAs($a)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-n2n.user.'.$a->id])->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs($a)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-n2n.conv.1'])->assertForbidden();
    }

    public function test_realtime_endpoints_are_404_when_n2n_is_off_and_need_a_login(): void
    {
        $this->getJson('/n2n/realtime/config')->assertUnauthorized();
        Setting::setValue('n2n.enabled', false);
        $this->actingAs($this->user())->getJson('/n2n/realtime/sync')->assertNotFound();
    }

    public function test_presence_hide_means_neither_seen_nor_seeing(): void
    {
        $viewer = $this->user(); $target = $this->user();
        $target->forceFill(['naara_id' => NaaraId::generate()])->save();

        $this->actingAs($target)->postJson('/n2n/realtime/ping')->assertOk();
        $see = $this->actingAs($viewer)->getJson('/n2n/realtime/seen/'.$target->naara_id)->assertOk()->json();
        $this->assertTrue($see['visible']);
        $this->assertTrue($see['online']);

        $target->forceFill(['n2n_hide_presence' => true])->save();
        $this->assertFalse($this->actingAs($viewer)->getJson('/n2n/realtime/seen/'.$target->naara_id)->json('visible'), 'hidden user is invisible');

        $target->forceFill(['n2n_hide_presence' => false])->save();
        $viewer->forceFill(['n2n_hide_presence' => true])->save();
        $this->assertFalse($this->actingAs($viewer->fresh())->getJson('/n2n/realtime/seen/'.$target->naara_id)->json('visible'), 'a viewer who hides cannot see either');

        // A hidden user's heartbeat is not even recorded.
        $viewer->forceFill(['n2n_hide_presence' => true])->save();
        $this->actingAs($viewer->fresh())->postJson('/n2n/realtime/ping');
        $this->assertDatabaseMissing('n2n_presence', ['user_id' => $viewer->id]);
    }

    public function test_typing_reaches_only_a_visible_target_and_the_reply_never_reveals_who_exists(): void
    {
        $a = $this->user(); $b = $this->user();
        $b->forceFill(['naara_id' => NaaraId::generate()])->save();
        Event::fake([RealtimeBroadcast::class]);
        Setting::setValue('n2n.realtime_driver', 'reverb');

        $ok = $this->actingAs($a)->postJson('/n2n/realtime/typing', ['to' => $b->naara_id, 'context' => 'c1'])->assertOk()->json();
        $miss = $this->actingAs($a)->postJson('/n2n/realtime/typing', ['to' => 'NR-0000000000A'])->assertOk()->json();
        $this->assertSame($ok, $miss);
        Event::assertDispatchedTimes(RealtimeBroadcast::class, 1);
        $this->assertSame(0, RealtimeEvent::count(), 'typing is never logged');

        $b->forceFill(['n2n_hide_presence' => true])->save();
        $this->actingAs($a)->postJson('/n2n/realtime/typing', ['to' => $b->naara_id])->assertOk();
        Event::assertDispatchedTimes(RealtimeBroadcast::class, 1);
    }

    public function test_pruning_removes_only_expired_events_and_the_health_report_is_honest(): void
    {
        $u = $this->user();
        $old = RealtimeEvent::create(['user_id' => $u->id, 'type' => 'x', 'created_at' => now()->subDays(3)]);
        $new = RealtimeEvent::create(['user_id' => $u->id, 'type' => 'x', 'created_at' => now()]);
        $this->artisan('n2n:prune-realtime')->assertSuccessful();
        $this->assertNull(RealtimeEvent::find($old->id));
        $this->assertNotNull(RealtimeEvent::find($new->id));

        RealtimeHealth::notePoll();
        $r = app(RealtimeHealth::class)->report();
        $this->assertSame('poll', $r['driver']);
        $this->assertNull($r['reverb_reachable']);
        $this->assertSame(1, $r['polls_this_minute']);
        $this->assertStringContainsString('Not measured', $r['capacity']);
        $this->assertArrayHasKey('matrix', $r);
    }

    public function test_the_prune_job_is_registered_with_the_scheduler_health_registry(): void
    {
        $this->assertArrayHasKey('n2n:prune-realtime', \App\Support\SchedulerHealth::tasks());
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'n2n:prune-realtime')));
    }

    public function test_the_admin_page_saves_the_driver_and_shows_health_and_a_member_cannot_open_it(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = $this->user(); $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)
            ->set('driver', 'reverb')->set('pollVisibleMs', 4000)->call('save')->assertHasNoErrors()->assertSee('Not measured');
        $this->assertSame('reverb', Realtime::driver());
        $this->assertSame(4000, \App\Support\N2N\N2NSettings::int('poll_visible_ms', 3000, 1000));
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)->set('pollVisibleMs', 10)->call('save')->assertHasErrors(['pollVisibleMs']);
        \Livewire\Livewire::actingAs($this->user())->test(\App\Livewire\Admin\N2N\Settings::class)->assertForbidden();
    }
}
