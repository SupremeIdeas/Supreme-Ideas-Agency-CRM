<?php

namespace App\Services\Media;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * ffmpeg for the whole platform, on any host (shared cPanel, VPS, container).
 *
 * Finding it: an admin-set path, then a managed copy this class installs under storage/app/bin, then FFMPEG_BINARY, then PATH and the usual folders. Every candidate is
 * actually executed once (-version) before it is trusted, so a file that exists but cannot run (noexec mount, wrong CPU, blocked by the host) is reported, not assumed.
 *
 * Installing it when the host has none: a managed static build is downloaded (johnvansickle.com static builds, else the npm @ffmpeg-installer packages), checked against the
 * publisher's checksum, unpacked and proven with a real H.264 + AAC test encode. It runs in small steps (one chunk of the download per call) so it works inside a shared
 * host's 30 second PHP limit: the admin screen polls installStep(), the CLI (php artisan ffmpeg:install) loops it. An admin can also point at their own download link, type
 * the path of an ffmpeg already on the server, or upload a static binary when the host blocks outgoing downloads.
 *
 * Running programs needs proc_open or exec. If the host disables both, nothing server-side can run ffmpeg: execStatus() says so in plain words and callers fall back
 * (see InboxMedia / ChannelSender) instead of failing.
 */
class FfmpegManager
{
    public const SETTING_PATH = 'media.ffmpeg_path';

    public const SETTING_MIRROR = 'media.ffmpeg_mirror_url';

    public const SETTING_MANAGED = 'media.ffmpeg_managed';

    private const CACHE_BIN = 'ffmpeg.binary.v1';

    private const CACHE_INFO = 'ffmpeg.info.v1';

    private const CACHE_INSTALL = 'ffmpeg.install.v1';

    private const CHUNK = 6 * 1048576;

    // ── can this host run programs at all? ──────────────────────────────────

    /** @return array{ok: bool, proc_open: bool, exec: bool, reason: ?string} */
    public function execStatus(): array
    {
        $dis = array_map('trim', explode(',', (string) ini_get('disable_functions').','.(string) ini_get('suhosin.executor.func.blacklist')));
        $has = fn (string $f) => function_exists($f) && ! in_array($f, $dis, true);
        $proc = $has('proc_open');
        $exec = $has('exec');

        return ['ok' => $proc || $exec, 'proc_open' => $proc, 'exec' => $exec,
            'reason' => ($proc || $exec) ? null : 'This host blocks running programs (proc_open and exec are disabled in php.ini). Ask the host to enable one of them, or move to a VPS. Until then, photos, MP4 video, documents, locations and browser-recorded voice notes still work; GIF-to-video conversion does not.'];
    }

    /**
     * Run a program. Prefers proc_open (real timeouts); falls back to exec().
     *
     * @param  list<string>  $cmd
     * @return array{ok: bool, code: int, out: string, err: string}
     */
    public function run(array $cmd, int $timeout = 60): array
    {
        $st = $this->execStatus();
        if (! $st['ok']) {
            return ['ok' => false, 'code' => -1, 'out' => '', 'err' => 'exec disabled'];
        }
        try {
            if ($st['proc_open']) {
                $p = new Process($cmd);
                $p->setTimeout($timeout);
                $p->run();

                return ['ok' => $p->isSuccessful(), 'code' => (int) $p->getExitCode(), 'out' => $p->getOutput(), 'err' => $p->getErrorOutput()];
            }
            $line = implode(' ', array_map('escapeshellarg', $cmd)).' 2>&1';
            $out = [];
            $code = 0;
            @exec($line, $out, $code);

            return ['ok' => $code === 0, 'code' => $code, 'out' => implode("\n", $out), 'err' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'code' => -1, 'out' => '', 'err' => $e->getMessage()];
        }
    }

    // ── finding it ──────────────────────────────────────────────────────────

    public static function managedDir(): string
    {
        return storage_path('app/bin');
    }

    public static function managedPath(): string
    {
        return self::managedDir().'/ffmpeg';
    }

    /** The ffmpeg that will be used, or null. Every candidate is run once before it is trusted; the answer is cached for 10 minutes (forget() after a change). */
    public function binary(): ?string
    {
        $cached = Cache::get(self::CACHE_BIN);
        if (is_array($cached)) {
            return $cached['path'];
        }
        $found = null;
        foreach ($this->candidates() as [$path, $source]) {
            if ($this->works($path)) {
                $found = ['path' => $path, 'source' => $source];
                break;
            }
        }
        Cache::put(self::CACHE_BIN, $found ?? ['path' => null, 'source' => null], 600);

        return $found['path'] ?? null;
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /** @return list<array{0: string, 1: string}> path, source */
    private function candidates(): array
    {
        $out = [];
        $custom = trim((string) Setting::getValue(self::SETTING_PATH, ''));
        if ($custom !== '') {
            $out[] = [$custom, 'custom'];
        }
        $out[] = [self::managedPath(), 'managed'];
        $env = trim((string) env('FFMPEG_BINARY', ''));
        if ($env !== '') {
            $out[] = [$env, 'env'];
        }
        $dirs = array_filter(array_merge(explode(PATH_SEPARATOR, (string) getenv('PATH')), [
            '/usr/local/bin', '/usr/bin', '/bin', '/opt/bin', '/opt/homebrew/bin', '/usr/local/ffmpeg/bin', '/usr/local/cpanel/3rdparty/bin',
            (string) getenv('HOME').'/bin', (string) getenv('HOME').'/.local/bin', (string) getenv('HOME').'/ffmpeg',
        ]));
        foreach (array_unique($dirs) as $d) {
            $out[] = [rtrim($d, '/').'/ffmpeg', 'system'];
        }

        return $out;
    }

    private function works(string $path): bool
    {
        try {
            if (! @is_file($path) || ! @is_executable($path)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return $this->run([$path, '-hide_banner', '-version'], 15)['ok'];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_BIN);
        Cache::forget(self::CACHE_INFO);
    }

    /**
     * What an admin needs to see.
     *
     * @return array{available: bool, path: ?string, source: ?string, version: ?string, h264: bool, aac: bool, opus: bool, mp3: bool, exec: array, arch: string, managed: array<string,mixed>|null, can_install: bool, install_blocker: ?string}
     */
    public function info(bool $fresh = false): array
    {
        if ($fresh) {
            $this->forget();
        }
        $bin = $this->binary();
        $cached = Cache::get(self::CACHE_INFO);
        $exec = $this->execStatus();
        $arch = php_uname('m');
        $blocker = $this->installBlocker();
        if ($bin && is_array($cached) && ($cached['path'] ?? null) === $bin) {
            return $cached + ['exec' => $exec, 'managed' => $this->managedMeta(), 'can_install' => $blocker === null, 'install_blocker' => $blocker];
        }
        $info = ['available' => false, 'path' => $bin, 'source' => Cache::get(self::CACHE_BIN)['source'] ?? null, 'version' => null, 'h264' => false, 'aac' => false, 'opus' => false, 'mp3' => false, 'arch' => $arch];
        if ($bin) {
            $v = $this->run([$bin, '-hide_banner', '-version'], 15);
            $info['available'] = $v['ok'];
            $info['version'] = $v['ok'] && preg_match('/ffmpeg version (\S+)/', $v['out'], $m) ? $m[1] : null;
            $enc = $this->run([$bin, '-hide_banner', '-encoders'], 20)['out'];
            $info['h264'] = str_contains($enc, 'libx264');
            $info['aac'] = (bool) preg_match('/\baac\b/', $enc);
            $info['opus'] = str_contains($enc, 'libopus');
            $info['mp3'] = str_contains($enc, 'libmp3lame');
            Cache::put(self::CACHE_INFO, $info, 600);
        }

        return $info + ['exec' => $exec, 'managed' => $this->managedMeta(), 'can_install' => $blocker === null, 'install_blocker' => $blocker];
    }

    /** @return array<string,mixed>|null */
    private function managedMeta(): ?array
    {
        $m = Setting::getValue(self::SETTING_MANAGED, null);

        return is_array($m) && is_file(self::managedPath()) ? $m : null;
    }

    // ── installing a managed copy ───────────────────────────────────────────

    /** Why a managed copy cannot be installed here, or null. */
    public function installBlocker(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return 'Automatic install is for Linux servers. On this system install ffmpeg yourself (for example brew install ffmpeg) or type its path below.';
        }
        if ($this->archKey() === null) {
            return 'This server\'s processor ('.php_uname('m').') has no ready-made static build. Install ffmpeg with the system package manager, or type its path below.';
        }
        if (! $this->execStatus()['ok']) {
            return $this->execStatus()['reason'];
        }
        if (! is_dir(self::managedDir()) && ! @mkdir(self::managedDir(), 0755, true) && ! is_dir(self::managedDir())) {
            return 'The folder storage/app/bin cannot be created. Make storage/ writable for the web user.';
        }
        if (! is_writable(self::managedDir())) {
            return 'The folder storage/app/bin is not writable. Make storage/ writable for the web user.';
        }

        return null;
    }

    /** Architecture key used by the download sources, or null. */
    private function archKey(): ?string
    {
        return match (strtolower(php_uname('m'))) {
            'x86_64', 'amd64' => 'amd64',
            'aarch64', 'arm64' => 'arm64',
            'i386', 'i486', 'i586', 'i686' => 'i686',
            'armv7l', 'armv7', 'armhf' => 'armhf',
            default => null,
        };
    }

    /** @return list<array{id: string, label: string, url: string, format: string, inner: string, md5: ?string, sha512: ?string}> */
    public function sources(): array
    {
        $a = $this->archKey();
        $out = [];
        $mirror = trim((string) Setting::getValue(self::SETTING_MIRROR, ''));
        if ($mirror !== '') {
            $fmt = str_ends_with($mirror, '.tar.xz') ? 'tar.xz' : ((str_ends_with($mirror, '.tgz') || str_ends_with($mirror, '.tar.gz')) ? 'tgz' : (str_ends_with($mirror, '.gz') ? 'gz' : 'raw'));
            $out[] = ['id' => 'mirror', 'label' => 'Your download link', 'url' => $mirror, 'format' => $fmt, 'inner' => '', 'md5' => null, 'sha512' => null];
        }
        if ($a === null) {
            return $out;
        }
        $st = $this->tarXzUsable();
        if ($st) {
            $url = "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-{$a}-static.tar.xz";
            $out[] = ['id' => 'johnvansickle', 'label' => 'John Van Sickle static build', 'url' => $url, 'format' => 'tar.xz', 'inner' => 'ffmpeg', 'md5' => $url.'.md5', 'sha512' => null];
        }
        $npm = ['amd64' => 'linux-x64', 'arm64' => 'linux-arm64', 'i686' => 'linux-ia32', 'armhf' => 'linux-arm'][$a];
        $out[] = ['id' => 'npm', 'label' => 'ffmpeg-installer package', 'url' => 'npm:@ffmpeg-installer/'.$npm, 'format' => 'tgz', 'inner' => 'package/ffmpeg', 'md5' => null, 'sha512' => null];

        return $out;
    }

    private function tarXzUsable(): bool
    {
        if (! $this->execStatus()['ok']) {
            return false;
        }
        $r = $this->run(['sh', '-c', 'command -v tar >/dev/null 2>&1 && command -v xz >/dev/null 2>&1 && echo yes'], 10);

        return trim($r['out']) === 'yes';
    }

    /** @return array<string,mixed> */
    public function installState(): array
    {
        return Cache::get(self::CACHE_INSTALL) ?: ['phase' => 'idle'];
    }

    private function putState(array $s): array
    {
        $s['updated'] = time();
        Cache::put(self::CACHE_INSTALL, $s, 3600);

        return $s;
    }

    private function note(array &$s, string $line): void
    {
        $s['log'] = array_slice(array_merge($s['log'] ?? [], [now()->format('H:i:s').'  '.$line]), -40);
    }

    /** Begin an install. @return array<string,mixed> the first state */
    public function installStart(): array
    {
        $blocker = $this->installBlocker();
        if ($blocker !== null) {
            return $this->putState(['phase' => 'failed', 'error' => $blocker, 'log' => []]);
        }
        $this->cleanTmp();
        $s = ['phase' => 'plan', 'sources' => array_column($this->sources(), 'id'), 'attempt' => 0, 'log' => [], 'error' => null];
        $this->note($s, 'Starting. Sources to try: '.implode(', ', $s['sources']).'.');

        return $this->putState($s);
    }

    /** One slice of work (about 5-20 seconds). Call until phase is done or failed. @return array<string,mixed> */
    public function installStep(): array
    {
        $s = $this->installState();
        if (in_array($s['phase'], ['idle', 'done', 'failed'], true)) {
            return $s;
        }
        $prevLimit = (int) ini_get('max_execution_time');
        try {
            @set_time_limit(120);
            $s = match ($s['phase']) {
                'plan' => $this->stepPlan($s),
                'download' => $this->stepDownload($s),
                'extract' => $this->stepExtract($s),
                'verify' => $this->stepVerify($s),
                default => $s,
            };
        } catch (\Throwable $e) {
            $s = $this->failSource($s, $e->getMessage());
        } finally {
            @set_time_limit($prevLimit);   // never leave a changed limit behind (it would outlive this step in a CLI process or a test run)
        }

        return $this->putState($s);
    }

    /** Run the whole install (CLI). @param callable(array<string,mixed>): void|null $progress */
    public function installRun(?callable $progress = null): array
    {
        $s = $this->installStart();
        $guard = 0;
        while (! in_array($s['phase'], ['done', 'failed'], true) && $guard++ < 4000) {
            $s = $this->installStep();
            if ($progress) {
                $progress($s);
            }
        }

        return $s;
    }

    private function tmp(string $name = ''): string
    {
        return self::managedDir().'/.install'.($name !== '' ? '/'.$name : '');
    }

    private function cleanTmp(): void
    {
        if (is_dir($this->tmp())) {
            File::deleteDirectory($this->tmp());
        }
        @mkdir($this->tmp(), 0755, true);
    }

    /** Move on to the next source, or fail with every reason. */
    private function failSource(array $s, string $why): array
    {
        $this->note($s, 'Failed: '.$why);
        $s['reasons'] = array_merge($s['reasons'] ?? [], [($s['sources'][$s['attempt']] ?? '?').': '.$why]);
        $s['attempt'] = ($s['attempt'] ?? 0) + 1;
        $this->cleanTmp();
        if ($s['attempt'] >= count($s['sources'])) {
            $s['phase'] = 'failed';
            $s['error'] = 'Every download source failed ('.implode('; ', $s['reasons']).'). If this server blocks outgoing downloads, upload a static ffmpeg binary below, or ask your host to allow https://johnvansickle.com and https://registry.npmjs.org.';

            return $s;
        }
        $s['phase'] = 'plan';
        unset($s['dl']);
        $this->note($s, 'Trying the next source.');

        return $s;
    }

    private function current(array $s): ?array
    {
        $id = $s['sources'][$s['attempt']] ?? null;

        return collect($this->sources())->firstWhere('id', $id);
    }

    private function stepPlan(array $s): array
    {
        $src = $this->current($s);
        if (! $src) {
            return $this->failSource($s, 'no source available');
        }
        $url = $src['url'];
        $sha512 = null;
        if (str_starts_with($url, 'npm:')) {
            $pkg = substr($url, 4);
            $meta = Http::timeout(20)->acceptJson()->get('https://registry.npmjs.org/'.$pkg)->throw()->json();
            $ver = $meta['dist-tags']['latest'] ?? null;
            $dist = $meta['versions'][$ver]['dist'] ?? null;
            if (! $dist) {
                throw new \RuntimeException('the package registry did not describe the download');
            }
            $url = (string) $dist['tarball'];
            $sha512 = $dist['integrity'] ?? null;     // "sha512-<base64>"
            $this->note($s, "{$pkg} {$ver}");
        }
        $md5 = null;
        if (! empty($src['md5'])) {
            try {
                $m = Http::timeout(15)->get($src['md5']);
                if ($m->successful() && preg_match('/^([a-f0-9]{32})/i', trim($m->body()), $mm)) {
                    $md5 = strtolower($mm[1]);
                }
            } catch (\Throwable) {
                // a missing checksum file is not fatal for this source
            }
        }
        // total size and whether ranges are supported: ask for the first byte
        $r = Http::timeout(25)->withHeaders(['Range' => 'bytes=0-0'])->withOptions(['allow_redirects' => true])->get($url);
        if (! in_array($r->status(), [200, 206], true)) {
            throw new \RuntimeException('download link answered '.$r->status());
        }
        $ranged = $r->status() === 206 && preg_match('#/(\d+)$#', (string) $r->header('Content-Range'), $m2);
        $total = $ranged ? (int) $m2[1] : (int) $r->header('Content-Length');
        $s['dl'] = ['url' => $url, 'file' => $this->tmp('download.bin'), 'done' => 0, 'total' => $total, 'ranged' => (bool) $ranged, 'md5' => $md5, 'sha512' => $sha512, 'format' => $src['format'], 'inner' => $src['inner']];
        @unlink($s['dl']['file']);
        $s['phase'] = 'download';
        $this->note($s, 'Downloading from '.parse_url($url, PHP_URL_HOST).($total ? ' ('.round($total / 1048576, 1).' MB)' : '').($ranged ? '' : ' in one go').'.');

        return $s;
    }

    private function stepDownload(array $s): array
    {
        $d = &$s['dl'];
        if ($d['ranged']) {
            $to = $d['total'] > 0 ? min($d['total'] - 1, $d['done'] + self::CHUNK - 1) : $d['done'] + self::CHUNK - 1;
            $r = Http::timeout(60)->withHeaders(['Range' => "bytes={$d['done']}-{$to}"])->withOptions(['allow_redirects' => true])->get($d['url']);
            if ($r->status() !== 206 && $r->status() !== 200) {
                throw new \RuntimeException('download interrupted ('.$r->status().')');
            }
            $body = $r->body();
            if ($r->status() === 200 && $d['done'] > 0) {
                throw new \RuntimeException('the server stopped supporting resumable downloads');
            }
            file_put_contents($d['file'], $body, FILE_APPEND);
            $d['done'] += strlen($body);
            if ($d['total'] > 0 ? $d['done'] >= $d['total'] : strlen($body) < self::CHUNK) {
                $s['phase'] = 'extract';
                $this->note($s, 'Download complete ('.round($d['done'] / 1048576, 1).' MB).');
            }

            return $s;
        }
        // no ranges: one streamed download straight to disk
        @set_time_limit(0);
        Http::timeout(600)->withOptions(['sink' => $d['file'], 'allow_redirects' => true])->get($d['url'])->throw();
        $d['done'] = (int) @filesize($d['file']);
        $s['phase'] = 'extract';
        $this->note($s, 'Download complete ('.round($d['done'] / 1048576, 1).' MB).');

        return $s;
    }

    private function stepExtract(array $s): array
    {
        $d = $s['dl'];
        $file = $d['file'];
        $size = (int) @filesize($file);
        if ($size < 5 * 1048576 && $d['format'] !== 'raw') {
            throw new \RuntimeException('the download is too small to be ffmpeg ('.$size.' bytes)');
        }
        if ($d['md5'] && strtolower(md5_file($file)) !== $d['md5']) {
            throw new \RuntimeException('the download failed its checksum (corrupted)');
        }
        if ($d['sha512'] && str_starts_with($d['sha512'], 'sha512-') && ! hash_equals(substr($d['sha512'], 7), base64_encode(hash_file('sha512', $file, true)))) {
            throw new \RuntimeException('the download failed its checksum (corrupted)');
        }
        $this->note($s, 'Checksum ok. Unpacking.');
        $out = $this->tmp('ffmpeg');
        @unlink($out);
        match ($d['format']) {
            'tar.xz' => $this->extractTarXz($file, $out),
            'tgz' => $this->extractTgz($file, $d['inner'], $out),
            'gz' => $this->gunzip($file, $out),
            default => copy($file, $out),
        };
        if (! is_file($out) || filesize($out) < 5 * 1048576) {
            throw new \RuntimeException('unpacking did not produce an ffmpeg program');
        }
        $head = (string) file_get_contents($out, false, null, 0, 4);
        if ($head !== "\x7FELF") {
            throw new \RuntimeException('the unpacked file is not a Linux program');
        }
        @chmod($out, 0755);
        $s['phase'] = 'verify';

        return $s;
    }

    private function extractTarXz(string $file, string $out): void
    {
        $dir = $this->tmp('x');
        @mkdir($dir, 0755, true);
        $r = $this->run(['tar', '-xJf', $file, '-C', $dir, '--wildcards', '*/ffmpeg'], 120);
        if (! $r['ok']) {
            throw new \RuntimeException('tar could not unpack it: '.trim(substr($r['err'].$r['out'], 0, 160)));
        }
        $hit = glob($dir.'/*/ffmpeg');
        if (! $hit) {
            throw new \RuntimeException('ffmpeg was not inside the archive');
        }
        rename($hit[0], $out);
    }

    private function extractTgz(string $file, string $inner, string $out): void
    {
        $tgz = $this->tmp('pkg.tgz');
        @rename($file, $tgz);
        $dir = $this->tmp('x');
        @mkdir($dir, 0755, true);
        try {
            $phar = new \PharData($tgz);
            $phar->extractTo($dir, $inner, true);
        } catch (\Throwable $e) {
            $r = $this->run(['tar', '-xzf', $tgz, '-C', $dir, $inner], 120);
            if (! $r['ok']) {
                throw new \RuntimeException('the package could not be unpacked: '.$e->getMessage());
            }
        }
        if (! is_file($dir.'/'.$inner)) {
            throw new \RuntimeException('ffmpeg was not inside the package');
        }
        rename($dir.'/'.$inner, $out);
    }

    private function gunzip(string $file, string $out): void
    {
        $in = gzopen($file, 'rb');
        $o = fopen($out, 'wb');
        while (! gzeof($in)) {
            fwrite($o, gzread($in, 1048576));
        }
        gzclose($in);
        fclose($o);
    }

    private function stepVerify(array $s): array
    {
        $bin = $this->tmp('ffmpeg');
        $v = $this->run([$bin, '-hide_banner', '-version'], 20);
        if (! $v['ok']) {
            throw new \RuntimeException('it will not run on this server'.($v['err'] !== '' ? ' ('.trim(substr($v['err'], 0, 120)).')' : '').'. The folder may be mounted without permission to run programs.');
        }
        $video = $this->run([$bin, '-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=duration=0.3:size=64x64:rate=10', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-f', 'null', '-'], 40);
        $audio = $this->run([$bin, '-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=duration=0.3', '-c:a', 'aac', '-f', 'null', '-'], 40);
        if (! $video['ok'] || ! $audio['ok']) {
            throw new \RuntimeException('it runs but cannot encode H.264 video or AAC audio, which the channels need');
        }
        $final = self::managedPath();
        @mkdir(self::managedDir(), 0755, true);
        @unlink($final);
        if (! @rename($bin, $final)) {
            copy($bin, $final);
        }
        @chmod($final, 0755);
        $ver = preg_match('/ffmpeg version (\S+)/', $v['out'], $m) ? $m[1] : null;
        Setting::setValue(self::SETTING_MANAGED, ['version' => $ver, 'source' => $this->current($s)['id'] ?? null, 'installed_at' => now()->toIso8601String(), 'bytes' => (int) filesize($final)], 'media');
        $this->cleanTmp();
        $this->forget();
        $s['phase'] = 'done';
        $s['version'] = $ver;
        $this->note($s, "Installed ffmpeg {$ver}. H.264 video and AAC audio verified.");

        return $s;
    }

    /** Remove the managed copy. */
    public function uninstall(): void
    {
        @unlink(self::managedPath());
        Setting::where('key', self::SETTING_MANAGED)->first()?->delete();
        $this->cleanTmp();
        Cache::forget(self::CACHE_INSTALL);
        $this->forget();
    }

    /** An admin-uploaded static binary (for hosts that block outgoing downloads). @throws \InvalidArgumentException */
    public function installUploaded(UploadedFile $file): array
    {
        $blocker = $this->installBlocker();
        if ($blocker !== null) {
            throw new \InvalidArgumentException($blocker);
        }
        if ($file->getSize() < 5 * 1048576 || $file->getSize() > 250 * 1048576) {
            throw new \InvalidArgumentException('That file does not look like an ffmpeg program (expected 5 to 250 MB).');
        }
        if ((string) file_get_contents($file->getRealPath(), false, null, 0, 4) !== "\x7FELF") {
            throw new \InvalidArgumentException('That is not a Linux program. Upload the static "ffmpeg" file (not a .tar.xz or .zip: unpack it first).');
        }
        $this->cleanTmp();
        $tmp = $this->tmp('ffmpeg');
        copy($file->getRealPath(), $tmp);
        @chmod($tmp, 0755);
        $s = ['phase' => 'verify', 'sources' => ['upload'], 'attempt' => 0, 'log' => []];
        try {
            $s = $this->stepVerify($s);
        } catch (\Throwable $e) {
            $this->cleanTmp();
            throw new \InvalidArgumentException('That program was rejected: '.$e->getMessage());
        }

        return $this->putState($s);
    }
}
