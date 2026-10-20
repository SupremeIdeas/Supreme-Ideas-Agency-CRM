<?php

namespace App\Services\N2N\Brands;

use App\Models\N2N\Thumbnail;
use App\Services\N2N\Media\ImageSanitizer;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Server-side provider thumbnails (N2N Batch 8, MASTER-ONLY): fetched ONCE by a queued job, re-encoded (so nothing hostile survives) and kept
 * on the private disk, so a profile page makes zero third-party requests before the visitor taps play. The fetch targets are fixed provider
 * hosts built from a validated id: never a URL the owner typed (no SSRF surface).
 */
class ThumbnailCache
{
    public static function fetch(string $provider, string $id): Thumbnail
    {
        $row = Thumbnail::query()->firstOrCreate(['provider' => $provider, 'provider_id' => $id]);
        if ($row->status === 'ok') {
            return $row;
        }
        $url = self::sourceUrl($provider, $id);
        $raw = $url ? self::download($url) : null;
        $clean = $raw !== null ? ImageSanitizer::clean($raw, 'image/jpeg') : null;
        if ($clean === null) {
            $row->forceFill(['status' => 'failed', 'fetched_at' => now()])->save();

            return $row;
        }
        $disk = MediaStorage::privateDisk();
        $key = 'n2n/thumbs/'.$provider.'/'.Str::random(32).'.jpg';
        Storage::disk($disk)->put($key, $clean['thumb'], ['visibility' => 'private']);
        $row->forceFill(['disk' => $disk, 'storage_key' => $key, 'status' => 'ok', 'fetched_at' => now()])->save();

        return $row;
    }

    private static function sourceUrl(string $provider, string $id): ?string
    {
        if ($provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{6,15}$/', $id) === 1) {
            return 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
        }
        if ($provider === 'vimeo' && preg_match('/^\d{5,12}$/', $id) === 1) {
            try {
                $r = Http::timeout(6)->acceptJson()->get('https://vimeo.com/api/oembed.json', ['url' => 'https://vimeo.com/'.$id]);
                $t = (string) ($r->json('thumbnail_url') ?? '');
                $host = strtolower((string) parse_url($t, PHP_URL_HOST));

                return $r->ok() && str_starts_with($t, 'https://') && ($host === 'vimeocdn.com' || str_ends_with($host, '.vimeocdn.com')) ? $t : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function download(string $url): ?string
    {
        try {
            $r = Http::timeout(8)->withOptions(['allow_redirects' => false])->get($url);

            return $r->ok() && strlen($r->body()) < 2_000_000 ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
