<?php

namespace App\Services\Inbox;

use App\Models\ChannelConnection;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Media\WebmOpusToOgg;
use App\Services\WhatsApp\WhatsAppCloudClient;
use App\Support\Business\BusinessSettings;

/**
 * Sends everything the Naara composer can produce into a WhatsApp, Telegram or Instagram conversation (MASTER-ONLY, Business Suite inbox): formatted text,
 * photos, GIFs, video, documents, audio, voice notes, a location, a contact card and (Telegram only) a poll. One OutboundMessage row is written per part that
 * actually went out. Each channel gets what it can carry: a GIF becomes an animation on Telegram, an mp4 on WhatsApp (ffmpeg) and stays a GIF on Instagram;
 * a voice note is re-encoded to m4a where the channel will not take a browser's webm. Anything a channel cannot carry is refused BEFORE anything is sent,
 * with a plain sentence saying so, so a message is never half-delivered because of its type. SMS is not here: it keeps its own billed send window.
 */
class ChannelSender
{
    private const LABELS = ['image' => 'Photo', 'gif' => 'GIF', 'video' => 'Video', 'audio' => 'Audio', 'voice' => 'Voice note', 'document' => 'Document', 'location' => 'Location', 'contact' => 'Contact', 'poll' => 'Poll'];

    public function __construct(
        private WhatsAppCloudClient $whatsapp,
        private TelegramBotClient $telegram,
        private InstagramClient $instagram,
        private InboxMedia $media,
    ) {
    }

    public static function label(string $kind): string
    {
        return self::LABELS[$kind] ?? 'Attachment';
    }

    /** @return list<OutboundMessage> the rows written, in the order they were sent
     * @throws ChannelSendException */
    public function send(MessageThread $thread, OutgoingMessage $m, User $by): array
    {
        $channel = (string) $thread->channel;
        if (! in_array($channel, ['whatsapp', 'telegram', 'instagram'], true)) {
            throw new ChannelSendException('SMS replies use the send window.');
        }
        if ($m->isEmpty()) {
            throw new ChannelSendException('Write a message first.');
        }
        if ($thread->opted_out_at !== null) {
            throw new ChannelSendException('This person asked not to be messaged. You can reply once they write to you again.');
        }
        if (! BusinessSettings::module('inbox')) {
            throw new ChannelSendException('The shared inbox is not available right now.');
        }
        $connection = ChannelConnection::query()->where('wallet_group_id', $thread->wallet_group_id)->where('channel', $channel)->where('status', ChannelConnection::ACTIVE)->first();
        if (! $connection) {
            throw new ChannelSendException('This channel is not connected any more.');
        }
        $this->guard($channel, $m);

        $ctx = ['thread' => $thread, 'channel' => $channel, 'c' => $connection, 'to' => $thread->counterpart_number];
        $text = trim($m->text);
        $captionLimit = ChannelFormat::CAPTION_LIMITS[$channel] ?? 0;
        $captionOk = $captionLimit > 0 && $text !== '' && mb_strlen(ChannelFormat::convert($text, $channel)['text']) <= $captionLimit;
        $textSent = false;
        $rows = [];

        foreach ($m->files as $i => $f) {
            $useCaption = $i === 0 && $captionOk;
            $rows[] = $this->sendFile($ctx, $f, $useCaption ? $text : '');
            $textSent = $textSent || $useCaption;
        }
        if ($m->location) {
            $rows[] = $this->sendLocation($ctx, $m->location);
        }
        if ($m->contact) {
            $rows[] = $this->sendContact($ctx, $m->contact);
        }
        if ($m->poll) {
            $rows[] = $this->sendPoll($ctx, $m->poll);
        }
        if ($text !== '' && ! $textSent) {
            $rows[] = $this->sendText($ctx, $text);
        }

        return $rows;
    }

    /** Refuse what the channel cannot carry, before anything leaves (also used when a message is scheduled, so a doomed one is never queued). @throws ChannelSendException */
    public function check(string $channel, OutgoingMessage $m): void
    {
        $this->guard($channel, $m);
    }

    /** Refuse what the channel cannot carry, before anything leaves. */
    private function guard(string $channel, OutgoingMessage $m): void
    {
        $name = ChannelConnection::label($channel);
        foreach ($m->files as $f) {
            $kind = $f['kind'];
            if (! InboxMedia::can($channel, $kind)) {
                throw new ChannelSendException("{$name} can't carry ".strtolower(self::label($kind)).'s.');
            }
            $max = (InboxMedia::MAX_BYTES[$channel][$kind] ?? 0) * 1048576;
            if ($max > 0 && $f['size'] > $max) {
                throw new ChannelSendException('“'.$f['name']."” is too large for {$name} (up to ".($max / 1048576).' MB for '.strtolower(self::label($kind)).'s).');
            }
        }
        if ($m->poll && ! InboxMedia::can($channel, 'poll')) {
            throw new ChannelSendException("Polls are only available on Telegram. {$name} can't carry them.");
        }
    }

    // ── parts ───────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $ctx */
    private function sendText(array $ctx, string $text): OutboundMessage
    {
        $conv = ChannelFormat::convert($text, $ctx['channel']);
        $ref = match ($ctx['channel']) {
            'whatsapp' => $this->whatsapp->sendMessage($ctx['to'], $conv['text'], $ctx['c']->external_id, (string) $ctx['c']->secret('access_token')),
            'telegram' => $this->telegram->call((string) $ctx['c']->secret('bot_token'), 'sendMessage', ['chat_id' => $ctx['to'], 'text' => $conv['text'], 'parse_mode' => 'HTML']),
            default => $this->instagram->sendMessage((string) $ctx['c']->secret('access_token'), $ctx['c']->external_id, $ctx['to'], $conv['text']),
        };

        return $this->record($ctx, $ref, $text);
    }

    /**
     * @param  array<string,mixed>  $ctx
     * @param  array{path: string, name: string, mime: string, size: int, kind: string}  $f
     */
    private function sendFile(array $ctx, array $f, string $caption): OutboundMessage
    {
        $channel = $ctx['channel'];
        $orig = $f;
        $f = $this->prepare($channel, $f);
        $url = InboxMedia::url($f['path'], (int) BusinessSettings::inbox('link_minutes'));
        $cap = $caption !== '' ? ChannelFormat::convert($caption, $channel) : ['text' => '', 'parse_mode' => null];
        $token = (string) $ctx['c']->secret($channel === 'telegram' ? 'bot_token' : 'access_token');

        $ref = match ($channel) {
            'whatsapp' => $this->whatsapp->sendPayload($ctx['to'], $this->waPayload($f, $url, $cap['text']), $ctx['c']->external_id, $token),
            'telegram' => $this->telegram->call($token, ...$this->tgCall($ctx['to'], $f, $url, $cap)),
            default => $this->instagram->sendAttachment($token, $ctx['c']->external_id, $ctx['to'], $f['kind'] === 'gif' ? 'image' : ($f['kind'] === 'voice' ? 'audio' : $f['kind']), $url),
        };

        return $this->record($ctx, $ref, $caption, ['kind' => $orig['kind'], 'path' => $orig['path'], 'name' => $orig['name'], 'mime' => $orig['mime']], $channel === 'instagram' && $caption !== '' ? true : false);
    }

    /**
     * What the channel will actually accept. Converts with ffmpeg when the server has it, and degrades gracefully when it does not, so a send never fails just because
     * a host cannot run programs: a browser's WebM voice note is re-wrapped as Ogg Opus in plain PHP (lossless, no ffmpeg); a GIF on WhatsApp, or a video in an unusual
     * format, goes as a document the person can open; only Instagram, which has no document type, refuses (with a sentence saying why).
     *
     * @param  array{path: string, name: string, mime: string, size: int, kind: string}  $f
     * @return array{path: string, name: string, mime: string, size: int, kind: string}
     */
    private function prepare(string $channel, array $f): array
    {
        $ext = pathinfo($f['path'], PATHINFO_EXTENSION) ?: 'bin';
        $ff = $this->media->ffmpegAvailable();
        $asDocument = fn (string $name, string $mime) => ['kind' => 'document', 'name' => $name, 'mime' => $mime] + $f;

        if ($f['kind'] === 'gif' && $channel === 'whatsapp') {
            if ($ff) {
                $t = $this->media->transcode($f['path'], $ext, ['-movflags', 'faststart', '-pix_fmt', 'yuv420p', '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2'], 'mp4', 'video/mp4', 'A GIF on WhatsApp has to be sent as a video, which');

                return ['kind' => 'video', 'name' => pathinfo($f['name'], PATHINFO_FILENAME).'.mp4'] + $t;
            }

            return $asDocument(pathinfo($f['name'], PATHINFO_FILENAME).'.gif', 'image/gif');   // WhatsApp cannot show a GIF inline; as a file it still opens and animates
        }
        if ($f['kind'] === 'video' && $f['mime'] !== 'video/mp4') {
            if ($ff) {
                $t = $this->media->transcode($f['path'], $ext, ['-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-movflags', 'faststart'], 'mp4', 'video/mp4', 'This video format has to be converted to MP4, which');

                return ['kind' => 'video', 'name' => pathinfo($f['name'], PATHINFO_FILENAME).'.mp4'] + $t;
            }
            if ($channel === 'instagram') {
                throw new ChannelSendException('Instagram only takes MP4 video, and this server cannot convert other formats yet. Send an MP4, or ask an administrator to set up the media converter (Admin > Business Suite > Setup and health).');
            }

            return $asDocument($f['name'], $f['mime']);
        }
        if (in_array($f['kind'], ['audio', 'voice'], true) && ! in_array($f['mime'], ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/x-m4a', 'audio/ogg'], true)) {
            $base = pathinfo($f['name'], PATHINFO_FILENAME);
            if (str_contains($f['mime'], 'webm') && in_array($channel, ['whatsapp', 'telegram'], true)) {
                $ogg = WebmOpusToOgg::convert($this->media->bytes($f['path']));
                if ($ogg !== null) {
                    $t = $this->media->put($ogg, $f['path'], 'ogg', 'audio/ogg');

                    return ['kind' => $f['kind'], 'name' => $base.'.ogg'] + $t;
                }
            }
            if ($ff) {
                $t = $this->media->transcode($f['path'], $ext, ['-vn', '-c:a', 'aac', '-b:a', '64k'], 'm4a', 'audio/mp4', 'This audio format has to be converted, which');

                return ['kind' => $f['kind'], 'name' => $base.'.m4a'] + $t;
            }
            if ($channel === 'instagram') {
                throw new ChannelSendException('Instagram needs voice notes converted, and this server cannot do that yet. Ask an administrator to set up the media converter (Admin > Business Suite > Setup and health), or send a text message.');
            }

            return $asDocument($f['name'], $f['mime']);
        }

        return $f;
    }

    /** @param array{path: string, name: string, mime: string, size: int, kind: string} $f @return array<string,mixed> */
    private function waPayload(array $f, string $url, string $caption): array
    {
        return match ($f['kind']) {
            'image' => ['type' => 'image', 'image' => array_filter(['link' => $url, 'caption' => $caption])],
            'video' => ['type' => 'video', 'video' => array_filter(['link' => $url, 'caption' => $caption])],
            'audio', 'voice' => ['type' => 'audio', 'audio' => ['link' => $url]],
            default => ['type' => 'document', 'document' => array_filter(['link' => $url, 'caption' => $caption, 'filename' => $f['name']])],
        };
    }

    /**
     * @param  array{path: string, name: string, mime: string, size: int, kind: string}  $f
     * @param  array{text: string, parse_mode: ?string}  $cap
     * @return array{0: string, 1: array<string,mixed>}
     */
    private function tgCall(string $chat, array $f, string $url, array $cap): array
    {
        $extra = $cap['text'] !== '' ? ['caption' => $cap['text'], 'parse_mode' => 'HTML'] : [];

        return match ($f['kind']) {
            'image' => ['sendPhoto', ['chat_id' => $chat, 'photo' => $url] + $extra],
            'gif' => ['sendAnimation', ['chat_id' => $chat, 'animation' => $url] + $extra],
            'video' => ['sendVideo', ['chat_id' => $chat, 'video' => $url, 'supports_streaming' => true] + $extra],
            'voice' => ['sendVoice', ['chat_id' => $chat, 'voice' => $url] + $extra],
            'audio' => ['sendAudio', ['chat_id' => $chat, 'audio' => $url] + $extra],
            default => ['sendDocument', ['chat_id' => $chat, 'document' => $url] + $extra],
        };
    }

    /** @param array<string,mixed> $ctx @param array{lat: float, lng: float, label: ?string} $loc */
    private function sendLocation(array $ctx, array $loc): OutboundMessage
    {
        $label = trim((string) ($loc['label'] ?? '')) ?: round($loc['lat'], 5).', '.round($loc['lng'], 5);
        $token = (string) $ctx['c']->secret($ctx['channel'] === 'telegram' ? 'bot_token' : 'access_token');
        $ref = match ($ctx['channel']) {
            'whatsapp' => $this->whatsapp->sendPayload($ctx['to'], ['type' => 'location', 'location' => ['latitude' => $loc['lat'], 'longitude' => $loc['lng'], 'name' => mb_substr($label, 0, 100)]], $ctx['c']->external_id, $token),
            'telegram' => $this->telegram->call($token, 'sendLocation', ['chat_id' => $ctx['to'], 'latitude' => $loc['lat'], 'longitude' => $loc['lng']]),
            default => $this->instagram->sendMessage($token, $ctx['c']->external_id, $ctx['to'], 'Location: https://www.google.com/maps?q='.$loc['lat'].','.$loc['lng']),   // Instagram has no location message: a map link
        };

        return $this->record($ctx, $ref, 'Location: '.$label, ['kind' => 'location']);
    }

    /** @param array<string,mixed> $ctx @param array{name: string, phone: ?string} $c */
    private function sendContact(array $ctx, array $c): OutboundMessage
    {
        $name = mb_substr(trim($c['name']), 0, 80);
        $phone = preg_replace('/[^\d+]/', '', (string) ($c['phone'] ?? '')) ?: null;
        $token = (string) $ctx['c']->secret($ctx['channel'] === 'telegram' ? 'bot_token' : 'access_token');
        $card = $name.($phone ? ' '.$phone : '');
        $ref = match (true) {
            $ctx['channel'] === 'whatsapp' && $phone !== null => $this->whatsapp->sendPayload($ctx['to'], ['type' => 'contacts', 'contacts' => [['name' => ['formatted_name' => $name, 'first_name' => $name], 'phones' => [['phone' => $phone, 'type' => 'CELL']]]]], $ctx['c']->external_id, $token),
            $ctx['channel'] === 'telegram' && $phone !== null => $this->telegram->call($token, 'sendContact', ['chat_id' => $ctx['to'], 'phone_number' => $phone, 'first_name' => $name]),
            $ctx['channel'] === 'whatsapp' => $this->whatsapp->sendMessage($ctx['to'], 'Contact: '.$card, $ctx['c']->external_id, $token),
            $ctx['channel'] === 'telegram' => $this->telegram->call($token, 'sendMessage', ['chat_id' => $ctx['to'], 'text' => 'Contact: '.$card]),
            default => $this->instagram->sendMessage($token, $ctx['c']->external_id, $ctx['to'], 'Contact: '.$card),
        };

        return $this->record($ctx, $ref, 'Contact: '.$card, ['kind' => 'contact']);
    }

    /** @param array<string,mixed> $ctx @param array{question: string, options: list<string>, multiple: bool} $p */
    private function sendPoll(array $ctx, array $p): OutboundMessage
    {
        $opts = array_slice(array_values(array_filter(array_map(fn ($o) => ['text' => mb_substr(trim((string) $o), 0, 100)], $p['options']), fn ($o) => $o['text'] !== '')), 0, 10);
        $ref = $this->telegram->call((string) $ctx['c']->secret('bot_token'), 'sendPoll', ['chat_id' => $ctx['to'], 'question' => mb_substr(trim($p['question']), 0, 300), 'options' => $opts, 'is_anonymous' => false, 'allows_multiple_answers' => $p['multiple']]);

        return $this->record($ctx, $ref, 'Poll: '.$p['question'].' ('.implode(' / ', array_column($opts, 'text')).')', ['kind' => 'poll']);
    }

    /**
     * Write the row for a part that went out; a null provider ref means the channel refused it.
     *
     * @param  array<string,mixed>  $ctx
     * @param  array{kind: string, path?: string, name?: string, mime?: string}  $attach
     */
    private function record(array $ctx, ?string $ref, string $body, array $attach = [], bool $captionDropped = false): OutboundMessage
    {
        $channel = $ctx['channel'];
        if ($ref === null) {
            $ctx['c']->forceFill(['last_error' => 'The last reply could not be sent.'])->save();
            throw new ChannelSendException(in_array($channel, ['whatsapp', 'instagram'], true)
                ? 'It could not be sent. '.ChannelConnection::label($channel).' only allows replies within 24 hours of their last message.'
                : 'It could not be sent. Check the connection.');
        }
        $thread = $ctx['thread'];
        $kind = $attach['kind'] ?? null;

        $row = [
            'user_id' => $thread->user_id, 'channel' => $channel, 'wallet_group_id' => $thread->wallet_group_id, 'virtual_number_id' => null,
            'to_number' => $thread->counterpart_number, 'body' => $body !== '' ? $body : self::label((string) $kind),
            'attachment_url' => $attach['path'] ?? null, 'attachment_kind' => $kind, 'attachment_name' => $attach['name'] ?? null, 'attachment_mime' => $attach['mime'] ?? null,
            'provider' => $channel, 'provider_ref' => $ref, 'status' => 'sent', 'segments' => 1,
            // Telegram numbers its messages per chat, so the id alone is not unique: scope it to the conversation.
            'reference' => 'chan:'.$channel.':'.$thread->counterpart_number.':'.$ref,
        ];

        // The channel has already delivered this: a clash on the reference must never turn into an error the person would answer by sending again.
        try {
            return OutboundMessage::create($row);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return OutboundMessage::create(['reference' => $row['reference'].':'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5))] + $row);
        }
    }
}
