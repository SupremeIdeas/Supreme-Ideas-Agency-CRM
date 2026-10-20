<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Block;
use App\Models\N2N\Event;
use App\Models\N2N\EventRsvp;
use App\Models\N2N\Message;
use App\Models\N2N\RealtimeEvent;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Planner;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;

/** Naara Planner (B13, MASTER-ONLY): events in a 1:1 chat, timezones across DST, RSVP, edit/cancel, exactly-once reminders, .ics export, erasure. */
class PlannerTest extends N2NTestCase
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

    private function plan(User $u, array $ev, string $key = 'plan-event-0001')
    {
        return $this->actingAs($u)->post('/n2n/api/conversations/'.$this->cid.'/messages', ['meta' => json_encode(['event' => $ev])], ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
    }

    private function ev(array $over = []): array
    {
        return array_merge(['title' => 'Call about the contract', 'date' => '2026-10-24', 'time' => '09:00', 'tz' => 'Europe/London', 'duration' => 60, 'reminder' => 15, 'description' => 'Bring the figures, please'], $over);
    }

    private function make(array $over = [], string $key = 'plan-event-0001'): Event
    {
        $id = $this->plan($this->a, $this->ev($over), $key)->assertCreated()->json('id');

        return Event::query()->where('message_id', $id)->firstOrFail();
    }

    private function planner(): Planner
    {
        return app(Planner::class);
    }

    public function test_an_event_is_a_card_message_with_one_row_per_member(): void
    {
        $e = $this->make();
        $m = Message::query()->findOrFail($e->message_id);
        $this->assertSame('event', $m->type);
        $this->assertSame('Event: Call about the contract', $m->body);
        $this->assertSame(2, EventRsvp::query()->where('event_id', $e->id)->count());
        $this->assertSame('going', EventRsvp::query()->where('event_id', $e->id)->where('user_id', $this->a->id)->value('status'));
        $this->assertSame('no_response', EventRsvp::query()->where('event_id', $e->id)->where('user_id', $this->b->id)->value('status'));
    }

    public function test_the_same_wall_clock_time_is_a_different_utc_instant_across_the_uk_clock_change_and_lagos_never_moves(): void
    {
        // UK clocks go back on Sunday 2026-10-25 at 02:00 BST. 09:00 in London is 08:00 UTC the day before and 09:00 UTC after.
        $before = $this->make(['date' => '2026-10-24', 'time' => '09:00', 'tz' => 'Europe/London'], 'plan-dst-before-1');
        $after = $this->make(['date' => '2026-10-25', 'time' => '09:00', 'tz' => 'Europe/London'], 'plan-dst-after-01');
        $this->assertSame('2026-10-24 08:00:00', $before->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 09:00:00', $after->starts_at->format('Y-m-d H:i:s'));
        // a Lagos viewer (UTC+1, no DST) sees 09:00 then 10:00; a London viewer sees 09:00 both days
        $this->assertSame('09:00', $before->starts_at->copy()->setTimezone('Africa/Lagos')->format('H:i'));
        $this->assertSame('10:00', $after->starts_at->copy()->setTimezone('Africa/Lagos')->format('H:i'));
        $this->assertSame('09:00', $before->starts_at->copy()->setTimezone('Europe/London')->format('H:i'));
        $this->assertSame('09:00', $after->starts_at->copy()->setTimezone('Europe/London')->format('H:i'));
        // an event created from Lagos at 09:00 on the same day is 08:00 UTC both days
        $lagos = $this->make(['date' => '2026-10-25', 'time' => '09:00', 'tz' => 'Africa/Lagos'], 'plan-lagos-00001');
        $this->assertSame('08:00', $lagos->starts_at->format('H:i'));
        $this->assertSame('Africa/Lagos', $lagos->timezone);
    }

    public function test_past_far_future_bad_input_and_links_are_refused(): void
    {
        $this->plan($this->a, $this->ev(['date' => '2026-09-30']), 'plan-past-000001')->assertStatus(422);
        $this->plan($this->a, $this->ev(['date' => '2026-10-01', 'time' => '10:02']), 'plan-too-soon-001')->assertStatus(422);   // inside five minutes
        $this->plan($this->a, $this->ev(['date' => '2028-01-01']), 'plan-far-0000001')->assertStatus(422);
        $this->plan($this->a, $this->ev(['title' => '']), 'plan-no-title-001')->assertStatus(422);
        $this->plan($this->a, $this->ev(['date' => 'banana']), 'plan-bad-date-001')->assertStatus(422);
        $this->plan($this->a, $this->ev(['description' => 'see https://evil.example/x']), 'plan-link-000001')->assertStatus(422);
        $this->assertSame(0, Event::query()->count());
    }

    public function test_only_members_can_answer_and_the_answer_is_one_of_three(): void
    {
        $e = $this->make();
        $r = $this->planner()->rsvp($this->b, $e->id, 'maybe');
        $this->assertSame('maybe', $r->status);
        $this->assertSame('going', $this->planner()->rsvp($this->b, $e->id, 'going')->status);
        $this->expectException(MessagingException::class);
        $this->planner()->rsvp($this->b, $e->id, 'sure');
    }

    public function test_a_stranger_cannot_see_answer_or_export_an_event(): void
    {
        $e = $this->make();
        $x = $this->member();
        $this->expectException(MessagingException::class);
        try {
            $this->planner()->rsvp($x, $e->id, 'going');
        } finally {
            $this->actingAs($x)->get('/n2n/events/'.$e->id.'.ics')->assertNotFound();
        }
    }

    public function test_only_the_creator_edits_and_a_new_time_resets_the_other_answer_and_bumps_the_sequence(): void
    {
        $e = $this->make();
        $this->planner()->rsvp($this->b, $e->id, 'going');
        try {
            $this->planner()->edit($this->b, $e->id, $this->ev(['title' => 'Hijack']));
            $this->fail('A non-creator must not edit.');
        } catch (MessagingException $ex) {
            $this->assertSame('not_participant', $ex->reason);
        }
        // same time, new title: the answer stands
        $this->planner()->edit($this->a, $e->id, $this->ev(['title' => 'Contract call (renamed)']));
        $this->assertSame('going', EventRsvp::query()->where('event_id', $e->id)->where('user_id', $this->b->id)->value('status'));
        // new time: the answer resets
        $this->planner()->edit($this->a, $e->id, $this->ev(['title' => 'Contract call (renamed)', 'time' => '11:00']));
        $e->refresh();
        $this->assertSame(2, $e->sequence);
        $this->assertSame('no_response', EventRsvp::query()->where('event_id', $e->id)->where('user_id', $this->b->id)->value('status'));
        $this->assertSame('Event: Contract call (renamed)', Message::query()->find($e->message_id)->body);
    }

    public function test_cancelling_stops_answers_and_reminders_and_the_calendar_file_says_cancelled(): void
    {
        $e = $this->make();
        $this->planner()->cancel($this->a, $e->id);
        Carbon::setTestNow($e->starts_at->copy()->subMinutes(10));
        $this->assertSame(0, $this->planner()->remindDue());
        $this->actingAs($this->b)->get('/n2n/events/'.$e->id.'.ics')->assertOk()->assertSee('METHOD:CANCEL', false)->assertSee('STATUS:CANCELLED', false);
        $this->expectException(MessagingException::class);
        $this->planner()->rsvp($this->b, $e->id, 'going');
    }

    public function test_a_reminder_is_sent_exactly_once_per_person_inside_its_window_and_never_to_someone_who_declined(): void
    {
        $e = $this->make(['reminder' => 15]);
        $this->assertSame(0, $this->planner()->remindDue(), 'too early');
        Carbon::setTestNow($e->starts_at->copy()->subMinutes(16));
        $this->assertSame(0, $this->planner()->remindDue(), 'still outside the 15 minute window');
        Carbon::setTestNow($e->starts_at->copy()->subMinutes(14));
        $this->assertSame(2, $this->planner()->remindDue(), 'both members are reminded');
        $this->assertSame(0, $this->planner()->remindDue(), 're-running sends nothing');
        $this->assertSame(0, $this->planner()->remindDue());
        $this->assertSame(1, RealtimeEvent::query()->where('user_id', $this->a->id)->where('type', 'event.reminder')->count());
        $this->assertSame(1, RealtimeEvent::query()->where('user_id', $this->b->id)->where('type', 'event.reminder')->count());
    }

    public function test_a_declined_member_is_not_reminded_and_a_blocked_pair_is_silent(): void
    {
        $e = $this->make();
        $this->planner()->rsvp($this->b, $e->id, 'declined');
        Carbon::setTestNow($e->starts_at->copy()->subMinutes(10));
        $this->assertSame(1, $this->planner()->remindDue(), 'only the creator');
        $this->assertSame(0, RealtimeEvent::query()->where('user_id', $this->b->id)->where('type', 'event.reminder')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $e2 = $this->make(['date' => '2026-10-30'], 'plan-blocked-0001');
        Block::query()->create(['blocker_id' => $this->b->id, 'blocked_id' => $this->a->id, 'created_at' => now()]);
        Carbon::setTestNow($e2->starts_at->copy()->subMinutes(10));
        $this->planner()->remindDue();
        $this->assertSame(0, RealtimeEvent::query()->where('user_id', $this->a->id)->where('type', 'event.reminder')->where('payload->event_id', $e2->id)->count());
    }

    public function test_moving_an_event_allows_a_fresh_reminder_and_one_that_began_long_ago_is_never_sent(): void
    {
        $e = $this->make();
        Carbon::setTestNow($e->starts_at->copy()->subMinutes(10));
        $this->assertSame(2, $this->planner()->remindDue());
        Carbon::setTestNow($e->starts_at->copy()->subDays(1));
        $this->planner()->edit($this->a, $e->id, $this->ev(['date' => '2026-10-26', 'time' => '09:00']));
        Carbon::setTestNow($e->fresh()->starts_at->copy()->subMinutes(10));
        $this->assertSame(2, $this->planner()->remindDue(), 'a moved event is reminded again');
        // an event whose start is more than five minutes gone is never reminded
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $late = $this->make(['date' => '2026-10-20'], 'plan-late-0000001');
        Carbon::setTestNow($late->starts_at->copy()->addMinutes(30));
        $this->assertSame(0, $this->planner()->remindDue());
    }

    public function test_the_calendar_file_is_valid_escaped_and_folded(): void
    {
        $e = $this->make(['title' => 'Plan; budget, review', 'description' => str_repeat('Bring the long list of items to discuss. ', 6)."\nSecond line", 'reminder' => 30]);
        $ics = $this->actingAs($this->b)->get('/n2n/events/'.$e->id.'.ics')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20261024T080000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20261024T090000Z\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Plan\; budget\, review', $ics);
        $this->assertStringContainsString("TRIGGER:-PT30M\r\n", $ics);
        $this->assertStringContainsString('UID:naara-event-'.$e->id.'@', $ics);
        $this->assertStringContainsString('SEQUENCE:0', $ics);
        $this->assertStringContainsString('\nSecond line', $ics, 'newlines are escaped');
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'no line may exceed 75 octets: '.$line);
        }
        $this->assertStringNotContainsString("\n\n", $ics);
    }

    public function test_the_google_calendar_link_carries_the_same_instants(): void
    {
        $e = $this->make();
        $u = Planner::googleUrl($e);
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?action=TEMPLATE', $u);
        $this->assertStringContainsString('dates=20261024T080000Z%2F20261024T090000Z', $u);
    }

    public function test_the_upcoming_cap_and_the_agenda(): void
    {
        $e = $this->make();
        $this->make(['date' => '2026-10-28', 'title' => 'Later'], 'plan-agenda-0002');
        $agenda = $this->planner()->agenda($this->b, $this->cid);
        $this->assertSame(['Call about the contract', 'Later'], $agenda->map(fn ($r) => $r['event']->title)->all());
        $this->assertSame('no_response', $agenda[0]['mine']);
        $this->assertSame('going', $agenda[0]['theirs']);
        $this->planner()->cancel($this->a, $e->id);
        $this->assertCount(1, $this->planner()->agenda($this->b, $this->cid));
    }

    public function test_erasure_removes_events_the_member_created_and_their_answers_elsewhere(): void
    {
        $mine = $this->make();
        \App\Services\N2N\N2NErasure::erase($this->a);
        $this->assertNull(Event::query()->find($mine->id));
        $this->assertSame(0, EventRsvp::query()->where('user_id', $this->a->id)->count());
    }

    public function test_the_export_lists_the_members_events(): void
    {
        $e = $this->make();
        $data = \App\Support\UserDataExporter::for($this->b->fresh());
        $this->assertSame('Call about the contract', $data['n2n']['events'][0]['title']);
        $this->assertFalse($data['n2n']['events'][0]['planned_by_you']);
        $this->assertSame('no_response', $data['n2n']['events'][0]['your_answer']);
    }
}
