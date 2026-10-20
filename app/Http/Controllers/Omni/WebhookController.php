<?php

namespace App\Http\Controllers\Omni;

use App\Http\Controllers\Controller;
use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniRawEvent;
use App\Services\Omni\AdapterRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * POST /webhooks/omni/{channel}/{publicId} (MASTER-ONLY). Verify, store the raw body, answer 200, process later: no provider call and no heavy work in the
 * request. An unknown connection is a plain 404, a bad signature a 403 that stores nothing, a replay a 200 that stores nothing.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, AdapterRegistry $registry, string $channel, string $publicId)
    {
        $adapter = $registry->for($channel);
        if ($request->isMethod('get')) {
            return $this->handshake($request, $adapter, $channel, $publicId);
        }
        $connection = $adapter ? ChannelConnection::query()->where('public_id', $publicId)->where('channel', $channel)->first() : null;
        abort_unless($connection && $connection->isActive(), 404);
        abort_unless($adapter->verify($request, $connection), 403);

        $payload = json_decode($request->getContent(), true);
        abort_unless(is_array($payload), 400);
        try {
            $raw = OmniRawEvent::query()->create([
                'connection_id' => $connection->id, 'provider_event_id' => substr($adapter->eventId($payload), 0, 190), 'payload' => $payload,
                'status' => 'pending', 'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }
        $connection->forceFill(['last_event_at' => now()])->save();
        ProcessOmniRawEvent::dispatch($raw->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Meta's webhook set-up handshake (WhatsApp, Instagram, Messenger): GET with hub.mode=subscribe, hub.verify_token and hub.challenge. The challenge is echoed back
     * only when the token is THIS connection's own, or (for a number connected through Naara's Embedded Signup) Naara's platform verify token.
     */
    private function handshake(Request $request, ?\App\Services\Omni\ChannelAdapter $adapter, string $channel, string $publicId)
    {
        abort_unless($adapter && in_array($channel, [ChannelConnection::WHATSAPP, ChannelConnection::INSTAGRAM, ChannelConnection::MESSENGER], true), 404);
        $connection = ChannelConnection::query()->where('public_id', $publicId)->where('channel', $channel)->first();
        abort_unless($connection !== null, 404);
        $given = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $own = (string) $connection->webhook_secret;
        $platform = (string) config('omni.meta.verify_token', '');
        $ok = $given !== '' && $request->query('hub_mode', $request->query('hub.mode')) === 'subscribe'
            && (($own !== '' && hash_equals($own, $given)) || ($platform !== '' && hash_equals($platform, $given)));
        abort_unless($ok, 403);

        return response((string) $request->query('hub_challenge', $request->query('hub.challenge', '')), 200)->header('Content-Type', 'text/plain');
    }
}
