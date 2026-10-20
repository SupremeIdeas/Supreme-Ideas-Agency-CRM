<?php

namespace App\Services\N2N\Media;

/**
 * Re-encodes an uploaded image (N2N, MASTER-ONLY): decode -> honour EXIF orientation -> encode fresh. The fresh file carries NO EXIF, so
 * GPS coordinates and device details cannot leak, and anything hidden in the original container is dropped. Also makes the thumbnail.
 * This re-encoding is the only sanitisation applied: no malware scanner is assumed to exist (a ClamAV hook is optional and UNVERIFIED).
 */
class ImageSanitizer
{
    private const MAX_DIM = 4096;

    private const THUMB = 360;

    private const GIF_MAX_FRAMES = 400;

    private const GIF_MAX_PIXELS = 16_000_000;

    /**
     * Animated GIFs (Naara Pro, from the composer's GIF maker). GD would flatten the animation, so the bytes are KEPT, but only after a strict walk of the
     * file's own block structure: the header, every extension and image block must parse exactly, the frame count and canvas are capped, and anything after
     * the GIF trailer is cut off, so no data can ride along behind the image. The thumbnail is the first frame, re-encoded as JPEG.
     *
     * @return array{bytes: string, thumb: string, width: int, height: int, mime: string, ext: string}|null
     */
    public static function cleanGif(string $raw): ?array
    {
        $n = strlen($raw);
        if ($n < 14 || ! in_array(substr($raw, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }
        $w = ord($raw[6]) | (ord($raw[7]) << 8);
        $h = ord($raw[8]) | (ord($raw[9]) << 8);
        if ($w < 1 || $h < 1 || $w * $h > self::GIF_MAX_PIXELS) {
            return null;
        }
        $pos = 13;
        if (ord($raw[10]) & 0x80) {
            $pos += 3 * (1 << ((ord($raw[10]) & 7) + 1));                       // global colour table
        }
        $frames = 0;
        $skipBlocks = function (int $p) use ($raw, $n): ?int {                  // data sub-blocks up to and including the 0-length terminator
            while (true) {
                if ($p >= $n) {
                    return null;
                }
                $len = ord($raw[$p]);
                $p += 1 + $len;
                if ($len === 0) {
                    return $p;
                }
            }
        };
        while ($pos < $n) {
            $b = ord($raw[$pos]);
            if ($b === 0x3B) {                                                  // trailer: the file ends HERE
                if ($frames < 1) {
                    return null;
                }
                $clean = substr($raw, 0, $pos + 1);

                return self::gifResult($clean, $w, $h);
            }
            if ($b === 0x21) {                                                  // extension
                $next = $skipBlocks($pos + 2);
                if ($next === null) {
                    return null;
                }
                $pos = $next;
            } elseif ($b === 0x2C) {                                            // image
                if ($pos + 10 > $n || ++$frames > self::GIF_MAX_FRAMES) {
                    return null;
                }
                $flags = ord($raw[$pos + 9]);
                $pos += 10;
                if ($flags & 0x80) {
                    $pos += 3 * (1 << (($flags & 7) + 1));                      // local colour table
                }
                $pos++;                                                         // LZW minimum code size
                $next = $skipBlocks($pos);
                if ($next === null) {
                    return null;
                }
                $pos = $next;
            } else {
                return null;
            }
        }

        return null;                                                            // no trailer: truncated or hostile
    }

    private static function gifResult(string $clean, int $w, int $h): ?array
    {
        if (! function_exists('imagecreatefromstring') || ($img = @imagecreatefromstring($clean)) === false) {
            return null;                                                        // the first frame must decode, or it is not a usable GIF
        }
        $thumb = self::encode(self::fit($img, self::THUMB, true), 'image/jpeg');
        imagedestroy($img);

        return $thumb === null ? null : ['bytes' => $clean, 'thumb' => $thumb, 'width' => $w, 'height' => $h, 'mime' => 'image/gif', 'ext' => 'gif'];
    }

    /** @return array{bytes: string, thumb: string, width: int, height: int, mime: string, ext: string}|null null when the bytes are not a decodable image */
    public static function clean(string $raw, string $mime): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesizefromstring($raw);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 60_000_000) {   // also a decompression-bomb guard
            return null;
        }
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return null;
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
            $img = self::orient($img, (int) ($exif['Orientation'] ?? 1));
        }
        $img = self::fit($img, self::MAX_DIM);
        [$w, $h] = [imagesx($img), imagesy($img)];
        $main = self::encode($img, $mime);
        $thumb = self::encode(self::fit($img, self::THUMB, true), 'image/jpeg');
        imagedestroy($img);

        return $main === null || $thumb === null ? null : ['bytes' => $main, 'thumb' => $thumb, 'width' => $w, 'height' => $h, 'mime' => $mime === 'image/png' ? 'image/png' : ($mime === 'image/webp' ? 'image/webp' : 'image/jpeg'), 'ext' => $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg')];
    }

    private static function orient($img, int $o)
    {
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot !== 0) {
            $img = imagerotate($img, $rot, 0) ?: $img;
        }

        return $img;
    }

    private static function fit($img, int $max, bool $copy = false)
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        if ($w <= $max && $h <= $max) {
            return $copy ? (imagescale($img, $w, $h) ?: $img) : $img;
        }
        $scale = $max / max($w, $h);

        return imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))) ?: $img;
    }

    private static function encode($img, string $mime): ?string
    {
        ob_start();
        $ok = match ($mime) {
            'image/png' => (function () use ($img) { imagesavealpha($img, true); return imagepng($img, null, 6); })(),
            'image/webp' => function_exists('imagewebp') ? imagewebp($img, null, 82) : imagejpeg($img, null, 85),
            default => (function () use ($img) {
                $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
                imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

                return imagejpeg($flat, null, 85);
            })(),
        };
        $out = ob_get_clean();

        return $ok ? (string) $out : null;
    }
}
