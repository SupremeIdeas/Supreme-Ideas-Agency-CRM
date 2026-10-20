<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Message;
use App\Models\N2N\Poll;
use App\Models\N2N\PollVote;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Polls;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

/** N2N polls (MASTER-ONLY): one message, options and votes in their own rows, live results for the two members only. */
class PollsTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private User $c;

    private int $cid;

    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->b, $this->c] = [$this->member(), $this->member(), $this->member()];
        Contact::create(['user_id' => $this->b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $this->a->id])->saveQuietly();
        $r = $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => '', 'poll' => ['question' => 'Where do we eat?', 'options' => ['Pizza', 'Suya', 'Rice', 'pizza']]])], ['Idempotency-Key' => 'poll-first-01', 'Accept' => 'application/json'])->assertCreated();
        $this->cid = (int) $r->json('conversation_id');
        $this->poll = Poll::query()->firstOrFail();
    }

    private function member(): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);

        return $u->fresh();
    }

    private function opt(int $i): int
    {
        return (int) $this->poll->fresh()->id * 0 + \App\Models\N2N\PollOption::query()->where('poll_id', $this->poll->id)->orderBy('position')->skip($i)->value('id');
    }

    public function test_sending_a_poll_makes_one_poll_message_with_cleaned_unique_options(): void
    {
        $m = Message::query()->where('type', 'poll')->firstOrFail();
        $this->assertSame('Where do we eat?', $m->body);
        $this->assertSame(['Pizza', 'Suya', 'Rice'], \App\Models\N2N\PollOption::query()->where('poll_id', $this->poll->id)->orderBy('position')->pluck('text')->all(), 'the duplicate option is dropped');
        $this->assertFalse($this->poll->multiple);
    }

    public function test_bad_polls_are_refused(): void
    {
        foreach ([['question' => '', 'options' => ['a', 'b']], ['question' => 'q', 'options' => ['only one']], ['question' => 'q', 'options' => ['https://evil.example/x', 'b']], ['question' => str_repeat('x', 161), 'options' => ['a', 'b']]] as $bad) {
            try {
                Polls::clean($bad);
                $this->fail('accepted a bad poll');
            } catch (MessagingException) {
                $this->assertTrue(true);
            }
        }
        $c = Polls::clean(['question' => "<b>Q</b>\n  ?", 'options' => array_merge(range(1, 9), []), 'multiple' => true]);
        $this->assertSame('Q ?', $c['question']);
        $this->assertCount(6, $c['options']);
        $this->assertTrue($c['multiple']);
    }

    public function test_voting_moves_a_single_choice_vote_and_toggles_it_off(): void
    {
        $svc = app(Polls::class);
        $this->assertSame([$this->opt(0)], $svc->toggle($this->b, $this->poll->id, $this->opt(0)));
        $this->assertSame([$this->opt(1)], $svc->toggle($this->b, $this->poll->id, $this->opt(1)), 'a second pick moves the vote');
        $this->assertSame(1, PollVote::query()->where('user_id', $this->b->id)->count());
        $this->assertSame([], $svc->toggle($this->b, $this->poll->id, $this->opt(1)), 'the same option again retracts it');
        $svc->toggle($this->a, $this->poll->id, $this->opt(0));
        $r = $svc->forMessages($this->a, [$this->poll->message_id])[$this->poll->message_id];
        $this->assertSame(1, $r['total']);
        $this->assertSame(100, $r['options'][0]['pct']);
        $this->assertTrue($r['options'][0]['mine']);
    }

    public function test_multiple_choice_polls_allow_several_votes(): void
    {
        $this->poll->forceFill(['multiple' => true])->save();
        $svc = app(Polls::class);
        $svc->toggle($this->b, $this->poll->id, $this->opt(0));
        $this->assertCount(2, $svc->toggle($this->b, $this->poll->id, $this->opt(2)));
    }

    public function test_only_the_two_members_can_vote_and_only_the_asker_can_close(): void
    {
        $svc = app(Polls::class);
        try {
            $svc->toggle($this->c, $this->poll->id, $this->opt(0));
            $this->fail('an outsider voted');
        } catch (MessagingException $e) {
            $this->assertSame('not_found', $e->reason);
        }
        try {
            $svc->close($this->b, $this->poll->id);
            $this->fail('the other member closed it');
        } catch (MessagingException) {
            $this->assertNull($this->poll->fresh()->closed_at);
        }
        $svc->close($this->a, $this->poll->id);
        $this->assertNotNull($this->poll->fresh()->closed_at);
        $this->expectException(MessagingException::class);
        $svc->toggle($this->b, $this->poll->id, $this->opt(0));
    }

    public function test_the_page_shows_the_poll_with_live_counts_and_votes_work_through_the_component(): void
    {
        Livewire::actingAs($this->b)->test(\App\Livewire\N2N\Chat::class)->set('c', $this->cid)->call('vote', $this->poll->id, $this->opt(1))->assertSee('Where do we eat?')->assertSee('100%');
        $this->actingAs($this->a)->get('/n2n/chat?c='.$this->cid)->assertOk()->assertSee('Suya')->assertSee('1 person has voted');
    }

    public function test_erasure_removes_the_members_votes_but_the_poll_stays(): void
    {
        app(Polls::class)->toggle($this->b, $this->poll->id, $this->opt(0));
        \App\Services\N2N\N2NErasure::erase($this->b->fresh());
        $this->assertSame(0, PollVote::query()->where('user_id', $this->b->id)->count());
        $this->assertSame(1, Poll::query()->count());
    }
}
