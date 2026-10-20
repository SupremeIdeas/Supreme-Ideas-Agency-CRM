<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Models\CallEvent;
use App\Models\CallFlow;
use App\Models\CallRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `calls:read`: the workspace's call log. No audio and no transcript text (those stay behind the workspace's own owner/admin access). */
class CallsController extends BusinessApiController
{
    public function index(Request $request): JsonResponse
    {
        $g = $this->workspace($request);
        $limit = $this->limit($request);
        $flows = CallFlow::query()->where('wallet_group_id', $g->id)->pluck('id');

        $rows = CallEvent::query()->whereIn('call_flow_id', $flows)
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->query('before')))
            ->orderByDesc('id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $recordings = CallRecording::query()->where('wallet_group_id', $g->id)->whereIn('call_reference', $rows->pluck('call_sid')->filter()->all())->get()->keyBy('call_reference');

        return response()->json([
            'data' => $rows->map(function (CallEvent $e) use ($recordings) {
                $r = $recordings->get($e->call_sid);

                return [
                    'id' => $e->id, 'call_id' => $e->call_sid, 'from' => $e->from_number, 'to' => $e->to_number, 'status' => $e->status, 'at' => optional($e->created_at)->toIso8601String(),
                    'recording' => $r ? ['id' => $r->id, 'source' => $r->source, 'duration_seconds' => $r->duration_seconds, 'has_transcript' => $r->transcript_status === 'complete'] : null,
                ];
            })->values(),
            'next_before' => $more ? $rows->last()->id : null,
        ]);
    }
}
