<?php

namespace App\Services\Handbooks\Manuscript;

use ZipArchive;

/**
 * Batch image fitting for a manuscript (MASTER-ONLY). Any picture wider than the chosen width (or heavier than the chosen weight) is scaled down in proportion and
 * re-encoded in its own format, so a handbook reads sharply on a small phone without a 6 MB photo in the middle of a chapter. Names, paths and the manifest stay as they were,
 * so the book's links and reading order cannot break. Pictures already small enough are copied untouched; animated GIFs and SVGs are never touched.
 */
class EpubImageOptimizer
{
    /**
     * @param  list<string>|null  $only  image hrefs to process (null = every picture that needs it)
     * @return array{path:string, items:list<array{href:string, before:int, after:int, from:string, to:string, changed:bool}>, saved:int}
     */
    public function optimise(string $source, string $target, int $maxWidth, int $quality, int $flagKb, ?array $only = null): array
    {
        $pkg = new EpubPackage($source);
        $dir = $pkg->dir();
        $in = new ZipArchive;
        $in->open($source, ZipArchive::RDONLY);
        $out = new ZipArchive;
        if ($out->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not write the optimised EPUB.');
        }
        $types = [];
        foreach ($pkg->images() as $img) {
            $types[$dir.$img['href']] = $img;
        }
        $items = [];
        $saved = 0;
        // mimetype first and stored, as the EPUB format requires.
        $out->addFromString('mimetype', 'application/epub+zip');
        $out->setCompressionName('mimetype', ZipArchive::CM_STORE);
        for ($i = 0; $i < $in->numFiles; $i++) {
            $name = (string) $in->getNameIndex($i);
            if ($name === 'mimetype' || str_ends_with($name, '/')) {
                continue;
            }
            $bytes = (string) $in->getFromIndex($i);
            if (isset($types[$name]) && ($only === null || in_array($types[$name]['href'], $only, true))) {
                $img = $types[$name];
                $needs = $img['width'] > $maxWidth || $img['bytes'] > $flagKb * 1024;
                $new = $needs ? $this->reencode($bytes, $img['type'], $maxWidth, $quality) : null;
                if ($new !== null && strlen($new['bytes']) < strlen($bytes)) {
                    $items[] = ['href' => $img['href'], 'before' => strlen($bytes), 'after' => strlen($new['bytes']), 'from' => $img['width'].'x'.$img['height'], 'to' => $new['w'].'x'.$new['h'], 'changed' => true];
                    $saved += strlen($bytes) - strlen($new['bytes']);
                    $bytes = $new['bytes'];
                } else {
                    $items[] = ['href' => $img['href'], 'before' => strlen($bytes), 'after' => strlen($bytes), 'from' => $img['width'].'x'.$img['height'], 'to' => $img['width'].'x'.$img['height'], 'changed' => false];
                }
            }
            $out->addFromString($name, $bytes);
        }
        $out->close();
        $in->close();

        return ['path' => $target, 'items' => $items, 'saved' => $saved];
    }

    /** @return array{bytes:string, w:int, h:int}|null null when this picture cannot be safely re-encoded */
    private function reencode(string $bytes, string $type, int $maxWidth, int $quality): ?array
    {
        if (! in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) || ! function_exists('imagecreatefromstring') || ($type === 'image/webp' && ! function_exists('imagewebp'))) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = $w > $maxWidth ? $maxWidth : $w;
        $nh = (int) round($h * $nw / max(1, $w));
        $img = $src;
        if ($nw !== $w) {
            $img = imagescale($src, $nw, $nh, IMG_BICUBIC_FIXED);
            imagedestroy($src);
            if ($img === false) {
                return null;
            }
        }
        ob_start();
        match ($type) {
            'image/jpeg' => imagejpeg($img, null, $quality),
            'image/webp' => imagewebp($img, null, $quality),
            default => (function () use ($img) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagepng($img, null, 7);
            })(),
        };
        $data = (string) ob_get_clean();
        imagedestroy($img);

        return $data === '' ? null : ['bytes' => $data, 'w' => $nw, 'h' => $nh];
    }
}
