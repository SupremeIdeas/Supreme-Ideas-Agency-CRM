<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Support\BrandSettings;

/** Logos for documents, always embedded as data URIs so a PDF never fetches anything remote (MASTER-ONLY). Follows the platform's own brand logo system; PNG/JPEG pass through, WebP is converted, SVG and unreachable files are skipped. */
final class WlInvoiceLogos
{
    public static function dataUri(string $variant): ?string
    {
        $url = BrandSettings::resolvedLogo($variant, 'light');
        if (! $url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $file = null;
        if (str_starts_with($path, '/storage/')) {
            $file = storage_path('app/public/'.substr($path, 9));
        } elseif ($path !== '') {
            $file = public_path(ltrim($path, '/'));
        }
        if (! $file || ! is_file($file) || filesize($file) > 2 * 1048576) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        $mime = str_starts_with($raw, "\x89PNG") ? 'image/png' : (str_starts_with($raw, "\xFF\xD8") ? 'image/jpeg' : (str_contains(substr($raw, 0, 16), 'WEBP') ? 'image/webp' : null));
        if ($mime === null) {
            return null;   // SVG and anything else: the header falls back to the seller name in text
        }
        if ($mime === 'image/webp') {
            if (! function_exists('imagecreatefromstring') || ($im = @imagecreatefromstring($raw)) === false) {
                return null;
            }
            imagesavealpha($im, true);
            ob_start();
            imagepng($im);
            $raw = (string) ob_get_clean();
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }
}
