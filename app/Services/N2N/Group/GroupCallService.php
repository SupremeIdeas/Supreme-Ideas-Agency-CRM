<?php

namespace App\Services\N2N\Group;

use App\Models\N2N\Block;
use App\Models\N2N\Call;
use App\Models\N2N\CallInterval;
use App\Models\N2N\CallLink;
use App\Models\N2N\CallParticipant;
use App\Models\N2N\GroupCall;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Realtime\Realtime;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Group calls (N2N Batch 11, MASTER-ONLY). Rooms live on a self-hosted SFU; everything that matters is decided HERE, on the server:
 *   - who may join (a registered, logged-in member with a valid link, not removed, room not full),
 *   - who may speak (the host appoints up to 5 speakers including themselves; the SFU enforces it through the join token and live permission changes),
 *   - who pays (a speaker pays from their OWN credit for exactly the interval they are appointed, listeners are free).
 * Billing mirrors the 1:1 call engine: rolling credit holds extended ahead of need, settle at the end rounded UP to the increment, and an abrupt
 * disconnect settles at the last heartbeat. The server's clock is the only clock; a client never reports time.
 */
class GroupCallService
{
    public function __construct(private readonly UsageMeter $meter, private readonly SfuClient $sfu) {}

    // ---- creating a room and its link ---------------------------------------------------------------------------------------

    /** @return array{call: GroupCall, link: CallLink} @throws GroupCallException */
    public function create(User $host, string $title, ?int $capacity = null, bool $requiresApproval = false): array
    {
        $this->assertAvailable();
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if (mb_strlen($title) < 2 || mb_strlen($title) > 80) {
            throw new GroupCallException('invalid', 'Give the call a name of 2 to 80 characters.');
        }
        if (RateLimiter::tooManyAttempts('n2n-group-create:'.$host->id, 5)) {
            throw new GroupCallException('rate_limited', 'You are creating rooms too fast. Please wait a moment.');
        }
        RateLimiter::hit('n2n-group-create:'.$host->id, 60);
        if (GroupCall::query()->where('host_id', $host->id)->where('created_at', '>=', now()->subDay())->count() >= N2NSettings::groupRoomsPerHostPerDay()) {
            throw new GroupCallException('rate_limited', 'You have started the most group calls allowed for today.');
        }
        $cap = max(2, min(N2NSettings::groupMaxCapacity(), $capacity ?? N2NSettings::groupMaxCapacity()));

        return DB::transaction(function () use ($host, $title, $cap, $requiresApproval) {
            if (GroupCall::query()->where('state', GroupCall::OPEN)->count() >= N2NSettings::groupMaxRooms()) {
                throw new GroupCallException('busy', 'All group rooms are busy right now. Please try again in a little while.');
            }
            if ($this->presentElsewhere($host->id) || $this->inOneToOne($host->id)) {
                throw new GroupCallException('already_in_call', 'You are already in a call.');
            }
            $call = GroupCall::query()->create([
                'host_id' => $host->id, 'title' => $title, 'room' => 'n2n-'.Str::lower(Str::random(20)), 'capacity' => $cap, 'max_speakers' => N2NSettings::groupMaxSpeakers(),
                'state' => GroupCall::OPEN, 'started_at' => now(), 'last_active_at' => now(),
            ]);
            if (! $this->sfu->createRoom($call->room, $cap + 5)) {
                throw new GroupCallException('unavailable', 'Group calls are not available right now.');
            }
            $p = CallParticipant::query()->create(['group_call_id' => $call->id, 'user_id' => $host->id, 'role' => CallParticipant::HOST, 'speaking' => false, 'joined_at' => now(), 'last_beat_at' => now()]);
            try {
                $this->startSpeaking($call, $p, $host);        // the host starts as a speaker; if their credit cannot cover it they host as a listener
            } catch (InsufficientUsageCredit) {
                $this->sfu->setCanPublish($call->room, $host->id, false);
            }
            $link = $this->issueLink($call, $host, $requiresApproval);
            Auditor::log('n2n.group_created', GroupCall::class, $call->id, ['capacity' => $cap]);

            return ['call' => $call->fresh(), 'link' => $link];
        });
    }

    /** A fresh link (the old ones are revoked). The secret is shown to the host again with `urlFor`. */
    public function newLink(User $host, int $callId, ?bool $requiresApproval = null): CallLink
    {
        return DB::transaction(function () use ($host, $callId, $requiresApproval) {
            $call = $this->hostCall($host, $callId);
            $keep = $requiresApproval ?? (bool) CallLink::query()->where('group_call_id', $call->id)->latest('id')->value('requires_approval');
            CallLink::query()->where('group_call_id', $call->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $this->issueLink($call, $host, $keep);
        });
    }

    public function revokeLinks(User $host, int $callId): void
    {
        DB::transaction(function () use ($host, $callId) {
            $call = $this->hostCall($host, $callId);
            CallLink::query()->where('group_call_id', $call->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });
    }

    private function issueLink(GroupCall $call, User $host, bool $requiresApproval): CallLink
    {
        $token = Str::random(40);

        return CallLink::query()->create([
            'group_call_id' => $call->id, 'host_id' => $host->id, 'token_hash' => CallLink::hash($token), 'token_enc' => Crypt::encryptString($token),
            'requires_approval' => $requiresApproval, 'expires_at' => now()->addHours(N2NSettings::groupLinkHours()),
        ]);
    }

    public static function urlFor(CallLink $link): string
    {
        return route('n2n.group.join', ['token' => $link->token()]);
    }

    /** The public landing data for a link: who is hosting and whether it can still be used. Never reveals who is inside. @return array<string, mixed>|null */
    public function preview(string $token): ?array
    {
        $link = $this->linkFor($token);
        if ($link === null || ! $link->usable() || ! $link->call?->isOpen()) {
            return null;
        }

        return ['title' => $link->call->title, 'host_name' => $link->call->host?->name ?: 'A Naara member', 'host_ref' => $link->call->host?->referral_code, 'requires_approval' => $link->requires_approval];
    }

    private function linkFor(string $token): ?CallLink
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return CallLink::query()->with('call.host')->where('token_hash', CallLink::hash($token))->first();
    }

    // ---- joining ---------------------------------------------------------------------------------------------------------------

    /**
     * Join as a listener (or wait for approval). Returns what the browser needs to connect. @return array{call: GroupCall, participant: CallParticipant, pending: bool, connect: ?array<string, mixed>}
     *
     * @throws GroupCallException
     */
    public function join(User $user, string $token): array
    {
        $this->assertAvailable();
        if (RateLimiter::tooManyAttempts('n2n-group-join:'.$user->id, 30)) {
            throw new GroupCallException('rate_limited', 'You are joining too fast. Please wait a moment.');
        }
        RateLimiter::hit('n2n-group-join:'.$user->id, 60);
        $link = $this->linkFor($token);
        $invalid = new GroupCallException('link_invalid', 'This call link is not available. It may have expired, been turned off, or the call has ended.');
        if ($link === null || ! $link->usable() || ! $link->call?->isOpen() || ! $user->is_active) {
            throw $invalid;
        }

        return DB::transaction(function () use ($user, $link, $invalid) {
            $call = GroupCall::query()->lockForUpdate()->find($link->group_call_id);
            if ($call === null || ! $call->isOpen()) {
                throw $invalid;
            }
            if ($this->blockedWith($call->host_id, $user->id)) {
                throw $invalid;                                    // a block looks exactly like an expired link: no oracle
            }
            $p = CallParticipant::query()->where('group_call_id', $call->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($p?->banned_at !== null) {
                throw new GroupCallException('banned', 'You cannot join this call.');
            }
            if ($user->id !== $call->host_id && ($this->presentElsewhere($user->id, $call->id) || $this->inOneToOne($user->id))) {
                throw new GroupCallException('already_in_call', 'You are already in a call. Leave it first.');
            }
            $already = $p?->present() ?? false;
            if (! $already && $this->presentCount($call) >= $call->capacity) {
                throw new GroupCallException('room_full', 'This room is full right now. Please try again in a moment.');
            }
            $needsApproval = $link->requires_approval && $user->id !== $call->host_id && ! $already && $p?->joined_at === null;
            $p = $p ?? new CallParticipant(['group_call_id' => $call->id, 'user_id' => $user->id, 'role' => CallParticipant::LISTENER]);
            if ($needsApproval) {
                $p->forceFill(['pending_at' => $p->pending_at ?? now(), 'last_beat_at' => now()])->save();
                $link->increment('uses');
                $this->announce($call, [$call->host_id]);

                return ['call' => $call, 'participant' => $p, 'pending' => true, 'connect' => null];
            }
            if (! $already) {
                // A returning member always comes back as a listener: speaking is an appointment, never a thing that survives leaving.
                $isHost = $user->id === $call->host_id;
                $p->forceFill(['role' => $isHost ? CallParticipant::HOST : CallParticipant::LISTENER, 'speaking' => $isHost && $this->openInterval($call, $user->id) !== null,
                    'pending_at' => null, 'joined_at' => now(), 'left_at' => null, 'last_beat_at' => now()])->save();
                $link->increment('uses');
                $this->announce($call, [$user->id]);
            }

            return ['call' => $call, 'participant' => $p->fresh(), 'pending' => false, 'connect' => $this->connectPayload($call, $p->fresh(), $user)];
        });
    }

    /** The browser's connection details (the join token carries the publish grant the server chose). @return array<string, mixed> */
    public function connectPayload(GroupCall $call, CallParticipant $p, User $user): array
    {
        return [
            'url' => SfuClient::wsUrl(), 'room' => $call->room, 'identity' => SfuClient::identity($user->id),
            'token' => $this->sfu->joinToken($call->room, $user->id, $user->name ?: 'Naara member', (bool) $p->speaking, $p->role),
            'can_speak' => (bool) $p->speaking,
        ];
    }

    /** A pending person asks again: once approved they get their connection details. @return array{pending: bool, denied: bool, connect: ?array<string, mixed>} */
    public function myStatus(User $user, int $callId): array
    {
        $p = CallParticipant::query()->where('group_call_id', $callId)->where('user_id', $user->id)->first();
        $call = GroupCall::query()->find($callId);
        if ($p === null || $call === null) {
            throw new GroupCallException('not_found', 'That call was not found.');
        }
        if ($p->banned_at !== null) {
            return ['pending' => false, 'denied' => true, 'connect' => null];
        }
        if ($p->pending_at !== null) {
            return ['pending' => true, 'denied' => false, 'connect' => null];
        }

        return ['pending' => false, 'denied' => false, 'connect' => $call->isOpen() && $p->present() ? $this->connectPayload($call, $p, $user) : null];
    }

    public function approve(User $host, int $callId, int $userId, bool $allow = true): void
    {
        DB::transaction(function () use ($host, $callId, $userId, $allow) {
            $call = $this->hostCall($host, $callId);
            $p = $this->participant($call, $userId);
            if ($p->pending_at === null) {
                return;
            }
            if ($allow && $this->presentCount($call) >= $call->capacity) {
                throw new GroupCallException('room_full', 'The room is full.');
            }
            $allow ? $p->forceFill(['pending_at' => null, 'joined_at' => now(), 'left_at' => null, 'last_beat_at' => now()])->save() : $p->forceFill(['banned_at' => now(), 'pending_at' => null])->save();
            $this->announce($call, [$userId]);
        });
    }

    // ---- host controls ---------------------------------------------------------------------------------------------------------

    /** Appoint a listener as a speaker. Needs a free speaking place and the person's credit to cover the first slice. @throws GroupCallException */
    public function appoint(User $host, int $callId, int $userId): void
    {
        DB::transaction(function () use ($host, $callId, $userId) {
            $call = $this->hostCall($host, $callId);
            $p = $this->participant($call, $userId);
            if (! $p->present() || $p->speaking) {
                throw new GroupCallException('invalid', 'That person is not waiting to speak.');
            }
            $this->assertSpeakingPlace($call);
            $user = User::query()->findOrFail($userId);
            try {
                $this->startSpeaking($call, $p, $user);
            } catch (InsufficientUsageCredit) {
                throw new GroupCallException('no_credit', 'They cannot take the microphone right now.');   // no balance is revealed
            }
            if (! $this->sfu->setCanPublish($call->room, $userId, true)) {
                throw new GroupCallException('unavailable', 'The call server did not answer. Please try again.');    // the transaction rolls the appointment and hold back
            }
            $this->announce($call, [$userId]);
        });
    }

    /** The host takes the microphone again after stepping down. */
    public function startSpeakingAgain(User $host, int $callId): void
    {
        $this->appoint($host, $callId, $host->id);
    }

    /** Take a speaker back to the audience (the host, or the speaker themselves). The interval is settled to the second it ends. */
    public function demote(User $actor, int $callId, int $userId): void
    {
        DB::transaction(function () use ($actor, $callId, $userId) {
            $call = GroupCall::query()->lockForUpdate()->find($callId);
            if ($call === null || ! $call->isOpen() || ($actor->id !== $call->host_id && $actor->id !== $userId)) {
                throw new GroupCallException('forbidden', 'You cannot do that.');
            }
            $p = $this->participant($call, $userId);
            $this->stopSpeaking($call, $p, 'demoted', now());
            $this->announce($call, [$userId]);
        });
    }

    /** Silence a speaker's microphone. They stay appointed (and billed): appointment, not mute, is the billable state. */
    public function mute(User $host, int $callId, int $userId): void
    {
        $call = $this->hostCall($host, $callId, false);
        $this->participant($call, $userId);
        $this->sfu->mute($call->room, $userId);
    }

    /** Remove a person and bar them from this call, with this link or any other. */
    public function remove(User $host, int $callId, int $userId): void
    {
        DB::transaction(function () use ($host, $callId, $userId) {
            $call = $this->hostCall($host, $callId);
            if ($userId === $host->id) {
                throw new GroupCallException('invalid', 'To end the call, close the room.');
            }
            $p = $this->participant($call, $userId);
            $this->stopSpeaking($call, $p, 'removed', now());
            $p->forceFill(['left_at' => now(), 'banned_at' => now(), 'pending_at' => null])->save();
            $this->sfu->remove($call->room, $userId);
            Realtime::publish($userId, 'group.removed', ['call_id' => $call->id]);
            $this->announce($call);
            Auditor::log('n2n.group_removed', GroupCall::class, $call->id, ['user' => $userId]);
        });
    }

    public function leave(User $user, int $callId): void
    {
        DB::transaction(function () use ($user, $callId) {
            $call = GroupCall::query()->lockForUpdate()->find($callId);
            $p = $call ? CallParticipant::query()->where('group_call_id', $callId)->where('user_id', $user->id)->first() : null;
            if ($call === null || $p === null) {
                throw new GroupCallException('not_found', 'That call was not found.');
            }
            if ($user->id === $call->host_id && $call->isOpen()) {
                $this->closeRoom($call, 'host_left');

                return;
            }
            $this->stopSpeaking($call, $p, 'left', now());
            $p->forceFill(['left_at' => now(), 'pending_at' => null])->save();
            $this->sfu->remove($call->room, $user->id);
            $this->announce($call);
        });
    }

    public function close(User $host, int $callId): void
    {
        DB::transaction(function () use ($host, $callId) {
            $call = $this->hostCall($host, $callId);
            $this->closeRoom($call, 'closed_by_host');
        });
    }

    /** Admin/system close (moderation, kill switch). */
    public function adminClose(int $callId, string $reason = 'admin'): void
    {
        DB::transaction(function () use ($callId, $reason) {
            $call = GroupCall::query()->lockForUpdate()->find($callId);
            if ($call !== null && $call->isOpen()) {
                $this->closeRoom($call, $reason);
            }
        });
    }

    /** Settle every open interval, mark everyone gone, revoke the links, delete the SFU room. Caller holds the room lock. */
    private function closeRoom(GroupCall $call, string $reason): void
    {
        foreach (CallParticipant::query()->where('group_call_id', $call->id)->whereNull('left_at')->get() as $p) {
            $this->stopSpeaking($call, $p, 'closed', now());
            $p->forceFill(['left_at' => now(), 'pending_at' => null])->save();
        }
        CallInterval::query()->where('group_call_id', $call->id)->whereNull('to_at')->get()->each(fn ($iv) => $this->settleInterval($iv, 'closed', now()));
        CallLink::query()->where('group_call_id', $call->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $call->forceFill(['state' => GroupCall::CLOSED, 'end_reason' => $reason, 'ended_at' => now()])->save();
        $this->sfu->deleteRoom($call->room);
        foreach (CallParticipant::query()->where('group_call_id', $call->id)->pluck('user_id') as $uid) {
            Realtime::publish((int) $uid, 'group.closed', ['call_id' => $call->id, 'reason' => $reason]);
        }
    }

    // ---- keep-alive and the room view -------------------------------------------------------------------------------------------

    /**
     * Client heartbeat. Records the beat (server clock), applies the credit verdict to a speaker and returns what is true now.
     *
     * @return array<string, mixed>
     */
    public function heartbeat(User $user, int $callId): array
    {
        return DB::transaction(function () use ($user, $callId) {
            $call = GroupCall::query()->lockForUpdate()->find($callId);
            $p = $call ? CallParticipant::query()->where('group_call_id', $callId)->where('user_id', $user->id)->first() : null;
            if ($call === null || $p === null) {
                throw new GroupCallException('not_found', 'That call was not found.');
            }
            if ($call->isOpen() && $p->present()) {
                $p->forceFill(['last_beat_at' => now()])->save();
                $call->forceFill(['last_active_at' => now()])->save();
                $this->enforceSpeaker($call, $p);
            }

            return $this->me($call->fresh(), $p->fresh());
        });
    }

    /** What the room page shows: the roster (names only), the caller's own status and, for the host, who is waiting. @return array<string, mixed> */
    public function state(User $user, int $callId): array
    {
        $call = GroupCall::query()->find($callId);
        $p = $call ? CallParticipant::query()->where('group_call_id', $callId)->where('user_id', $user->id)->first() : null;
        if ($call === null || $p === null || $p->banned_at !== null) {
            throw new GroupCallException('not_found', 'That call was not found.');
        }
        $rows = CallParticipant::query()->with('user:id,name')->where('group_call_id', $callId)->get();
        $present = $rows->filter(fn ($r) => $r->present());
        $isHost = $user->id === $call->host_id;

        return $this->me($call, $p) + [
            'title' => $call->title, 'capacity' => $call->capacity, 'count' => $present->count(), 'max_speakers' => $call->max_speakers,
            'speakers' => $present->where('speaking', true)->map(fn ($r) => $this->person($r))->values()->all(),
            'listeners' => $present->where('speaking', false)->map(fn ($r) => $this->person($r))->values()->all(),
            'waiting' => $isHost ? $rows->filter(fn ($r) => $r->pending_at !== null && $r->banned_at === null)->map(fn ($r) => $this->person($r))->values()->all() : [],
            'link' => $isHost && $call->isOpen() ? ($this->activeLink($call) ? self::urlFor($this->activeLink($call)) : null) : null,
            'requires_approval' => $isHost ? (bool) ($this->activeLink($call)?->requires_approval) : null,
        ];
    }

    private function activeLink(GroupCall $call): ?CallLink
    {
        return CallLink::query()->where('group_call_id', $call->id)->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->first();
    }

    /** @return array<string, mixed> */
    private function person(CallParticipant $r): array
    {
        return ['id' => $r->user_id, 'name' => $r->user?->name ?: 'Naara member', 'host' => $r->role === CallParticipant::HOST];
    }

    /** @return array<string, mixed> */
    private function me(GroupCall $call, CallParticipant $p): array
    {
        return [
            'call_id' => $call->id, 'open' => $call->isOpen(), 'reason' => $call->end_reason, 'is_host' => $p->role === CallParticipant::HOST,
            'present' => $p->present(), 'pending' => $p->pending_at !== null, 'removed' => $p->banned_at !== null, 'speaking' => (bool) $p->speaking,
        ];
    }

    // ---- sweeper -------------------------------------------------------------------------------------------------------------

    /** Run every minute: lost participants end at their last beat, speakers out of credit step down at that moment, empty rooms close. @return int changes */
    public function sweep(): int
    {
        $n = 0;
        $stale = now()->subSeconds(N2NSettings::callStaleSeconds());
        foreach (GroupCall::query()->where('state', GroupCall::OPEN)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $stale, &$n) {
                $call = GroupCall::query()->lockForUpdate()->find($id);
                if ($call === null || ! $call->isOpen()) {
                    return;
                }
                foreach (CallParticipant::query()->where('group_call_id', $id)->whereNull('left_at')->whereNotNull('joined_at')->get() as $p) {
                    if ($p->last_beat_at !== null && $p->last_beat_at->lt($stale)) {
                        // The tab died: they left when they last answered. A lost host takes the room with them.
                        $this->stopSpeaking($call, $p, 'lost', $p->last_beat_at);
                        $p->forceFill(['left_at' => $p->last_beat_at])->save();
                        $this->sfu->remove($call->room, $p->user_id);
                        $n++;
                        if ($p->role === CallParticipant::HOST) {
                            $this->closeRoom($call, 'host_left');

                            return;
                        }
                        continue;
                    }
                    if ($p->speaking) {
                        $before = $p->speaking;
                        $this->enforceSpeaker($call, $p);
                        $n += $before && ! $p->fresh()->speaking ? 1 : 0;
                    }
                }
                if ($call->isOpen() && $this->presentCount($call) === 0 && ($call->last_active_at ?? $call->started_at)->lt(now()->subMinutes(N2NSettings::groupIdleMinutes()))) {
                    $this->closeRoom($call, 'idle');
                    $n++;
                } else {
                    $this->announce($call->fresh());
                }
            });
        }

        return $n;
    }

    // ---- billing internals -----------------------------------------------------------------------------------------------------

    /** Count of people speaking right now (the host counts when speaking). */
    private function speakingCount(GroupCall $call): int
    {
        return CallParticipant::query()->where('group_call_id', $call->id)->where('speaking', true)->whereNull('left_at')->count();
    }

    private function assertSpeakingPlace(GroupCall $call): void
    {
        if ($this->speakingCount($call) >= min($call->max_speakers, N2NSettings::groupMaxSpeakers())) {
            throw new GroupCallException('speakers_full', 'Only '.min($call->max_speakers, N2NSettings::groupMaxSpeakers()).' people can speak at once, including the host. Take someone back to the audience first.');
        }
    }

    /** Begin a billable interval and take the first credit slice. @throws InsufficientUsageCredit */
    private function startSpeaking(GroupCall $call, CallParticipant $p, User $user): void
    {
        $this->assertSpeakingPlace($call);
        $rate = N2NPricing::speakerRatePerMinuteCents();
        $hold = max(1, N2NSettings::callHoldMinutes()) * $rate;
        $iv = CallInterval::query()->create([
            'group_call_id' => $call->id, 'user_id' => $user->id, 'role' => $p->role === CallParticipant::HOST ? 'host' : 'speaker', 'from_at' => now(),
            'hold_count' => 0, 'hold_cents_each' => $hold, 'rate_cents' => $rate, 'increment_s' => N2NSettings::callIncrementSeconds(),
        ]);
        if ($rate > 0) {
            try {
                $this->takeHold($iv, $user);
            } catch (InsufficientUsageCredit $e) {
                $iv->delete();
                throw $e;
            }
        }
        $p->forceFill(['speaking' => true, 'role' => $p->role === CallParticipant::HOST ? CallParticipant::HOST : CallParticipant::SPEAKER])->save();
    }

    /** End a participant's open interval (if any) at $endAt and put them back in the audience. Idempotent. Caller holds the room lock. */
    private function stopSpeaking(GroupCall $call, CallParticipant $p, string $reason, Carbon $endAt): void
    {
        $iv = $this->openInterval($call, $p->user_id);
        if ($iv !== null) {
            $this->settleInterval($iv, $reason, $endAt);
        }
        if ($p->speaking || $p->role === CallParticipant::SPEAKER) {
            $p->forceFill(['speaking' => false, 'role' => $p->role === CallParticipant::HOST ? CallParticipant::HOST : CallParticipant::LISTENER])->save();
            if ($call->isOpen() && ! in_array($reason, ['removed', 'left', 'lost', 'closed'], true) && ! $this->sfu->setCanPublish($call->room, $p->user_id, false)) {
                $this->sfu->remove($call->room, $p->user_id);       // cannot revoke the grant: drop the connection; they come back as a listener
            }
        }
    }

    private function openInterval(GroupCall $call, int $userId): ?CallInterval
    {
        return CallInterval::query()->where('group_call_id', $call->id)->where('user_id', $userId)->whereNull('to_at')->latest('id')->first();
    }

    /** Keep a speaker's cover ahead of time; if the next slice cannot be held, they step down the moment the cover runs out. */
    private function enforceSpeaker(GroupCall $call, CallParticipant $p): void
    {
        if (! $p->speaking) {
            return;
        }
        $iv = $this->openInterval($call, $p->user_id);
        if ($iv === null) {
            $p->forceFill(['speaking' => false])->save();

            return;
        }
        if ($iv->rate_cents > 0) {
            if ($iv->ends_at === null) {
                $perHold = (int) max(1, round($iv->hold_cents_each / max(1, $iv->rate_cents) * 60));
                $elapsed = (int) $iv->from_at->diffInSeconds(now(), true);
                $user = User::query()->find($p->user_id);
                while ($user !== null && $elapsed + N2NSettings::callWarnSeconds() + 5 >= $iv->hold_count * $perHold) {
                    try {
                        $this->takeHold($iv, $user);
                    } catch (InsufficientUsageCredit) {
                        $iv->forceFill(['ends_at' => $iv->from_at->copy()->addSeconds($iv->hold_count * $perHold)])->save();
                        break;
                    }
                }
            }
            if ($iv->ends_at !== null && now()->gte($iv->ends_at)) {
                $this->stopSpeaking($call, $p, 'credit', $iv->ends_at);
                Realtime::publish($p->user_id, 'group.demoted', ['call_id' => $call->id, 'reason' => 'credit']);
                $this->announce($call, [$p->user_id]);
            }
        }
    }

    private function takeHold(CallInterval $iv, User $user): void
    {
        $this->meter->hold($user, (int) $iv->hold_cents_each, $this->holdRef($iv, $iv->hold_count), 'group_call');
        $iv->forceFill(['hold_count' => $iv->hold_count + 1])->save();
    }

    private function holdRef(CallInterval $iv, int $n): string
    {
        return 'grp:'.$iv->id.':h'.$n;
    }

    /** Settle one interval: bill from the server's `from_at` to $endAt, rounded UP to the increment, and return every unused slice. */
    private function settleInterval(CallInterval $iv, string $reason, Carbon $endAt): void
    {
        if ($iv->to_at !== null) {
            return;
        }
        $endAt = $endAt->gt(now()) ? now() : ($endAt->lt($iv->from_at) ? $iv->from_at : $endAt);
        $seconds = max(0, (int) $iv->from_at->diffInSeconds($endAt, true));
        $inc = max(1, (int) $iv->increment_s);
        $billedSeconds = $seconds === 0 ? 0 : (int) (ceil($seconds / $inc) * $inc);
        $cents = (int) ceil($billedSeconds * $iv->rate_cents / 60);
        $remaining = $cents;
        for ($i = 0; $i < $iv->hold_count; $i++) {
            $keep = min((int) $iv->hold_cents_each, $remaining);
            $this->meter->settle($this->holdRef($iv, $i), $keep);
            $remaining -= $keep;
        }
        if ($remaining > 0) {
            Log::warning('N2N group call billed more than it held', ['interval' => $iv->id, 'short_cents' => $remaining]);
        }
        $iv->forceFill(['to_at' => $endAt, 'end_reason' => $reason, 'billed_seconds' => $billedSeconds, 'billed_cents' => $cents - max(0, $remaining)])->save();
    }

    // ---- helpers -----------------------------------------------------------------------------------------------------------------

    private function assertAvailable(): void
    {
        if (! GroupAvailability::enabled()) {
            throw new GroupCallException('unavailable', 'Group calls are not available right now.');
        }
    }

    /** The open room, only for its host, locked for the change. */
    private function hostCall(User $host, int $callId, bool $lock = true): GroupCall
    {
        $q = GroupCall::query()->whereKey($callId);
        $call = ($lock ? $q->lockForUpdate() : $q)->first();
        if ($call === null || $call->host_id !== $host->id) {
            throw new GroupCallException('not_found', 'That call was not found.');   // missing and not-yours look the same
        }
        if (! $call->isOpen()) {
            throw new GroupCallException('closed', 'This call has ended.');
        }

        return $call;
    }

    private function participant(GroupCall $call, int $userId): CallParticipant
    {
        $p = CallParticipant::query()->where('group_call_id', $call->id)->where('user_id', $userId)->lockForUpdate()->first();
        if ($p === null) {
            throw new GroupCallException('not_found', 'That person is not in this call.');
        }

        return $p;
    }

    private function presentCount(GroupCall $call): int
    {
        return CallParticipant::query()->where('group_call_id', $call->id)->whereNull('left_at')->whereNull('pending_at')->whereNull('banned_at')->whereNotNull('joined_at')->count();
    }

    private function presentElsewhere(int $userId, ?int $exceptCallId = null): bool
    {
        return CallParticipant::query()->where('user_id', $userId)->whereNull('left_at')->whereNull('pending_at')->whereNotNull('joined_at')
            ->when($exceptCallId, fn ($q) => $q->where('group_call_id', '!=', $exceptCallId))
            ->whereIn('group_call_id', GroupCall::query()->where('state', GroupCall::OPEN)->select('id'))->exists();
    }

    private function inOneToOne(int $userId): bool
    {
        return Call::query()->where('state', Call::ACTIVE)->where(fn ($q) => $q->where('caller_id', $userId)->orWhere('callee_id', $userId))->exists();
    }

    private function blockedWith(int $a, int $b): bool
    {
        return Block::query()->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))->exists();
    }

    /** Tell everyone in the room (or just these people) to refresh their roster. */
    private function announce(GroupCall $call, array $only = []): void
    {
        $ids = $only !== [] ? $only : CallParticipant::query()->where('group_call_id', $call->id)->whereNull('left_at')->pluck('user_id')->all();
        foreach (array_unique(array_merge($ids, [$call->host_id])) as $uid) {
            Realtime::publish((int) $uid, 'group.updated', ['call_id' => $call->id]);
        }
    }
}
