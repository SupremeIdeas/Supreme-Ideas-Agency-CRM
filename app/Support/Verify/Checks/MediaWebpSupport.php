<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

class MediaWebpSupport implements Check
{
    public function area(): string
    {
        return 'media';
    }

    public function name(): string
    {
        return 'WebP image support';
    }

    public function run(): CheckResult
    {
        $gd = function_exists('imagewebp');
        $imagick = extension_loaded('imagick') && in_array('WEBP', \Imagick::queryFormats('WEBP'), true);

        return ($gd || $imagick)
            ? CheckResult::pass('This server can create WebP images.', ['gd' => $gd, 'imagick' => $imagick])
            : CheckResult::warn('Neither GD nor Imagick can write WebP, so images are served uncompressed.', ['gd' => $gd, 'imagick' => $imagick]);
    }
}
