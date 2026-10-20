<?php

namespace App\Support\MasterMarketing;

use App\Models\Setting;
use App\Support\SiteContent;

/**
 * MASTER-ONLY. Turns the White Label page's `video` section (Site Editor) into one player description: an uploaded file, a direct video link, a YouTube
 * video or a Vimeo video. Everything that reaches an <iframe> or <video> is rebuilt from a validated id or an https URL, never echoed from the raw field.
 */
final class VideoSource
{
    /** Container formats accepted for upload. The key is the file extension we store under. */
    public const UPLOAD_TYPES = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

    public const DEFAULT_MAX_MB = 800;

    /** Brand teal, used when the admin has not picked a player colour. */
    public const DEFAULT_COLOR = '#0A6E6E';

    public static function maxMb(): int
    {
        try {
            $v = (int) Setting::getValue('site.video_max_mb', self::DEFAULT_MAX_MB);
        } catch (\Throwable) {
            $v = self::DEFAULT_MAX_MB;
        }

        return max(10, min(4096, $v ?: self::DEFAULT_MAX_MB));
    }

    /**
     * @param  array<string, mixed>  $s  the `video` section fields
     * @return null|array{kind: string, id: string, src: string, embed: string, poster: string, mime: string}
     */
    public static function resolve(array $s): ?array
    {
        $poster = SiteContent::imageUrl((string) ($s['poster_image'] ?? ''));
        $source = (string) ($s['video_source'] ?? 'upload');

        if ($source === 'upload') {
            $file = trim((string) ($s['video_file'] ?? ''));
            if ($file === '' || ! (self::isSafeUrl($file) || VideoVault::isRef($file))) {
                return null;
            }

            return ['kind' => 'file', 'id' => '', 'src' => VideoVault::playable($file), 'embed' => '', 'poster' => $poster, 'mime' => self::mimeFor($file)];
        }

        return self::parseLink(trim((string) ($s['video_url'] ?? '')), $poster, $s);
    }

    /** @return null|array{kind: string, id: string, src: string, embed: string, poster: string, mime: string} */
    public static function parseLink(string $url, string $poster = '', array $s = []): ?array
    {
        if ($url === '') {
            return null;
        }

        if (($id = self::youtubeId($url)) !== null) {
            $loop = self::on($s, 'opt_loop', false) ? '&loop=1&playlist='.$id : '';
            $embed = 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0&modestbranding=1&playsinline=1&iv_load_policy=3'.$loop;
            // YouTube always has a thumbnail, so a link-only video still shows a real poster until the admin uploads a nicer one.
            return ['kind' => 'youtube', 'id' => $id, 'src' => '', 'embed' => $embed, 'poster' => $poster !== '' ? $poster : 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg', 'mime' => ''];
        }

        if (($v = self::vimeoId($url)) !== null) {
            $color = ltrim(self::color($s), '#');
            $embed = 'https://player.vimeo.com/video/'.$v['id'].'?autoplay=1&dnt=1&title=0&byline=0&portrait=0&color='.$color.($v['hash'] !== '' ? '&h='.$v['hash'] : '').(self::on($s, 'opt_loop', false) ? '&loop=1' : '');

            return ['kind' => 'vimeo', 'id' => $v['id'], 'src' => '', 'embed' => $embed, 'poster' => $poster, 'mime' => ''];
        }

        if (VideoVault::isRef($url) && VideoVault::pathOf($url) !== null) {
            return ['kind' => 'file', 'id' => '', 'src' => VideoVault::playable($url), 'embed' => '', 'poster' => $poster, 'mime' => self::mimeFor($url)];
        }

        if (self::isSafeUrl($url) && preg_match('~\.(mp4|webm|mov)(\?.*)?$~i', $url)) {
            return ['kind' => 'file', 'id' => '', 'src' => $url, 'embed' => '', 'poster' => $poster, 'mime' => self::mimeFor($url)];
        }

        return null;
    }

    public static function youtubeId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        $host = strtolower((string) parse_url($input, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.|music\.)/', '', $host);
        if (! in_array($host, ['youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            return null;
        }
        if (preg_match('~(?:youtu\.be/|[?&]v=|/embed/|/shorts/|/live/|/v/)([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return null|array{id: string, hash: string} */
    public static function vimeoId(string $input): ?array
    {
        $host = strtolower((string) parse_url($input, PHP_URL_HOST));
        if (! in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }
        if (! preg_match('~(?:video/|vimeo\.com/(?:[a-z]+/)*)(\d{5,12})(?:/([a-f0-9]{8,12}))?~i', $input, $m)) {
            return null;
        }
        $hash = $m[2] ?? '';
        if ($hash === '' && preg_match('/[?&]h=([a-f0-9]{8,12})/i', $input, $h)) {
            $hash = $h[1];
        }

        return ['id' => $m[1], 'hash' => $hash];
    }

    /** A hex colour from the `player_color` field, or the brand teal. */
    public static function color(array $s): string
    {
        $c = trim((string) ($s['player_color'] ?? ''));

        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : self::DEFAULT_COLOR;
    }

    /** An on/off option stored as the strings `on` / `off`. */
    public static function on(array $s, string $key, bool $default): bool
    {
        $v = $s[$key] ?? ($default ? 'on' : 'off');

        return in_array(strtolower((string) $v), ['on', '1', 'yes', 'true'], true);
    }

    private static function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;      // a file on this server
        }

        return preg_match('~^https://[^\s"\'<>]+$~i', $url) === 1;
    }

    private static function mimeFor(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return self::UPLOAD_TYPES[$ext] ?? 'video/mp4';
    }
}
