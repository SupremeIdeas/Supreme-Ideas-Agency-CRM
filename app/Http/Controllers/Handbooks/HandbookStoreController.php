<?php

namespace App\Http\Controllers\Handbooks;

use App\Http\Controllers\Controller;
use App\Models\Handbook;
use App\Models\HandbookCategory;
use App\Support\Handbooks\HandbookSettings;

/** The Travel Handbooks landing page (MASTER-ONLY): the pitch, a hero deck of the newest titles, a Popular shelf with a See more button to the full directory, how it works, the free-with-eSIM rules (read live from the admin's settings), browse by country and topic, a swipeable New-on-the-shelf slider, FAQ. */
class HandbookStoreController extends Controller
{
    public function landing()
    {
        if (! HandbookSettings::get('store_enabled')) {
            return response()->view('marketing.master-marketing.off', ['label' => 'Travel Handbooks'], 200)->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
        }
        $base = fn () => Handbook::query()->published()->with(['categories']);
        $count = max(3, (int) HandbookSettings::get('popular_count'));
        // Popular: the admin's featured picks first, then whatever people actually unlock and buy most.
        $popular = $base()->withCount('entitlements')->orderByDesc('is_featured')->orderByDesc('entitlements_count')->orderBy('sort_order')->orderByDesc('published_at')->limit($count)->get();
        // Always the newest titles: the hero deck and the "New on the shelf" slider both read this, so a freshly published book appears at once (newest first, whatever else is popular).
        $recent = $base()->orderByRaw('coalesce(published_at, created_at) desc')->orderByDesc('id')->limit(12)->get();
        $fresh = $recent;
        $hero = $recent->take(5)->values();
        $countries = Handbook::query()->published()->whereNotNull('country_code')->selectRaw('country_code, count(*) as n')->groupBy('country_code')->orderByDesc('n')->orderBy('country_code')->limit(18)->get();
        $categories = HandbookCategory::query()->whereHas('handbooks', fn ($h) => $h->where('status', Handbook::PUBLISHED))->withCount(['handbooks' => fn ($h) => $h->where('status', Handbook::PUBLISHED)])->orderByDesc('handbooks_count')->limit(12)->get();

        return view('handbooks.landing', [
            'popular' => $popular, 'fresh' => $fresh, 'hero' => $hero, 'countries' => $countries, 'categories' => $categories, 's' => HandbookSettings::all(),
            'total' => Handbook::query()->published()->count(), 'countryTotal' => Handbook::query()->published()->whereNotNull('country_code')->distinct()->count('country_code'),
            'disclaimer' => (string) config('handbooks.disclaimer'),
        ]);
    }
}
