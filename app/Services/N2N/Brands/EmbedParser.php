<?php

namespace App\Services\N2N\Brands;

/**
 * Turns whatever a brand owner pastes (a URL OR an `<iframe>` embed snippet) into provider + id, and DISCARDS everything else (N2N Batch 8,
 * MASTER-ONLY). Raw HTML is never stored and never output: the page renders its own iframe from provider + id. A snippet that carries
 * anything beyond one allow-listed iframe (script, handlers, javascript:, a second iframe, an unknown provider) is rejected outright rather
 * than "cleaned", because a half-sanitised snippet is how XSS gets in.
 */
class EmbedParser
{
    /** @return array{provider: string, id: string, kind: string} @throws BrandLimitException */
    public static function parse(string $input): array
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 2000) {
            throw new BrandLimitException('embed', 'Paste a video or player link, or its embed code.');
        }
        $url = $input;
        if (str_contains($input, '<')) {
            $url = self::srcFromSnippet($input);
        }
        $parsed = self::fromUrl($url);
        if ($parsed === null) {
            throw new BrandLimitException('provider', 'That player is not supported. Supported: YouTube, Vimeo, Spotify, Apple Music, Boomplay, Amazon Music and SoundCloud.');
        }

        return $parsed;
    }

    /** @throws BrandLimitException */
    private static function srcFromSnippet(string $html): string
    {
        $lower = strtolower($html);
        foreach (['<script', 'javascript:', 'data:', 'vbscript:', 'srcdoc', '<object', '<embed', '<style', '<link', '<meta', '<svg', '<img', '<form', '<base'] as $bad) {
            if (str_contains($lower, $bad)) {
                throw new BrandLimitException('unsafe', 'That embed code contains something we do not allow.');
            }
        }
        if (preg_match('/\son[a-z]+\s*=/i', $html) === 1) {
            throw new BrandLimitException('unsafe', 'That embed code contains something we do not allow.');
        }
        if (substr_count($lower, '<iframe') !== 1 || preg_match('/^\s*<iframe\b[^>]*>\s*<\/iframe>\s*$/is', $html) !== 1) {
            throw new BrandLimitException('unsafe', 'Paste exactly one player embed (a single iframe).');
        }
        if (preg_match('/\ssrc\s*=\s*(["\'])(https:\/\/[^"\'<>\s]+)\1/i', $html, $m) !== 1) {
            throw new BrandLimitException('unsafe', 'That embed code has no https player address.');
        }

        return html_entity_decode($m[2], ENT_QUOTES);
    }

    /** @return array{provider: string, id: string, kind: string}|null */
    public static function fromUrl(string $url): ?array
    {
        $p = parse_url(trim($url));
        if (! is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $host = strtolower((string) $p['host']);
        $path = (string) ($p['path'] ?? '');
        parse_str((string) ($p['query'] ?? ''), $q);

        if (in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com', 'youtu.be'], true)) {
            $id = $host === 'youtu.be' ? ltrim($path, '/') : (preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $path, $m) ? $m[1] : (string) ($q['v'] ?? ''));

            return preg_match('/^[A-Za-z0-9_-]{6,15}$/', $id) === 1 ? ['provider' => 'youtube', 'id' => $id, 'kind' => 'video'] : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return preg_match('#^/(?:video/)?(\d{5,12})(?:/|$)#', $path, $m) === 1 ? ['provider' => 'vimeo', 'id' => $m[1], 'kind' => 'video'] : null;
        }
        if ($host === 'open.spotify.com') {
            return preg_match('#^/(?:embed/)?(track|album|playlist|artist|episode|show)/([A-Za-z0-9]{10,30})$#', $path, $m) === 1
                ? ['provider' => 'spotify', 'id' => $m[1].'/'.$m[2], 'kind' => 'audio'] : null;
        }
        if (in_array($host, ['embed.music.apple.com', 'music.apple.com'], true)) {
            return preg_match('#^/([a-z]{2})/(song|album|playlist)/([a-z0-9%-]{1,120})/(\d{5,12}|pl\.[a-f0-9]{32})/?$#i', $path, $m) === 1
                ? ['provider' => 'apple_music', 'id' => strtolower($m[1]).'/'.strtolower($m[2]).'/'.strtolower($m[3]).'/'.strtolower($m[4]), 'kind' => 'audio'] : null;
        }
        if (in_array($host, ['www.boomplay.com', 'boomplay.com'], true)) {
            if (preg_match('#^/embed/(\d{5,12})/(MUSIC|COL)/?$#', $path, $m) !== 1) {
                return null;
            }
            if ($m[2] === 'COL') {
                return ['provider' => 'boomplay', 'id' => 'COL:'.$m[1], 'kind' => 'audio'];
            }
            $type = (string) ($q['colType'] ?? '');
            $col = (string) ($q['colID'] ?? '');

            return ['provider' => 'boomplay', 'id' => 'MUSIC:'.$m[1].(preg_match('/^\d{1,2}$/', $type) === 1 && preg_match('/^\d{5,12}$/', $col) === 1 ? ':'.$type.':'.$col : ''), 'kind' => 'audio'];
        }
        if ($host === 'music.amazon.com') {
            $asin = preg_match('#^/embed/([A-Z0-9]{10})/?$#', $path, $m) === 1 ? $m[1] : null;
            $id = (string) ($q['id'] ?? '');
            $mkt = (string) ($q['marketplaceId'] ?? '');
            $terr = strtoupper((string) ($q['musicTerritory'] ?? ''));

            return $asin !== null && preg_match('/^[A-Za-z0-9]{6,24}$/', $id) === 1 && preg_match('/^[A-Z0-9]{10,16}$/', $mkt) === 1 && preg_match('/^[A-Z]{2}$/', $terr) === 1
                ? ['provider' => 'amazon_music', 'id' => $asin.'|'.$id.'|'.$mkt.'|'.$terr, 'kind' => 'audio'] : null;
        }
        if (in_array($host, ['soundcloud.com', 'w.soundcloud.com'], true)) {
            $target = $host === 'w.soundcloud.com' ? (string) ($q['url'] ?? '') : 'https://soundcloud.com'.$path;
            $tp = parse_url($target);
            if (! is_array($tp) || strtolower((string) ($tp['host'] ?? '')) !== 'soundcloud.com' || preg_match('#^/[A-Za-z0-9_-]+(?:/(?:sets/)?[A-Za-z0-9_-]+)?$#', (string) ($tp['path'] ?? '')) !== 1) {
                return null;
            }

            return ['provider' => 'soundcloud', 'id' => ltrim((string) $tp['path'], '/'), 'kind' => 'audio'];
        }

        return null;
    }
}
