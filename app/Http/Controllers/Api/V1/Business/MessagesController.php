<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Models\InboundMessage;
use App\Models\OutboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `messages:read`: the workspace's message history across channels, newest first. Read only: nothing here can send. */
class MessagesController extends BusinessApiController
{
    public function index(Request $request): JsonResponse
    {
        $g = $this->workspace($request);
        $lines = $this->lineIds($g);
        $limit = $this->limit($request);

        $scope = fn ($q, string $counterpart) => $q
            ->where(fn ($w) => $w->where('wallet_group_id', $g->id)->orWhereIn('virtual_number_id', $lines ?: [0]))
            ->when($request->filled('channel'), fn ($w) => $w->where('channel', (string) $request->query('channel')))
            ->when($request->filled('counterpart'), fn ($w) => $w->where($counterpart, (string) $request->query('counterpart')))
            ->when($request->filled('before'), fn ($w) => $w->where('created_at', '<', $request->date('before')))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit);

        $in = $scope(InboundMessage::query(), 'from_number')->get()->map(fn (InboundMessage $m) => [
            'id' => 'in_'.$m->id, 'direction' => 'in', 'channel' => $m->channel ?: 'sms', 'counterpart' => $m->from_number, 'body' => $m->body, 'at' => optional($m->received_at ?? $m->created_at)->toIso8601String(), 'sort' => ($m->received_at ?? $m->created_at)->getTimestamp(),
        ]);
        $out = $scope(OutboundMessage::query(), 'to_number')->get()->map(fn (OutboundMessage $m) => [
            'id' => 'out_'.$m->id, 'direction' => 'out', 'channel' => $m->channel ?: 'sms', 'counterpart' => $m->to_number, 'body' => $m->body, 'at' => optional($m->created_at)->toIso8601String(), 'sort' => $m->created_at->getTimestamp(),
        ]);

        $merged = $in->concat($out)->sortByDesc('sort')->take($limit)->values();

        return response()->json([
            'data' => $merged->map(fn ($m) => collect($m)->except('sort')->all())->values(),
            'next_before' => $merged->count() === $limit ? $merged->last()['at'] : null,
        ]);
    }
}
