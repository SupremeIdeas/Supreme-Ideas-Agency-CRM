<?php

namespace App\Services\Omni\Adapters;

use App\Exceptions\SmsException;
use App\Models\ChannelConnection;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use App\Services\SMS\MessageSenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * SMS on a workspace's own number, in the omni inbox (MASTER-ONLY, blueprint Phase 4). The connection IS the number (external_id = its E.164). There is no public webhook for it:
 * the carrier's inbound SMS already arrives, signature-checked, at the existing SMS inbound webhook, which hands a copy to SmsBridge. Sending goes through the ONE existing SMS
 * pipeline (MessageSenderService: live quote, atomic charge, queued send, refund on failure), billed to the person who owns the number, exactly as a message sent from Messages.
 * Payload (built by SmsBridge, never by a browser): {"ref":"<provider ref>","from":"+234...","body":"...","media":"<url>|null","at":<unix>}.
 */
final class SmsAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return ChannelConnection::SMS;
    }

    public function verify(Request $request, ChannelConnection $connection): bool
    {
        return false;   // never reachable from outside: SmsBridge writes the raw event itself after the SMS webhook has verified the carrier
    }

    public function eventId(array $payload): string
    {
        return 'sms:'.(string) ($payload['ref'] ?? sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $from = preg_replace('/[^\d+]/', '', (string) ($payload['from'] ?? '')) ?? '';
        $body = trim((string) ($payload['body'] ?? ''));
        $media = trim((string) ($payload['media'] ?? ''));
        if ($from === '' || ($body === '' && $media === '')) {
            return [];
        }

        return [new NormalizedEvent(
            type: NormalizedEvent::MESSAGE, externalId: $from, providerMessageId: (string) ($payload['ref'] ?? '') ?: null, text: $body !== '' ? mb_substr($body, 0, 2000) : null,
            media: $media !== '' ? [['type' => 'image', 'file_id' => $media]] : [], phone: $from, at: isset($payload['at']) ? Carbon::createFromTimestamp((int) $payload['at']) : null,
        )];
    }

    public function requiresWindow(): bool
    {
        return false;   // SMS has no 24-hour rule
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        $text = trim((string) $draft->text);
        if ($text === '') {
            throw new ChannelSendException('SMS needs some text.');
        }
        $line = VirtualNumber::query()->where('phone_number', $connection->external_id)->where('wallet_group_id', $connection->wallet_group_id)->where('status', 'active')->first();
        $owner = $line ? User::query()->find($line->user_id) : null;
        if (! $line || ! $owner) {
            throw new ChannelSendException('This SMS number is no longer active on the workspace.');
        }
        try {
            $out = app(MessageSenderService::class)->send($owner, $line, $draft->externalId, $text);
        } catch (SmsException $e) {
            throw new ChannelSendException($e->getMessage());
        }

        return 'sms-'.$out->id;
    }
}
