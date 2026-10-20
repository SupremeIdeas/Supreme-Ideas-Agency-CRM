<?php

namespace App\Support;

use App\Models\FaqCategory;
use Illuminate\Support\Facades\Cache;

/**
 * Cached read path for the dedicated /faq page's categories + items — the
 * public page hits this on every request, so it's cached like every other
 * heavy-ish content lookup in this codebase (SiteChrome, PageSections).
 * Flushed by `Admin\FaqManager` on any category/item save/delete.
 */
class FaqContent
{
    private const CACHE_KEY = 'faq.content.v1';

    /** @return \Illuminate\Support\Collection<int, FaqCategory> Active categories, each with its active items, in display order. */
    public static function categories()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return FaqCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->get()
                ->filter(fn (FaqCategory $c) => $c->items->isNotEmpty())
                ->values();
        });
    }

    public static function totalAnswerCount(): int
    {
        return self::categories()->sum(fn (FaqCategory $c) => $c->items->count());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
