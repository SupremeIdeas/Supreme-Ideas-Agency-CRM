<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\Attachment;
use App\Models\N2N\Message;
use App\Services\N2N\Media\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves N2N media (MASTER-ONLY): ONLY to a participant of the message's conversation, and only while the message is not deleted. On an object
 * store the answer is a redirect to a short-lived signed URL; where the disk cannot sign, the file is streamed after the same check.
 * A missing, deleted or not-yours attachment answers with the same 404 (no existence oracle). Nothing is ever at a public URL.
 */
class MediaController extends Controller
{
    public function show(Request $request, int $attachment, MediaService $media): Response
    {
        $a = Attachment::query()->where('id', $attachment)->where('status', Attachment::ATTACHED)->first();
        $m = $a?->message_id ? Message::query()->with('conversation')->find($a->message_id) : null;
        abort_if($a === null || $m === null || $m->deleted_for_all_at !== null || ($m->expires_at !== null && $m->expires_at->isPast()) || ! $m->conversation?->isParticipant($request->user()->id), 404);
        // A message dropped by a block is only ever visible to its own sender.
        abort_if($m->suppressed && (int) $m->sender_id !== $request->user()->id, 404);

        $thumb = $request->boolean('thumb') && $a->thumb_key !== null;
        $download = $request->boolean('download') || $a->kind === 'doc';   // documents never open inline in the browser
        if (($url = $media->signedUrl($a, $thumb, $download)) !== null) {
            return redirect()->away($url)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }
        $store = Storage::disk($a->disk);
        $key = $thumb ? $a->thumb_key : $a->storage_key;
        abort_unless($store->exists($key), 404);

        $headers = [
            'Content-Type' => $thumb ? 'image/jpeg' : ($a->kind === 'doc' ? 'application/octet-stream' : $a->mime), 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];
        $name = $a->meta['name'] ?? 'naara-'.$a->kind.'-'.$a->id.'.'.pathinfo($a->storage_key, PATHINFO_EXTENSION);
        // On a local disk, serve the file itself so the browser can use Range requests: a voice note or video can only be seeked (tap the waveform, scrub) when the server answers ranges.
        $local = null;
        try {
            $local = $store->path($key);
        } catch (\Throwable) {
            // an object-store disk has no local path: stream below
        }
        if (is_string($local) && is_file($local)) {
            $r = $download ? response()->download($local, $name, $headers) : response()->file($local, $headers);
            $r->setPrivate();   // a file response defaults to public: media is never shareable by a cache
            $r->headers->set('Cache-Control', 'private, no-store');

            return $r;
        }

        return $store->response($key, $download ? $name : null, $headers, $download ? 'attachment' : 'inline');
    }
}
