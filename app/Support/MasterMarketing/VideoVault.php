<?php

namespace App\Support\MasterMarketing;

use App\Support\MediaStorage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * MASTER ONLY. Self-hosted marketing videos live in the vault: a PRIVATE location with no public URL. A page never carries the file's address; it carries a short-lived signed
 * link that only a browser's media element can use (a download manager or "open in a new tab" is refused). A stored reference looks like `vault:site/video/<uuid>.mp4`.
 * Honest limit: this stops saving the file and sharing its link. Nothing on the web can stop someone filming their own screen.
 */
final class VideoVault
{
    public const PREFIX = 'vault:';

    /** Where vault files may live: the stream route never serves any other path. */
    private const ALLOWED = ['site/video/', 'marketing-video/'];

    public static function isRef(?string $v): bool
    {
        return is_string($v) && str_starts_with($v, self::PREFIX);
    }

    public static function disk(): string
    {
        return MediaStorage::privateDisk();
    }

    /** Move a finished local file into the vault. Returns the stored reference. */
    public static function store(string $localFile, string $ext, string $dir = 'site/video'): string
    {
        $name = Str::uuid()->toString().'.'.$ext;
        $path = Storage::disk(self::disk())->putFileAs($dir, new File($localFile), $name, 'private');

        return self::PREFIX.$path;
    }

    /** The link a page uses to play a vault video: signed, expires, tied to nothing a visitor can reuse for download. */
    public static function url(string $ref, int $hours = 6): ?string
    {
        $path = self::pathOf($ref);
        if ($path === null) {
            return null;
        }

        return URL::temporarySignedRoute('mm.video.stream', now()->addHours($hours), ['v' => Crypt::encryptString($path)]);
    }

    public static function pathOf(string $ref): ?string
    {
        $p = self::isRef($ref) ? substr($ref, strlen(self::PREFIX)) : $ref;

        return self::allowed($p) ? $p : null;
    }

    public static function pathFromToken(string $token): ?string
    {
        try {
            $p = Crypt::decryptString($token);
        } catch (\Throwable) {
            return null;
        }

        return self::allowed($p) ? $p : null;
    }

    private static function allowed(string $p): bool
    {
        if (str_contains($p, '..') || str_contains($p, "\0") || ! preg_match('#^[A-Za-z0-9/_\-.]+\.(mp4|webm|mov)$#', $p)) {
            return false;
        }
        foreach (self::ALLOWED as $prefix) {
            if (str_starts_with($p, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Moves every self-hosted video of the marketing video sections that still has a public address into the vault, and points its row at the vault copy.
     * Returns how many were moved. A file that cannot be found is left as it was.
     */
    public static function lockExisting(): int
    {
        $moved = 0;
        $public = Storage::disk(MediaStorage::disk());
        foreach (\App\Models\MasterMarketingVideoSection::query()->where('source_type', 'upload')->get() as $row) {
            $url = (string) $row->video_url;
            if (self::isRef($url) || ! preg_match('#(?:^|/)((?:marketing-video|site/video)/[A-Za-z0-9/_\-.]+\.(mp4|webm|mov))$#', parse_url($url, PHP_URL_PATH) ?: $url, $m) || ! $public->exists($m[1])) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'vlt');
            file_put_contents($tmp, $public->readStream($m[1]));
            $ref = self::store($tmp, $m[2]);
            @unlink($tmp);
            $public->delete($m[1]);
            $row->update(['video_url' => $ref]);
            $moved++;
        }

        return $moved;
    }

    /** What a person's browser should load for a stored value: a vault ref becomes a signed link, anything else (an older public file, an https link) is unchanged. */
    public static function playable(string $stored): string
    {
        return self::isRef($stored) ? (self::url($stored) ?? '') : $stored;
    }
}
