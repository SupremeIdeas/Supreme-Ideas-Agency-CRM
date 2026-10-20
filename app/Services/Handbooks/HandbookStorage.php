<?php

namespace App\Services\Handbooks;

use App\Models\Handbook;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Where handbook files live (MASTER-ONLY): the PRIVATE disk only, never public. Files are reached through entitlement-checked routes and short-lived signed links. */
class HandbookStorage
{
    public static function disk(): string
    {
        return MediaStorage::privateDisk();
    }

    /** Store an uploaded file under the handbook and return its path. */
    public function put(Handbook $b, UploadedFile|string $file, string $kind, ?int $edition = null): string
    {
        $ext = match ($kind) {
            'epub' => 'epub', 'pdf' => 'pdf', 'cover', 'back' => strtolower($file instanceof UploadedFile ? ($file->guessExtension() ?: 'jpg') : pathinfo($file, PATHINFO_EXTENSION)), default => 'bin',
        };
        $dir = 'handbooks/'.$b->id.'/'.(in_array($kind, ['cover', 'back'], true) ? $kind : 'e'.($edition ?? $b->edition));
        $name = Str::random(24).'.'.$ext;
        $path = $dir.'/'.$name;
        $stream = $file instanceof UploadedFile ? fopen($file->getRealPath(), 'rb') : fopen($file, 'rb');
        Storage::disk(self::disk())->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $path;
    }

    public function putContents(string $path, string $bytes): void
    {
        Storage::disk(self::disk())->put($path, $bytes);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::disk())->exists($path);
    }

    public function get(string $path): string
    {
        return (string) Storage::disk(self::disk())->get($path);
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::disk())->delete($path);
        }
    }

    /** A short-lived signed link. The route still re-checks the entitlement on every request, so a leaked link only works for the person it was made for and only for minutes. */
    public function signed(string $routeName, array $params, ?int $minutes = null): string
    {
        return URL::temporarySignedRoute($routeName, now()->addMinutes($minutes ?? (int) config('handbooks.url_ttl_minutes', 10)), $params);
    }

    /** Local path for a stored file (copied to a temp file when the disk is remote), for the inspector. The caller deletes it. */
    public function tempCopy(string $path): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hb');
        file_put_contents($tmp, Storage::disk(self::disk())->get($path));

        return $tmp;
    }
}
