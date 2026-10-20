<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\Attachment;
use App\Services\N2N\Messaging\ChatExport;
use App\Services\N2N\Messaging\ConversationPrefs;
use App\Services\N2N\Messaging\MessagingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-member conversation tools that need a plain HTTP response (N2N Batch 12, MASTER-ONLY): the text export download and the member's own
 * wallpaper photo (upload, serve, remove). Conversation ids are plain numbers resolved through MessageService::mine(), so a missing chat and
 * someone else's chat answer identically. Nothing here is ever shown to the other person.
 */
class ConversationToolsController extends Controller
{
    /** GET /n2n/chat/{conversationId}/export — a text file, never cached, rate-limited by the route. */
    public function export(Request $request, ChatExport $export, int $conversationId): Response
    {
        try {
            $text = $export->text($request->user(), $conversationId);
        } catch (MessagingException) {
            abort(404);
        }

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="naara-chat-'.$conversationId.'.txt"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** POST /n2n/api/conversations/{conversationId}/wallpaper (multipart `photo`). One per chat: a new photo replaces the old. */
    public function upload(Request $request, ConversationPrefs $prefs, int $conversationId): JsonResponse
    {
        $request->validate(['photo' => 'required|file|max:'.(int) ceil(\App\Services\N2N\Media\MediaPolicy::maxBytes('image') / 1024)]);
        try {
            $theme = $prefs->setPhoto($request->user(), $conversationId, $request->file('photo'));
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }

        return response()->json(['ok' => true, 'photo_id' => $theme['photo_id']]);
    }

    public function remove(Request $request, ConversationPrefs $prefs, int $conversationId): JsonResponse
    {
        try {
            $prefs->removePhoto($request->user(), $conversationId);
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }

        return response()->json(['ok' => true]);
    }

    /** GET /n2n/wallpaper/{attachment}: only the member who uploaded it; a missing and a not-yours photo are the same 404. */
    public function wallpaper(Request $request, int $attachment): Response
    {
        $a = Attachment::query()->where('id', $attachment)->where('uploader_id', $request->user()->id)->where('status', Attachment::ATTACHED)->whereNull('message_id')->first();
        abort_if($a === null || ($a->meta['purpose'] ?? null) !== 'wallpaper', 404);
        $store = Storage::disk($a->disk);
        abort_unless($store->exists($a->storage_key), 404);

        return $store->response($a->storage_key, null, [
            'Content-Type' => $a->mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }
}
