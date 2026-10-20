<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\Attachment;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Identity\ResolveThrottled;
use App\Services\N2N\Media\MediaPolicy;
use App\Services\N2N\Media\MediaService;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\LiveLocations;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The composer's send endpoint (N2N, MASTER-ONLY): multipart `meta` JSON + files, `Idempotency-Key`, session-cookie auth (the contract of
 * Chat Composer Pro). Media needs Naara Pro and is checked, measured and sanitised on the server; a refused send removes the uploads it staged.
 */
class MessagingController extends Controller
{
    /** POST /n2n/api/conversations/{conversationId}/messages  and  POST /n2n/api/messages?to= (new chat). */
    public function send(Request $request, MessageService $svc, MediaService $media, ?int $conversationId = null): JsonResponse
    {
        $meta = $request->has('meta') ? (array) json_decode((string) $request->input('meta'), true) : $request->all();
        $clientId = (string) ($request->header('Idempotency-Key') ?: ($meta['clientId'] ?? $meta['client_id'] ?? ''));
        $text = (string) ($meta['text'] ?? $meta['body'] ?? '');
        $target = $conversationId !== null ? ['conversation_id' => $conversationId] : ['to' => (string) ($request->query('to') ?? $meta['to'] ?? '')];
        $user = $request->user();

        $files = array_values(array_filter(array_merge((array) $request->file('files', []), $request->hasFile('voice') ? [$request->file('voice')] : [])));
        $staged = [];

        // Scheduled send (composer "Schedule send"): queued now, delivered by n2n:send-scheduled through the ordinary send path. Files are staged now
        // (Pro, sniffing and size caps apply immediately) and held until delivery; nothing is charged until then.
        $at = isset($meta['scheduledAt']) && is_numeric($meta['scheduledAt']) && (float) $meta['scheduledAt'] > 0 ? \Illuminate\Support\Carbon::createFromTimestampMs((int) $meta['scheduledAt']) : null;
        if ($at !== null && $at->gt(now()->subMinutes(5))) {
            try {
                if ($conversationId === null) {
                    throw new MessagingException('invalid', 'Send a first message before scheduling more.');
                }
                if (is_array($meta['live'] ?? null)) {
                    throw new MessagingException('invalid', 'Live location starts when you send it. It cannot be scheduled.');
                }
                if ($files !== []) {
                    if (! NaaraPro::active($user)) {
                        throw new MessagingException('pro_required', 'Unlock photos, videos and voice notes with Naara Pro.');
                    }
                    if (count($files) > MediaPolicy::maxPerMessage()) {
                        throw new MessagingException('invalid', 'You can attach up to '.MediaPolicy::maxPerMessage().' files to one message.');
                    }
                    foreach ($files as $file) {
                        $isVoice = $request->hasFile('voice') && $file === $request->file('voice');
                        $staged[] = $media->stage($user, $file, $isVoice ? 'voice' : null, $isVoice ? (int) ($meta['voice']['duration'] ?? 0) : null, $isVoice ? ($meta['voice']['transcript'] ?? null) : null, null, $isVoice ? ($meta['voice']['peaks'] ?? null) : null, $file->getClientOriginalName())->id;
                    }
                }
                $row = app(\App\Services\N2N\Messaging\ScheduledSend::class)->create($user, $conversationId, $clientId, $text, $at, (int) ($meta['reply_to_id'] ?? 0) ?: null, array_merge($staged, array_map('intval', (array) ($meta['attachment_ids'] ?? []))));
            } catch (MessagingException $e) {
                $this->discard($media, $staged);

                return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
            }

            return response()->json(['scheduled' => true, 'id' => $row->id, 'client_id' => $clientId, 'send_at' => $row->send_at->toIso8601String()], 201);
        }
        try {
            if ($files !== []) {
                if (! NaaraPro::active($user)) {
                    throw new MessagingException('pro_required', 'Unlock photos, videos and voice notes with Naara Pro.');   // before any byte is read
                }
                if (count($files) > MediaPolicy::maxPerMessage()) {
                    throw new MessagingException('invalid', 'You can attach up to '.MediaPolicy::maxPerMessage().' files to one message.');
                }
                foreach ($files as $i => $file) {
                    $isVoice = $request->hasFile('voice') && $file === $request->file('voice');
                    $staged[] = $media->stage($user, $file, $isVoice ? 'voice' : null, $isVoice ? (int) ($meta['voice']['duration'] ?? 0) : null, $isVoice ? ($meta['voice']['transcript'] ?? null) : null, null, $isVoice ? ($meta['voice']['peaks'] ?? null) : null, $file->getClientOriginalName())->id;
                }
            }
            $r = $svc->send($user, $target, $clientId, $text, $request->ip(), array_merge($staged, array_map('intval', (array) ($meta['attachment_ids'] ?? []))), null, (int) ($meta['reply_to_id'] ?? 0) ?: null, is_array($meta['poll'] ?? null) ? $meta['poll'] : null, is_array($meta['live'] ?? null) ? $meta['live'] : null, is_array($meta['event'] ?? null) ? $meta['event'] : null, ! empty($meta['silent']), isset($meta['disappear_after']) && is_numeric($meta['disappear_after']) ? (int) $meta['disappear_after'] : null);
        } catch (InsufficientUsageCredit $e) {
            $this->discard($media, $staged);

            return response()->json([
                'error' => 'insufficient_credit', 'message' => 'You need more NaaraCredit to send this.',
                'needed' => Money::credits($e->neededCents), 'available' => Money::credits($e->availableCents),
                'actions' => ['buy' => route('n2n.credits'), 'ask' => route('n2n.credits').'#share'],
            ], 402);
        } catch (MessagingException $e) {
            $this->discard($media, $staged);

            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()] + ($e->reason === 'pro_required' ? ['actions' => ['upgrade' => route('brand.get-listed')]] : []), $e->status());
        } catch (ResolveThrottled $e) {
            $this->discard($media, $staged);

            return response()->json(['error' => 'rate_limited', 'message' => $e->getMessage()], 429);
        }

        return response()->json([
            'id' => $r['message']->id, 'conversation_id' => $r['conversation']->id, 'client_id' => $clientId, 'status' => $r['conversation']->status,
            'delivered' => true,   // a blocked or disallowed send looks exactly like a delivered one: no oracle
            'cost' => Money::credits(\App\Services\N2N\Credit\Money::cents($r['message']->getRawOriginal('cost_credits'))),
        ], $r['replay'] ? 200 : 201);
    }

    /** POST /n2n/api/media/quote {bytes}: the cost preview shown BEFORE sending, from the same pricing the charge uses. */
    public function quote(Request $request, MediaService $media): JsonResponse
    {
        $bytes = (int) $request->validate(['bytes' => 'required|integer|min:1|max:'.(MediaPolicy::maxBytes('video') * 2)])['bytes'];

        return response()->json($media->quote($bytes) + ['pro' => NaaraPro::active($request->user())]);
    }

    /** POST /n2n/api/media/presign {kind, bytes}: a presigned upload URL on an object store; `{mode: direct}` where the disk cannot presign. */
    public function presign(Request $request, MediaService $media): JsonResponse
    {
        $d = $request->validate(['kind' => 'required|in:image,voice,video', 'bytes' => 'required|integer|min:1']);
        try {
            if (! NaaraPro::active($request->user())) {
                throw new MessagingException('pro_required', 'Unlock photos, videos and voice notes with Naara Pro.');
            }

            return response()->json($media->presign($request->user(), $d['kind'], (int) $d['bytes']));
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }
    }

    /** POST /n2n/api/media/finalize {id, duration?, transcript?}: verify the uploaded object (real type, size) before it can be sent. */
    public function finalize(Request $request, MediaService $media): JsonResponse
    {
        $d = $request->validate(['id' => 'required|integer', 'duration' => 'nullable|integer|min:0', 'transcript' => 'nullable|string|max:4000']);
        try {
            $a = $media->finalize($request->user(), (int) $d['id'], $d['duration'] ?? null, $d['transcript'] ?? null);
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }

        return response()->json(['id' => $a->id, 'kind' => $a->kind, 'bytes' => $a->bytes] + $media->quote((int) $a->bytes));
    }

    private function discard(MediaService $media, array $ids): void
    {
        foreach (Attachment::query()->whereIn('id', $ids)->where('status', Attachment::STAGED)->get() as $a) {
            $media->deleteObjects($a);
        }
    }

    /** POST /n2n/api/live/{messageId}/update {lat, lng, acc?}: the sharer's device reports a point. `{active:false}` tells the page to stop watching. */
    public function liveUpdate(Request $request, LiveLocations $live, int $messageId): JsonResponse
    {
        $d = $request->validate(['lat' => 'required|numeric', 'lng' => 'required|numeric', 'acc' => 'nullable|numeric']);
        try {
            return response()->json(['active' => $live->update($request->user(), $messageId, $d['lat'], $d['lng'], $d['acc'] ?? null)]);
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }
    }

    /** POST /n2n/api/live/{messageId}/stop */
    public function liveStop(Request $request, LiveLocations $live, int $messageId): JsonResponse
    {
        abort_unless($live->stop($request->user(), $messageId), 404);

        return response()->json(['active' => false]);
    }

    /** GET /n2n/api/live/{messageId}: what this member may see of that live location right now (members of the chat only). */
    public function liveRead(Request $request, LiveLocations $live, int $messageId): JsonResponse
    {
        try {
            return response()->json($live->read($request->user(), $messageId))->header('Cache-Control', 'no-store, private');
        } catch (MessagingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], $e->status());
        }
    }
}
