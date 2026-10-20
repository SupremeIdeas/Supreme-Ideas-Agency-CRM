<?php

namespace App\Services\Inbox;

use App\Support\Business\BusinessSettings;
use Illuminate\Support\Facades\Cache;

/**
 * What the Naara composer offers in a given shared-inbox conversation (MASTER-ONLY): the same composer as Naara chat, trimmed to what the channel can really carry
 * and to what the admin has switched on, so a button that could only ever fail is never shown.
 */
class InboxComposerConfig
{
    private const ATTACH = [
        'whatsapp' => ['media', 'camera', 'video', 'document', 'audio', 'location', 'contact'],
        'telegram' => ['media', 'camera', 'video', 'document', 'audio', 'location', 'contact', 'poll'],
        'instagram' => ['media', 'camera', 'video', 'audio'],
    ];

    /** Text length the channel accepts (Instagram's is the tightest). */
    private const MAX_CHARS = ['whatsapp' => 4096, 'telegram' => 4096, 'instagram' => 1000];

    /** @return array{features: list<string>, attachKinds: list<string>, maxFiles: int, maxFileMb: int, maxChars: int, endpoint: string} */
    public static function for(string $channel): array
    {
        $files = (bool) BusinessSettings::inbox('attachments');
        $features = ['format', 'emoji', 'hints', 'snippets'];
        if ($files) {
            array_push($features, 'gif', 'attach');
            // A browser's WebM voice note is re-wrapped as Ogg Opus in plain PHP for WhatsApp and Telegram (no ffmpeg needed); Instagram has no such route and needs the converter.
            if ($channel !== 'instagram' || self::ffmpeg()) {
                $features[] = 'voice';
            }
        }
        if (BusinessSettings::inbox('scheduling')) {
            $features[] = 'schedule';
        }
        $ceiling = (int) BusinessSettings::inbox('max_file_mb');
        $channelMax = max(array_values(array_filter(InboxMedia::MAX_BYTES[$channel] ?? [0], fn ($mb) => $mb > 0)) ?: [5]);

        return [
            'features' => $features,
            'attachKinds' => $files ? (self::ATTACH[$channel] ?? []) : [],
            'maxFiles' => $files ? (int) BusinessSettings::inbox('max_files') : 0,
            'maxFileMb' => min($ceiling, $channelMax),
            'maxChars' => self::MAX_CHARS[$channel] ?? 1000,
            'endpoint' => route('business.inbox.send'),
        ];
    }

    private static function ffmpeg(): bool
    {
        return (bool) Cache::remember('inbox.ffmpeg', 600, fn () => InboxMedia::ffmpegAvailable());
    }
}
