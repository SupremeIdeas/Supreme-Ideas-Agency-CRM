<?php

namespace App\Services\N2N\Media;

use App\Models\Setting;

/** Admin-owned media limits (N2N, MASTER-ONLY). Nothing here is a literal in the upload code. */
class MediaPolicy
{
    public const KINDS = ['image', 'voice', 'video', 'audio', 'doc'];

    /** @var array<string, list<string>> allowed REAL mime types per kind */
    public const MIMES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'voice' => ['audio/ogg', 'audio/webm', 'audio/mp4', 'audio/x-m4a', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'video/webm', 'video/ogg'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'audio' => ['audio/ogg', 'audio/webm', 'audio/mp4', 'audio/x-m4a', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/aac'],
        'doc' => ['application/pdf', 'text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    ];

    public static function maxBytes(string $kind): int
    {
        $mb = match ($kind) {
            'image' => (float) Setting::getValue('n2n.media_image_max_mb', 10),
            'voice' => (float) Setting::getValue('n2n.media_voice_max_mb', 10),
            'audio' => (float) Setting::getValue('n2n.media_audio_max_mb', 25),
            'doc' => (float) Setting::getValue('n2n.media_doc_max_mb', 15),
            default => (float) Setting::getValue('n2n.media_video_max_mb', 25),
        };

        return (int) (max(0.1, $mb) * 1048576);
    }

    public static function maxSeconds(string $kind): int
    {
        return match ($kind) {
            'voice' => max(5, (int) Setting::getValue('n2n.media_voice_max_s', 300)),
            'audio' => max(30, (int) Setting::getValue('n2n.media_audio_max_s', 1800)),
            default => max(5, (int) Setting::getValue('n2n.media_video_max_s', 60)),
        };
    }

    public static function maxPerMessage(): int
    {
        return max(1, (int) Setting::getValue('n2n.media_max_per_message', 4));
    }

    public static function stagedTtlHours(): int
    {
        return max(1, (int) Setting::getValue('n2n.media_staged_ttl_hours', 6));
    }

    /** How long after sending the sender may delete a message (and its media) for everyone. */
    public static function deleteWindowHours(): int
    {
        return max(1, (int) Setting::getValue('n2n.delete_window_hours', 48));
    }

    public static function signedUrlMinutes(): int
    {
        return max(1, min(60, (int) Setting::getValue('n2n.media_url_minutes', 5)));
    }
}
