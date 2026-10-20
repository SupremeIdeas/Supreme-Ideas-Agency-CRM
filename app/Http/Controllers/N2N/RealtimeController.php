<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Realtime\PresenceService;
use App\Services\N2N\Realtime\Realtime;
use App\Services\N2N\Realtime\RealtimeHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The browser's one realtime contact point (N2N, MASTER-ONLY): config once, then sync (poll driver / replay), ping, typing. */
class RealtimeController extends Controller
{
    /** GET /n2n/realtime/config: the client asks once and picks its transport. Never returns the Reverb secret. */
    public function config(Request $request): JsonResponse
    {
        $driver = Realtime::driver();
        $r = config('n2n.realtime.reverb');
        $out = [
            'driver' => $driver,
            'cursor' => Realtime::latestCursor($request->user()->id),
            'user' => $request->user()->id,
            'poll' => [
                'visible_ms' => max(1000, (int) Setting::getValue('n2n.poll_visible_ms', 3000)),
                'hidden_ms' => max(5000, (int) Setting::getValue('n2n.poll_hidden_ms', 30000)),
                'max_backoff_ms' => 60000,
            ],
        ];
        if ($driver === 'reverb') {
            $out['reverb'] = [
                'key' => $r['key'], 'host' => $r['public_host'] ?: $request->getHost(), 'port' => $r['public_port'], 'scheme' => $r['public_scheme'],
                'channel' => 'n2n.user.'.$request->user()->id, 'event' => '.n2n.event',
            ];
        }

        return response()->json($out)->header('Cache-Control', 'no-store');
    }

    /** GET /n2n/realtime/sync?cursor=: events since the cursor, oldest first. One request per interval, not one per feature. */
    public function sync(Request $request): JsonResponse
    {
        $cursor = max(0, (int) $request->query('cursor', 0));
        RealtimeHealth::notePoll();
        $batch = Realtime::since($request->user()->id, $cursor);
        $last = $batch['events'] === [] ? $cursor : end($batch['events'])['id'];

        return response()->json($batch + ['cursor' => $last])->header('Cache-Control', 'no-store');
    }

    /** POST /n2n/realtime/ping: presence heartbeat. */
    public function ping(Request $request, PresenceService $presence): JsonResponse
    {
        $presence->touch($request->user());

        return response()->json(['ok' => true]);
    }

    /** POST /n2n/realtime/typing {to}: ephemeral typing indicator to a person the sender addressed by Naara ID or username. */
    public function typing(Request $request, PresenceService $presence, IdentityResolver $resolver): JsonResponse
    {
        $data = $request->validate(['to' => 'required|string|max:60', 'context' => 'nullable|string|max:40']);
        $hit = $resolver->resolve($data['to'], 'deliver');
        $target = $hit->found ? User::query()->find($hit->userId) : null;
        // Always the same reply, whether or not the target exists or hides: typing is never an oracle.
        if ($target !== null && $target->id !== $request->user()->id) {
            $presence->typing($request->user(), $target, (string) ($data['context'] ?? ''));
        }

        return response()->json(['ok' => true]);
    }

    /** GET /n2n/realtime/seen/{naaraId}: last seen, subject to both sides' privacy switch. */
    public function seen(Request $request, string $naaraId, PresenceService $presence, IdentityResolver $resolver): JsonResponse
    {
        $hit = $resolver->resolve($naaraId, 'deliver');
        $target = $hit->found ? User::query()->find($hit->userId) : null;

        return response()->json($target ? $presence->seenBy($request->user(), $target) : ['visible' => false, 'online' => false, 'last_seen' => null]);
    }
}
