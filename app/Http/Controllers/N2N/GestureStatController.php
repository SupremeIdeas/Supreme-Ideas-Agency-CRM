<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Services\N2N\Messaging\ChatGesturePrefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Gesture counters (N2N, MASTER-ONLY): anonymous totals per day, gesture, outcome and device class. No user id, no message content, no timestamps finer than a day. */
class GestureStatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rows = $request->input('s');
        if (! is_array($rows)) {
            return response()->json(['ok' => false], 422);
        }
        $day = now()->toDateString();
        foreach (array_slice($rows, 0, 24) as $r) {
            $g = $r['g'] ?? null;
            $o = $r['o'] ?? null;
            $d = ($r['d'] ?? 'touch') === 'mouse' ? 'mouse' : 'touch';
            $n = max(1, min(50, (int) ($r['n'] ?? 1)));
            if (! in_array($g, ChatGesturePrefs::GESTURES, true) || ! in_array($o, ChatGesturePrefs::OUTCOMES, true)) {
                continue;
            }
            DB::table('n2n_gesture_stats')->upsert([['day' => $day, 'gesture' => $g, 'outcome' => $o, 'device' => $d, 'n' => $n]], ['day', 'gesture', 'outcome', 'device'], ['n' => DB::raw('n2n_gesture_stats.n + '.$n)]);
        }

        return response()->json(['ok' => true]);
    }
}
