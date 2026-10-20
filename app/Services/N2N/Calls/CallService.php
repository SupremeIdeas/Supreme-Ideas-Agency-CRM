<?php

namespace App\Services\N2N\Calls;

use App\Models\N2N\Call;
use App\Models\User;
use App\Notifications\N2NMissedCall;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Realtime\Realtime;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The server-side call state machine (N2N Batch 10, MASTER-ONLY). The SERVER clock is the authority: `answered_at` is set when the server
 * accepts, billing runs from it to the end the server decides, and nothing a client sends about time is used. The caller pays, the callee is
 * free. Credit is held in rolling slices (UsageMeter holds), extended ahead of need, settled exactly at the end and the unused part returned.
 *
 *   ringing -> active -> ended      ringing -> declined | missed | busy | failed
 *
 * Every transition runs under a row lock and is idempotent, so an abrupt tab close, a double hangup and the sweeper racing each other settle once.
 */
class CallService
{
    public function __construct(private readonly UsageMeter $meter, private readonly IdentityResolver $resolver) {}

    // ---- placing a call -------------------------------------------------------------------------------------------------

    /** @throws CallException|InsufficientUsageCredit */
    public function start(User $caller, string $to, ?string $ip = null, bool $video = false): Call
    {
        if (! CallsAvailability::enabled()) {
            throw new CallException('unavailable', 'Calls are not available right now.');
        }
        if (\App\Services\N2N\Messaging\Moderation::restricted($caller)) {
            throw new CallException('restricted', 'Your chat access is paused, so you cannot place calls right now.');
        }
        if (RateLimiter::tooManyAttempts('n2n-call:'.$caller->id, N2NSettings::callAttemptsPerMinute())) {
            throw new CallException('rate_limited', 'You are calling too fast. Please wait a moment.');
        }
        RateLimiter::hit('n2n-call:'.$caller->id, 60);
        $hit = $this->resolver->resolve($to, 'search', $caller, $ip);
        $callee = $hit->found ? User::query()->find($hit->userId) : null;
        if ($callee === null || $callee->id === $caller->id) {
            throw new CallException('not_found', 'We could not find that person on Naara.');
        }
        $video = $video && CallsAvailability::videoEnabled();
        $rate = $video ? N2NPricing::videoRatePerMinuteCents() : N2NPricing::callRatePerMinuteCents();
        $hold = max(1, N2NSettings::callHoldMinutes()) * $rate;
        $deliverable = CallPolicy::deliverable($caller, $callee);

        $lock = Cache::lock('n2n:call:'.min($caller->id, $callee->id).':'.max($caller->id, $callee->id), 10);
        try {
            $lock->block(5);
        } catch (\Throwable) {
            throw new CallException('busy', 'Please try again in a moment.');
        }
        try {
            if ($this->inCall($caller->id)) {
                throw new CallException('already_in_call', 'You are already in a call.');
            }
            $busy = $deliverable && $this->inCall($callee->id);
            $call = Call::query()->create([
                'caller_id' => $caller->id, 'callee_id' => $callee->id, 'state' => $busy ? 'busy' : Call::RINGING, 'suppressed' => ! $deliverable, 'video' => $video,
                'end_reason' => $busy ? 'busy' : null, 'hold_count' => 0, 'billed_seconds' => 0, 'billed_cents' => 0, 'rate_cents' => $rate, 'increment_s' => N2NSettings::callIncrementSeconds(), 'hold_cents_each' => $hold,
                'ring_expires_at' => now()->addSeconds(N2NSettings::callRingSeconds()), 'ended_at' => $busy ? now() : null,
            ]);
            if ($busy) {
                return $call;
            }
            try {
                $this->takeHold($call);                                            // may throw InsufficientUsageCredit: the call row is removed below
            } catch (InsufficientUsageCredit $e) {
                $call->delete();
                throw $e;
            }
        } finally {
            $lock->release();
        }

        if ($deliverable) {
            Realtime::publish($callee->id, 'call.incoming', ['call_id' => $call->id, 'from' => $caller->naara_id, 'name' => $caller->name, 'video' => (bool) $call->video, 'tone' => 'ringtone.default', 'ring_s' => N2NSettings::callRingSeconds()]);
            $this->pushIfOffline($callee, $caller, $call);
        }

        return $call->fresh();
    }

    // ---- answering ----------------------------------------------------------------------------------------------------------

    /** @throws CallException */
    public function accept(User $user, int $callId): Call
    {
        return DB::transaction(function () use ($user, $callId) {
            $call = $this->mine($user, $callId, true);
            if ($call->callee_id !== $user->id || $call->suppressed) {
                throw new CallException('not_found', 'That call is no longer available.');
            }
            if ($call->state === Call::ACTIVE) {
                return $call;                                               // a double tap is harmless
            }
            if ($call->state !== Call::RINGING || ($call->ring_expires_at && $call->ring_expires_at->isPast())) {
                throw new CallException('gone', 'That call is no longer available.');
            }
            if ($this->inCall($user->id, $call->id)) {
                throw new CallException('already_in_call', 'You are already in a call.');
            }
            $call->forceFill(['state' => Call::ACTIVE, 'answered_at' => now(), 'caller_beat_at' => now(), 'callee_beat_at' => now()])->save();
            Realtime::publish($call->caller_id, 'call.accepted', ['call_id' => $call->id]);

            return $call;
        });
    }

    public function decline(User $user, int $callId): void
    {
        DB::transaction(function () use ($user, $callId) {
            $call = $this->mine($user, $callId, true);
            if ($call->callee_id === $user->id && $call->state === Call::RINGING) {
                $this->finishUnanswered($call, 'declined', 'declined');
            }
        });
    }

    /** Either side hangs up. Ringing + caller = cancelled (a missed call for the callee); active = settle. */
    public function hangup(User $user, int $callId, ?bool $relayUsed = null): ?Call
    {
        return DB::transaction(function () use ($user, $callId, $relayUsed) {
            $call = $this->mine($user, $callId, true);
            if ($relayUsed !== null && $call->relay_used === null) {
                $call->forceFill(['relay_used' => $relayUsed])->save();
            }
            if ($call->state === Call::RINGING && $call->caller_id === $user->id) {
                $this->finishUnanswered($call, 'missed', 'cancelled');
            } elseif ($call->state === Call::ACTIVE) {
                $this->end($call, 'hangup', now());
            }

            return $call;
        });
    }

    // ---- signalling + keep-alive ----------------------------------------------------------------------------------------------

    /** Relay one WebRTC message to the other side. Only the two participants, only while the call is live, only known kinds, bounded size. */
    public function signal(User $user, int $callId, string $kind, mixed $data): void
    {
        $call = $this->mine($user, $callId);
        if (! $call->live() || $call->suppressed) {
            throw new CallException('gone', 'That call is no longer available.');
        }
        if (! in_array($kind, ['offer', 'answer', 'candidate', 'restart', 'media'], true)
            || ($kind === 'offer' && $call->caller_id !== $user->id) || ($kind === 'answer' && $call->callee_id !== $user->id)
            || ($kind === 'media' && ! self::validMedia($data))
            || strlen((string) json_encode($data)) > 16000) {
            throw new CallException('invalid', 'That signal was refused.');
        }
        Realtime::publish($call->peerOf($user->id), 'call.signal', ['call_id' => $call->id, 'kind' => $kind, 'data' => $data]);
    }

    /** A `media` signal only ever carries: cam (bool), share (bool) and react (a short emoji string). Anything else is refused. */
    private static function validMedia(mixed $d): bool
    {
        if (! is_array($d) || array_diff(array_keys($d), ['cam', 'share', 'react']) !== []) {
            return false;
        }
        foreach (['cam', 'share'] as $k) {
            if (isset($d[$k]) && ! is_bool($d[$k])) {
                return false;
            }
        }

        return ! isset($d['react']) || (is_string($d['react']) && mb_strlen($d['react']) <= 8);
    }

    /**
     * Client heartbeat (every few seconds). Records this side's beat, applies the server's verdicts (peer lost, credit exhausted) and tells the
     * client what is true. The client's own clock is never consulted.
     *
     * @return array{state: string, reason: ?string, seconds_left: ?int, warn: bool, elapsed: int}
     */
    public function heartbeat(User $user, int $callId): array
    {
        return DB::transaction(function () use ($user, $callId) {
            $call = $this->mine($user, $callId, true);
            if ($call->state === Call::ACTIVE) {
                $call->forceFill([$call->caller_id === $user->id ? 'caller_beat_at' : 'callee_beat_at' => now()])->save();
                $this->enforce($call);
            } elseif ($call->state === Call::RINGING && $call->ring_expires_at && $call->ring_expires_at->isPast()) {
                $this->finishUnanswered($call, 'missed', 'no_answer');
            }

            return $this->view($call->fresh());
        });
    }

    public function state(User $user, int $callId): array
    {
        return $this->view($this->mine($user, $callId));
    }

    // ---- sweeper ----------------------------------------------------------------------------------------------------------------

    /** Run every minute: no-answer rings become missed; a call whose side stopped beating ends at the last good beat. @return int calls changed */
    public function sweep(): int
    {
        $n = 0;
        foreach (Call::query()->where('state', Call::RINGING)->where('ring_expires_at', '<', now())->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$n) {
                $c = Call::query()->lockForUpdate()->find($id);
                if ($c && $c->state === Call::RINGING) {
                    $this->finishUnanswered($c, 'missed', 'no_answer');
                    $n++;
                }
            });
        }
        foreach (Call::query()->where('state', Call::ACTIVE)->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$n) {
                $c = Call::query()->lockForUpdate()->find($id);
                if ($c && $c->state === Call::ACTIVE) {
                    $this->enforce($c);
                    $n += $c->fresh()->state === Call::ACTIVE ? 0 : 1;
                }
            });
        }

        return $n;
    }

    // ---- internals ----------------------------------------------------------------------------------------------------------------

    /** Apply the server's verdicts to an ACTIVE call: lost peer, credit cover. Caller holds the row lock. */
    private function enforce(Call $call): void
    {
        $stale = N2NSettings::callStaleSeconds();
        $c = $call->caller_beat_at ?? $call->answered_at;
        $e = $call->callee_beat_at ?? $call->answered_at;
        $callerGone = $c->lt(now()->subSeconds($stale));
        $calleeGone = $e->lt(now()->subSeconds($stale));
        if ($callerGone || $calleeGone) {
            // The call effectively ended when the side that vanished last answered: both gone -> the later of the two beats.
            $endAt = $callerGone && $calleeGone ? ($c->gt($e) ? $c : $e) : ($callerGone ? $c : $e);
            $this->end($call, 'peer_lost', $endAt);

            return;
        }
        $this->ensureCover($call);
        if ($call->ends_at !== null && now()->gte($call->ends_at)) {
            $this->end($call, 'credit', $call->ends_at);
        }
    }

    /** Keep the hold ahead of the elapsed time; if the next slice cannot be taken, fix the moment the cover runs out. */
    private function ensureCover(Call $call): void
    {
        if ($call->ends_at !== null || $call->rate_cents <= 0) {
            return;
        }
        $perHold = (int) max(1, round($call->hold_cents_each / max(1, $call->rate_cents) * 60));
        $elapsed = (int) $call->answered_at->diffInSeconds(now(), true);
        while ($elapsed + N2NSettings::callWarnSeconds() + 5 >= $call->hold_count * $perHold) {
            try {
                $this->takeHold($call);
            } catch (InsufficientUsageCredit) {
                $call->forceFill(['ends_at' => $call->answered_at->copy()->addSeconds($call->hold_count * $perHold)])->save();

                return;
            }
        }
    }

    private function takeHold(Call $call): void
    {
        $this->meter->hold($call->caller, (int) $call->hold_cents_each, $this->holdRef($call, $call->hold_count), 'call');
        $call->forceFill(['hold_count' => $call->hold_count + 1])->save();
    }

    private function holdRef(Call $call, int $n): string
    {
        return 'call:'.$call->id.':h'.$n;
    }

    /** Settle an ACTIVE call: bill from the server's answered_at to $endAt, rounded UP to the increment; return every unused slice. */
    private function end(Call $call, string $reason, \Illuminate\Support\Carbon $endAt): void
    {
        if ($call->state !== Call::ACTIVE) {
            return;
        }
        $endAt = $endAt->gt(now()) ? now() : $endAt;
        $seconds = max(0, (int) $call->answered_at->diffInSeconds($endAt, true));
        $inc = max(1, (int) $call->increment_s);
        $billedSeconds = $seconds === 0 ? 0 : (int) (ceil($seconds / $inc) * $inc);
        $cents = (int) ceil($billedSeconds * $call->rate_cents / 60);
        $remaining = $cents;
        for ($i = 0; $i < $call->hold_count; $i++) {
            $keep = min((int) $call->hold_cents_each, $remaining);
            $this->meter->settle($this->holdRef($call, $i), $keep);
            $remaining -= $keep;
        }
        if ($remaining > 0) {
            Log::warning('N2N call billed more than it held', ['call' => $call->id, 'short_cents' => $remaining]);   // cannot happen with the cover rule; the shortfall is not charged
        }
        $call->forceFill(['state' => 'ended', 'end_reason' => $reason, 'ended_at' => $endAt, 'billed_seconds' => $billedSeconds, 'billed_cents' => $cents - max(0, $remaining)])->save();
        foreach ([$call->caller_id, $call->callee_id] as $uid) {
            Realtime::publish($uid, 'call.ended', ['call_id' => $call->id, 'reason' => $reason]);
        }
    }

    /** A call that never became active: release every hold, mark it, and tell the people involved. */
    private function finishUnanswered(Call $call, string $state, string $reason): void
    {
        if ($call->state !== Call::RINGING) {
            return;
        }
        for ($i = 0; $i < $call->hold_count; $i++) {
            $this->meter->release($this->holdRef($call, $i));
        }
        $call->forceFill(['state' => $state, 'end_reason' => $reason, 'ended_at' => now()])->save();
        Realtime::publish($call->caller_id, 'call.ended', ['call_id' => $call->id, 'reason' => $reason]);
        if (! $call->suppressed) {
            Realtime::publish($call->callee_id, 'call.ended', ['call_id' => $call->id, 'reason' => $reason]);
            if ($state === 'missed') {
                $callee = User::query()->find($call->callee_id);
                $caller = User::query()->find($call->caller_id);
                if ($callee && $caller) {
                    $callee->notify(new N2NMissedCall($caller->name ?: 'Someone', $caller->username ? url('/u/'.$caller->username) : url('/n2n/calls')));
                }
            }
        }
    }

    private function inCall(int $userId, ?int $exceptId = null): bool
    {
        return Call::query()->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->whereIn('state', [Call::RINGING, Call::ACTIVE])
            ->where(fn ($q) => $q->where('caller_id', $userId)->orWhere('callee_id', $userId))
            ->where(fn ($q) => $q->where('state', Call::ACTIVE)->orWhere('ring_expires_at', '>', now()))->exists();
    }

    /** The call, only if $user is a participant (the same error for missing and not-yours). */
    private function mine(User $user, int $callId, bool $lock = false): Call
    {
        $q = Call::query()->whereKey($callId);
        $call = ($lock ? $q->lockForUpdate() : $q)->first();
        if ($call === null || ! $call->isParticipant($user->id)) {
            throw new CallException('not_found', 'That call was not found.');
        }

        return $call;
    }

    private function view(Call $call): array
    {
        // seconds_left is only known once the next credit slice could not be taken (ends_at); until then the cover is rolling.
        $left = $call->state === Call::ACTIVE && $call->ends_at !== null ? max(0, (int) now()->diffInSeconds($call->ends_at, false)) : null;

        return [
            'state' => $call->state, 'reason' => $call->end_reason, 'seconds_left' => $left, 'warn' => $left !== null && $left <= N2NSettings::callWarnSeconds(),
            'elapsed' => $call->answered_at ? (int) $call->answered_at->diffInSeconds($call->ended_at ?? now(), true) : 0,
        ];
    }

    private function pushIfOffline(User $callee, User $caller, Call $call): void
    {
        $seen = \App\Models\N2N\Presence::query()->where('user_id', $callee->id)->value('last_seen_at');
        if ($seen === null || now()->diffInSeconds($seen) > 90) {
            \App\Jobs\SendWebPushJob::dispatch($callee->id, ['title' => ($caller->name ?: 'Someone').' is calling you', 'body' => 'Open Naara to answer.', 'url' => url('/n2n/chat?call='.$call->id), 'tag' => 'n2n-call-'.$call->id]);
        }
    }
}
