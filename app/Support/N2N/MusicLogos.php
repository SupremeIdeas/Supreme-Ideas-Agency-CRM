<?php

namespace App\Support\N2N;

/**
 * Logos for music platforms and distributors (N2N, MASTER-ONLY): committed files only, never a live hotlink. A platform or distributor without a
 * bundled mark gets its initials on a clean chip (never a made-up logo). Platform marks live in public/images/music/platforms/{slug}.webp, distributor marks in
 * public/images/music/distributors.
 *
 * @phpstan-type Logo array{label: string, url: ?string, initial: string, dark: bool}
 */
final class MusicLogos
{
    /** @return array{label: string, url: ?string, initial: string, dark: bool} */
    public static function platform(string $slug): array
    {
        $label = (string) (config('n2n_music_platforms')[$slug]['label'] ?? ucfirst(str_replace('_', ' ', $slug)));
        $rel = 'images/music/platforms/'.$slug.'.webp';

        return ['label' => $label, 'url' => preg_match('/^[a-z_]+$/', $slug) === 1 && is_file(public_path($rel)) ? asset($rel) : null, 'initial' => mb_strtoupper(mb_substr($label, 0, 1)), 'dark' => false];
    }

    /** @return array{label: string, url: ?string, initial: string, dark: bool}|null */
    public static function distributor(?string $slug): ?array
    {
        $d = $slug !== null ? (config('n2n_music_distributors.distributors')[$slug] ?? null) : null;
        if ($d === null) {
            return null;
        }
        $rel = $d['logo'] ? 'images/music/distributors/'.$d['logo'] : null;

        return ['label' => (string) $d['label'], 'url' => $rel && is_file(public_path($rel)) ? asset($rel) : null, 'initial' => mb_strtoupper(mb_substr((string) $d['label'], 0, 1)), 'dark' => (bool) $d['dark']];
    }

    /** @return array<string, string> slug => label */
    public static function distributorOptions(): array
    {
        return array_map(fn ($d) => (string) $d['label'], (array) config('n2n_music_distributors.distributors'));
    }
}
