<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Models\MasterMarketingInstaller;
use App\Support\AppExport;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\MasterMarketing\InstallerCatalog;
use App\Support\MasterMarketing\InstallerInspector;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MASTER ONLY. Public: counted redirect to an installer. Admin: upload a (possibly large) installer file. The file goes to the public media disk, its size and
 * SHA-256 are recorded so the download page can show them, and the previous upload for that platform is removed.
 */
class InstallerController extends Controller
{
    public function get(string $platform): RedirectResponse
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $row = MasterMarketingInstaller::query()->where('platform', $platform)->first();
        abort_unless($row && $row->isLive(), 404);
        $row->increment('downloads');

        return redirect()->away($row->targetUrl());
    }

    public function upload(Request $request, string $platform): RedirectResponse
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $isApk = $platform === 'android';
        abort_unless($isApk || array_key_exists($platform, InstallerCatalog::platforms()), 404);
        $exts = $isApk ? ['apk', 'aab'] : InstallerCatalog::acceptedExtensions($platform);   // .aab is accepted so the inspector can explain why it is refused

        // One file, OR the numbered parts of a big file (name.zip.part00, name.zip.part01, ...) which are joined here and checked, so nobody has to join them by hand.
        $request->validate(['installer' => ['nullable', 'file', 'max:2097152'], 'parts' => ['nullable', 'array', 'max:40'], 'parts.*' => ['file', 'max:1048576'], 'expected_sha256' => ['nullable', 'regex:/^[A-Fa-f0-9]{64}$/'], 'version' => ['nullable', 'string', 'max:32']]);
        if ($request->hasFile('parts')) {
            $joined = $this->join($request->file('parts'));
            if (is_string($joined)) {
                return back()->withErrors(['installer' => $joined]);
            }
            [$tmp, $origName] = $joined;
            $size = filesize($tmp);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $sha = hash_file('sha256', $tmp);
            if ($request->filled('expected_sha256') && ! hash_equals(strtolower($request->input('expected_sha256')), $sha)) {
                @unlink($tmp);

                return back()->withErrors(['installer' => 'The joined file does not match the SHA-256 you gave ('.$sha.'). A part is missing, out of order or damaged. Nothing was saved.']);
            }
        } else {
            $file = $request->file('installer');
            if (! $file) {
                return back()->withErrors(['installer' => 'Choose a file, or all the numbered parts of one.']);
            }
            $tmp = $file->getRealPath();
            $origName = $file->getClientOriginalName();
            $size = $file->getSize();
            $ext = strtolower($file->getClientOriginalExtension());
            $sha = hash_file('sha256', $tmp);
        }
        if (! in_array($ext, $exts, true)) {
            if (isset($joined)) {
                @unlink($tmp);
            }

            return back()->withErrors(['installer' => 'That file type is not accepted here. Use: .'.implode(', .', $exts).'.']);
        }

        $check = InstallerInspector::inspect($tmp, $platform, $origName, $request->input('version'));
        if ($check['problems']) {
            if (isset($joined)) {
                @unlink($tmp);
            }

            return back()->withErrors(['installer' => implode(' ', $check['problems'])]);
        }
        $message = $this->persist($platform, $tmp, $origName, (int) $size, $ext, $sha, isset($joined), $request->input('version'), $request->boolean('publish'), $check['facts']);

        return back()->with('status', $message.($check['warnings'] ? ' Note: '.implode(' ', $check['warnings']) : ''));
    }

    /** What the drop zone shows before anything is sent: which slot each file belongs in, which version, and which parts belong together. */
    public function plan(Request $request): JsonResponse
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $names = array_slice((array) $request->input('names', []), 0, 200);
        $out = [];
        foreach ($names as $n) {
            if (is_string($n)) {
                $out[$n] = InstallerCatalog::guess($n);
            }
        }

        return response()->json(['files' => $out]);
    }

    /** One slice of a big file (or of one numbered part). The browser sends slices small enough for any host's upload limit; finalize() joins them. */
    public function chunk(Request $request, string $platform): JsonResponse
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $request->validate(['upload_id' => ['required', 'regex:/^[A-Za-z0-9]{16,40}$/'], 'index' => ['required', 'integer', 'min:0', 'max:20000'], 'chunk' => ['required', 'file', 'max:16384']]);
        $dir = $this->chunkDir($request->input('upload_id'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->sweepStaleChunks();
        }
        $request->file('chunk')->move($dir, sprintf('%06d', (int) $request->input('index')));

        return response()->json(['ok' => true]);
    }

    /** Joins every slice in order, checks the SHA-256 when one was given, and stores the result exactly as a normal upload would. */
    public function finalize(Request $request, string $platform): JsonResponse
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $isApk = $platform === 'android';
        abort_unless($isApk || array_key_exists($platform, InstallerCatalog::platforms()), 404);
        $data = $request->validate([
            'upload_id' => ['required', 'regex:/^[A-Za-z0-9]{16,40}$/'], 'total' => ['required', 'integer', 'min:1', 'max:20000'], 'name' => ['required', 'string', 'max:200'],
            'expected_sha256' => ['nullable', 'regex:/^[A-Fa-f0-9]{64}$/'], 'version' => ['nullable', 'string', 'max:32'], 'publish' => ['nullable', 'boolean'], 'force' => ['nullable', 'boolean'],
        ]);
        $dir = $this->chunkDir($data['upload_id']);
        $fail = function (string $m, array $extra = []) use ($dir): JsonResponse {
            $this->removeDir($dir);

            return response()->json(['ok' => false, 'message' => $m] + $extra, 422);
        };

        $name = InstallerCatalog::guess($data['name'])['base'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $exts = $isApk ? ['apk', 'aab'] : InstallerCatalog::acceptedExtensions($platform);   // .aab is accepted so the inspector can explain why it is refused
        if (! in_array($ext, $exts, true)) {
            return $fail('That file type is not accepted for this slot. Use: .'.implode(', .', $exts).'.');
        }
        $have = is_dir($dir) ? array_values(array_filter(scandir($dir), fn ($f) => ctype_digit($f))) : [];
        if (count($have) !== (int) $data['total'] || $have !== array_map(fn ($i) => sprintf('%06d', $i), range(0, (int) $data['total'] - 1))) {
            return $fail('Some slices did not arrive ('.count($have).' of '.$data['total'].'). Nothing was saved; send the file again.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'naara-join-');
        $out = fopen($tmp, 'wb');
        foreach ($have as $f) {
            $in = fopen($dir.'/'.$f, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $sha = hash_file('sha256', $tmp);
        $size = filesize($tmp);
        if (! empty($data['expected_sha256']) && ! hash_equals(strtolower($data['expected_sha256']), $sha)) {
            @unlink($tmp);

            return $fail('The joined file does not match the SHA-256 you gave. A part is missing, out of order or damaged. Nothing was saved.', ['sha256' => $sha, 'expected' => strtolower($data['expected_sha256'])]);
        }
        // look inside: right kind of file, right CPU, a real manifest, the version it says it is
        $version = $data['version'] ?? InstallerCatalog::guess($name)['version'];
        $check = InstallerInspector::inspect($tmp, $platform, $name, $data['version'] ?? null);
        if ($check['problems']) {
            @unlink($tmp);

            return $fail(implode(' ', $check['problems']));
        }
        // never silently replace a newer build with an older one
        $current = $isApk ? null : MasterMarketingInstaller::query()->where('platform', $platform)->value('version');
        if (! $request->boolean('force') && $version && $current && version_compare($version, $current, '<')) {
            @unlink($tmp);

            return response()->json(['ok' => false, 'needs_confirm' => true, 'message' => 'The live '.InstallerCatalog::platforms()[$platform]['label'].' file is version '.$current.' and this one is '.$version.'. Replacing a newer build with an older one is usually a mistake.'], 422);
        }
        $this->removeDir($dir);
        $message = $this->persist($platform, $tmp, $name, $size, $ext, $sha, true, $data['version'] ?? ($check['facts']['version'] ?? null), (bool) ($data['publish'] ?? false), $check['facts']);

        return response()->json(['ok' => true, 'message' => $message, 'sha256' => $sha, 'size' => $size, 'verified' => ! empty($data['expected_sha256']), 'name' => $name, 'facts' => $check['facts'], 'warnings' => $check['warnings']]);
    }

    /** Stores the finished file on the media disk and records it. Returns the sentence the admin sees. */
    private function persist(string $platform, string $tmp, string $origName, int $size, string $ext, string $sha, bool $tmpIsOurs, ?string $version, bool $publish, array $facts = []): string
    {
        $isApk = $platform === 'android';
        $disk = MediaStorage::disk();
        // keep dots and case so the person's download is still called Naara-1.0.1-mac-arm64-x7Qp2a.zip, not naara-101-mac-arm64-...
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($origName, PATHINFO_FILENAME)), '-.').'-'.Str::random(6).'.'.$ext;
        $stream = fopen($tmp, 'rb');
        $path = 'installers/'.$platform.'/'.$name;
        Storage::disk($disk)->put($path, $stream, 'public');
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($tmpIsOurs) {
            @unlink($tmp);
        }

        if ($isApk) {
            AppExport::save(['android_apk_url' => Storage::disk($disk)->url($path), 'download_enabled' => true]);
            Auditor::log('installer.uploaded', null, null, ['platform' => 'android', 'file' => $name]);

            return 'Android file uploaded and linked on the download page.';
        }

        $row = MasterMarketingInstaller::query()->firstOrNew(['platform' => $platform]);
        if ($row->file_path && Storage::disk($disk)->exists($row->file_path)) {
            Storage::disk($disk)->delete($row->file_path);
        }
        $row->fill(['file_name' => $name, 'file_path' => $path, 'size_bytes' => $size, 'sha256' => $sha, 'checks' => $facts ?: null, 'external_url' => null]);
        if ($version) {
            $row->version = $version;
        }
        if ($publish) {
            $row->is_published = true;
        }
        $row->save();
        Auditor::log('installer.uploaded', null, null, ['platform' => $platform, 'file' => $name, 'sha256' => $sha]);

        $label = InstallerCatalog::platforms()[$platform]['label'];

        return $label.' file uploaded'.($row->is_published ? ' and live on the download page.' : '. Tick Published to show it on the download page.');
    }

    private function chunkDir(string $id): string
    {
        return storage_path('app/installer-chunks/'.$id);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f !== '.' && $f !== '..') {
                @unlink($dir.'/'.$f);
            }
        }
        @rmdir($dir);
    }

    /** Abandoned uploads (a closed tab) are removed after a day. */
    private function sweepStaleChunks(): void
    {
        $root = storage_path('app/installer-chunks');
        foreach (is_dir($root) ? scandir($root) : [] as $d) {
            if ($d !== '.' && $d !== '..' && is_dir($root.'/'.$d) && filemtime($root.'/'.$d) < time() - 86400) {
                $this->removeDir($root.'/'.$d);
            }
        }
    }

    /**
     * Joins name.ext.part00, name.ext.part01 ... into one temporary file. Returns [path, originalName], or a plain-language error.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $parts
     * @return array{0:string,1:string}|string
     */
    private function join(array $parts): array|string
    {
        $byNumber = [];
        $base = null;
        foreach ($parts as $part) {
            if (! preg_match('/^(.+)\.part(\d{2,3})$/i', $part->getClientOriginalName(), $m)) {
                return 'Every part must be named like Naara-1.0.0.zip.part00, Naara-1.0.0.zip.part01 and so on. "'.$part->getClientOriginalName().'" is not.';
            }
            if ($base !== null && strcasecmp($base, $m[1]) !== 0) {
                return 'These parts belong to different files ("'.$base.'" and "'.$m[1].'"). Upload one file\'s parts at a time.';
            }
            $base = $m[1];
            $byNumber[(int) $m[2]] = $part;
        }
        ksort($byNumber);
        if (array_keys($byNumber) !== range(0, count($byNumber) - 1)) {
            return 'A part is missing: the numbers must run 00, 01, 02 with no gaps ('.count($byNumber).' selected).';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'naara-join-');
        $out = fopen($tmp, 'wb');
        foreach ($byNumber as $part) {
            $in = fopen($part->getRealPath(), 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        return [$tmp, $base];
    }
}
