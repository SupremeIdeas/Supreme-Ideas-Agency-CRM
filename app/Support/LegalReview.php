<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Whether each shipped legal document has been reviewed by counsel. The defaults in LegalContent are sensible starting points, NOT legal advice, so every document
 * starts as an unreviewed DRAFT and stays one until a super admin records a review. Editing a document's text clears its review (the reviewed words are no longer
 * the live words). This only informs the admin (legal editor badge, Setup health); nothing is hidden or added on the public pages.
 */
final class LegalReview
{
    private static function key(string $slug): string
    {
        return 'legalreview.'.$slug;
    }

    /** @return array{on: string, by: string}|null */
    public static function record(string $slug): ?array
    {
        $v = Setting::getValue(self::key($slug), null);

        return is_array($v) && ! empty($v['on']) ? ['on' => (string) $v['on'], 'by' => (string) ($v['by'] ?? '')] : null;
    }

    public static function reviewed(string $slug): bool
    {
        return self::record($slug) !== null;
    }

    public static function mark(string $slug, string $by): void
    {
        if (LegalContent::exists($slug)) {
            Setting::setValue(self::key($slug), ['on' => now()->toDateString(), 'by' => $by], 'legal');
        }
    }

    public static function clear(string $slug): void
    {
        Setting::where('key', self::key($slug))->get()->each->delete();
    }

    /** @return list<string> slugs still waiting for counsel */
    public static function unreviewed(): array
    {
        return array_values(array_filter(LegalContent::slugs(), fn ($s) => ! self::reviewed($s)));
    }
}
