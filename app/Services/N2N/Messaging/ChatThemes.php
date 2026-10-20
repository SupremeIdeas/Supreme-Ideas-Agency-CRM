<?php

namespace App\Services\N2N\Messaging;

/**
 * Per-member chat themes (N2N Batch 12, MASTER-ONLY): an accent colour, an ORIGINAL wallpaper (generated gradients / SVG art, nothing copied
 * from any reference app) and an optional own photo. A theme is only ever a small set of scoped CSS variables, built here from allow-listed
 * values, so no user text can reach a stylesheet. Text on the bubbles is checked for contrast (WCAG AA, 4.5:1) in light AND dark mode for every
 * accent, and an accent that would fail is nudged until it passes, so no choice can produce unreadable messages.
 */
class ChatThemes
{
    public const MIN_RATIO = 4.5;

    private const INK = '#0d1b2a';

    private const PAPER = '#ffffff';

    /** @var array<string, array{label: string, hex: string}> */
    public const ACCENTS = [
        'teal' => ['label' => 'Teal', 'hex' => '#0A6E6E'],
        'gold' => ['label' => 'Gold', 'hex' => '#D4A017'],
        'navy' => ['label' => 'Navy', 'hex' => '#1F3A5F'],
        'ocean' => ['label' => 'Ocean', 'hex' => '#0369A1'],
        'forest' => ['label' => 'Forest', 'hex' => '#166534'],
        'rose' => ['label' => 'Rose', 'hex' => '#BE185D'],
        'plum' => ['label' => 'Plum', 'hex' => '#86198F'],
        'violet' => ['label' => 'Violet', 'hex' => '#6D28D9'],
        'ember' => ['label' => 'Ember', 'hex' => '#C2410C'],
        'slate' => ['label' => 'Slate', 'hex' => '#475569'],
    ];

    /** @return array<string, array{label: string, light: string, dark: string}> every background is built here, from nothing but numbers and fixed colours */
    public static function wallpapers(): array
    {
        $svg = fn (string $s) => 'url("data:image/svg+xml,'.rawurlencode($s).'")';
        $ankaraL = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><g fill="none" stroke="rgb(10,110,110)" stroke-opacity=".14" stroke-width="2"><circle cx="48" cy="48" r="10"/><circle cx="48" cy="48" r="22"/><circle cx="48" cy="48" r="34"/><path d="M0 48h14M82 48h14M48 0v14M48 82v14"/></g><g fill="rgb(212,160,23)" fill-opacity=".18"><rect x="-4" y="-4" width="8" height="8" transform="rotate(45 0 0)"/><rect x="92" y="-4" width="8" height="8" transform="rotate(45 96 0)"/><rect x="-4" y="92" width="8" height="8" transform="rotate(45 0 96)"/><rect x="92" y="92" width="8" height="8" transform="rotate(45 96 96)"/></g></svg>');
        $ankaraD = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><g fill="none" stroke="rgb(120,220,220)" stroke-opacity=".10" stroke-width="2"><circle cx="48" cy="48" r="10"/><circle cx="48" cy="48" r="22"/><circle cx="48" cy="48" r="34"/><path d="M0 48h14M82 48h14M48 0v14M48 82v14"/></g><g fill="rgb(212,160,23)" fill-opacity=".14"><rect x="-4" y="-4" width="8" height="8" transform="rotate(45 0 0)"/><rect x="92" y="-4" width="8" height="8" transform="rotate(45 96 0)"/><rect x="-4" y="92" width="8" height="8" transform="rotate(45 0 96)"/><rect x="92" y="92" width="8" height="8" transform="rotate(45 96 96)"/></g></svg>');
        $topoL = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><g fill="none" stroke="rgb(31,58,95)" stroke-opacity=".12" stroke-width="1.6"><path d="M0 40c30-20 50 20 80 0s60-20 120 10"/><path d="M0 70c30-20 50 20 80 0s60-20 120 10"/><path d="M0 100c30-20 50 20 80 0s60-20 120 10"/><path d="M0 130c30-20 50 20 80 0s60-20 120 10"/><path d="M0 160c30-20 50 20 80 0s60-20 120 10"/><path d="M0 190c30-20 50 20 80 0s60-20 120 10"/></g></svg>');
        $topoD = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><g fill="none" stroke="rgb(160,190,230)" stroke-opacity=".10" stroke-width="1.6"><path d="M0 40c30-20 50 20 80 0s60-20 120 10"/><path d="M0 70c30-20 50 20 80 0s60-20 120 10"/><path d="M0 100c30-20 50 20 80 0s60-20 120 10"/><path d="M0 130c30-20 50 20 80 0s60-20 120 10"/><path d="M0 160c30-20 50 20 80 0s60-20 120 10"/><path d="M0 190c30-20 50 20 80 0s60-20 120 10"/></g></svg>');
        $wavesL = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><path d="M0 20q15-14 30 0t30 0 30 0 30 0" fill="none" stroke="rgb(3,105,161)" stroke-opacity=".14" stroke-width="2"/></svg>');
        $wavesD = $svg('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><path d="M0 20q15-14 30 0t30 0 30 0 30 0" fill="none" stroke="rgb(140,200,240)" stroke-opacity=".11" stroke-width="2"/></svg>');

        return [
            'plain' => ['label' => 'Plain', 'light' => '#eef2f6', 'dark' => '#0b1623'],
            'dunes' => ['label' => 'Dunes', 'light' => 'radial-gradient(120% 55% at 15% 108%,rgba(201,139,74,.35),transparent 60%),radial-gradient(90% 45% at 95% 102%,rgba(217,168,94,.40),transparent 62%),linear-gradient(180deg,#f8eed9,#f0dcb6 60%,#e8c993)', 'dark' => 'radial-gradient(120% 55% at 15% 108%,rgba(201,139,74,.22),transparent 60%),radial-gradient(90% 45% at 95% 102%,rgba(217,168,94,.18),transparent 62%),linear-gradient(180deg,#1d150e,#2a1d12 60%,#33230f)'],
            'lagoon' => ['label' => 'Lagoon', 'light' => 'radial-gradient(70% 55% at 85% 8%,rgba(20,184,184,.28),transparent 62%),radial-gradient(80% 60% at 8% 95%,rgba(3,105,161,.22),transparent 62%),linear-gradient(180deg,#eaf7f7,#dff0f4)', 'dark' => 'radial-gradient(70% 55% at 85% 8%,rgba(20,184,184,.22),transparent 62%),radial-gradient(80% 60% at 8% 95%,rgba(3,105,161,.28),transparent 62%),linear-gradient(180deg,#07181e,#0a2230)'],
            'aurora' => ['label' => 'Aurora', 'light' => 'radial-gradient(60% 40% at 20% 15%,rgba(109,40,217,.20),transparent 65%),radial-gradient(60% 40% at 85% 30%,rgba(20,184,184,.24),transparent 65%),radial-gradient(70% 45% at 50% 100%,rgba(190,24,93,.16),transparent 65%),linear-gradient(180deg,#f4f0fb,#eaf4f6)', 'dark' => 'radial-gradient(60% 40% at 20% 15%,rgba(139,92,246,.30),transparent 65%),radial-gradient(60% 40% at 85% 30%,rgba(20,184,184,.26),transparent 65%),radial-gradient(70% 45% at 50% 100%,rgba(236,72,153,.18),transparent 65%),linear-gradient(180deg,#0e0a1c,#08171d)'],
            'harmattan' => ['label' => 'Harmattan', 'light' => 'linear-gradient(180deg,#f3ece0 0%,#e9dfd0 45%,#ddd0bd 100%)', 'dark' => 'linear-gradient(180deg,#181410 0%,#221c15 50%,#2b231a 100%)'],
            'dots' => ['label' => 'Dots', 'light' => 'radial-gradient(rgba(10,110,110,.20) 1.3px,transparent 1.6px) 0 0/20px 20px,#eef7f6', 'dark' => 'radial-gradient(rgba(120,220,220,.14) 1.3px,transparent 1.6px) 0 0/20px 20px,#08171b'],
            'ankara' => ['label' => 'Ankara', 'light' => $ankaraL.' 0 0/96px 96px,#fbf7ee', 'dark' => $ankaraD.' 0 0/96px 96px,#0d1b22'],
            'topo' => ['label' => 'Topo', 'light' => $topoL.' 0 0/200px 200px,#eef2f8', 'dark' => $topoD.' 0 0/200px 200px,#0b1420'],
            'waves' => ['label' => 'Waves', 'light' => $wavesL.' 0 0/120px 40px,#eaf5fb', 'dark' => $wavesD.' 0 0/120px 40px,#071822'],
            'kente' => ['label' => 'Kente', 'light' => 'repeating-linear-gradient(90deg,rgba(212,160,23,.22) 0 8px,transparent 8px 30px),repeating-linear-gradient(0deg,rgba(10,110,110,.14) 0 6px,transparent 6px 38px),#faf3e2', 'dark' => 'repeating-linear-gradient(90deg,rgba(212,160,23,.16) 0 8px,transparent 8px 30px),repeating-linear-gradient(0deg,rgba(20,184,184,.12) 0 6px,transparent 6px 38px),#14110b'],
        ];
    }

    /** @return array{accent: string, wallpaper: string, dim: int, photo_id: ?int} a stored theme filled with defaults (never throws on old or odd data) */
    public static function normalise(?array $t): array
    {
        $t = $t ?? [];

        return [
            'accent' => isset(self::ACCENTS[$t['accent'] ?? '']) ? $t['accent'] : 'teal',
            'wallpaper' => isset(self::wallpapers()[$t['wallpaper'] ?? '']) ? $t['wallpaper'] : 'plain',
            'dim' => max(0, min(60, (int) ($t['dim'] ?? 0))),
            'photo_id' => isset($t['photo_id']) && (int) $t['photo_id'] > 0 ? (int) $t['photo_id'] : null,
        ];
    }

    /** Validate a theme the member submitted. @throws MessagingException */
    public static function validate(array $in): array
    {
        if (isset($in['accent']) && ! isset(self::ACCENTS[$in['accent']])) {
            throw new MessagingException('invalid', 'That colour is not available.');
        }
        if (isset($in['wallpaper']) && ! isset(self::wallpapers()[$in['wallpaper']])) {
            throw new MessagingException('invalid', 'That wallpaper is not available.');
        }
        if (isset($in['dim']) && (! is_numeric($in['dim']) || $in['dim'] < 0 || $in['dim'] > 60)) {
            throw new MessagingException('invalid', 'Dimming can be 0 to 60.');
        }

        return self::normalise($in);
    }

    /**
     * The scoped CSS for one thread. $scope is a selector this class builds from a number, never from user text.
     *
     * @return array{css: string, checks: array<string, float>, pass: bool}
     */
    public static function css(array $theme, string $scope, ?string $photoUrl = null): array
    {
        $theme = self::normalise($theme);
        $wp = self::wallpapers()[$theme['wallpaper']];
        $out = ['light' => self::bubbles($theme['accent'], false), 'dark' => self::bubbles($theme['accent'], true)];
        $dim = $theme['dim'] / 100;
        $photo = $photoUrl !== null && preg_match('~^/[A-Za-z0-9/_\-.]+$~', $photoUrl) === 1
            ? ($dim > 0 ? 'linear-gradient(rgba(0,0,0,'.$dim.'),rgba(0,0,0,'.$dim.')),' : '').'url("'.$photoUrl.'") center/cover no-repeat' : null;

        $vars = function (array $b, string $wall) use ($photo) {
            return '--nc-wall:'.($photo ?? $wall).';--nc-out-bg:'.$b['out_bg'].';--nc-out-fg:'.$b['out_fg'].';--nc-out-meta:'.$b['out_meta'].';--nc-in-bg:'.$b['in_bg'].';--nc-in-fg:'.$b['in_fg'].';--nc-in-meta:'.$b['in_meta'].';';
        };
        $css = $scope.'{'.$vars($out['light'], $wp['light']).'}html.dark '.$scope.'{'.$vars($out['dark'], $wp['dark']).'}';
        $checks = [];
        foreach (['light', 'dark'] as $m) {
            foreach (['out', 'in'] as $s) {
                $checks["{$s}_{$m}"] = round(self::ratio($out[$m][$s.'_fg'], $out[$m][$s.'_bg']), 2);
                $checks["{$s}_{$m}_meta"] = round(self::ratio($out[$m][$s.'_meta'], $out[$m][$s.'_bg']), 2);
            }
        }

        return ['css' => $css, 'checks' => $checks, 'pass' => min($checks) >= self::MIN_RATIO];
    }

    /** @return array<string, string> bubble colours for one accent in one mode; every text/background pair is at least 4.5:1 by construction */
    public static function bubbles(string $accent, bool $dark): array
    {
        $hex = self::ACCENTS[$accent]['hex'] ?? self::ACCENTS['teal']['hex'];
        $bg = $dark && self::lum($hex) < 0.12 ? self::mix($hex, '#ffffff', 0.35) : $hex;   // a dark accent would vanish on a dark wallpaper
        [$bg, $fg] = self::readable($bg);
        $meta = self::metaOn($fg, $bg);

        $inBase = $dark ? '#1c2b3c' : '#ffffff';
        $inBg = self::mix($inBase, $hex, 0.08);
        $inFg = $dark ? '#f0f4f8' : self::INK;
        [$inBg, $inFg] = self::readable($inBg, [$inFg]);

        return ['out_bg' => $bg, 'out_fg' => $fg, 'out_meta' => $meta, 'in_bg' => $inBg, 'in_fg' => $inFg, 'in_meta' => self::metaOn($inFg, $inBg)];
    }

    /** Pick ink or paper (or the given candidates) for this background, nudging the background until the text reaches the minimum. @return array{0: string, 1: string} */
    private static function readable(string $bg, ?array $candidates = null): array
    {
        $candidates ??= [self::PAPER, self::INK];
        for ($i = 0; $i < 24; $i++) {
            usort($candidates, fn ($a, $b) => self::ratio($b, $bg) <=> self::ratio($a, $bg));
            if (self::ratio($candidates[0], $bg) >= self::MIN_RATIO + 0.2) {   // a little headroom so the meta line (slightly softer) also passes
                return [$bg, $candidates[0]];
            }
            // Nudge away from the text colour: lighter bg for dark text, darker bg for light text.
            $bg = self::mix($bg, self::lum($candidates[0]) > 0.5 ? '#000000' : '#ffffff', 0.08);
        }

        return [$bg, $candidates[0]];
    }

    /** A softer shade of the text colour for timestamps and ticks that still passes 4.5:1. */
    private static function metaOn(string $fg, string $bg): string
    {
        foreach ([0.28, 0.2, 0.12, 0.06, 0.0] as $t) {
            $c = self::mix($fg, $bg, $t);
            if (self::ratio($c, $bg) >= self::MIN_RATIO) {
                return $c;
            }
        }

        return $fg;
    }

    // ---- colour maths ------------------------------------------------------------------------------------

    /** WCAG relative luminance of a #rrggbb colour. */
    public static function lum(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $f = fn (float $v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::lum($a);
        $lb = self::lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** $t of $b mixed into $a. */
    private static function mix(string $a, string $b, float $t): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($x[$i] * (1 - $t) + $y[$i] * $t), [0, 1, 2]));
    }
}
