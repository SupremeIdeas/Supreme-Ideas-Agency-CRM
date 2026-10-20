<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Instagram Messaging and Facebook Messenger share one Meta "messaging" webhook shape (MASTER-ONLY): entry[].messaging[] {sender.id, recipient.id, timestamp (ms),
 * message {mid, text, attachments[] {type, payload.url}, is_echo}} plus delivery/read receipts. The recipient id is THIS connection's account (Instagram professional
 * account id, or the Facebook Page id). Echoes of our own replies and other accounts' events are dropped. Replies use the Send API: POST /{id}/messages with
 * recipient.id and message.text, allowed within 24 hours of the person's last message.
 */
abstract class MessagingPlatformAdapter implements ChannelAdapter
{
    use MetaSigned;

    abstract protected function label(): string;

    public function eventId(array $payload): string
    {
        $parts = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $e) {
                $parts[] = (string) (($e['message']['mid'] ?? '') ?: ('r'.($e['sender']['id'] ?? '').($e['timestamp'] ?? '').(isset($e['read']) ? 'read' : (isset($e['delivery']) ? 'del' : ''))));
            }
        }

        return 'ms:'.($parts !== [] ? sha1(implode('|', $parts)) : sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $out = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $e) {
                $sender = (string) ($e['sender']['id'] ?? '');
                $recipient = (string) ($e['recipient']['id'] ?? '');
                if ($sender === '' || ($connection && $recipient !== (string) $connection->external_id)) {
                    continue;
                }
                $m = (array) ($e['message'] ?? []);
                if ($m === [] || ! empty($m['is_echo']) || empty($m['mid'])) {
                    continue;   // receipts, reactions without a message and echoes of our own replies
                }
                $text = trim((string) ($m['text'] ?? ''));
                $media = [];
                foreach ((array) ($m['attachments'] ?? []) as $a) {
                    $type = (string) ($a['type'] ?? 'file');
                    if (in_array($type, ['image', 'audio', 'video', 'file'], true) && ! empty($a['payload']['url'])) {
                        $media[] = ['type' => $type === 'file' ? 'file' : $type, 'file_id' => (string) $a['payload']['url']];
                    }
                }
                if ($text === '' && $media === []) {
                    continue;
                }
                $out[] = new NormalizedEvent(
                    type: NormalizedEvent::MESSAGE, externalId: $sender, providerMessageId: (string) $m['mid'], text: $text !== '' ? mb_substr($text, 0, 2000) : null, media: $media,
                    at: isset($e['timestamp']) ? Carbon::createFromTimestamp((int) floor(((int) $e['timestamp']) / 1000)) : null,
                );
            }
        }

        return $out;
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        $text = trim((string) $draft->text);
        if ($text === '') {
            throw new ChannelSendException($this->label().' text messages need some text.');
        }
        $token = (string) $connection->secret('access_token');
        if ($token === '') {
            throw new ChannelSendException('This '.$this->label().' connection has no access token. Reconnect it from Channels.');
        }
        try {
            $r = Http::withToken($token)->timeout(15)->post($this->graph().'/'.$connection->external_id.'/messages', [
                'recipient' => ['id' => $draft->externalId], 'messaging_type' => 'RESPONSE', 'message' => ['text' => mb_substr($text, 0, 1000)],
            ]);
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach '.$this->label().'. The message was not sent; try again in a minute.');
        }
        $id = $r->json('message_id');
        if ($r->successful() && is_string($id) && $id !== '') {
            return $id;
        }
        $code = (int) $r->json('error.code');
        throw new ChannelSendException(match (true) {
            $code === 190 || $r->status() === 401 => $this->label().' rejected the access token. Reconnect this account from Channels.',
            in_array($code, [10, 551], true) || (int) $r->json('error.error_subcode') === 2534022 => $this->label().' only allows replies within 24 hours of the customer\'s last message.',
            $r->status() === 429 => $this->label().' asked us to slow down. Try again shortly.',
            default => $this->label().' refused the message'.(($d = (string) $r->json('error.message')) !== '' ? ': '.mb_substr($d, 0, 160) : '.'),
        });
    }
}
