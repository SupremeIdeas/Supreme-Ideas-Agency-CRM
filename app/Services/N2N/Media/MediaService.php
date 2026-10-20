<?php

namespace App\Services\N2N\Media;

use App\Models\N2N\Attachment;
use App\Models\User;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Messaging\MessagingException;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload, validate, store and serve N2N media (MASTER-ONLY). The server decides everything: the real type comes from the bytes, the size is
 * measured here, images are re-encoded (EXIF/GPS stripped), keys are random, the bucket is private and nobody gets a public URL: recipients
 * get a short-lived signed URL after a participant check. Cost is computed from the SERVER-measured size.
 */
class MediaService
{
    /** Direct upload: the composer posted the file with the message. @throws MessagingException */
    public function stage(User $sender, UploadedFile $file, ?string $hintKind = null, ?int $declaredSeconds = null, ?string $transcript = null, ?int $declaredBytes = null, mixed $peaks = null, ?string $originalName = null): Attachment
    {
        $path = $file->getRealPath();
        $bytes = (int) filesize($path);
        $fh = fopen($path, 'rb');
        $head = (string) fread($fh, 64);
        fclose($fh);

        return $this->process($sender, (string) file_get_contents($path), $head, $bytes, $hintKind, $declaredSeconds, $transcript, $declaredBytes, null, $peaks, $originalName);
    }

    /** A safe display name for a download: no path, no control or reserved characters, at most 80 characters, and always the REAL extension. */
    public static function cleanName(?string $name, string $ext): string
    {
        $base = pathinfo(str_replace('\\', '/', (string) $name), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $base));
        $base = trim(preg_replace('/\s+/u', ' ', $base), " .");

        return ($base === '' ? 'file' : mb_substr($base, 0, 70)).'.'.$ext;
    }

    /** The waveform a voice note ships with: at most 96 integers 0..100, nothing else. Anything malformed is dropped (the bubble then draws a neutral shape). @return list<int>|null */
    public static function cleanPeaks(mixed $peaks): ?array
    {
        if (! is_array($peaks) || $peaks === []) {
            return null;
        }
        $out = [];
        foreach (array_slice(array_values($peaks), 0, 96) as $v) {
            if (! is_numeric($v)) {
                return null;
            }
            $out[] = (int) max(0, min(100, round((float) $v)));
        }

        return count($out) >= 8 ? $out : null;
    }

    /** @throws MessagingException */
    private function process(User $sender, string $raw, string $head, int $bytes, ?string $hintKind, ?int $declaredSeconds, ?string $transcript, ?int $declaredBytes, ?Attachment $into = null, mixed $peaks = null, ?string $originalName = null): Attachment
    {
        $sn = MediaSniffer::sniff($head, $hintKind);
        if ($sn !== null && $sn['kind'] === 'voice' && $hintKind !== 'voice') {
            $sn['kind'] = 'audio';   // an audio FILE (music, a recording made elsewhere) is not a voice note
        }
        if ($sn === null && $hintKind !== 'voice') {
            $sn = MediaSniffer::sniffDocument($raw);   // PDF, text, macro-free Office files
        }
        if ($sn === null || ! in_array($sn['mime'], MediaPolicy::MIMES[$sn['kind']], true)) {
            throw new MessagingException('type_not_allowed', 'That file type is not allowed.');
        }
        $kind = $sn['kind'];
        if ($bytes <= 0 || $bytes > MediaPolicy::maxBytes($kind)) {
            throw new MessagingException('too_large', 'That file is too large. The limit for '.(['image' => 'photos', 'voice' => 'voice notes', 'audio' => 'audio files', 'doc' => 'documents'][$kind] ?? 'videos').' is '.round(MediaPolicy::maxBytes($kind) / 1048576, 1).' MB.');
        }
        if ($declaredBytes !== null && abs($declaredBytes - $bytes) > max(2048, (int) ($bytes * 0.02))) {
            throw new MessagingException('size_mismatch', 'The upload did not match its declared size. Please try again.');
        }
        $seconds = null;
        if (in_array($kind, ['voice', 'video', 'audio'], true)) {
            $seconds = $declaredSeconds !== null ? max(0, $declaredSeconds) : null;
            $seconds = $this->probeSeconds($raw) ?? $seconds;                     // ffprobe when the host has it; the declared value is only a fallback
            if ($seconds !== null && $seconds > MediaPolicy::maxSeconds($kind)) {
                throw new MessagingException('too_large', 'That is too long. The limit is '.MediaPolicy::maxSeconds($kind).' seconds.');
            }
        }

        $thumbKey = null;
        $w = $h = null;
        $disk = $into?->disk ?? MediaStorage::privateDisk();
        $store = Storage::disk($disk);
        $rand = Str::random(40);
        $ym = now()->format('Y/m');
        if ($kind === 'image') {
            $clean = $sn['mime'] === 'image/gif' ? ImageSanitizer::cleanGif($raw) : ImageSanitizer::clean($raw, $sn['mime']);
            if ($clean === null) {
                throw new MessagingException('type_not_allowed', 'That image could not be read.');
            }
            [$raw, $w, $h, $sn['mime'], $sn['ext']] = [$clean['bytes'], $clean['width'], $clean['height'], $clean['mime'], $clean['ext']];
            $thumbKey = "n2n/media/{$ym}/{$rand}_t.jpg";
            $store->put($thumbKey, $clean['thumb'], ['visibility' => 'private']);
            $bytes = strlen($raw);
        }
        $key = $into?->storage_key ?? "n2n/media/{$ym}/{$rand}.{$sn['ext']}";
        $store->put($key, $raw, ['visibility' => 'private']);

        $data = [
            'uploader_id' => $sender->id, 'kind' => $kind, 'disk' => $disk, 'storage_key' => $key, 'thumb_key' => $thumbKey, 'mime' => $sn['mime'], 'bytes' => $bytes,
            'duration_s' => $seconds, 'width' => $w, 'height' => $h, 'status' => Attachment::STAGED,
            'meta' => array_filter(['transcript' => $transcript !== null ? Str::limit($transcript, 2000, '') : null, 'peaks' => $kind === 'voice' ? self::cleanPeaks($peaks) : null, 'name' => in_array($kind, ['doc', 'audio'], true) ? self::cleanName($originalName, $sn['ext']) : null]) ?: null,
            'expires_at' => now()->addHours(MediaPolicy::stagedTtlHours()),
        ];

        if ($into !== null) {
            $into->forceFill($data)->save();

            return $into->fresh();
        }

        return Attachment::query()->create($data);
    }

    /** Preview of what an attachment of this size will cost (credits), from the same pricing the charge uses. */
    public function quote(int $bytes): array
    {
        return ['cents' => $this->costCents($bytes), 'credits' => Money::credits($this->costCents($bytes))];
    }

    /** A media message never costs less than a text message. */
    public function costCents(int $bytes): int
    {
        return max(N2NPricing::messageCostCents(), N2NPricing::mediaCostCents($bytes));
    }

    // ---- presigned upload (object stores) -----------------------------------------------------------------

    /** @return array{mode: string, url?: string, key?: string, id?: int, headers?: array} `direct` when the disk cannot presign (cPanel / local) */
    public function presign(User $sender, string $kind, int $declaredBytes): array
    {
        if (! in_array($kind, MediaPolicy::KINDS, true) || $declaredBytes <= 0 || $declaredBytes > MediaPolicy::maxBytes($kind)) {
            throw new MessagingException('too_large', 'That file is not allowed.');
        }
        $disk = MediaStorage::privateDisk();
        $store = Storage::disk($disk);
        if (! $this->isObjectStore($disk)) {
            return ['mode' => 'direct'];
        }
        $key = 'n2n/media/'.now()->format('Y/m').'/'.Str::random(40).'.bin';
        $up = $store->temporaryUploadUrl($key, now()->addMinutes(10), ['ContentLength' => $declaredBytes]);
        $att = Attachment::query()->create([
            'uploader_id' => $sender->id, 'kind' => $kind, 'disk' => $disk, 'storage_key' => $key, 'mime' => 'pending', 'bytes' => $declaredBytes,
            'status' => Attachment::STAGED, 'meta' => ['pending' => true], 'expires_at' => now()->addMinutes(30),
        ]);

        return ['mode' => 'presigned', 'url' => $up['url'], 'headers' => $up['headers'] ?? [], 'key' => $key, 'id' => $att->id];
    }

    /** After a presigned upload: HEAD the object, read its first bytes, check the REAL type and size, sanitise, and only then keep it. */
    public function finalize(User $sender, int $attachmentId, ?int $declaredSeconds = null, ?string $transcript = null): Attachment
    {
        $att = Attachment::query()->where('id', $attachmentId)->where('uploader_id', $sender->id)->where('status', Attachment::STAGED)->first();
        if ($att === null || ! ($att->meta['pending'] ?? false)) {
            throw new MessagingException('not_found', 'That upload was not found.');
        }
        $store = Storage::disk($att->disk);
        try {
            $bytes = (int) $store->size($att->storage_key);
            $stream = $store->readStream($att->storage_key);
            $head = (string) fread($stream, 64);
            fclose($stream);
            $raw = $bytes <= MediaPolicy::maxBytes($att->kind) ? (string) $store->get($att->storage_key) : '';
        } catch (\Throwable) {
            throw new MessagingException('not_found', 'That upload was not found.');
        }
        try {
            return $this->process($sender, $raw, $head, $bytes, $att->kind === 'voice' ? 'voice' : null, $declaredSeconds, $transcript, (int) $att->bytes, $att, null, null);
        } catch (MessagingException $e) {
            $store->delete($att->storage_key);     // a failed finalize never leaves an unchecked object behind
            $att->delete();
            throw $e;
        }
    }

    // ---- serving ------------------------------------------------------------------------------------------

    /** A short-lived signed URL (object store) or null when the disk cannot sign (the controller then streams after its own check). */
    public function signedUrl(Attachment $a, bool $thumb = false, bool $download = false): ?string
    {
        $store = Storage::disk($a->disk);
        $key = $thumb && $a->thumb_key ? $a->thumb_key : $a->storage_key;
        if (! $this->isObjectStore($a->disk)) {
            return null;   // local / cPanel disk: the controller streams after its own participant check
        }
        try {
            $fallback = 'naara-'.$a->kind.'-'.$a->id.'.'.pathinfo($a->storage_key, PATHINFO_EXTENSION);
            $name = (string) ($a->meta['name'] ?? $fallback);
            $opts = $download ? ['ResponseContentDisposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._ -]/', '_', $name).'"; filename*=UTF-8\'\''.rawurlencode($name)] : [];
            if ($a->kind === 'doc') {
                $opts['ResponseContentType'] = 'application/octet-stream';   // a document is only ever downloaded, never rendered by the browser
            }

            return $store->temporaryUrl($key, now()->addMinutes(MediaPolicy::signedUrlMinutes()), $opts);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Only a real S3-compatible store signs URLs for the browser; anything else is streamed by the app after authorisation. */
    private function isObjectStore(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    public function deleteObjects(Attachment $a): void
    {
        $store = Storage::disk($a->disk);
        foreach (array_filter([$a->storage_key, $a->thumb_key]) as $k) {
            try {
                $store->delete($k);
            } catch (\Throwable) {
                // a missing object is fine; the row is the record of intent
            }
        }
        $a->forceFill(['status' => Attachment::DELETED])->save();
        Auditor::log('n2n.media_deleted', Attachment::class, $a->id, ['kind' => $a->kind]);
    }

    /** Remove staged uploads that were never attached (and expired). */
    public function cleanupStaged(): int
    {
        $n = 0;
        foreach (Attachment::query()->where('status', Attachment::STAGED)->where('expires_at', '<', now())->limit(500)->get() as $a) {
            $this->deleteObjects($a);
            $n++;
        }

        return $n;
    }

    /** Duration from ffprobe when the host has it (shared hosting usually does not: then only size caps and the declared value apply). */
    private function probeSeconds(string $raw): ?int
    {
        if (! function_exists('proc_open') || strlen($raw) > 60 * 1048576) {
            return null;
        }
        static $has = null;
        $has ??= (bool) @shell_exec('command -v ffprobe 2>/dev/null');
        if (! $has) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'n2nprobe');
        file_put_contents($tmp, $raw);
        $out = @shell_exec('ffprobe -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($tmp).' 2>/dev/null');
        @unlink($tmp);

        return is_numeric(trim((string) $out)) ? (int) ceil((float) trim((string) $out)) : null;
    }
}
