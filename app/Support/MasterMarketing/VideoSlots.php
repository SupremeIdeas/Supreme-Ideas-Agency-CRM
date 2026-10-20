<?php

namespace App\Support\MasterMarketing;

use App\Models\MasterMarketingVideoSection;
use Illuminate\Support\Facades\Cache;

/**
 * MASTER-ONLY. The named places on master marketing pages where an admin can show a video (Admin > Marketing video sections).
 * A slot with no live video renders nothing, so an empty slot never leaves a gap.
 */
final class VideoSlots
{
    /** slot key => label. Add a slot here AND place <x-master-marketing.video-slot> in the page. */
    public const SLOTS = [
        // Hero cards: one video per page, shown as a rounded poster card with a play button right under the page's hero (see marketing/master-marketing/_hero-video.blade.php)
        'home.hero' => 'Hero video: Homepage',
        'about.hero' => 'Hero video: About',
        'how-it-works.hero' => 'Hero video: How it works',
        'contact.hero' => 'Hero video: Contact',
        'pricing.hero' => 'Hero video: Pricing',
        'download.hero' => 'Hero video: Download the app',
        'products.hero' => 'Hero video: Products',
        'business-suite.hero' => 'Hero video: Business Suite',
        'chat.hero' => 'Hero video: Naara Chat',
        'brain.hero' => 'Hero video: NaaraBrain',
        'gift.hero' => 'Hero video: Naara Gift',
        'white-label.hero' => 'Hero video: White Label',
        'developers.hero' => 'Hero video: Developers',
        'handbooks.hero' => 'Hero video: Travel handbooks',
        // Introduction videos on app screens (a card at the top of the screen that a person can hide)
        'business.overview' => 'Intro video: Business Suite overview',
        'merchant.overview' => 'Intro video: Merchant overview',
        'dashboard.welcome' => 'Intro video: Customer dashboard',
        // Section videos further down a page
        'home.intro' => 'Homepage: below the hero',
        'chat.intro' => 'Naara Chat page: intro',
        'brain.intro' => 'NaaraBrain page: intro',
        'gift.intro' => 'Naara Gift page: intro',
        'white-label.intro' => 'White Label page: intro',
    ];

    private const CACHE = 'mm.video_slots.v1';

    /** Route name => hero slot. The marketing layout asks this once, so every page gets its hero card without each page template remembering to. */
    private const HERO_BY_ROUTE = [
        'home' => 'home.hero', 'about' => 'about.hero', 'how-it-works' => 'how-it-works.hero', 'contact' => 'contact.hero', 'pricing' => 'pricing.hero',
        'download' => 'download.hero', 'products' => 'products.hero', 'business-suite' => 'business-suite.hero', 'brand.business-suite' => 'business-suite.hero',
        'handbooks.store' => 'handbooks.hero', 'handbooks.directory' => 'handbooks.hero', 'n2n.landing' => 'chat.hero', 'brain.landing' => 'brain.hero', 'gift.landing' => 'gift.hero', 'white-label.landing' => 'white-label.hero', 'developers' => 'developers.hero',
    ];

    public static function heroFor(?string $routeName): ?string
    {
        return self::HERO_BY_ROUTE[(string) $routeName] ?? null;
    }

    /** Route name => intro slot for the signed-in app screens (the customer layout asks this once). */
    private const INTRO_BY_ROUTE = ['business.workspace' => 'business.overview', 'merchant.dashboard' => 'merchant.overview', 'dashboard' => 'dashboard.welcome'];

    public static function introFor(?string $routeName): ?string
    {
        return self::INTRO_BY_ROUTE[(string) $routeName] ?? null;
    }

    /** Fixed slots, plus one slot per blog post (`blog:12`) and per user guide (`guide:merchant`): any number of videos, shown as a carousel. */
    public static function known(string $slot): bool
    {
        return isset(self::SLOTS[$slot]) || (bool) preg_match('/^(blog:\d{1,9}|guide:[a-z0-9_-]{1,40})$/', $slot);
    }

    /**
     * Rows of one slot as player descriptions, in order; a row whose video cannot be resolved is left out.
     *
     * @return list<array<string, mixed>>
     */
    public static function deck(string $slot): array
    {
        $out = [];
        foreach (self::for($slot) as $it) {
            $poster = trim((string) ($it['poster_url'] ?? ''));
            $v = VideoSource::parseLink((string) $it['video_url'], $poster !== '' ? \App\Support\SiteContent::imageUrl($poster) : '');
            if (! $v) {
                continue;
            }
            $file = $v['kind'] === 'file';
            $out[] = [
                'id' => (int) ($it['id'] ?? 0), 'kind' => $v['kind'], 'src' => $file ? \App\Support\SiteContent::imageUrl($v['src']) : '', 'embed' => $file ? '' : $v['embed'], 'mime' => $v['mime'],
                'poster' => $v['poster'], 'title' => (string) $it['title'], 'description' => (string) ($it['description'] ?? ''), 'label' => trim((string) ($it['label'] ?? '')),
                'duration' => trim((string) ($it['duration_label'] ?? '')), 'orientation' => ($it['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape',
                'protect' => $file && (bool) ($it['protected'] ?? true), 'preview' => $file && (bool) ($it['autoplay'] ?? false), 'loop' => (bool) ($it['loop'] ?? false),
                'created' => $it['created_at'] ?? null,
                'wave' => (is_array($it['wave'] ?? null) && count($it['wave']) === VideoWave::POINTS) ? $it['wave'] : VideoWave::synthetic((string) ($it['id'] ?? $it['video_url'])),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> live videos for a slot, in order (cached; busted on every save) */
    public static function for(string $slot): array
    {
        if (! self::known($slot)) {
            return [];
        }
        try {
            $all = Cache::remember(self::CACHE, 300, fn () => MasterMarketingVideoSection::live()->orderBy('position')->orderBy('id')->get()->groupBy('slot')->map->toArray()->all());
        } catch (\Throwable) {
            return [];
        }

        return array_values($all[$slot] ?? []);
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE);
        } catch (\Throwable) {
        }
    }
}
