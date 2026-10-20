<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `contacts:read`: the people a workspace has conversations with (counterparts of its message threads). Identifiers only; message text is `messages:read`. */
class ContactsController extends BusinessApiController
{
    public function index(Request $request): JsonResponse
    {
        $g = $this->workspace($request);
        $lines = $this->lineIds($g);
        $limit = $this->limit($request);

        $rows = MessageThread::query()
            ->where(fn ($q) => $q->where('wallet_group_id', $g->id)->orWhereIn('virtual_number_id', $lines ?: [0]))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', (string) $request->query('channel')))
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->query('before')))
            ->orderByDesc('id')->limit($limit + 1)->get();

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return response()->json([
            'data' => $rows->map(fn (MessageThread $t) => [
                'id' => $t->id, 'channel' => $t->channel ?: 'sms', 'counterpart' => $t->counterpart_number,
                'last_activity_at' => optional($t->last_at)->toIso8601String(), 'last_direction' => $t->last_direction, 'opted_out' => $t->opted_out_at !== null,
            ])->values(),
            'next_before' => $more ? $rows->last()->id : null,
        ]);
    }
}
