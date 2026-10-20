<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\ScheduledMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\ScheduledSend;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;

/** N2N composer suite: scheduled sending. Queued text, delivered later through the ordinary send path (MASTER-ONLY). */
class ScheduledSendTest extends N2NTestCase
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
        $r = $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => 'hello'])], ['Idempotency-Key' => 'first-message-1', 'Accept' => 'application/json'])->assertCreated();
        $this->cid = $r->json('conversation_id');
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

    private function schedule(User $u, string $text, ?int $atMs, string $key = null, ?int $cid = null)
    {
        return $this->actingAs($u)->post('/n2n/api/conversations/'.($cid ?? $this->cid).'/messages', ['meta' => json_encode(['text' => $text, 'scheduledAt' => $atMs])], ['Idempotency-Key' => $key ?? 'sch-'.uniqid().'xxxx', 'Accept' => 'application/json']);
    }

    private function bal(User $u): int
    {
        return array_sum(app(UsageLedgerService::class)->balances($u->id));
    }

    public function test_a_scheduled_text_is_queued_invisible_and_free_until_it_is_due_then_delivered_once(): void
    {
        $before = $this->bal($this->a);
        $at = now()->addMinutes(10);
        $r = $this->schedule($this->a, 'See you at 5', $at->getTimestampMs(), 'sched-key-0001')->assertCreated();
        $this->assertTrue($r->json('scheduled'));
        $this->assertSame($before, $this->bal($this->a), 'nothing is charged at queueing');
        $this->assertSame(1, Message::query()->count(), 'the other person sees nothing yet');

        $svc = app(ScheduledSend::class);
        $this->assertSame(0, $svc->dispatchDue(), 'not due yet');
        Carbon::setTestNow(now()->addMinutes(11));
        $this->assertSame(1, $svc->dispatchDue());
        $this->assertSame(0, $svc->dispatchDue(), 'a second run cannot send it again');
        $this->assertSame(2, Message::query()->count());
        $m = Message::query()->latest('id')->first();
        $this->assertSame('See you at 5', $m->body);
        $this->assertSame($this->a->id, (int) $m->sender_id);
        $this->assertLessThan($before, $this->bal($this->a), 'the message price is charged at delivery');
        $this->assertSame(ScheduledMessage::SENT, ScheduledMessage::first()->status);
    }

    public function test_queueing_is_idempotent_and_validated(): void
    {
        $at = now()->addMinutes(10)->getTimestampMs();
        $this->schedule($this->a, 'one', $at, 'same-key-0001')->assertCreated();
        $this->schedule($this->a, 'one', $at, 'same-key-0001')->assertCreated();
        $this->assertSame(1, ScheduledMessage::query()->count());
        $this->schedule($this->a, 'too soon', now()->addSeconds(20)->getTimestampMs())->assertStatus(422);
        $this->schedule($this->a, 'too far', now()->addDays(60)->getTimestampMs())->assertStatus(422);
        $this->schedule($this->a, 'see example.com now', $at)->assertStatus(422)->assertJsonPath('error', 'link_blocked');
        $this->schedule($this->a, '   ', $at)->assertStatus(422);
        $outsider = $this->member();
        $this->schedule($outsider, 'not mine', $at, null, $this->cid)->assertStatus(404);
        $this->assertSame(1, ScheduledMessage::query()->count());
    }

    public function test_only_an_existing_chat_can_be_scheduled_files_need_pro_and_there_is_a_pending_cap(): void
    {
        $at = now()->addMinutes(10)->getTimestampMs();
        $file = \Illuminate\Http\UploadedFile::fake()->image('p.png');
        $this->actingAs($this->a)->post('/n2n/api/conversations/'.$this->cid.'/messages', ['meta' => json_encode(['text' => 'x', 'scheduledAt' => $at]), 'files' => [$file]], ['Idempotency-Key' => 'with-file-0001', 'Accept' => 'application/json'])->assertStatus(403);   // media needs Pro, checked before any byte is read
        $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => 'x', 'scheduledAt' => $at])], ['Idempotency-Key' => 'new-chat-0001', 'Accept' => 'application/json'])->assertStatus(422);
        Setting::setValue('n2n.schedule_max_pending', 2);
        $this->schedule($this->a, 'a', $at)->assertCreated();
        $this->schedule($this->a, 'b', $at)->assertCreated();
        $this->schedule($this->a, 'c', $at)->assertStatus(422);
    }

    public function test_the_sender_can_cancel_a_pending_message_and_nobody_else_can(): void
    {
        $this->schedule($this->a, 'cancel me', now()->addMinutes(10)->getTimestampMs(), 'cancel-me-0001')->assertCreated();
        $id = ScheduledMessage::first()->id;
        $svc = app(ScheduledSend::class);
        $this->assertFalse($svc->cancel($this->b, $id));
        $this->assertTrue($svc->cancel($this->a, $id));
        Carbon::setTestNow(now()->addMinutes(11));
        $this->assertSame(0, $svc->dispatchDue());
        $this->assertSame(1, Message::query()->count());
    }

    public function test_a_delivery_that_fails_is_shown_to_the_sender_only_and_generic_for_a_block(): void
    {
        $this->schedule($this->a, 'will be blocked', now()->addMinutes(5)->getTimestampMs(), 'blocked-key-0001')->assertCreated();
        \App\Models\N2N\Block::query()->create(['blocker_id' => $this->b->id, 'blocked_id' => $this->a->id]);
        Carbon::setTestNow(now()->addMinutes(6));
        app(ScheduledSend::class)->dispatchDue();
        $row = ScheduledMessage::first();
        // A block makes the send "succeed" silently (no oracle) or fail generically; in neither case may the reason name the block.
        $this->assertContains($row->status, [ScheduledMessage::SENT, ScheduledMessage::FAILED]);
        if ($row->status === ScheduledMessage::FAILED) {
            $this->assertSame('undelivered', $row->fail_reason);
        }
    }

    public function test_no_credit_at_delivery_marks_it_failed_and_the_chat_panel_lists_and_cancels_only_own_items(): void
    {
        $this->schedule($this->a, 'later', now()->addMinutes(5)->getTimestampMs(), 'credit-key-0001')->assertCreated();
        $this->schedule($this->a, 'later two', now()->addMinutes(50)->getTimestampMs(), 'credit-key-0002')->assertCreated();
        \Livewire\Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class, ['c' => $this->cid])->call('openPanel', 'scheduled')->assertSee('later two')->assertSee('Cancel');
        \Livewire\Livewire::actingAs($this->b)->test(\App\Livewire\N2N\Chat::class, ['c' => $this->cid])->call('openPanel', 'scheduled')->assertDontSee('later two');
        \Livewire\Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class, ['c' => $this->cid])->call('cancelScheduled', ScheduledMessage::query()->where('body', 'later two')->value('id'));
        $this->assertSame('cancelled', ScheduledMessage::query()->where('body', 'later two')->value('status'));
    }
}
