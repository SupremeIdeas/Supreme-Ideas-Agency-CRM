<?php

namespace App\Support\MasterMarketing;

use App\Models\MasterMarketingInstaller;
use Illuminate\Support\Facades\Schema;

/** The desktop and browser platforms we ship (MASTER ONLY). Android and iOS stay in App Builder / App Export so there is one source of truth for them. */
class InstallerCatalog
{
    /** @return array<string, array{label:string, kind:string, ext:list<string>, icon:string, hint:string}> */
    public static function platforms(): array
    {
        return [
            'windows' => ['family' => 'windows', 'label' => 'Windows', 'variant' => '64-bit', 'kind' => 'Installer (.exe)', 'ext' => ['exe', 'msi', 'zip'], 'icon' => 'download', 'hint' => 'Windows 10 or newer, 64-bit'],
            'macos' => ['family' => 'macos', 'label' => 'macOS (Apple silicon)', 'variant' => 'Apple silicon', 'kind' => 'Disk image (.dmg)', 'ext' => ['dmg', 'pkg', 'zip'], 'icon' => 'download', 'hint' => 'macOS 11 or newer, M1 and later'],
            'macos_intel' => ['family' => 'macos', 'label' => 'macOS (Intel)', 'variant' => 'Intel', 'kind' => 'Disk image (.dmg)', 'ext' => ['dmg', 'pkg', 'zip'], 'icon' => 'download', 'hint' => 'macOS 11 or newer, Intel Macs'],
            'linux' => ['family' => 'linux', 'label' => 'Linux (AppImage)', 'variant' => 'AppImage', 'kind' => 'AppImage', 'ext' => ['appimage', 'gz', 'zip'], 'icon' => 'download', 'hint' => 'Most distributions, no install needed'],
            'linux_deb' => ['family' => 'linux', 'label' => 'Linux (.deb / .rpm)', 'variant' => '.deb package', 'kind' => 'Package', 'ext' => ['deb', 'rpm'], 'icon' => 'download', 'hint' => 'Ubuntu, Debian, Fedora'],
            'chrome' => ['family' => 'chrome', 'label' => 'Chrome extension', 'variant' => 'Extension', 'kind' => 'Chrome, Edge, Brave', 'ext' => ['zip', 'crx'], 'icon' => 'globe', 'hint' => 'Chrome 114 or newer'],
        ];
    }

    public static function acceptedExtensions(string $platform): array
    {
        return self::platforms()[$platform]['ext'] ?? [];
    }

    /**
     * Reads a file name the way a person would: which slot it belongs in and which version it is. Works on parts too (Naara-1.0.1-win-x64.zip.part00).
     *
     * @return array{platform:?string, version:?string, base:string}
     */
    public static function guess(string $name): array
    {
        $base = (string) preg_replace('/\.part\d{2,3}$/i', '', basename($name));
        $l = strtolower($base);
        $ext = strtolower(pathinfo($l, PATHINFO_EXTENSION));
        $version = preg_match('/(\d+\.\d+(?:\.\d+)?)/', $base, $m) ? $m[1] : null;
        $mac = in_array($ext, ['dmg', 'pkg'], true) || preg_match('/(^|[-_.])(mac|macos|osx|darwin)([-_.]|$)/', $l);
        $platform = match (true) {
            in_array($ext, ['apk', 'aab'], true) => 'android',
            $ext === 'crx' || str_contains($l, 'chrome') || str_contains($l, 'extension') => 'chrome',
            (bool) $mac => preg_match('/arm64|aarch64|silicon|universal/', $l) ? 'macos' : (preg_match('/x64|x86_64|intel|amd64/', $l) ? 'macos_intel' : 'macos'),
            in_array($ext, ['exe', 'msi'], true) || preg_match('/(^|[-_.])win(dows)?([-_.]|$)/', $l) => 'windows',
            in_array($ext, ['deb', 'rpm'], true) => 'linux_deb',
            $ext === 'appimage' || str_contains($l, 'linux') => 'linux',
            default => null,
        };

        return ['platform' => $platform, 'version' => $version, 'base' => $base];
    }

    /** The platform families a visitor chooses between, each with its variants (Apple silicon / Intel, AppImage / .deb). @return array<string, list<string>> */
    public static function families(): array
    {
        $out = [];
        foreach (self::platforms() as $key => $p) {
            $out[$p['family']][] = $key;
        }

        return $out;
    }

    /** Best guess for which platform the visitor is on, so the page can lead with it. */
    public static function detect(?string $userAgent): ?string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'mac os') && ! str_contains($ua, 'iphone') && ! str_contains($ua, 'ipad') => 'macos',
            str_contains($ua, 'linux') && ! str_contains($ua, 'android') => 'linux',
            default => null,
        };
    }

    /** @return array<string, MasterMarketingInstaller|null> keyed by platform, in display order */
    public static function rows(): array
    {
        $by = Schema::hasTable('master_marketing_installers') ? MasterMarketingInstaller::query()->get()->keyBy('platform') : collect();
        $out = [];
        foreach (array_keys(self::platforms()) as $p) {
            $out[$p] = $by->get($p);
        }

        return $out;
    }

    /**
     * Are the files on the download page all from the same release? Compares the versions of every slot that has a file, so one stale slot (a Windows 1.0.1 beside
     * Mac 1.0.2) is visible at a glance instead of being found by a customer.
     *
     * @return array{versions: array<string,string>, latest: ?string, stale: list<string>, aligned: bool}
     */
    public static function consistency(): array
    {
        $versions = [];
        foreach (self::rows() as $key => $row) {
            if ($row && ($row->file_path || $row->external_url) && $row->version) {
                $versions[$key] = $row->version;
            }
        }
        $latest = null;
        foreach ($versions as $v) {
            if ($latest === null || version_compare($v, $latest, '>')) {
                $latest = $v;
            }
        }
        $stale = array_keys(array_filter($versions, fn ($v) => $v !== $latest));

        return ['versions' => $versions, 'latest' => $latest, 'stale' => $stale, 'aligned' => $stale === []];
    }

    /** Rows people can actually download right now. */
    public static function live(): array
    {
        return array_filter(self::rows(), fn ($r) => $r !== null && $r->isLive());
    }
}
