<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use App\Services\Omni\WebChatToken;
use Illuminate\Http\Request;

/**
 * Web chat (MASTER-ONLY, blueprint Phase 2): the business's own site is the "provider". The visitor's browser talks to our public API with a signed visitor
 * token; there is nothing to send to, so an outbound message is simply stored as sent and the visitor's widget picks it up on its next poll.
 * Payload shape (built by WebChatController, never by the browser directly): {"visitor":"<id>","mid":"<client uuid>","text":"...","name":"...","email":"..."}.
 */
final class WebChatAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return ChannelConnection::WEBCHAT;
    }

    public function verify(Request $request, ChannelConnection $connection): bool
    {
        return WebChatToken::visitor($connection, $request->bearerToken()) !== null;
    }

    public function eventId(array $payload): string
    {
        return 'wc:'.(string) ($payload['mid'] ?? sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $visitor = (string) ($payload['visitor'] ?? '');
        $text = trim((string) ($payload['text'] ?? ''));
        if ($visitor === '' || $text === '') {
            return [];
        }
        $email = filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;

        return [new NormalizedEvent(
            type: NormalizedEvent::MESSAGE, externalId: $visitor, providerMessageId: (string) ($payload['mid'] ?? ''), text: mb_substr($text, 0, 2000),
            name: ($n = trim((string) ($payload['name'] ?? ''))) !== '' ? mb_substr($n, 0, 120) : null, email: $email,
        )];
    }

    public function requiresWindow(): bool
    {
        return false;   // the visitor is on the site: no 24-hour rule
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        return 'wc-'.bin2hex(random_bytes(8));   // delivered by the visitor's next poll
    }
}
