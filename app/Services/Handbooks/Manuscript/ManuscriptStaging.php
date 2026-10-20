<?php

namespace App\Services\Handbooks\Manuscript;

use App\Services\Handbooks\HandbookStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Holds a manuscript while an admin works on it (MASTER-ONLY): uploaded EPUB or Word file, converted to an EPUB, previewed, image-fitted, and only then attached to the
 * handbook. Lives on the private disk under handbook-staging/, is reachable only through admin routes by its random token, and is swept after two days.
 */
class ManuscriptStaging
{
    public const TTL_HOURS = 48;

    public function __construct(private readonly DocxToEpub $docx)
    {
    }

    public static function validToken(string $t): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $t) === 1;
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(HandbookStorage::disk());
    }

    private function key(string $token): string
    {
        return 'handbook-staging/'.$token.'.epub';
    }

    /**
     * Stage an upload. A .docx is converted to an EPUB first.
     *
     * @return array{ok:bool, token:?string, errors:list<string>, warnings:list<string>, source:string}
     */
    public function stage(UploadedFile $file, ?string $title = null, string $language = 'en'): array
    {
        $this->sweep();
        $ext = strtolower($file->getClientOriginalExtension());
        $local = $file->getRealPath();
        $warnings = [];
        $source = $ext === 'docx' ? 'docx' : 'epub';
        if ($ext === 'docx') {
            $tmp = tempnam(sys_get_temp_dir(), 'nmx').'.epub';
            $r = $this->docx->convert($local, $tmp, $title, $language);
            if (! $r['ok']) {
                @unlink($tmp);

                return ['ok' => false, 'token' => null, 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'source' => $source];
            }
            $warnings = $r['warnings'];
            $local = $tmp;
        } elseif ($ext !== 'epub') {
            return ['ok' => false, 'token' => null, 'errors' => ['Upload an .epub or a Word .docx file.'], 'warnings' => [], 'source' => $source];
        }
        $token = bin2hex(random_bytes(16));
        $stream = fopen($local, 'rb');
        $this->disk()->put($this->key($token), $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($ext === 'docx') {
            @unlink($local);
        }

        return ['ok' => true, 'token' => $token, 'errors' => [], 'warnings' => $warnings, 'source' => $source];
    }

    /** Replace a staged file with a new version of itself (after image fitting). */
    public function replace(string $token, string $localPath): void
    {
        $stream = fopen($localPath, 'rb');
        $this->disk()->put($this->key($token), $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function exists(string $token): bool
    {
        return self::validToken($token) && $this->disk()->exists($this->key($token));
    }

    public function size(string $token): int
    {
        return $this->exists($token) ? (int) $this->disk()->size($this->key($token)) : 0;
    }

    /** A local copy for reading (the private disk may be remote). The caller deletes it when done. */
    public function localCopy(string $token): string
    {
        abort_unless($this->exists($token), 404);
        $tmp = tempnam(sys_get_temp_dir(), 'nms').'.epub';
        file_put_contents($tmp, $this->disk()->get($this->key($token)));

        return $tmp;
    }

    public function forget(string $token): void
    {
        if (self::validToken($token)) {
            $this->disk()->delete($this->key($token));
        }
    }

    public function sweep(): void
    {
        foreach ($this->disk()->files('handbook-staging') as $f) {
            if ($this->disk()->lastModified($f) < now()->subHours(self::TTL_HOURS)->getTimestamp()) {
                $this->disk()->delete($f);
            }
        }
    }
}
