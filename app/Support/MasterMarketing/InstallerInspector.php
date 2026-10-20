<?php

namespace App\Support\MasterMarketing;

use ZipArchive;

/**
 * MASTER ONLY. Looks INSIDE an uploaded installer before it can go on the download page, so the wrong file in the wrong slot is caught here and not by a customer:
 * a Windows file is really Windows, the Mac Intel slot does not hold an Apple silicon build, the Chrome zip has a real manifest, an Android file really is an APK or AAB.
 * Pure reads; never executes anything.
 *
 * @phpstan-type Result array{problems:list<string>, warnings:list<string>, facts:array<string,string>}
 */
class InstallerInspector
{
    private const MACHO_ARCH = [0x0100000C => 'arm64', 0x01000007 => 'x64'];

    private const ELF_ARCH = [0x3E => 'x64', 0xB7 => 'arm64'];

    /** @return Result */
    public static function inspect(string $path, string $platform, string $name, ?string $declaredVersion = null): array
    {
        $r = ['problems' => [], 'warnings' => [], 'facts' => []];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $head = (string) @file_get_contents($path, false, null, 0, 4096);
        $isZip = str_starts_with($head, "PK\x03\x04");

        match ($platform) {
            'windows' => self::windows($r, $path, $ext, $head, $isZip),
            'macos', 'macos_intel' => self::mac($r, $path, $platform, $ext, $head, $isZip),
            'linux' => self::linux($r, $ext, $head, $isZip),
            'linux_deb' => self::package($r, $ext, $head),
            'chrome' => self::chrome($r, $path, $ext, $isZip),
            'android' => self::android($r, $path, $ext, $isZip),
            default => null,
        };

        $fileVersion = InstallerCatalog::guess($name)['version'];
        if ($declaredVersion && $fileVersion && $declaredVersion !== $fileVersion) {
            $r['warnings'][] = 'The version you typed ('.$declaredVersion.') is not the one in the file name ('.$fileVersion.').';
        }
        if (isset($r['facts']['version']) && $fileVersion && $r['facts']['version'] !== $fileVersion) {
            $r['warnings'][] = 'The file name says version '.$fileVersion.' but the extension inside says '.$r['facts']['version'].'. Check this is the right zip.';
        }
        if (stripos($name, 'TEST-SIGNED') !== false || stripos($name, 'debug') !== false) {
            $r['warnings'][] = 'This is a test-signed build. It installs for testing but cannot go to Google Play. Replace it with the release-signed build before real users get it.';
            $r['facts']['signing'] = 'test';
        }

        return $r;
    }

    /** @param Result $r */
    private static function windows(array &$r, string $path, string $ext, string $head, bool $isZip): void
    {
        if ($ext === 'exe' && ! str_starts_with($head, 'MZ')) {
            $r['problems'][] = 'This .exe is not a Windows program (it does not start like one).';
        } elseif ($ext === 'msi' && ! str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            $r['problems'][] = 'This .msi is not a Windows installer package.';
        } elseif ($ext === 'zip') {
            $names = self::zipNames($path);
            if ($names === null) {
                $r['problems'][] = 'This zip cannot be opened.';
            } elseif (! self::any($names, '/\.exe$/i')) {
                $r['problems'][] = 'This zip has no .exe inside, so it is not a Windows build.';
            } elseif (self::any($names, '/\.app\//')) {
                $r['problems'][] = 'This zip looks like a Mac or Linux build, not Windows.';
            } else {
                $r['facts']['contains'] = 'Windows program (.exe)';
            }
        }
    }

    /** @param Result $r */
    private static function mac(array &$r, string $path, string $platform, string $ext, string $head, bool $isZip): void
    {
        $want = $platform === 'macos' ? 'arm64' : 'x64';
        if ($ext === 'zip') {
            $names = self::zipNames($path);
            if ($names === null) {
                $r['problems'][] = 'This zip cannot be opened.';

                return;
            }
            if (! self::any($names, '/\.app\//')) {
                $r['problems'][] = 'This zip has no Mac app (.app) inside, so it is not a Mac build.';

                return;
            }
            $r['facts']['contains'] = 'Mac app (.app)';
            $arch = self::zipMachoArch($path, $names);
            if ($arch) {
                $r['facts']['arch'] = $arch;
                if ($arch !== $want) {
                    $r['problems'][] = 'This is a'.($arch === 'x64' ? 'n Intel' : 'n Apple silicon').' build, but you chose the '.($want === 'x64' ? 'Intel' : 'Apple silicon').' slot. Put it in the other Mac slot.';
                }
            }
        } elseif ($ext === 'pkg' && ! str_starts_with($head, 'xar!')) {
            $r['problems'][] = 'This .pkg is not a Mac installer package.';
        } elseif ($ext === 'dmg' && ($head === '' || str_starts_with($head, 'MZ') || str_starts_with($head, "\x7FELF"))) {
            $r['problems'][] = 'This .dmg is not a Mac disk image.';
        }
    }

    /** @param Result $r */
    private static function linux(array &$r, string $ext, string $head, bool $isZip): void
    {
        if ($ext === 'appimage') {
            if (! str_starts_with($head, "\x7FELF")) {
                $r['problems'][] = 'This AppImage is not a Linux program (it does not start like one).';
            } else {
                $r['facts']['arch'] = self::ELF_ARCH[ord($head[18] ?? "\0")] ?? 'other';
                $r['facts']['contains'] = 'Linux program (AppImage)';
            }
        }
    }

    /** @param Result $r */
    private static function package(array &$r, string $ext, string $head): void
    {
        if ($ext === 'deb' && ! str_starts_with($head, "!<arch>\n")) {
            $r['problems'][] = 'This .deb is not a Debian package.';
        } elseif ($ext === 'rpm' && ! str_starts_with($head, "\xED\xAB\xEE\xDB")) {
            $r['problems'][] = 'This .rpm is not an RPM package.';
        } else {
            $r['facts']['contains'] = strtoupper($ext).' package';
        }
    }

    /** @param Result $r */
    private static function chrome(array &$r, string $path, string $ext, bool $isZip): void
    {
        if ($ext !== 'zip') {
            return;
        }
        $zip = new ZipArchive;
        if (! $isZip || $zip->open($path) !== true) {
            $r['problems'][] = 'This zip cannot be opened.';

            return;
        }
        $raw = $zip->getFromName('manifest.json');
        $zip->close();
        $m = $raw ? json_decode($raw, true) : null;
        if (! is_array($m) || ! isset($m['manifest_version'])) {
            $r['problems'][] = 'No manifest.json at the top of this zip, so Chrome cannot load it. Zip the extension folder contents, not the folder itself.';

            return;
        }
        $r['facts']['contains'] = 'Chrome extension (manifest v'.$m['manifest_version'].')';
        if (isset($m['version'])) {
            $r['facts']['version'] = (string) $m['version'];
        }
        if ((int) $m['manifest_version'] < 3) {
            $r['problems'][] = 'This extension uses manifest version '.$m['manifest_version'].'. Chrome only accepts version 3.';
        }
    }

    /** @param Result $r */
    private static function android(array &$r, string $path, string $ext, bool $isZip): void
    {
        $names = $isZip ? self::zipNames($path) : null;
        if ($names === null) {
            $r['problems'][] = 'This is not an Android package (an APK or AAB is a zip file).';

            return;
        }
        if ($ext === 'apk' && ! (in_array('AndroidManifest.xml', $names, true) && self::any($names, '/^classes\d*\.dex$/'))) {
            $r['problems'][] = 'This .apk has no AndroidManifest.xml and code inside, so Android would refuse it.';
        } elseif ($ext === 'aab' && ! in_array('BundleConfig.pb', $names, true)) {
            $r['problems'][] = 'This .aab is missing BundleConfig.pb, so Google Play would refuse it.';
        } else {
            $r['facts']['contains'] = $ext === 'apk' ? 'Android app (APK)' : 'Play bundle (AAB)';
        }
        if ($ext === 'aab') {
            $r['problems'][] = 'An .aab is for the Google Play upload only. People cannot install it from a download page: upload the .apk here and keep the .aab for Play Console.';
        }
    }

    /** @return list<string>|null */
    private static function zipNames(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $out[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();

        return $out;
    }

    /** @param list<string> $names */
    private static function any(array $names, string $regex): bool
    {
        foreach ($names as $n) {
            if (preg_match($regex, $n)) {
                return true;
            }
        }

        return false;
    }

    /** CPU of the main program inside a Mac .app zip (Mach-O header), or null when it cannot be read. @param list<string> $names */
    private static function zipMachoArch(string $path, array $names): ?string
    {
        $main = null;
        foreach ($names as $n) {
            if (preg_match('#\.app/Contents/MacOS/[^/]+$#', $n) && ! str_contains($n, 'Helper')) {
                $main = $n;
                break;
            }
        }
        if (! $main) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $s = $zip->getStream($main);
        $bytes = $s ? (string) fread($s, 8) : '';
        $zip->close();
        if (strlen($bytes) < 8) {
            return null;
        }
        $magic = unpack('V', substr($bytes, 0, 4))[1];
        if ($magic === 0xBEBAFECA || $magic === 0xCAFEBABE) {
            return 'universal';
        }

        return $magic === 0xFEEDFACF ? (self::MACHO_ARCH[unpack('V', substr($bytes, 4, 4))[1]] ?? null) : null;
    }
}
