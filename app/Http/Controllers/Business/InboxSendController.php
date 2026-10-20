<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\InboxComposer;
use App\Support\Business\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Naara composer's built-in HTTP path for the shared inbox (MASTER-ONLY, Business Suite). The composer posts multipart `meta` (JSON), `files[i]` and an
 * optional `voice`, with an Idempotency-Key. A refusal comes back as 422 with a plain `message` the composer shows; the composer treats 4xx as final.
 */
class InboxSendController extends Controller
{
    public function __invoke(Request $request, InboxComposer $composer): JsonResponse
    {
        $user = $request->user();
        $ws = InboxAccess::workspaceFor($user);
        abort_unless($ws !== null, 404);

        $meta = json_decode((string) $request->input('meta', ''), true);
        if (! is_array($meta)) {
            return response()->json(['message' => 'That message could not be read.'], 422);
        }

        // convoId is "inbox-{channel}-{counterpart}"; the counterpart can itself contain dashes (a Telegram group id).
        if (! preg_match('/^inbox-(whatsapp|telegram|instagram)-(.+)$/s', (string) ($meta['chat'] ?? ''), $m)) {
            return response()->json(['message' => 'Open a WhatsApp, Telegram or Instagram conversation to send this.'], 422);
        }
        $thread = MessageThread::query()->where('wallet_group_id', $ws->id)->where('channel', $m[1])->where('counterpart_number', $m[2])->first();
        if (! $thread) {
            return response()->json(['message' => 'That conversation is not available.'], 422);
        }

        $files = array_values(array_filter((array) $request->file('files', []), fn ($f) => $f instanceof \Illuminate\Http\UploadedFile));
        $voice = $request->file('voice');
        $key = (string) ($request->header('Idempotency-Key') ?: ($meta['clientId'] ?? \Illuminate\Support\Str::uuid()));

        try {
            $result = $composer->submit($thread, $user, $meta, $files, $voice instanceof \Illuminate\Http\UploadedFile ? $voice : null, $key);
        } catch (ChannelSendException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
