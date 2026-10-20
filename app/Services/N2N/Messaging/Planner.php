<?php

namespace App\Services\N2N\Messaging;

use App\Jobs\SendWebPushJob;
use App\Models\N2N\Block;
use App\Models\N2N\Conversation;
use App\Models\N2N\Event;
use App\Models\N2N\EventRsvp;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Realtime\Realtime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Naara Planner (B13, N2N, MASTER-ONLY): plan an event in a 1:1 chat, RSVP, remind both people exactly once, export to a calendar.
 *
 * Rules that keep it safe and honest:
 *  - an event is a chat message (type `event`): creating one costs a normal message, and blocks, disappearing, hides and moderation already apply;
 *  - it cannot be created in the past; times are stored in UTC with the creator's IANA timezone, and every viewer sees it in their own timezone;
 *  - only the two members can see, answer or export it; only the creator can edit or cancel; changing the time resets the other person's answer;
 *  - the reminder is claimed with a compare-and-set on `reminded_at` BEFORE anything is sent, so a re-run, a slow worker or two workers never send it twice;
 *  - an event, title and description go through the same link filter as any message; the reminder push is generic unless the admin allows previews;
 *  - erasure removes the events a person created and their answers to others'.
 */
class Planner
{
    public const MAX_UPCOMING = 50;

    public const REMINDERS = [0, 5, 15, 30, 60, 1440];

    public const DURATIONS = [0, 15, 30, 60, 90, 120, 180, 480];

    public function __construct(private readonly ConversationPrefs $prefs) {}

    /**
     * Validate and normalise the form. `date` (Y-m-d) and `time` (H:i) are the creator's wall-clock time in `tz`.
     *
     * @param  array{title?: mixed, description?: mixed, date?: mixed, time?: mixed, tz?: mixed, duration?: mixed, reminder?: mixed}  $raw
     * @return array{title: string, description: ?string, starts_at: Carbon, ends_at: ?Carbon, timezone: string, reminder: int}
     *
     * @throws MessagingException
     */
    public static function clean(array $raw): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($raw['title'] ?? ''))));
        $desc = trim(preg_replace('/[ \t]+/u', ' ', strip_tags((string) ($raw['description'] ?? ''))));
        if ($title === '' || mb_strlen($title) > 80) {
            throw new MessagingException('invalid', 'Give the event a title of up to 80 characters.');
        }
        if (mb_strlen($desc) > 500) {
            throw new MessagingException('invalid', 'The description can be up to 500 characters.');
        }
        foreach (array_filter([$title, $desc]) as $text) {
            if (($why = LinkFilter::problem($text)) !== null) {
                throw new MessagingException('link_blocked', $why);
            }
        }
        $tz = (string) ($raw['tz'] ?? '');
        $tz = in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : (string) config('app.timezone', 'UTC');
        try {
            $starts = Carbon::createFromFormat('Y-m-d H:i', trim((string) ($raw['date'] ?? '')).' '.trim((string) ($raw['time'] ?? '')), $tz);
        } catch (\Throwable) {
            $starts = false;
        }
        if (! $starts instanceof CarbonInterface) {
            throw new MessagingException('invalid', 'Pick a date and a time for the event.');
        }
        $starts = $starts->setTimezone('UTC');
        if ($starts->lt(now()->addMinutes(5))) {
            throw new MessagingException('invalid', 'An event cannot be in the past. Pick a time at least five minutes from now.');
        }
        if ($starts->gt(now()->addYear())) {
            throw new MessagingException('invalid', 'Events can be planned up to one year ahead.');
        }
        $dur = (int) ($raw['duration'] ?? 60);
        $dur = in_array($dur, self::DURATIONS, true) ? $dur : 60;
        $rem = (int) ($raw['reminder'] ?? 15);
        $rem = in_array($rem, self::REMINDERS, true) ? $rem : 15;

        return ['title' => $title, 'description' => $desc !== '' ? $desc : null, 'starts_at' => $starts, 'ends_at' => $dur > 0 ? $starts->copy()->addMinutes($dur) : null, 'timezone' => $tz, 'reminder' => $rem];
    }

    /** Called inside the send transaction, once the card message exists. */
    public function attach(Message $message, array $clean): Event
    {
        $event = Event::query()->create([
            'conversation_id' => $message->conversation_id, 'message_id' => $message->id, 'creator_id' => $message->sender_id, 'title' => $clean['title'], 'description' => $clean['description'],
            'starts_at' => $clean['starts_at'], 'ends_at' => $clean['ends_at'], 'timezone' => $clean['timezone'], 'reminder_minutes' => $clean['reminder'],
        ]);
        foreach (Participant::query()->where('conversation_id', $message->conversation_id)->pluck('user_id') as $uid) {
            EventRsvp::query()->create(['event_id' => $event->id, 'user_id' => $uid, 'status' => (int) $uid === (int) $message->sender_id ? EventRsvp::GOING : EventRsvp::PENDING, 'responded_at' => (int) $uid === (int) $message->sender_id ? now() : null]);
        }

        return $event;
    }

    /** How many upcoming events the member has created (a cap that stops a chat being flooded with plans). */
    public function assertRoom(User $creator): void
    {
        $n = Event::query()->where('creator_id', $creator->id)->whereNull('cancelled_at')->where('starts_at', '>', now())->count();
        if ($n >= self::MAX_UPCOMING) {
            throw new MessagingException('invalid', 'You have '.self::MAX_UPCOMING.' upcoming events. Cancel one or wait for it to pass.');
        }
    }

    /** The event, only if this member may see its card. @throws MessagingException */
    public function visible(User $user, int $eventId): Event
    {
        $e = Event::query()->find($eventId);
        if ($e === null || $e->message_id === null) {
            throw new MessagingException('not_found', 'That event is not available.');
        }
        $this->prefs->visibleMessage($user, (int) $e->message_id);   // member of the chat, card not deleted, hidden or expired

        return $e;
    }

    /** @throws MessagingException */
    public function rsvp(User $user, int $eventId, string $status): EventRsvp
    {
        if (! in_array($status, [EventRsvp::GOING, EventRsvp::MAYBE, EventRsvp::DECLINED], true)) {
            throw new MessagingException('invalid', 'Choose Going, Maybe or Can\'t go.');
        }
        $e = $this->visible($user, $eventId);
        if ($e->cancelled_at !== null) {
            throw new MessagingException('invalid', 'This event was cancelled.');
        }
        if ($e->endsAt()->isPast()) {
            throw new MessagingException('invalid', 'This event has already happened.');
        }
        EventRsvp::query()->where('event_id', $e->id)->where('user_id', $user->id)->update(['status' => $status, 'responded_at' => now()]);   // one atomic UPDATE: concurrent answers end consistent
        $this->tell($e, $user);

        return EventRsvp::query()->where('event_id', $e->id)->where('user_id', $user->id)->firstOrFail();
    }

    /**
     * Creator only. A new time resets the other person's answer (and their reminder) because they agreed to a different time.
     *
     * @throws MessagingException
     */
    public function edit(User $user, int $eventId, array $raw): Event
    {
        $e = $this->visible($user, $eventId);
        $this->assertCreator($user, $e);
        if ($e->cancelled_at !== null || $e->starts_at->isPast()) {
            throw new MessagingException('invalid', 'Only an upcoming event can be edited.');
        }
        $c = self::clean($raw);
        $moved = ! $c['starts_at']->equalTo($e->starts_at);
        DB::transaction(function () use ($e, $c, $moved, $user) {
            $e->forceFill(['title' => $c['title'], 'description' => $c['description'], 'starts_at' => $c['starts_at'], 'ends_at' => $c['ends_at'], 'timezone' => $c['timezone'], 'reminder_minutes' => $c['reminder'], 'sequence' => $e->sequence + 1])->save();
            Message::query()->whereKey($e->message_id)->update(['body' => 'Event: '.$c['title']]);
            if ($moved) {
                EventRsvp::query()->where('event_id', $e->id)->where('user_id', '!=', $user->id)->update(['status' => EventRsvp::PENDING, 'responded_at' => null, 'reminded_at' => null]);
                EventRsvp::query()->where('event_id', $e->id)->where('user_id', $user->id)->update(['reminded_at' => null]);
            }
        });
        $this->tell($e, $user);

        return $e->fresh();
    }

    /** @throws MessagingException */
    public function cancel(User $user, int $eventId): Event
    {
        $e = $this->visible($user, $eventId);
        $this->assertCreator($user, $e);
        if ($e->cancelled_at === null) {
            $e->forceFill(['cancelled_at' => now(), 'sequence' => $e->sequence + 1])->save();
            $this->tell($e, $user);
        }

        return $e;
    }

    /** Upcoming events for one member (optionally one chat), soonest first, with that member's answer and the other person's. */
    public function agenda(User $user, ?int $conversationId = null, int $limit = 50): \Illuminate\Support\Collection
    {
        $convIds = Participant::query()->where('user_id', $user->id)->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))->pluck('conversation_id');
        $events = Event::query()->whereIn('conversation_id', $convIds)->whereNull('cancelled_at')->where('starts_at', '>', now()->subHour())->orderBy('starts_at')->limit($limit * 2)->get();
        $visible = Message::query()->forViewer($user->id)->whereIn('id', $events->pluck('message_id'))->whereNull('deleted_for_all_at')->pluck('id')->flip();
        $events = $events->filter(fn ($e) => isset($visible[$e->message_id]) && $e->endsAt()->isFuture())->take($limit)->values();
        $rsvps = EventRsvp::query()->whereIn('event_id', $events->pluck('id'))->get()->groupBy('event_id');

        return $events->map(fn ($e) => ['event' => $e, 'mine' => optional(($rsvps[$e->id] ?? collect())->firstWhere('user_id', $user->id))->status ?? EventRsvp::PENDING,
            'theirs' => optional(($rsvps[$e->id] ?? collect())->firstWhere('user_id', '!=', $user->id))->status ?? EventRsvp::PENDING]);
    }

    /**
     * Send every due reminder exactly once. A reminder is due for a member who has not declined, when it is inside the reminder window and the event has
     * not already been under way for more than five minutes. @return int how many reminders were sent
     */
    public function remindDue(int $limit = 500): int
    {
        $sent = 0;
        $due = EventRsvp::query()->join('n2n_events', 'n2n_events.id', '=', 'n2n_event_rsvps.event_id')
            ->whereNull('n2n_event_rsvps.reminded_at')->where('n2n_event_rsvps.status', '!=', EventRsvp::DECLINED)->whereNull('n2n_events.cancelled_at')
            ->where('n2n_events.starts_at', '>', now()->subMinutes(5))
            ->where('n2n_events.starts_at', '<=', now()->addDay())
            ->select('n2n_event_rsvps.id as rsvp_id', 'n2n_event_rsvps.user_id', 'n2n_events.id as event_id', 'n2n_events.starts_at', 'n2n_events.reminder_minutes', 'n2n_events.conversation_id', 'n2n_events.title')
            ->orderBy('n2n_events.starts_at')->limit($limit)->get();
        foreach ($due as $row) {
            if (Carbon::parse($row->starts_at, 'UTC')->subMinutes((int) $row->reminder_minutes)->isFuture()) {
                continue;
            }
            // claim first, send second: a crash after the claim loses one reminder, never duplicates one
            if (EventRsvp::query()->whereKey($row->rsvp_id)->whereNull('reminded_at')->update(['reminded_at' => now()]) !== 1) {
                continue;
            }
            $this->remind((int) $row->user_id, $row);
            $sent++;
        }

        return $sent + app(Followups::class)->remindDue($limit);
    }

    /** An RFC 5545 calendar file for the member's copy of the event (Google, Apple and Outlook all open it). @throws MessagingException */
    public function ics(User $user, int $eventId): string
    {
        $e = $this->visible($user, $eventId);
        $fmt = fn (CarbonInterface $t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//NaaraSim//Naara Planner//EN', 'CALSCALE:GREGORIAN', 'METHOD:'.($e->cancelled_at ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT', 'UID:naara-event-'.$e->id.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'naarasim'), 'SEQUENCE:'.$e->sequence, 'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($e->starts_at), 'DTEND:'.$fmt($e->endsAt()), 'SUMMARY:'.self::esc($e->title),
        ];
        if ($e->description) {
            $lines[] = 'DESCRIPTION:'.self::esc($e->description);
        }
        $lines[] = 'STATUS:'.($e->cancelled_at ? 'CANCELLED' : 'CONFIRMED');
        if ($e->reminder_minutes > 0 && ! $e->cancelled_at) {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:'.self::esc($e->title), 'TRIGGER:-PT'.$e->reminder_minutes.'M', 'END:VALARM');
        }
        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    /** A Google Calendar "add event" link built from the same fields (no API, no account link). */
    public static function googleUrl(Event $e): string
    {
        $fmt = fn (CarbonInterface $t) => $t->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query(['action' => 'TEMPLATE', 'text' => $e->title, 'dates' => $fmt($e->starts_at).'/'.$fmt($e->endsAt()), 'details' => (string) $e->description], '', '&', PHP_QUERY_RFC3986);
    }

    /** Remove what erasure must remove: events the member created (their cards then read "Event removed") and their answers to others' events. */
    public static function eraseFor(User $user): void
    {
        Event::query()->where('creator_id', $user->id)->delete();
        EventRsvp::query()->where('user_id', $user->id)->delete();
    }

    private function assertCreator(User $user, Event $e): void
    {
        if ((int) $e->creator_id !== $user->id) {
            throw new MessagingException('not_participant', 'Only the person who planned this event can change it.');
        }
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $s);
    }

    /** Lines longer than 75 octets are folded (continuation lines start with one space), never inside a UTF-8 character. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = [];
        $cur = '';
        $limit = 75;
        foreach (mb_str_split($line) as $ch) {
            if (strlen($cur.$ch) > $limit) {
                $out[] = $cur;
                $cur = $ch;
                $limit = 74;
            } else {
                $cur .= $ch;
            }
        }
        $out[] = $cur;

        return implode("\r\n ", $out);
    }

    /** Tell the other member that the event changed (their card refreshes). */
    private function tell(Event $e, User $actor): void
    {
        $other = (int) Participant::query()->where('conversation_id', $e->conversation_id)->where('user_id', '!=', $actor->id)->value('user_id');
        if ($other > 0) {
            Realtime::publish($other, 'message.new', ['conversation_id' => $e->conversation_id, 'message_id' => $e->message_id, 'tone' => null, 'muted' => true]);
        }
        Realtime::publish($actor->id, 'message.sent', ['conversation_id' => $e->conversation_id, 'message_id' => $e->message_id]);
    }

    private function remind(int $userId, object $row): void
    {
        $other = (int) Participant::query()->where('conversation_id', $row->conversation_id)->where('user_id', '!=', $userId)->value('user_id');
        if ($other > 0 && Block::query()->where(fn ($q) => $q->where('blocker_id', $other)->where('blocked_id', $userId))->orWhere(fn ($q) => $q->where('blocker_id', $userId)->where('blocked_id', $other))->exists()) {
            return;   // a block silences a reminder as it silences everything else between the two
        }
        $part = Participant::query()->where('conversation_id', $row->conversation_id)->where('user_id', $userId)->first();
        $muted = ConversationPrefs::isMuted($part);
        Realtime::publish($userId, 'event.reminder', ['conversation_id' => (int) $row->conversation_id, 'event_id' => (int) $row->event_id, 'starts_at' => Carbon::parse($row->starts_at, 'UTC')->toIso8601String(), 'tone' => $muted ? null : 'message.naara']);
        if (! $muted) {
            $preview = (bool) Setting::getValue('n2n.push_preview', false);
            SendWebPushJob::dispatch($userId, [
                'title' => 'Event reminder',
                'body' => $preview ? mb_substr((string) $row->title, 0, 60).' starts soon.' : 'You have an event coming up on Naara.',
                'url' => url('/n2n/chat?c='.$row->conversation_id),
            ]);
        }
    }
}
