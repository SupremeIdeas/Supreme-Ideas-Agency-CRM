<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Models\MasterMarketingDockPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** MASTER-ONLY. Widget Dock persistence: save or reset where the signed-in member parked their floating widgets. */
class DockController extends Controller
{
    private const MAX_WIDGETS = 24;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'positions' => 'required|array|max:'.self::MAX_WIDGETS,
            'positions.*.side' => 'required|in:left,right',
            'positions.*.y' => 'required|numeric|min:0|max:1',
            'stamp' => 'required|integer|min:0',
        ]);

        $clean = [];
        foreach ($data['positions'] as $id => $p) {
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', (string) $id)) {
                continue;
            }
            $clean[$id] = ['side' => $p['side'], 'y' => round((float) $p['y'], 4)];
        }

        MasterMarketingDockPosition::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['positions' => $clean, 'stamp' => $data['stamp']],
        );

        return response()->json(['ok' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        MasterMarketingDockPosition::where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
