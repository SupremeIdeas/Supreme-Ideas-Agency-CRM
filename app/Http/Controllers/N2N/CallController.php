<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\Call;
use App\Services\N2N\Calls\CallException;
use App\Services\N2N\Calls\CallService;
use App\Services\N2N\Calls\CallsAvailability;
use App\Services\N2N\Calls\TurnCredentials;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Support\N2N\N2NSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Call signalling endpoints (N2N Batch 10, MASTER-ONLY). Plain numeric ids (no model binding), so a missing call and a call that is not yours
 * answer identically; every action is authorised against the two participants inside CallService. The ICE servers (with time-limited TURN
 * credentials) are issued per call and per participant, never embedded in JavaScript.
 */
class CallController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $s = CallsAvailability::status();

        return response()->json(['enabled' => $s['enabled'], 'video' => CallsAvailability::videoEnabled(), 'heartbeat_s' => N2NSettings::callHeartbeatSeconds(), 'warn_s' => N2NSettings::callWarnSeconds(), 'ring_s' => N2NSettings::callRingSeconds()]);
    }

    public function start(Request $request, CallService $calls): JsonResponse
    {
        return $this->run(function () use ($request, $calls) {
            $call = $calls->start($request->user(), (string) $request->input('to', ''), $request->ip(), $request->boolean('video'));

            return $this->payload($call, $request->user()->id) + ['ok' => true];
        });
    }

    public function accept(Request $request, CallService $calls, int $callId): JsonResponse
    {
        return $this->run(fn () => $this->payload($calls->accept($request->user(), $callId), $request->user()->id) + ['ok' => true]);
    }

    public function decline(Request $request, CallService $calls, int $callId): JsonResponse
    {
        return $this->run(function () use ($request, $calls, $callId) {
            $calls->decline($request->user(), $callId);

            return ['ok' => true];
        });
    }

    public function hangup(Request $request, CallService $calls, int $callId): JsonResponse
    {
        return $this->run(function () use ($request, $calls, $callId) {
            $calls->hangup($request->user(), $callId, $request->has('relay') ? $request->boolean('relay') : null);

            return ['ok' => true];
        });
    }

    public function signal(Request $request, CallService $calls, int $callId): JsonResponse
    {
        return $this->run(function () use ($request, $calls, $callId) {
            $calls->signal($request->user(), $callId, (string) $request->input('kind', ''), $request->input('data'));

            return ['ok' => true];
        });
    }

    public function heartbeat(Request $request, CallService $calls, int $callId): JsonResponse
    {
        return $this->run(fn () => $calls->heartbeat($request->user(), $callId) + ['ok' => true]);
    }

    /** A fresh set of ICE servers for an ICE restart (network change) in a call the user is part of. */
    public function ice(Request $request, int $callId): JsonResponse
    {
        $call = Call::query()->find($callId);
        abort_if($call === null || ! $call->isParticipant($request->user()->id) || ! $call->live(), 404);

        return response()->json(['ice' => TurnCredentials::iceServers($request->user()->id, $call)]);
    }

    /** Should the after-call rating sheet be shown for this call? (404 when the call is not yours, exactly like any other call route.) */
    public function reviewPrompt(Request $request, \App\Services\N2N\Calls\CallReviewPrompt $review, int $callId): JsonResponse
    {
        $call = $this->mineOr404($request, $callId);

        return response()->json(['show' => $review->eligible($request->user(), $call), 'video' => (bool) $call->video]);
    }

    public function reviewRate(Request $request, \App\Services\N2N\Calls\CallReviewPrompt $review, int $callId): JsonResponse
    {
        $call = $this->mineOr404($request, $callId);
        $stars = (int) $request->validate(['stars' => ['required', 'integer', 'min:1', 'max:5']])['stars'];
        abort_unless($review->eligible($request->user(), $call), 422, 'This call can no longer be rated.');

        return response()->json(['ok' => true] + $review->rate($request->user(), $call, $stars));
    }

    public function reviewDismiss(Request $request, \App\Services\N2N\Calls\CallReviewPrompt $review, int $callId): JsonResponse
    {
        $review->dismiss($request->user(), $this->mineOr404($request, $callId));

        return response()->json(['ok' => true]);
    }

    public function reviewOpened(Request $request, \App\Services\N2N\Calls\CallReviewPrompt $review, int $callId): JsonResponse
    {
        $review->opened($request->user(), $this->mineOr404($request, $callId));

        return response()->json(['ok' => true]);
    }

    private function mineOr404(Request $request, int $callId): Call
    {
        $call = Call::query()->find($callId);
        abort_if($call === null || ! $call->isParticipant($request->user()->id), 404);

        return $call;
    }

    private function payload(Call $call, int $userId): array
    {
        return ['call' => ['id' => $call->id, 'state' => $call->state, 'video' => (bool) $call->video, 'role' => $call->caller_id === $userId ? 'caller' : 'callee', 'peer' => $this->peer($call, $userId), 'reason' => $call->end_reason],
            'ice' => $call->live() ? TurnCredentials::iceServers($userId, $call) : [], 'heartbeat_s' => N2NSettings::callHeartbeatSeconds()];
    }

    private function peer(Call $call, int $userId): array
    {
        $p = \App\Models\User::query()->select('id', 'name', 'username', 'naara_id')->find($call->peerOf($userId));

        return ['name' => $p?->name, 'username' => $p?->username, 'naara_id' => $p?->naara_id];
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (InsufficientUsageCredit $e) {
            return response()->json(['ok' => false, 'error' => 'insufficient_credit', 'message' => 'You need more NaaraCredit to place this call.', 'actions' => ['buy' => route('n2n.credits'), 'ask' => route('n2n.people')]], 402);
        } catch (CallException $e) {
            $status = ['not_found' => 404, 'gone' => 410, 'rate_limited' => 429, 'unavailable' => 503][$e->reason] ?? 422;

            return response()->json(['ok' => false, 'error' => $e->reason, 'message' => $e->getMessage()], $status);
        }
    }
}
