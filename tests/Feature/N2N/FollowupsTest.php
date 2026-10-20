<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Followup;
use App\Models\N2N\RealtimeEvent;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\Followups;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Planner;
use App\Services\N2N\N2NErasure;
use App\Services\Wallet\WalletService;
use App\Support\UserDataExporter;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/** Planner follow-ups (B13 Phase C, MASTER-ONLY): private, capped, exactly-once reminders, erased with the member. */
class FollowupsTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private int $cid;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
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

    private function svc(): Followups
    {
        return app(Followups::class);
    }

    public function test_create_is_private_and_about_the_other_person(): void
    {
        $f = $this->svc()->create($this->a, '  Call   <b>John</b> ', now()->addHour(), $this->cid);
        $this->assertSame('Call John', $f->title);
        $this->assertSame($this->b->id, $f->about_user_id);
        $this->assertCount(1, $this->svc()->list($this->a));
        $this->assertCount(0, $this->svc()->list($this->b));          // the person it is about never sees it
        $this->expectException(MessagingException::class);
        $this->svc()->delete($this->b, $f->id);
    }

    public function test_cannot_attach_to_a_chat_you_are_not_in(): void
    {
        $c = $this->member();
        $this->expectException(MessagingException::class);
        $this->svc()->create($c, 'Spy', now()->addHour(), $this->cid);
    }

    public function test_validation_and_cap(): void
    {
        foreach ([['', null], [str_repeat('x', 121), null], ['ok', now()->subMinute()], ['ok', now()->addYears(2)]] as [$t, $d]) {
            try {
                $this->svc()->create($this->a, $t, $d);
                $this->fail('should have been refused');
            } catch (MessagingException) {
                $this->addToAssertionCount(1);
            }
        }
        for ($i = 0; $i < Followups::MAX_OPEN; $i++) {
            Followup::query()->create(['user_id' => $this->a->id, 'title' => 't'.$i]);
        }
        $this->expectException(MessagingException::class);
        $this->svc()->create($this->a, 'one too many');
    }

    public function test_reminder_fires_exactly_once_and_not_when_done(): void
    {
        $due = $this->svc()->create($this->a, 'Due', now()->addMinutes(5));
        $done = $this->svc()->create($this->a, 'Done', now()->addMinutes(5));
        $this->svc()->toggleDone($this->a, $done->id);
        Carbon::setTestNow(now()->addMinutes(6));
        $this->assertSame(1, app(Planner::class)->remindDue());
        $this->assertSame(0, app(Planner::class)->remindDue());       // second sweep: nothing
        $this->assertNotNull($due->fresh()->reminded_at);
        $this->assertNull($done->fresh()->reminded_at);
        $this->assertSame(1, RealtimeEvent::query()->where('type', 'followup.due')->where('user_id', $this->a->id)->count());
    }

    public function test_snooze_rearms_and_toggle_star(): void
    {
        $f = $this->svc()->create($this->a, 'Later', now()->addMinutes(5));
        Carbon::setTestNow(now()->addMinutes(6));
        app(Planner::class)->remindDue();
        $this->svc()->snooze($this->a, $f->id, now()->addHour());
        $this->assertNull($f->fresh()->reminded_at);
        $this->assertTrue($this->svc()->toggleStar($this->a, $f->id));
    }

    public function test_erasure_and_export(): void
    {
        $this->svc()->create($this->a, 'Mine', now()->addHour(), $this->cid);
        $this->svc()->create($this->b, 'About A', now()->addHour(), $this->cid);
        $this->assertSame('Mine', UserDataExporter::for($this->a->fresh())['n2n']['followups'][0]['title']);
        N2NErasure::erase($this->a);
        $this->assertSame(0, Followup::query()->where('user_id', $this->a->id)->count());
        $this->assertNull(Followup::query()->where('user_id', $this->b->id)->first()->about_user_id);   // the link to the erased member is cut
    }

    public function test_planner_page_renders_and_adds(): void
    {
        $this->actingAs($this->a)->get('/n2n/planner')->assertOk()->assertSee('Upcoming events');
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Planner::class)
            ->set('title', 'Ring Ada')->call('add', now()->addHour()->toIso8601String())->assertSet('error', null)
            ->call('setTab', 'followups')->assertSee('Ring Ada');
        $this->assertSame(1, Followup::query()->where('user_id', $this->a->id)->count());
        $this->actingAs($this->b)->get('/n2n/planner')->assertOk()->assertDontSee('Ring Ada');
    }

    public function test_chat_panel_adds_a_followup_for_this_chat(): void
    {
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class, [])->set('c', $this->cid)
            ->call('addFollowup', 'Ask about invoice', now()->addHour()->toIso8601String())->assertSet('error', null);
        $this->assertSame($this->cid, (int) Followup::query()->where('user_id', $this->a->id)->value('conversation_id'));
    }
}
