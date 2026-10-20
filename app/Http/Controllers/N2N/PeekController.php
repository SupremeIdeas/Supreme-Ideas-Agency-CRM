<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Support\N2N\ChatFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "peek" card (N2N, MASTER-ONLY): the last few messages of a chat, shown when a member presses and holds a chat row, so they can read it without opening it (and
 * without marking anything read). Read-only, plain text only (never HTML), media shown as a label, and only for a participant: a missing chat and a not-yours chat answer
 * identically. A locked chat is never previewed; a message the member hid, cleared or lost to disappearing is not in the card (Message::scopeForViewer).
 */
class PeekController extends Controller
{
    private const COUNT = 5;

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::query()->find($conversationId);
        abort_if($conversation === null || ! $conversation->isParticipant($user->id), 404);
        $part = Participant::query()->where('conversation_id', $conversationId)->where('user_id', $user->id)->first();
        abort_if($part === null, 404);
        if ($part->locked_at !== null) {
            return response()->json(['locked' => true, 'messages' => []])->header('Cache-Control', 'private, no-store');
        }

        $rows = Message::query()->forViewer($user->id)->where('conversation_id', $conversationId)
            ->orderByDesc('id')->limit(self::COUNT)->get(['id', 'sender_id', 'type', 'body', 'deleted_for_all_at', 'created_at']);

        $labels = ['image' => 'Photo', 'voice' => 'Voice message', 'video' => 'Video', 'audio' => 'Audio file', 'doc' => 'Document', 'live' => 'Live location', 'event' => 'Event', 'poll' => 'Poll'];
        $messages = $rows->reverse()->values()->map(function (Message $m) use ($user, $labels) {
            $text = $m->deleted_for_all_at !== null ? 'Message deleted' : (trim((string) $m->body) !== '' ? mb_substr(ChatFormat::plain((string) $m->body), 0, 220) : ($labels[$m->type] ?? 'Message'));

            return ['mine' => (int) $m->sender_id === $user->id, 'text' => $text, 'at' => $m->created_at?->diffForHumans(null, true, true), 'deleted' => $m->deleted_for_all_at !== null];
        })->all();

        return response()->json(['locked' => false, 'messages' => $messages])->header('Cache-Control', 'private, no-store');
    }
}
