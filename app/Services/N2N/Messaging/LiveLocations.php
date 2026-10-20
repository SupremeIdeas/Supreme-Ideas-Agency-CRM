<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Block;
use App\Models\N2N\LiveLocation;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Live location in a 1:1 Naara chat (N2N, MASTER-ONLY). Designed so it cannot become a stalking tool:
 *  - the sharer chooses 15 minutes, 1 hour or 8 hours (an admin cap can shorten that) and it ALWAYS ends; nothing can extend it, a new share is a new message;
 *  - only the latest point is kept, readable by the other member of that chat only, and it is wiped on stop or expiry (no trail, no history);
 *  - either person blocking the other ends the read at once; a message deleted, hidden or expired is unreadable;
 *  - coordinates never enter message bodies, previews, search, exports, reports or moderation excerpts;
 *  - the page can only report while Naara is open in the browser; this is said to the sharer.
 */
class LiveLocations
{
    public const DURATIONS = [15, 60, 480];

    public function __construct(private readonly ConversationPrefs $prefs) {}

    public static function maxMinutes(): int
    {
        return max(15, min(480, (int) Setting::getValue('n2n.live_location_max_minutes', 480)));
    }

    /** @return list<int> the durations a member may pick (those within the admin cap) */
    public static function allowed(): array
    {
        return array_values(array_filter(self::DURATIONS, fn ($m) => $m <= self::maxMinutes()));
    }

    /**
     * @param  array{minutes?: mixed, lat?: mixed, lng?: mixed, acc?: mixed}  $raw
     * @return array{minutes: int, lat: float, lng: float, acc: ?int}
     *
     * @throws MessagingException
     */
    public static function clean(array $raw): array
    {
        $minutes = (int) ($raw['minutes'] ?? 0);
        $lat = is_numeric($raw['lat'] ?? null) ? (float) $raw['lat'] : null;
        $lng = is_numeric($raw['lng'] ?? null) ? (float) $raw['lng'] : null;
        if (! in_array($minutes, self::allowed(), true) || $lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            throw new MessagingException('invalid', 'Live location could not be started. Check your location permission and try again.');
        }

        return ['minutes' => $minutes, 'lat' => round($lat, 6), 'lng' => round($lng, 6), 'acc' => is_numeric($raw['acc'] ?? null) ? max(0, min(100000, (int) $raw['acc'])) : null];
    }

    /** Called inside the send transaction, once the message row exists. */
    public function attach(Message $message, array $clean): LiveLocation
    {
        return LiveLocation::query()->create([
            'message_id' => $message->id, 'user_id' => $message->sender_id, 'lat' => $clean['lat'], 'lng' => $clean['lng'], 'accuracy_m' => $clean['acc'],
            'expires_at' => now()->addMinutes($clean['minutes']), 'last_update_at' => now(),
        ]);
    }

    /** The sharer's device reports a new point. False when sharing is over (the page then stops watching). @throws MessagingException */
    public function update(User $user, int $messageId, mixed $lat, mixed $lng, mixed $acc = null): bool
    {
        $row = LiveLocation::query()->where('message_id', $messageId)->where('user_id', $user->id)->first();
        if ($row === null) {
            throw new MessagingException('not_found', 'That message is not available.');
        }
        if (! $row->active()) {
            return false;
        }
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            throw new MessagingException('invalid', 'That position was not valid.');
        }
        if (RateLimiter::tooManyAttempts('n2n-live:'.$row->id, 1)) {
            return true;   // a device reporting faster than every few seconds is simply ignored
        }
        RateLimiter::hit('n2n-live:'.$row->id, 5);
        $row->forceFill(['lat' => round((float) $lat, 6), 'lng' => round((float) $lng, 6), 'accuracy_m' => is_numeric($acc) ? max(0, min(100000, (int) $acc)) : null, 'last_update_at' => now()])->save();

        return true;
    }

    /** Stop sharing now: the position is wiped. Idempotent. */
    public function stop(User $user, int $messageId): bool
    {
        $row = LiveLocation::query()->where('message_id', $messageId)->where('user_id', $user->id)->first();
        if ($row === null) {
            return false;
        }
        $this->wipe($row, $row->stopped_at ?? now());

        return true;
    }

    /**
     * What a member may see right now. Never throws for "ended": the card then shows "Live location ended".
     *
     * @return array{active: bool, mine: bool, lat?: float, lng?: float, accuracy?: ?int, updated_at?: string, expires_at?: string}
     *
     * @throws MessagingException not a member / message not visible
     */
    public function read(User $viewer, int $messageId): array
    {
        $m = $this->prefs->visibleMessage($viewer, $messageId);
        $row = $m->type === 'live' ? LiveLocation::query()->where('message_id', $m->id)->first() : null;
        if ($row === null) {
            throw new MessagingException('not_found', 'That message is not available.');
        }
        $mine = (int) $row->user_id === $viewer->id;
        $other = $mine ? $this->otherMember($m, $viewer->id) : (int) $row->user_id;
        $blocked = $other > 0 && Block::query()->where(fn ($q) => $q->where('blocker_id', $other)->where('blocked_id', $viewer->id))->orWhere(fn ($q) => $q->where('blocker_id', $viewer->id)->where('blocked_id', $other))->exists();
        if (! $row->active()) {
            if ($row->lat !== null) {
                $this->wipe($row, $row->stopped_at ?? $row->expires_at);
            }

            return ['active' => false, 'mine' => $mine];
        }
        if ($blocked || ($m->suppressed && ! $mine)) {
            return ['active' => false, 'mine' => $mine];
        }

        return ['active' => true, 'mine' => $mine, 'lat' => (float) $row->lat, 'lng' => (float) $row->lng, 'accuracy' => $row->accuracy_m,
            'updated_at' => $row->last_update_at?->toIso8601String(), 'expires_at' => $row->expires_at->toIso8601String()];
    }

    /** Clear every position whose share has ended (stopped or expired). Returns how many were wiped. */
    public function purgeEnded(): int
    {
        $n = 0;
        foreach (LiveLocation::query()->whereNotNull('lat')->where(fn ($q) => $q->whereNotNull('stopped_at')->orWhere('expires_at', '<=', now()))->limit(1000)->get() as $row) {
            $this->wipe($row, $row->stopped_at ?? $row->expires_at);
            $n++;
        }

        return $n;
    }

    /** Remove every live position a member holds (account erasure). */
    public static function eraseFor(User $user): void
    {
        LiveLocation::query()->where('user_id', $user->id)->update(['lat' => null, 'lng' => null, 'accuracy_m' => null, 'stopped_at' => now()]);
    }

    private function wipe(LiveLocation $row, $stoppedAt): void
    {
        $row->forceFill(['lat' => null, 'lng' => null, 'accuracy_m' => null, 'stopped_at' => $stoppedAt])->save();
    }

    private function otherMember(Message $m, int $me): int
    {
        return (int) \App\Models\N2N\Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $me)->value('user_id');
    }
}
