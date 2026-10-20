<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Block;
use App\Models\N2N\LiveLocation;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\LiveLocations;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/** Live location (MASTER-ONLY): time-boxed, latest point only, the other member only, wiped when it ends. */
class LiveLocationTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private int $cid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->member();
        $this->b = $this->member();
        Contact::create(['user_id' => $this->b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $this->a->id])->saveQuietly();
        $this->cid = $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => 'hello'])], ['Idempotency-Key' => 'first-message-1', 'Accept' => 'application/json'])->assertCreated()->json('conversation_id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);

        return $u->fresh();
    }

    private function start(User $u, array $live, string $key = 'live-start-0001')
    {
        return $this->actingAs($u)->post('/n2n/api/conversations/'.$this->cid.'/messages', ['meta' => json_encode(['live' => $live])], ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
    }

    private function begin(int $minutes = 15): int
    {
        return $this->start($this->a, ['minutes' => $minutes, 'lat' => 6.4541, 'lng' => 3.3947, 'acc' => 12])->assertCreated()->json('id');
    }

    public function test_starting_creates_a_label_only_message_and_the_other_member_can_read_the_point(): void
    {
        $id = $this->begin();
        $m = Message::query()->findOrFail($id);
        $this->assertSame('live', $m->type);
        $this->assertSame('Live location', $m->body);
        $this->assertStringNotContainsString('6.45', (string) $m->body);

        $r = $this->actingAs($this->b)->getJson('/n2n/api/live/'.$id)->assertOk()->assertHeader('Cache-Control')->json();
        $this->assertTrue($r['active']);
        $this->assertFalse($r['mine']);
        $this->assertEqualsWithDelta(6.4541, $r['lat'], 0.00001);
        $this->assertEqualsWithDelta(3.3947, $r['lng'], 0.00001);
    }

    public function test_only_the_two_members_can_read_and_only_the_sharer_can_update_or_stop(): void
    {
        $id = $this->begin();
        $stranger = $this->member();
        $this->actingAs($stranger)->getJson('/n2n/api/live/'.$id)->assertNotFound();
        $this->actingAs($stranger)->postJson('/n2n/api/live/'.$id.'/update', ['lat' => 1, 'lng' => 1])->assertNotFound();
        $this->actingAs($this->b)->postJson('/n2n/api/live/'.$id.'/update', ['lat' => 1, 'lng' => 1])->assertNotFound();
        $this->actingAs($this->b)->postJson('/n2n/api/live/'.$id.'/stop')->assertNotFound();
        $this->assertEqualsWithDelta(6.4541, LiveLocation::query()->where('message_id', $id)->value('lat'), 0.00001, 'the other member cannot move the pin');
    }

    public function test_the_sharer_updates_only_the_latest_point_and_it_is_rate_limited(): void
    {
        $id = $this->begin();
        RateLimiter::clear('n2n-live:'.LiveLocation::query()->where('message_id', $id)->value('id'));
        $this->actingAs($this->a)->postJson('/n2n/api/live/'.$id.'/update', ['lat' => 6.5, 'lng' => 3.4, 'acc' => 8])->assertOk()->assertJson(['active' => true]);
        $this->actingAs($this->a)->postJson('/n2n/api/live/'.$id.'/update', ['lat' => 7.7, 'lng' => 4.4])->assertOk();   // inside five seconds: ignored
        $row = LiveLocation::query()->where('message_id', $id)->first();
        $this->assertEqualsWithDelta(6.5, (float) $row->lat, 0.00001);
        $this->assertSame(1, LiveLocation::query()->count(), 'no trail: one row, latest point only');
    }

    public function test_stopping_wipes_the_position_for_everyone(): void
    {
        $id = $this->begin();
        $this->actingAs($this->a)->postJson('/n2n/api/live/'.$id.'/stop')->assertOk()->assertJson(['active' => false]);
        $row = LiveLocation::query()->where('message_id', $id)->first();
        $this->assertNull($row->getRawOriginal('lat'));
        $this->assertNull($row->getRawOriginal('lng'));
        $this->assertSame(['active' => false, 'mine' => false], $this->actingAs($this->b)->getJson('/n2n/api/live/'.$id)->assertOk()->json());
        $this->actingAs($this->a)->postJson('/n2n/api/live/'.$id.'/update', ['lat' => 1, 'lng' => 1])->assertOk()->assertJson(['active' => false]);
    }

    public function test_it_expires_on_its_own_and_the_purge_job_wipes_anything_left(): void
    {
        $id = $this->begin(15);
        Carbon::setTestNow(now()->addMinutes(16));
        $this->assertSame(1, app(LiveLocations::class)->purgeEnded());
        $this->assertNull(LiveLocation::query()->where('message_id', $id)->first()->getRawOriginal('lat'));
        $this->assertFalse($this->actingAs($this->b)->getJson('/n2n/api/live/'.$id)->json('active'));
        $this->assertSame(0, app(LiveLocations::class)->purgeEnded());
    }

    public function test_a_block_either_way_ends_the_read_at_once(): void
    {
        $id = $this->begin();
        Block::query()->create(['blocker_id' => $this->b->id, 'blocked_id' => $this->a->id, 'created_at' => now()]);
        $this->assertFalse($this->actingAs($this->b)->getJson('/n2n/api/live/'.$id)->json('active'));
        $this->assertFalse($this->actingAs($this->a)->getJson('/n2n/api/live/'.$id)->json('active'));
    }

    public function test_only_the_offered_durations_are_accepted_and_an_admin_cap_shortens_them(): void
    {
        $this->start($this->a, ['minutes' => 600, 'lat' => 1, 'lng' => 1], 'live-bad-dur-01')->assertStatus(422);
        $this->start($this->a, ['minutes' => 15, 'lat' => 99, 'lng' => 1], 'live-bad-lat-01')->assertStatus(422);
        $this->start($this->a, ['minutes' => 15, 'lat' => 'x', 'lng' => 1], 'live-bad-lat-02')->assertStatus(422);
        Setting::setValue('n2n.live_location_max_minutes', 60);
        $this->assertSame([15, 60], LiveLocations::allowed());
        $this->start($this->a, ['minutes' => 480, 'lat' => 1, 'lng' => 1], 'live-capped-001')->assertStatus(422);
        $this->start($this->a, ['minutes' => 60, 'lat' => 1, 'lng' => 1], 'live-capped-002')->assertCreated();
    }

    public function test_a_live_location_cannot_be_scheduled(): void
    {
        $this->actingAs($this->a)->post('/n2n/api/conversations/'.$this->cid.'/messages', ['meta' => json_encode(['live' => ['minutes' => 15, 'lat' => 1, 'lng' => 1], 'scheduledAt' => now()->addMinutes(10)->getTimestampMs()])], ['Idempotency-Key' => 'live-sched-001', 'Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, LiveLocation::query()->count());
    }

    public function test_erasure_wipes_the_members_positions(): void
    {
        $id = $this->begin();
        \App\Services\N2N\N2NErasure::erase($this->a);
        $this->assertNull(LiveLocation::query()->where('message_id', $id)->first()->getRawOriginal('lat'));
    }

    public function test_the_position_is_hidden_from_serialisation(): void
    {
        $this->begin();
        $this->assertArrayNotHasKey('lat', LiveLocation::query()->first()->toArray());
    }
}
