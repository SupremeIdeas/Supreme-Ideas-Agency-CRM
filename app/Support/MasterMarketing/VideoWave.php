<?php

namespace App\Support\MasterMarketing;

use App\Services\Media\FfmpegManager;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * MASTER ONLY. The wave drawn in the video progress bar. For a self-hosted file it is the video's real loudness over time (ffmpeg reads the audio; the bars rise where the
 * speaker or music is loud and fall in the quiet parts). When ffmpeg cannot run, or the video is a YouTube/Vimeo link we cannot read, a smooth wave is drawn from the video's own
 * id so each video still has its own steady shape: that one is decoration, not data.
 */
final class VideoWave
{
    public const POINTS = 96;

    /** @return list<float> POINTS values between 0.12 and 1 */
    public static function forRef(string $ref, string $seed): array
    {
        $real = self::fromVault($ref);

        return $real ?? self::synthetic($seed);
    }

    /** @return list<float>|null */
    public static function fromVault(string $ref): ?array
    {
        $path = VideoVault::pathOf($ref);
        $disk = Storage::disk(VideoVault::disk());
        if ($path === null || ! $disk->exists($path) || ! ($disk->getAdapter() instanceof \League\Flysystem\Local\LocalFilesystemAdapter)) {
            return null;   // an object store would have to be downloaded first: not worth it for a decoration
        }

        return self::fromFile($disk->path($path));
    }

    /** @return list<float>|null */
    public static function fromFile(string $file): ?array
    {
        try {
            $bin = app(FfmpegManager::class)->binary();
        } catch (\Throwable) {
            return null;
        }
        if (! $bin || ! is_file($file)) {
            return null;
        }
        // mono, 8 kHz, signed 16-bit: tiny, and plenty to see loud and quiet
        $p = new Process([$bin, '-v', 'error', '-i', $file, '-vn', '-ac', '1', '-ar', '8000', '-f', 's16le', '-'], null, null, null, 300);
        $windows = [];
        $acc = 0.0;
        $n = 0;
        $carry = '';
        try {
            $p->run(function ($type, $buf) use (&$windows, &$acc, &$n, &$carry) {
                if ($type !== Process::OUT) {
                    return;
                }
                $buf = $carry.$buf;
                $len = strlen($buf) - (strlen($buf) % 2);
                $carry = substr($buf, $len);
                foreach (unpack('s*', substr($buf, 0, $len)) ?: [] as $s) {
                    $acc += $s * $s;
                    if (++$n === 4000) {          // half a second
                        $windows[] = sqrt($acc / $n);
                        $acc = 0.0;
                        $n = 0;
                    }
                }
            });
        } catch (\Throwable) {
            return null;
        }
        if ($n > 400) {
            $windows[] = sqrt($acc / $n);
        }

        return count($windows) < 4 ? null : self::shape($windows);
    }

    /**
     * Loudness windows -> POINTS values: averaged into POINTS buckets, evened out so a single click does not spike, lifted so quiet parts still show, scaled to 0.12 - 1.
     *
     * @param  list<float>  $windows
     * @return list<float>
     */
    public static function shape(array $windows): array
    {
        $out = [];
        $count = count($windows);
        for ($i = 0; $i < self::POINTS; $i++) {
            $from = (int) floor($i * $count / self::POINTS);
            $to = max($from + 1, (int) floor(($i + 1) * $count / self::POINTS));
            $slice = array_slice($windows, $from, $to - $from);
            $out[] = array_sum($slice) / max(1, count($slice));
        }
        $max = max($out) ?: 1.0;
        $out = array_map(fn ($v) => sqrt($v / $max), $out);              // sqrt: quiet parts stay visible
        $sm = [];
        foreach ($out as $i => $v) {                                      // 5-point moving average
            $win = array_slice($out, max(0, $i - 2), 5);
            $sm[] = array_sum($win) / count($win);
        }
        $lo = min($sm);
        $hi = max($sm);
        $span = max(0.0001, $hi - $lo);

        return array_map(fn ($v) => round(0.12 + 0.88 * (($v - $lo) / $span), 3), $sm);
    }

    /** @return list<float> a smooth, steady wave that belongs to this seed */
    public static function synthetic(string $seed): array
    {
        mt_srand(crc32($seed));
        $a = [mt_rand(60, 140) / 100, mt_rand(60, 140) / 100, mt_rand(60, 140) / 100];
        $ph = [mt_rand(0, 628) / 100, mt_rand(0, 628) / 100, mt_rand(0, 628) / 100];
        $raw = [];
        for ($i = 0; $i < self::POINTS; $i++) {
            $x = $i / self::POINTS * 6.2832;
            $raw[] = 0.55 + 0.28 * sin(2 * $x * $a[0] + $ph[0]) + 0.17 * sin(5 * $x * $a[1] + $ph[1]) + 0.09 * sin(11 * $x * $a[2] + $ph[2]) + (mt_rand(-40, 40) / 1000);
        }
        mt_srand();   // put the generator back to random for everyone else

        $lo = min($raw);
        $hi = max($raw);

        return array_map(fn ($v) => round(0.12 + 0.88 * (($v - $lo) / max(0.0001, $hi - $lo)), 3), $raw);
    }
}
