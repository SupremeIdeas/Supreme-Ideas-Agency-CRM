<?php

namespace App\Services\Inbox;

use App\Services\Media\FfmpegManager;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files in the shared inbox (MASTER-ONLY): stored privately, handed to a channel provider through a short-lived link (the provider fetches the file itself),
 * and shown back to the team through a signed route. Nothing here is ever a permanent public URL.
 */
class InboxMedia
{
    /** kind => extension allow-list (checked against the real MIME type as well as the name). */
    private const MIME_KIND = [
        'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'image/gif' => 'gif',
        'video/mp4' => 'video', 'video/webm' => 'video', 'video/quicktime' => 'video', 'video/3gpp' => 'video',
        'audio/mpeg' => 'audio', 'audio/mp4' => 'audio', 'audio/aac' => 'audio', 'audio/ogg' => 'audio', 'audio/wav' => 'audio', 'audio/x-wav' => 'audio', 'audio/webm' => 'audio', 'audio/x-m4a' => 'audio', 'audio/amr' => 'audio',
        'application/pdf' => 'document', 'text/plain' => 'document', 'text/csv' => 'document',
        'application/msword' => 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-excel' => 'document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
        'application/vnd.ms-powerpoint' => 'document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
    ];

    /** Per-channel byte ceilings (the providers' own limits, a little under). */
    public const MAX_BYTES = [
        'whatsapp' => ['image' => 5, 'gif' => 5, 'video' => 16, 'audio' => 16, 'voice' => 16, 'document' => 100],
        'telegram' => ['image' => 10, 'gif' => 20, 'video' => 20, 'audio' => 20, 'voice' => 20, 'document' => 20],
        'instagram' => ['image' => 8, 'gif' => 8, 'video' => 25, 'audio' => 25, 'voice' => 25, 'document' => 0],
    ];

    /** What each channel can carry. Instagram has no documents, locations, contacts or polls; WhatsApp has no polls. */
    public const CAPABILITIES = [
        'whatsapp' => ['image', 'gif', 'video', 'audio', 'voice', 'document', 'location', 'contact'],
        'telegram' => ['image', 'gif', 'video', 'audio', 'voice', 'document', 'location', 'contact', 'poll'],
        'instagram' => ['image', 'gif', 'video', 'audio', 'voice'],
    ];

    public static function can(string $channel, string $kind): bool
    {
        return in_array($kind, self::CAPABILITIES[$channel] ?? [], true);
    }

    public static function kindOf(string $mime): ?string
    {
        return self::MIME_KIND[strtolower(trim(explode(';', $mime)[0]))] ?? null;
    }

    /** @return array{path: string, name: string, mime: string, size: int, kind: string} */
    public function store(UploadedFile $file, int $walletGroupId, bool $voice = false): array
    {
        $mime = strtolower(trim(explode(';', (string) ($file->getMimeType() ?: $file->getClientMimeType()))[0]));
        // a voice note recorded in a browser is usually audio/webm (sometimes sniffed as video/webm: it has no video track)
        $kind = $voice ? 'voice' : self::kindOf($mime);
        if ($kind === null) {
            throw new ChannelSendException('“'.Str::limit($file->getClientOriginalName(), 40).'” is not a type that can be sent here.');
        }
        $ext = $voice ? 'webm' : (strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $path = 'inbox-out/'.$walletGroupId.'/'.Str::uuid().'.'.preg_replace('/[^a-z0-9]/', '', $ext);
        Storage::disk(MediaStorage::privateDisk())->put($path, (string) file_get_contents($file->getRealPath()));

        return ['path' => $path, 'name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()) ?: 'file', 100, ''), 'mime' => $mime, 'size' => (int) $file->getSize(), 'kind' => $kind];
    }

    /** Delete files that no message references (a send that was refused). @param list<array{path: string}> $files */
    public function forget(array $files): void
    {
        foreach ($files as $f) {
            Storage::disk(MediaStorage::privateDisk())->delete($f['path']);
        }
    }

    public function bytes(string $path): string
    {
        return (string) Storage::disk(MediaStorage::privateDisk())->get($path);
    }

    /** Store raw bytes (a transcoded copy) next to the original. @return array{path: string, mime: string, size: int} */
    public function put(string $bytes, string $like, string $ext, string $mime): array
    {
        $path = dirname($like).'/'.Str::uuid().'.'.$ext;
        Storage::disk(MediaStorage::privateDisk())->put($path, $bytes);

        return ['path' => $path, 'mime' => $mime, 'size' => strlen($bytes)];
    }

    /** A link a provider (or the browser) can open for a short while. Object stores sign it themselves; a local disk uses our own signed route. */
    public static function url(string $path, int $minutes = 30): string
    {
        $minutes = max(5, $minutes);
        $disk = Storage::disk(MediaStorage::privateDisk());
        try {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute('business.inbox.media', now()->addMinutes($minutes), ['path' => rtrim(strtr(base64_encode($path), '+/', '-_'), '=')]);
        }
    }

    /** Can this server convert media? (The managed, system or admin-set ffmpeg, proven to run: see FfmpegManager.) */
    public static function ffmpegAvailable(): bool
    {
        return app(FfmpegManager::class)->available();
    }

    /**
     * Re-encode a stored file with ffmpeg. `$args` is the output side of the command; returns the new file or throws a plain-language error.
     *
     * @return array{path: string, mime: string, size: int}
     */
    public function transcode(string $path, string $inExt, array $args, string $outExt, string $outMime, string $why): array
    {
        $ff = app(FfmpegManager::class);
        $bin = $ff->binary();
        if ($bin === null) {
            throw new ChannelSendException($why.' needs the media converter (ffmpeg), which is not set up on this server yet. An administrator can install it in Admin > Business Suite > Setup and health.');
        }
        $tmp = sys_get_temp_dir();
        $in = $tmp.'/'.Str::uuid().'.'.$inExt;
        $out = $tmp.'/'.Str::uuid().'.'.$outExt;
        try {
            file_put_contents($in, $this->bytes($path));
            $r = $ff->run([$bin, '-y', '-loglevel', 'error', '-i', $in, ...$args, $out], 120);
            if (! $r['ok'] || ! is_file($out) || filesize($out) === 0) {
                throw new ChannelSendException('That file could not be converted for this channel.');
            }

            return $this->put((string) file_get_contents($out), $path, $outExt, $outMime);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
