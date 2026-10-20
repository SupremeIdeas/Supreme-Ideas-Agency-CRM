<?php

namespace App\Services\Media;

use App\Support\MediaStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes the first thing a self-hosted video shows (blueprint 7.1): a still frame from the file, written as a WebP poster next to it. ffmpeg is optional: on a host
 * that cannot run it (common on shared cPanel) this simply returns null and the caller falls back to the admin's uploaded thumbnail, then a branded placeholder.
 */
class VideoPoster
{
    public const PLACEHOLDER = 'images/video-poster-placeholder.webp';

    public function __construct(private FfmpegManager $ffmpeg) {}

    public function available(): bool
    {
        return $this->ffmpeg->available();
    }

    /**
     * Grab a frame from a stored video and save it as a WebP poster. Returns the poster URL, or null when ffmpeg is unavailable or the file is unreadable.
     *
     * @param  string  $videoUrl  the video's public URL (as stored in video_url)
     */
    public function generate(string $videoUrl, string $dir = 'posters', float $atSeconds = 1.0): ?string
    {
        $bin = $this->ffmpeg->binary();
        if ($bin === null || ! $this->ffmpeg->available()) {
            return null;
        }
        $tmp = sys_get_temp_dir().'/vp_'.bin2hex(random_bytes(6));
        $src = $tmp.'.src';
        $out = $tmp.'.webp';
        try {
            if (! $this->fetch($videoUrl, $src)) {
                return null;
            }
            // Try a second in (past a black first frame); a very short clip falls back to the very first frame.
            foreach ([$atSeconds, 0.0] as $t) {
                $r = $this->ffmpeg->run([$bin, '-y', '-ss', number_format($t, 2, '.', ''), '-i', $src, '-frames:v', '1', '-vf', 'scale=1280:-2', '-c:v', 'libwebp', '-quality', '80', $out], 60);
                if ($r['ok'] && is_file($out) && filesize($out) > 200) {
                    return MediaStorage::storePublicBytes((string) file_get_contents($out), 'webp', $dir);
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($src);
            @unlink($out);
        }
    }

    /** Copy the video to a temp file: from the app's own storage when the URL points at it, else a bounded HTTPS download (never an arbitrary scheme). */
    private function fetch(string $url, string $dest): bool
    {
        if (($path = MediaStorage::localPublicPath($url)) !== null && Storage::disk('public')->exists($path)) {
            return @copy(Storage::disk('public')->path($path), $dest);
        }
        if (! str_starts_with($url, 'https://')) {
            return false;
        }
        $r = Http::timeout(60)->withOptions(['sink' => $dest, 'stream' => true])->withHeaders(['Range' => 'bytes=0-33554431'])->get($url);   // first 32 MB is plenty for a frame near the start

        return $r->successful() && is_file($dest) && filesize($dest) > 0;
    }
}
