<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The pipeline's proof channel (MASTER-ONLY, blueprint Phase 1): a made-up provider with a signed JSON webhook, so duplicates, out-of-order statuses, media,
 * failed sends and tenant isolation can be tested end to end with no external account. Registered only while tests run or `omni.fake` is on.
 *
 * Webhook body: {"id":"evt1","events":[{"type":"message","from":"c-1","mid":"m1","text":"Hi","name":"Ada","phone":"+2348000000001"},{"type":"status","from":"c-1","mid":"m9","status":"delivered"}]}
 * Signature: header X-Fake-Signature = hex hmac sha256 of the raw body with the connection's webhook secret.
 */
final class FakeChannelAdapter implements ChannelAdapter
{
    /** @var list<array{to:string,text:?string,key:?string}> */
    public static array $sent = [];

    public function channel(): string
    {
        return 'fake';
    }

    public function verify(Request $request, ChannelConnection $connection): bool
    {
        $secret = (string) $connection->webhook_secret;
        $given = (string) $request->header('X-Fake-Signature', '');
        if ($secret === '' || $given === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $given);
    }

    public function eventId(array $payload): string
    {
        return (string) ($payload['id'] ?? sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $out = [];
        foreach ((array) ($payload['events'] ?? []) as $e) {
            $out[] = new NormalizedEvent(
                type: ($e['type'] ?? '') === 'status' ? NormalizedEvent::STATUS : NormalizedEvent::MESSAGE,
                externalId: (string) ($e['from'] ?? ''),
                providerMessageId: isset($e['mid']) ? (string) $e['mid'] : null,
                text: $e['text'] ?? null,
                media: (array) ($e['media'] ?? []),
                name: $e['name'] ?? null,
                phone: $e['phone'] ?? null,
                email: $e['email'] ?? null,
                status: $e['status'] ?? null,
                error: $e['error'] ?? null,
                at: isset($e['at']) ? Carbon::parse($e['at']) : null,
            );
        }

        return $out;
    }

    public function requiresWindow(): bool
    {
        return true;
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        if (($connection->credentials['fail'] ?? false) === true) {
            throw new ChannelSendException('The fake provider refused this message.');
        }
        self::$sent[] = ['to' => $draft->externalId, 'text' => $draft->text, 'key' => $draft->idempotencyKey];

        return 'fake-'.count(self::$sent);
    }
}
