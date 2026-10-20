<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\CallLink;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Group\GroupAvailability;
use App\Services\N2N\Group\GroupCallException;
use App\Services\N2N\Group\GroupCallService;
use App\Support\N2N\N2NSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * Group-call endpoints (N2N Batch 11, MASTER-ONLY). Plain numeric ids (no model binding): a missing room and someone else's room answer
 * identically. Every action is authorised against the room's host or the member themselves inside GroupCallService, never trusted from the page.
 */
class GroupCallController extends Controller
{
    public function config(): JsonResponse
    {
        $s = GroupAvailability::status();

        return response()->json(['enabled' => $s['enabled'], 'heartbeat_s' => N2NSettings::callHeartbeatSeconds(), 'warn_s' => N2NSettings::callWarnSeconds(), 'max_speakers' => N2NSettings::groupMaxSpeakers()]);
    }

    public function create(Request $request, GroupCallService $svc): JsonResponse
    {
        $data = $request->validate(['title' => 'required|string|max:120', 'capacity' => 'nullable|integer|min:2|max:100', 'requires_approval' => 'nullable|boolean']);

        return $this->run(function () use ($svc, $request, $data) {
            ['call' => $call, 'link' => $link] = $svc->create($request->user(), $data['title'], $data['capacity'] ?? null, (bool) ($data['requires_approval'] ?? false));

            return ['ok' => true, 'call_id' => $call->id, 'link' => GroupCallService::urlFor($link), 'room_url' => route('n2n.group.room', $call->id)];
        });
    }

    /** POST /n2n/api/group/join {token}: the member is signed in (route middleware); the answer carries their connection details or "waiting for the host". */
    public function join(Request $request, GroupCallService $svc): JsonResponse
    {
        $token = (string) $request->validate(['token' => 'required|string|size:40'])['token'];

        return $this->run(function () use ($svc, $request, $token) {
            $r = $svc->join($request->user(), $token);

            return ['ok' => true, 'call_id' => $r['call']->id, 'pending' => $r['pending'], 'connect' => $r['connect'], 'room_url' => route('n2n.group.room', $r['call']->id)];
        });
    }

    public function status(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(fn () => ['ok' => true] + $svc->myStatus($request->user(), $callId));
    }

    public function state(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(fn () => ['ok' => true] + $svc->state($request->user(), $callId));
    }

    public function heartbeat(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(fn () => ['ok' => true] + $svc->heartbeat($request->user(), $callId));
    }

    public function appoint(Request $request, GroupCallService $svc, int $callId, int $userId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId, $userId) {
            $svc->appoint($request->user(), $callId, $userId);

            return ['ok' => true];
        });
    }

    public function demote(Request $request, GroupCallService $svc, int $callId, int $userId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId, $userId) {
            $svc->demote($request->user(), $callId, $userId);

            return ['ok' => true];
        });
    }

    public function mute(Request $request, GroupCallService $svc, int $callId, int $userId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId, $userId) {
            $svc->mute($request->user(), $callId, $userId);

            return ['ok' => true];
        });
    }

    public function remove(Request $request, GroupCallService $svc, int $callId, int $userId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId, $userId) {
            $svc->remove($request->user(), $callId, $userId);

            return ['ok' => true];
        });
    }

    public function approve(Request $request, GroupCallService $svc, int $callId, int $userId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId, $userId) {
            $svc->approve($request->user(), $callId, $userId, $request->boolean('allow', true));

            return ['ok' => true];
        });
    }

    public function leave(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId) {
            $svc->leave($request->user(), $callId);

            return ['ok' => true];
        });
    }

    public function close(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId) {
            $svc->close($request->user(), $callId);

            return ['ok' => true];
        });
    }

    public function newLink(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId) {
            $link = $svc->newLink($request->user(), $callId, $request->has('requires_approval') ? $request->boolean('requires_approval') : null);

            return ['ok' => true, 'link' => GroupCallService::urlFor($link)];
        });
    }

    public function revokeLinks(Request $request, GroupCallService $svc, int $callId): JsonResponse
    {
        return $this->run(function () use ($svc, $request, $callId) {
            $svc->revokeLinks($request->user(), $callId);

            return ['ok' => true];
        });
    }

    /**
     * GET /n2n/call/{token}: the page a call link opens. A signed-in member goes straight to joining; a visitor sees who is calling and is sent
     * through sign-up carrying the HOST's referral code (the growth path), then back here.
     */
    public function landing(Request $request, GroupCallService $svc, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $info = $svc->preview($token);
        if ($info === null) {
            return response()->view('n2n.group-link', ['info' => null, 'token' => $token, 'ref' => null], 404);
        }
        if ($request->user() === null) {
            $request->session()->put('url.intended', route('n2n.group.join', ['token' => $token]));
        }

        return response()->view('n2n.group-link', ['info' => $info, 'token' => $token, 'ref' => $info['host_ref']]);
    }

    /** GET /n2n/group/{callId}: the room screen. Only someone who is (or is waiting to be) in the room gets it; everyone else sees the same 404. */
    public function room(Request $request, GroupCallService $svc, int $callId): View
    {
        try {
            $state = $svc->state($request->user(), $callId);
        } catch (GroupCallException) {
            abort(404);
        }

        return view('n2n.group-room', ['callId' => $callId, 'state' => $state]);
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (InsufficientUsageCredit $e) {
            return response()->json(['ok' => false, 'error' => 'insufficient_credit', 'message' => 'You need more NaaraCredit to do that.', 'needed' => Money::credits($e->neededCents), 'available' => Money::credits($e->availableCents)], 402);
        } catch (GroupCallException $e) {
            return response()->json(['ok' => false, 'error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }
    }
}
