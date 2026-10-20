<?php

namespace App\Services\BrandLinks;

use Illuminate\Support\Facades\Http;

/** "Refresh from source" for the pinned Public Suffix List (admin action). Downloads, sanity-checks, and swaps the file atomically; a bad download changes nothing. */
final class PublicSuffixUpdater
{
    public static function version(): string
    {
        $file = base_path((string) config('brand_links.psl.file'));
        foreach (is_file($file) ? (array) file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
            if (str_starts_with($line, '// VERSION:')) {
                return trim(substr($line, 11));
            }
            if (str_starts_with($line, '// ===BEGIN ICANN')) {
                break;
            }
        }

        return 'unknown';
    }

    /** @return array{ok: bool, message: string} */
    public static function refresh(): array
    {
        try {
            $r = Http::timeout(30)->withHeaders(['User-Agent' => 'NaaraLinkCheck/1.0'])->get((string) config('brand_links.psl.source_url'));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the source: '.$e->getMessage()];
        }
        $body = (string) $r->body();
        if (! $r->successful() || strlen($body) < 100000 || ! str_contains($body, '===BEGIN ICANN DOMAINS===') || ! str_contains($body, '===END ICANN DOMAINS===') || ! preg_match('/^\/\/ VERSION: /m', $body)) {
            return ['ok' => false, 'message' => 'The download did not look like the Public Suffix List, so nothing was changed.'];
        }
        $file = base_path((string) config('brand_links.psl.file'));
        $tmp = $file.'.tmp';
        file_put_contents($tmp, $body);
        rename($tmp, $file);
        PublicSuffix::flush();

        return ['ok' => true, 'message' => 'Updated to '.self::version().'.'];
    }
}
