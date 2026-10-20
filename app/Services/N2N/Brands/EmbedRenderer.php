<?php

namespace App\Services\N2N\Brands;

use App\Models\N2N\BrandEmbed;

/**
 * Builds OUR iframe attributes from provider + id (N2N Batch 8, MASTER-ONLY). Nothing the owner typed is ever echoed. The page shows a
 * thumbnail / placeholder first and swaps in this iframe only on tap, so no third-party request happens before the visitor chooses to play.
 * The sandbox is minimal and per provider: players that need cookies/storage get allow-same-origin; none gets top-navigation or forms.
 */
class EmbedRenderer
{
    private const SANDBOX = 'allow-scripts allow-same-origin allow-presentation allow-popups';

    /** @return array{src: string, sandbox: string, allow: string, height: int}|null */
    public static function iframe(BrandEmbed|array $e): ?array
    {
        $provider = is_array($e) ? $e['provider'] : $e->provider;
        $id = is_array($e) ? $e['id'] : $e->provider_id;
        $src = match ($provider) {
            'youtube' => preg_match('/^[A-Za-z0-9_-]{6,15}$/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0' : null,
            'vimeo' => preg_match('/^\d{5,12}$/', $id) ? 'https://player.vimeo.com/video/'.$id.'?autoplay=1&dnt=1' : null,
            'spotify' => preg_match('#^(track|album|playlist|artist|episode|show)/[A-Za-z0-9]{10,30}$#', $id) ? 'https://open.spotify.com/embed/'.$id : null,
            'soundcloud' => preg_match('#^[A-Za-z0-9_-]+(?:/(?:sets/)?[A-Za-z0-9_-]+)?$#', $id) ? 'https://w.soundcloud.com/player/?auto_play=true&url='.rawurlencode('https://soundcloud.com/'.$id) : null,
            'apple_music' => preg_match('#^[a-z]{2}/(song|album|playlist)/[a-z0-9%-]{1,120}/(\d{5,12}|pl\.[a-f0-9]{32})$#', $id) ? 'https://embed.music.apple.com/'.$id : null,
            'boomplay' => self::boomplay($id),
            'amazon_music' => self::amazon($id),
            default => null,
        };
        if ($src === null) {
            return null;
        }

        return ['src' => $src, 'sandbox' => self::SANDBOX, 'allow' => 'autoplay; encrypted-media; fullscreen; picture-in-picture', 'height' => self::height($provider, $id)];
    }

    public static function label(string $provider): string
    {
        return ['apple_music' => 'Apple Music', 'amazon_music' => 'Amazon Music', 'boomplay' => 'Boomplay', 'spotify' => 'Spotify', 'soundcloud' => 'SoundCloud', 'youtube' => 'YouTube', 'vimeo' => 'Vimeo'][$provider] ?? ucfirst($provider);
    }

    private static function height(string $provider, string $id): int
    {
        return match ($provider) {
            'spotify' => preg_match('#^(track|episode)/#', $id) ? 152 : 352,
            'soundcloud' => 152,
            'apple_music' => str_contains($id, '/song/') ? 175 : 450,
            'boomplay' => str_starts_with($id, 'COL:') ? 450 : 300,
            'amazon_music' => 352,
            default => 315,
        };
    }

    private static function boomplay(string $id): ?string
    {
        if (preg_match('/^COL:(\d{5,12})$/', $id, $m) === 1) {
            return 'https://www.boomplay.com/embed/'.$m[1].'/COL';
        }
        if (preg_match('/^MUSIC:(\d{5,12})(?::(\d{1,2}):(\d{5,12}))?$/', $id, $m) === 1) {
            return 'https://www.boomplay.com/embed/'.$m[1].'/MUSIC'.(isset($m[2]) ? '?colType='.$m[2].'&colID='.$m[3] : '');
        }

        return null;
    }

    private static function amazon(string $id): ?string
    {
        if (preg_match('/^([A-Z0-9]{10})\|([A-Za-z0-9]{6,24})\|([A-Z0-9]{10,16})\|([A-Z]{2})$/', $id, $m) !== 1) {
            return null;
        }

        return 'https://music.amazon.com/embed/'.$m[1].'/?id='.$m[2].'&marketplaceId='.$m[3].'&musicTerritory='.$m[4];
    }

    /** The public page URL used for "open on the provider" fallbacks. */
    public static function watchUrl(BrandEmbed $e): ?string
    {
        return match ($e->provider) {
            'youtube' => 'https://www.youtube.com/watch?v='.$e->provider_id,
            'vimeo' => 'https://vimeo.com/'.$e->provider_id,
            'spotify' => 'https://open.spotify.com/'.$e->provider_id,
            'soundcloud' => 'https://soundcloud.com/'.$e->provider_id,
            'apple_music' => 'https://music.apple.com/'.$e->provider_id,
            default => null,
        };
    }
}
