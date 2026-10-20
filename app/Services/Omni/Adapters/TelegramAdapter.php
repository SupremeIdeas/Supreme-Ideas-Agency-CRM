<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\TelegramBotClient;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Telegram Bot API adapter (MASTER-ONLY, blueprint Phase 2). Field names are the ones in Telegram's official Bot API reference (checked 2026-10-19):
 * Update.update_id; Update.message (Message.message_id, from {id, is_bot, first_name, last_name, username}, chat {id, type}, date, text, caption, photo[],
 * document, voice, audio, video, sticker, location, contact). Telegram has no body signature: the proof is the secret_token given to setWebhook, which comes
 * back in X-Telegram-Bot-Api-Secret-Token (1-256 chars of A-Z a-z 0-9 _ -) and is compared in constant time. A reply to a delivered update is "any 2xx".
 *
 * Only private chats are filed; a bot added to a group, a message from another bot, edited messages and every non-message update are ignored on purpose.
 * Attachments are recorded as descriptors (type, file_id, name, mime, size); downloading the file is a later step (Bot API getFile), not done here.
 */
final class TelegramAdapter implements ChannelAdapter
{
    public function __construct(private readonly TelegramBotClient $client)
    {
    }

    public function channel(): string
    {
        return ChannelConnection::TELEGRAM;
    }

    public function verify(Request $request, ChannelConnection $connection): bool
    {
        $expected = (string) $connection->webhook_secret;
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }

    public function eventId(array $payload): string
    {
        // update_id is unique per bot only; the raw-event table is unique per connection, so it is enough on its own.
        return 'tg:'.(string) ($payload['update_id'] ?? sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $m = $payload['message'] ?? null;
        if (! is_array($m)) {
            return [];   // edited_message, callback_query, my_chat_member, ... are not filed
        }
        $chat = (array) ($m['chat'] ?? []);
        $from = (array) ($m['from'] ?? []);
        if (($chat['type'] ?? '') !== 'private' || ! isset($chat['id']) || ! empty($from['is_bot'])) {
            return [];
        }
        $media = $this->media($m);
        $text = isset($m['text']) ? (string) $m['text'] : (isset($m['caption']) ? (string) $m['caption'] : null);
        if (($text === null || trim($text) === '') && $media === []) {
            return [];   // a service message (joined, pinned, ...) carries nothing to show
        }
        $name = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? ''))) ?: ($from['username'] ?? ($chat['first_name'] ?? null));

        return [new NormalizedEvent(
            type: NormalizedEvent::MESSAGE,
            externalId: (string) $chat['id'],
            providerMessageId: isset($m['message_id']) ? (string) $m['message_id'] : null,
            text: $text !== null && trim($text) !== '' ? $text : null,
            media: $media,
            name: $name ?: null,
            at: isset($m['date']) ? Carbon::createFromTimestamp((int) $m['date']) : null,
        )];
    }

    /** @return list<array{type:string,file_id:string,name?:string,mime?:string,size?:int}> */
    private function media(array $m): array
    {
        $out = [];
        if (! empty($m['photo']) && is_array($m['photo'])) {
            $biggest = collect($m['photo'])->sortByDesc(fn ($p) => (int) ($p['file_size'] ?? 0) ?: (int) ($p['width'] ?? 0) * (int) ($p['height'] ?? 0))->first();
            $out[] = ['type' => 'image', 'file_id' => (string) ($biggest['file_id'] ?? ''), 'size' => (int) ($biggest['file_size'] ?? 0)];
        }
        foreach (['document' => 'file', 'voice' => 'voice', 'audio' => 'audio', 'video' => 'video'] as $key => $type) {
            if (! empty($m[$key]['file_id'])) {
                $out[] = array_filter(['type' => $type, 'file_id' => (string) $m[$key]['file_id'], 'name' => $m[$key]['file_name'] ?? null, 'mime' => $m[$key]['mime_type'] ?? null, 'size' => isset($m[$key]['file_size']) ? (int) $m[$key]['file_size'] : null], fn ($v) => $v !== null);
            }
        }
        if (! empty($m['sticker']['file_id'])) {
            $out[] = ['type' => 'sticker', 'file_id' => (string) $m['sticker']['file_id']];
        }

        return array_values(array_filter($out, fn ($x) => $x['file_id'] !== ''));
    }

    public function requiresWindow(): bool
    {
        return false;   // Telegram has no 24-hour rule
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        $token = (string) $connection->secret('bot_token');
        $text = trim((string) $draft->text);
        if ($text === '') {
            throw new ChannelSendException('Telegram text messages need some text.');
        }
        $r = $this->client->sendMessageDetailed($token, $draft->externalId, $text);
        if ($r['ok']) {
            return (string) $r['id'];
        }
        $why = match (true) {
            $r['code'] === 403 => 'The customer blocked the bot or has not started it.',
            $r['code'] === 429 => 'Telegram asked us to slow down'.($r['retry_after'] ? ' (retry after '.$r['retry_after'].' s)' : '').'.',
            default => 'Telegram refused the message'.($r['description'] ? ': '.$r['description'] : '.'),
        };

        throw new ChannelSendException($why);
    }
}
