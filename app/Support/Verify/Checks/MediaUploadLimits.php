<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

class MediaUploadLimits implements Check
{
    public function area(): string
    {
        return 'media';
    }

    public function name(): string
    {
        return 'Upload limits and temp storage';
    }

    public function run(): CheckResult
    {
        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        $tmp = sys_get_temp_dir();
        $storage = storage_path('app');
        $evidence = ['upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'max_execution_time' => ini_get('max_execution_time'), 'temp_writable' => is_writable($tmp), 'storage_writable' => is_writable($storage)];

        if (! $evidence['temp_writable'] || ! $evidence['storage_writable']) {
            return CheckResult::fail('The temp or storage folder is not writable, so uploads will fail.', $evidence);
        }
        if ($upload > 0 && $post > 0 && $post < $upload) {
            return CheckResult::fail('post_max_size is smaller than upload_max_filesize, so large uploads are cut off.', $evidence);
        }
        if ($upload > 0 && $upload < 8 * 1024 * 1024) {
            return CheckResult::warn('upload_max_filesize is under 8 MB. Photos and voice notes may be refused; large videos need the chunked uploader.', $evidence);
        }

        return CheckResult::pass('Upload limits and temp storage look fine.', $evidence);
    }

    private static function bytes(string $v): int
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
}
