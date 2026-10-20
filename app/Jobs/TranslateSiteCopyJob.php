<?php

namespace App\Jobs;

use App\Services\AI\AnthropicClient;
use App\Support\Locale;
use App\Support\SiteContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Translate the marketing copy an admin has edited in the Site Editor into every
 * shipped language, off the request cycle (money-safety rule 8: external API calls
 * are always queued). Only text the admin actually changed is sent; the shipped
 * defaults already have curated translations in lang/{locale}/site.php.
 *
 * Each stored translation carries a fingerprint of the English edit it came from, so
 * editing the English again makes the old translation inert (visitors see the new
 * English) until this job runs again. A failure for one language never blocks the
 * others, and a language that fails simply keeps showing the English edit.
 */
class TranslateSiteCopyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    /** Fields that are settings, links or styling rather than words. */
    private const NON_TEXT = '/(url|link|href|colou?r|image|icon|route|radius|size|style|class|key|id|order|visible|slug|align|variant|gradient|bg|src)/i';

    public function __construct(public string $page) {}

    public function handle(AnthropicClient $ai): void
    {
        if (! $ai->enabled() || ! in_array($this->page, SiteContent::editablePages(), true)) {
            return;
        }

        $edited = self::editedText($this->page);
        if ($edited === []) {
            return;
        }

        $stored = SiteContent::adminTranslations($this->page);
        $names = [];
        foreach (Locale::available() as $code => $meta) {
            if ($code !== 'en') {
                $names[$code] = $meta['name'];
            }
        }

        foreach ($names as $code => $language) {
            $pending = [];
            foreach ($edited as $section => $fields) {
                foreach ($fields as $field => $value) {
                    $hash = SiteContent::fingerprint($value);
                    if (($stored[$code][$section][$field]['h'] ?? null) !== $hash) {
                        $pending[$section.'|'.$field] = $value;
                    }
                }
            }
            if ($pending === []) {
                continue;
            }

            try {
                $out = $ai->completeJson(
                    "You translate website marketing copy for NaaraSim, a pan-African eSIM and virtual phone number service, into {$language}. "
                    .'Keep the meaning, a warm and direct tone, and the length similar. Never translate or alter: brand and product names (Naara, NaaraSim, NaaraCare, Nia, Naara Line, Naara Verify, Naara Rent, Naara Data, Naara Connect, Naara Gift, NaaraCredits), '
                    .'numbers, currency symbols, URLs, e-mail addresses, :placeholders, {tokens}, or HTML tags and attributes. '
                    .'You receive a JSON object of keys to values; a value is a string or an array/object of strings. Return ONLY a JSON object with exactly the same keys and exactly the same structure, with only the human-readable strings translated.',
                    [['role' => 'user', 'content' => json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                    8000,
                );
            } catch (\Throwable $e) {
                Log::warning('Site copy translation failed', ['page' => $this->page, 'locale' => $code, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($pending as $composite => $original) {
                $translated = $out[$composite] ?? null;
                if (! self::sameShape($original, $translated)) {
                    continue;
                }
                [$section, $field] = explode('|', $composite, 2);
                $stored[$code][$section][$field] = ['v' => $translated, 'h' => SiteContent::fingerprint($original)];
            }

            SiteContent::saveAdminTranslations($this->page, $stored);
        }
    }

    /**
     * The text fields the admin has changed from the shipped defaults: section => field => string|array.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function editedText(string $page): array
    {
        $defaults = SiteContent::defaults()[$page] ?? [];
        $out = [];
        foreach (SiteContent::overrides($page) as $section => $over) {
            foreach ($over as $field => $value) {
                if (preg_match(self::NON_TEXT, (string) $field) === 1 || $value === null || $value === '' || $value === []) {
                    continue;
                }
                if (($defaults[$section][$field] ?? null) === $value || ! self::hasWords($value)) {
                    continue;
                }
                $out[$section][$field] = $value;
            }
        }

        return $out;
    }

    /** True when a string, or any string inside an array, contains letters (so a bare number, colour or URL is skipped). */
    private static function hasWords(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (self::hasWords($v)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($value) && preg_match('/\p{L}{2,}/u', $value) === 1 && preg_match('#^(https?:)?//|^/|^\#[0-9a-f]{3,8}$#i', trim($value)) !== 1;
    }

    /** The reply must mirror the original's structure, so a malformed answer can never corrupt a page. */
    private static function sameShape(mixed $original, mixed $translated): bool
    {
        if (is_string($original)) {
            return is_string($translated) && ($original === '' ? $translated === '' : trim($translated) !== '');
        }
        if (is_array($original)) {
            if (! is_array($translated) || array_keys($original) !== array_keys($translated)) {
                return false;
            }
            foreach ($original as $k => $v) {
                if (! self::sameShape($v, $translated[$k])) {
                    return false;
                }
            }

            return true;
        }

        return $original === $translated; // numbers / booleans must come back untouched
    }
}
