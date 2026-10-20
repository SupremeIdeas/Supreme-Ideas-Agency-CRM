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
 * WhatsApp Business Cloud API adapter (MASTER-ONLY, blueprint Phase 3). Webhook shape (Meta's documented one): entry[].changes[].value with
 * metadata.phone_number_id, contacts[] {profile.name, wa_id}, messages[] {from, id, timestamp, type, text.body | image/audio/video/document/sticker {id, mime_type, caption,
 * filename} | location | contacts | interactive.button_reply/list_reply | button.text} and statuses[] {id, status sent|delivered|read|failed, timestamp, recipient_id,
 * errors[].title}. Only messages for THIS connection's phone number id are filed (one app can serve many numbers). Media is recorded as a descriptor (the media id);
 * downloading it needs a second authenticated call and is a later step. Sending is a plain text message; outside the 24-hour window the Outbox refuses before we get here.
 */
final class WhatsAppAdapter implements ChannelAdapter
{
    use MetaSigned;

    public function channel(): string
    {
        return ChannelConnection::WHATSAPP;
    }

    public function eventId(array $payload): string
    {
        $parts = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $v = (array) ($change['value'] ?? []);
                foreach ((array) ($v['messages'] ?? []) as $m) {
                    $parts[] = 'm'.($m['id'] ?? '');
                }
                foreach ((array) ($v['statuses'] ?? []) as $s) {
                    $parts[] = 's'.($s['id'] ?? '').':'.($s['status'] ?? '');
                }
            }
        }

        return 'wa:'.($parts !== [] ? sha1(implode('|', $parts)) : sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $out = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $v = (array) ($change['value'] ?? []);
                $phoneId = (string) ($v['metadata']['phone_number_id'] ?? '');
                if ($connection && $phoneId !== (string) $connection->external_id) {
                    continue;   // another number of the same Meta app
                }
                $names = [];
                foreach ((array) ($v['contacts'] ?? []) as $c) {
                    $names[(string) ($c['wa_id'] ?? '')] = (string) ($c['profile']['name'] ?? '');
                }
                foreach ((array) ($v['messages'] ?? []) as $m) {
                    $from = preg_replace('/\D+/', '', (string) ($m['from'] ?? ''));
                    if ($from === '' || empty($m['id'])) {
                        continue;
                    }
                    [$text, $media] = $this->body($m);
                    if ($text === null && $media === []) {
                        continue;
                    }
                    $out[] = new NormalizedEvent(
                        type: NormalizedEvent::MESSAGE, externalId: $from, providerMessageId: (string) $m['id'], text: $text, media: $media,
                        name: ($names[$from] ?? '') !== '' ? $names[$from] : null, phone: '+'.$from,
                        at: isset($m['timestamp']) ? Carbon::createFromTimestamp((int) $m['timestamp']) : null,
                    );
                }
                foreach ((array) ($v['statuses'] ?? []) as $s) {
                    $status = (string) ($s['status'] ?? '');
                    $to = preg_replace('/\D+/', '', (string) ($s['recipient_id'] ?? ''));
                    if (empty($s['id']) || $to === '' || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
                        continue;
                    }
                    $out[] = new NormalizedEvent(
                        type: NormalizedEvent::STATUS, externalId: $to, providerMessageId: (string) $s['id'], status: $status,
                        error: $status === 'failed' ? (string) (($s['errors'][0]['title'] ?? '') ?: ($s['errors'][0]['message'] ?? 'failed at WhatsApp')) : null,
                        at: isset($s['timestamp']) ? Carbon::createFromTimestamp((int) $s['timestamp']) : null,
                    );
                }
            }
        }

        return $out;
    }

    /** @return array{0:?string,1:list<array<string,mixed>>} */
    private function body(array $m): array
    {
        $type = (string) ($m['type'] ?? 'text');
        switch ($type) {
            case 'text':
                $t = trim((string) ($m['text']['body'] ?? ''));

                return [$t !== '' ? $t : null, []];
            case 'button':
                return [trim((string) ($m['button']['text'] ?? '')) ?: null, []];
            case 'interactive':
                $i = (array) ($m['interactive'] ?? []);
                $r = (array) ($i['button_reply'] ?? $i['list_reply'] ?? []);

                return [trim((string) ($r['title'] ?? '')) ?: null, []];
            case 'location':
                $l = (array) ($m['location'] ?? []);

                return [isset($l['latitude'], $l['longitude']) ? 'Location: '.$l['latitude'].', '.$l['longitude'].(isset($l['name']) ? ' ('.$l['name'].')' : '') : null, []];
            case 'contacts':
                $n = (string) ($m['contacts'][0]['name']['formatted_name'] ?? '');

                return [$n !== '' ? 'Contact card: '.$n : 'Contact card', []];
            case 'image':
            case 'audio':
            case 'video':
            case 'document':
            case 'sticker':
                $x = (array) ($m[$type] ?? []);
                if (empty($x['id'])) {
                    return [null, []];
                }
                $caption = trim((string) ($x['caption'] ?? ''));

                return [$caption !== '' ? $caption : null, [array_filter(['type' => $type === 'audio' ? (! empty($x['voice']) ? 'voice' : 'audio') : $type, 'file_id' => (string) $x['id'], 'name' => $x['filename'] ?? null, 'mime' => $x['mime_type'] ?? null])]];
            default:
                return [null, []];   // reactions, system and unsupported types carry nothing to show
        }
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        $token = (string) $connection->secret('access_token');
        if ($token === '') {
            throw new ChannelSendException('This WhatsApp connection has no access token. Reconnect it from Channels.');
        }
        $to = preg_replace('/\D+/', '', $draft->externalId);
        if ($draft->template !== null) {
            $params = array_values(array_map('strval', (array) ($draft->template['params'] ?? [])));
            $template = ['name' => (string) $draft->template['name'], 'language' => ['code' => (string) $draft->template['language']]];
            if ($params !== []) {
                $template['components'] = [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => mb_substr($p, 0, 1024)], $params)]];
            }
            $body = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => $template];
        } else {
            $text = trim((string) $draft->text);
            if ($text === '') {
                throw new ChannelSendException('WhatsApp text messages need some text.');
            }
            $body = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4096)]];
        }
        $r = $this->post($connection->external_id, $token, $body);
        $id = $r->json('messages.0.id');
        if ($r->successful() && is_string($id) && $id !== '') {
            return $id;
        }
        $code = (int) $r->json('error.code');
        throw new ChannelSendException(match (true) {
            $code === 131047 => 'WhatsApp only allows free-form replies within 24 hours of the customer\'s last message. Send an approved template instead.',
            $code === 190 || $r->status() === 401 => 'WhatsApp rejected the access token. Reconnect this number from Channels.',
            $code === 132001 => 'That template does not exist or is not approved in that language. Check it in Templates.',
            $code === 132000 => 'The template needs a different number of values. Check the template in Templates.',
            $code === 131030 => 'That number is not on the allowed recipient list of your test number.',
            $r->status() === 429 || $code === 130429 => 'WhatsApp asked us to slow down. Try again shortly.',
            default => 'WhatsApp refused the message'.(($d = (string) $r->json('error.message')) !== '' ? ': '.mb_substr($d, 0, 160) : '.'),
        });
    }

    private function post(string $phoneId, string $token, array $body)
    {
        try {
            return Http::withToken($token)->timeout(15)->post($this->graph().'/'.$phoneId.'/messages', $body);
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach WhatsApp. The message was not sent; try again in a minute.');
        }
    }
}
