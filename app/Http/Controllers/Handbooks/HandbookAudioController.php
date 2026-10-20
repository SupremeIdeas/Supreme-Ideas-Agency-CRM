<?php

namespace App\Http\Controllers\Handbooks;

use App\Http\Controllers\Controller;
use App\Models\Handbook;
use App\Models\HandbookAudioEntitlement;
use App\Models\HandbookAudioTrack;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The audiobook player page and its range-capable stream (MASTER-ONLY). Only a person who bought the audiobook can reach either; anything else is a plain 404. */
class HandbookAudioController extends Controller
{
    private function owned(string $slug): Handbook
    {
        $b = Handbook::query()->published()->where('slug', $slug)->with('audiobook.tracks')->first();
        $a = $b?->audiobook;
        abort_unless($b && $a && $a->is_enabled && $a->stream_enabled && HandbookAudioEntitlement::query()->where('user_id', auth()->id())->where('audiobook_id', $a->id)->exists(), 404);

        return $b;
    }

    public function show(string $slug)
    {
        $b = $this->owned($slug);
        $a = $b->audiobook;

        return view('handbooks.listen', [
            'b' => $b, 'a' => $a,
            'tracks' => $a->tracks->map(fn ($t) => ['id' => $t->id, 'title' => $t->chapter_title, 'seconds' => (int) $t->seconds, 'url' => route('handbooks.audio', [$b->slug, $t->id])])->values()->all(),
            'download' => (bool) $a->download_enabled,
        ]);
    }

    /** Streams one chapter with HTTP range support so players can seek and resume. Download-only access is not offered unless the admin turned it on. */
    public function stream(Request $request, string $slug, int $trackId): Response
    {
        $b = $this->owned($slug);
        $t = HandbookAudioTrack::query()->where('audiobook_id', $b->audiobook->id)->whereKey($trackId)->first();
        abort_unless($t, 404);
        $disk = Storage::disk(HandbookStorage::disk());
        abort_unless($disk->exists($t->file_path), 404);
        $size = (int) $disk->size($t->file_path);
        $mime = match (strtolower(pathinfo($t->file_path, PATHINFO_EXTENSION))) {
            'm4a', 'aac' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', default => 'audio/mpeg',
        };
        $start = 0;
        $end = $size - 1;
        $status = 200;
        if (preg_match('/bytes=(\d*)-(\d*)/', (string) $request->header('Range'), $m) === 1 && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => 'bytes */'.$size]);
            }
            $status = 206;
        }
        $headers = ['Content-Type' => $mime, 'Accept-Ranges' => 'bytes', 'Content-Length' => $end - $start + 1, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($b->audiobook->download_enabled && $request->boolean('dl') ? 'attachment' : 'inline').'; filename="'.$b->slug.'-'.$t->position.'.'.pathinfo($t->file_path, PATHINFO_EXTENSION).'"'];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return new StreamedResponse(function () use ($disk, $t, $start, $end) {
            $h = $disk->readStream($t->file_path);
            if (! is_resource($h)) {
                return;
            }
            if ($start > 0) {
                fseek($h, $start);
            }
            $left = $end - $start + 1;
            while ($left > 0 && ! feof($h)) {
                $chunk = fread($h, min(8192, $left));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
                flush();
            }
            fclose($h);
        }, $status, $headers);
    }
}
