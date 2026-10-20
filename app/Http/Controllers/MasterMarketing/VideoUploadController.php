<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Support\Auditor;
use App\Support\MasterMarketing\VideoSource;
use App\Support\MasterMarketing\VideoVault;
use App\Support\MediaStorage;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MASTER-ONLY. Resumable, chunked video upload for the White Label page (Admin > Marketing site > White Label > Video). A large video never travels in one request, so it
 * works under any PHP upload limit (cPanel included): the browser slices the file, sends each slice as a raw body, and asks for the file to be assembled at the end.
 * The same file picked again after a dropped connection resumes where it stopped. Nothing is stored in the public disk until the assembled file is verified by its
 * magic bytes (a renamed document is refused), and chunks live outside the web root.
 *
 *   POST   init              {name, size, mtime}  -> {id, chunk, received[]}
 *   PUT    {id}/chunk/{n}    raw bytes            -> {received}
 *   POST   {id}/complete                          -> {url, bytes, type}
 *   DELETE {id}                                   -> {ok}
 *   POST   poster            multipart image      -> {url}
 *   POST   forget            {url}                -> {ok}   (deletes a file this feature stored)
 */
class VideoUploadController extends Controller
{
    private const MIN_CHUNK = 256 * 1024;

    private const MAX_CHUNK = 4 * 1024 * 1024;

    private const STALE_HOURS = 24;

    public function init(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['name' => 'required|string|max:200', 'size' => 'required|integer|min:1', 'mtime' => 'nullable|integer']);

        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (! isset(VideoSource::UPLOAD_TYPES[$ext])) {
            return $this->fail('Please choose an MP4, WebM or MOV video.', 422);
        }
        $max = VideoSource::maxMb() * 1024 * 1024;
        if ($data['size'] > $max) {
            return $this->fail('That video is larger than the '.VideoSource::maxMb().' MB limit. Compress it, or paste a YouTube or Vimeo link instead.', 422);
        }

        $this->purgeStale();
        $id = sha1($request->user()->id.'|'.$data['name'].'|'.$data['size'].'|'.($data['mtime'] ?? 0));
        $dir = $this->dir($id);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.'/meta.json', json_encode(['name' => $data['name'], 'ext' => $ext, 'size' => (int) $data['size'], 'user' => $request->user()->id, 'at' => time()]));

        return response()->json(['id' => $id, 'chunk' => $this->chunkSize(), 'received' => $this->received($dir), 'max_mb' => VideoSource::maxMb()]);
    }

    public function chunk(Request $request, string $id, int $n): JsonResponse
    {
        $this->authorizeAdmin();
        $meta = $this->meta($id, $request);
        $body = $request->getContent();
        $len = strlen($body);
        if ($len === 0 || $len > self::MAX_CHUNK + 1024 || $n < 0 || $n * self::MIN_CHUNK > $meta['size'] * 4) {
            return $this->fail('That piece of the upload was not accepted.', 422);
        }
        file_put_contents($this->dir($id).'/'.str_pad((string) $n, 6, '0', STR_PAD_LEFT).'.part', $body, LOCK_EX);

        return response()->json(['received' => $this->received($this->dir($id))]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();
        $meta = $this->meta($id, $request);
        $dir = $this->dir($id);
        $parts = glob($dir.'/*.part') ?: [];
        sort($parts);

        $assembled = $dir.'/assembled.bin';
        $out = fopen($assembled, 'wb');
        $written = 0;
        foreach ($parts as $p) {
            $in = fopen($p, 'rb');
            $written += stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        if ($written !== $meta['size']) {
            @unlink($assembled);

            return $this->fail('Some pieces are missing ('.$written.' of '.$meta['size'].' bytes). Please start the upload again; it resumes from where it stopped.', 409);
        }

        $ext = $this->sniff($assembled);
        if ($ext === null) {
            $this->wipe($dir);

            return $this->fail('That file is not a playable MP4, WebM or MOV video.', 422);
        }

        // into the vault: private, no public address (see VideoVault); the page plays it through a signed, expiring link
        $ref = VideoVault::store($assembled, $ext);
        $this->wipe($dir);
        Auditor::log('site.video_uploaded', null, null, ['bytes' => $written, 'type' => $ext]);

        return response()->json(['url' => $ref, 'preview' => VideoVault::url($ref, 2), 'bytes' => $written, 'type' => $ext]);
    }

    public function abort(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();
        $this->meta($id, $request);
        $this->wipe($this->dir($id));

        return response()->json(['ok' => true]);
    }

    public function poster(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $request->validate(['poster' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072']);

        return response()->json(['url' => MediaStorage::storePublic($request->file('poster'), 'site')]);
    }

    /** Delete a video this feature stored (never anything else). */
    public function forget(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $url = (string) $request->input('url', '');
        $disk = MediaStorage::disk();
        $base = rtrim(Storage::disk($disk)->url(''), '/').'/';
        if ($url !== '' && str_starts_with($url, $base)) {
            $path = substr($url, strlen($base));
            if (preg_match('~^site/video/[a-f0-9-]{36}\.(mp4|webm|mov)$~', $path)) {
                Storage::disk($disk)->delete($path);
                Auditor::log('site.video_removed');
            }
        }

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------------------------------------------------------------------------

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    /** Pieces are sized to the server's real limits: raw bodies are bounded by post_max_size, so half of it (never above 4 MB) is always safe. */
    private function chunkSize(): int
    {
        $post = $this->bytes((string) ini_get('post_max_size')) ?: self::MAX_CHUNK * 2;

        return (int) max(self::MIN_CHUNK, min(self::MAX_CHUNK, floor($post / 2)));
    }

    private function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private function dir(string $id): string
    {
        abort_unless(preg_match('/^[a-f0-9]{40}$/', $id) === 1, 404);

        return storage_path('app/private/mm-video-uploads/'.$id);
    }

    /** @return array{name: string, ext: string, size: int, user: int, at: int} */
    private function meta(string $id, Request $request): array
    {
        $file = $this->dir($id).'/meta.json';
        abort_unless(is_file($file), 404);
        $meta = json_decode((string) file_get_contents($file), true);
        abort_unless(is_array($meta) && (int) ($meta['user'] ?? 0) === (int) $request->user()->id, 404);

        return $meta;
    }

    /** @return list<int> */
    private function received(string $dir): array
    {
        return array_map(fn ($f) => (int) basename($f, '.part'), glob($dir.'/*.part') ?: []);
    }

    private function sniff(string $file): ?string
    {
        $h = (string) file_get_contents($file, false, null, 0, 16);
        if (strlen($h) >= 12 && substr($h, 4, 4) === 'ftyp') {
            return str_starts_with(substr($h, 8, 4), 'qt') ? 'mov' : 'mp4';
        }
        if (str_starts_with($h, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        return null;
    }

    private function wipe(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /** Half-finished uploads older than a day are removed, so abandoned ones never pile up on a small host. */
    private function purgeStale(): void
    {
        $root = storage_path('app/private/mm-video-uploads');
        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (filemtime($d) < time() - self::STALE_HOURS * 3600) {
                $this->wipe($d);
            }
        }
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
