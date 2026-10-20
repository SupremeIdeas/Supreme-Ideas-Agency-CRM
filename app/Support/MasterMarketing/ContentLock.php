<?php

namespace App\Support\MasterMarketing;

use App\Models\Setting;

/**
 * MASTER ONLY. The content lock: makes it hard to save or lift Naara's images and videos from the public site. Admin turns each part on or off (Admin > Content protection).
 * Honest scope: this stops right-click save, drag-out, long-press save, "save page", print, and saving self-hosted videos. A browser cannot stop a person photographing or
 * recording their own screen; the optional shield and the apps' screen protection (only while a protected video plays) make that harder, not impossible.
 */
final class ContentLock
{
    private const KEY = 'site.content_lock';

    /** @return array{enabled: bool, images: bool, videos: bool, keys: bool, shield: bool, scope: string} */
    public static function settings(): array
    {
        try {
            $s = (array) Setting::getValue(self::KEY, []);
        } catch (\Throwable) {
            $s = [];
        }

        $scope = $s['scope'] ?? 'marketing';

        return [
            'enabled' => (bool) ($s['enabled'] ?? true),     // the master switch
            'images' => (bool) ($s['images'] ?? true),       // no right-click save, drag-out or long-press save on images
            'videos' => (bool) ($s['videos'] ?? true),       // no download button, right-click menu or picture-in-picture on any video
            'keys' => (bool) ($s['keys'] ?? true),           // Save page and Print shortcuts, and printing images
            'shield' => (bool) ($s['shield'] ?? false),      // blur the page while it is hidden or PrintScreen is pressed (stricter; off by default)
            'scope' => in_array($scope, ['marketing', 'all'], true) ? $scope : 'marketing',
        ];
    }

    public static function save(array $in): void
    {
        $cur = self::settings();
        $new = [];
        foreach (['enabled', 'images', 'videos', 'keys', 'shield'] as $k) {
            $new[$k] = array_key_exists($k, $in) ? (bool) $in[$k] : $cur[$k];
        }
        $scope = $in['scope'] ?? $cur['scope'];
        $new['scope'] = in_array($scope, ['marketing', 'all'], true) ? $scope : 'marketing';
        Setting::setValue(self::KEY, $new, 'security', 'Content lock for images and videos');
    }

    /** Should the lock run on this page? `$surface` is 'marketing' (public pages) or 'app' (signed-in screens). */
    public static function active(string $surface): bool
    {
        $s = self::settings();

        return $s['enabled'] && ($surface === 'marketing' || $s['scope'] === 'all');
    }
}
