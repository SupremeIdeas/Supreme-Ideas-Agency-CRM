<?php

namespace App\Livewire\Admin\MasterMarketing;

use App\Models\MasterMarketingInstaller;
use App\Support\AppExport;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\MasterMarketing\InstallerCatalog;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > Installers (MASTER ONLY): one screen for every way people install Naara. Windows, macOS, Linux and the Chrome extension live here (upload a file or paste a link);
 * Android and iOS stay in App Export (the same settings App Builder uses) and are edited here too, so there is a single source of truth.
 */
#[Layout('components.layouts.admin')]
class Installers extends Component
{
    /** @var array<string, array{version:string, external_url:string, min_os:string, notes:string, is_published:bool}> */
    public array $form = [];

    public string $androidStoreUrl = '';

    public bool $androidStoreLive = false;

    public string $iosStoreUrl = '';

    public bool $iosStoreLive = false;

    public bool $androidApkOn = true;

    public ?string $status = null;

    public function mount(): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        foreach (InstallerCatalog::rows() as $p => $row) {
            $this->form[$p] = ['version' => (string) ($row?->version), 'external_url' => (string) ($row?->external_url), 'min_os' => (string) ($row?->min_os), 'notes' => (string) ($row?->notes), 'is_published' => (bool) ($row?->is_published)];
        }
        $this->androidStoreUrl = (string) AppExport::get('android_store_url');
        $this->androidStoreLive = (bool) AppExport::get('android_store_live');
        $this->iosStoreUrl = (string) AppExport::get('ios_store_url');
        $this->iosStoreLive = (bool) AppExport::get('ios_store_live');
        $this->androidApkOn = (bool) AppExport::get('download_enabled');
    }

    public function save(string $platform): void
    {
        abort_unless(array_key_exists($platform, InstallerCatalog::platforms()), 404);
        $f = $this->validate([
            "form.$platform.version" => 'nullable|string|max:32',
            "form.$platform.external_url" => 'nullable|url|max:2000',
            "form.$platform.min_os" => 'nullable|string|max:80',
            "form.$platform.notes" => 'nullable|string|max:1000',
            "form.$platform.is_published" => 'boolean',
        ])['form'][$platform];

        $row = MasterMarketingInstaller::query()->firstOrNew(['platform' => $platform]);
        $row->fill([
            'version' => $f['version'] ?: null,
            'external_url' => $f['external_url'] ?: null,
            'min_os' => $f['min_os'] ?: null,
            'notes' => $f['notes'] ?: null,
            'is_published' => (bool) $f['is_published'],
        ]);
        // a pasted link replaces an uploaded file, so the page never has two competing sources
        if ($row->external_url && $row->file_path) {
            $this->discardFile($row);
        }
        $row->save();
        Auditor::log('installer.saved', null, null, ['platform' => $platform, 'published' => $row->is_published]);
        $this->status = InstallerCatalog::platforms()[$platform]['label'].' saved.'.($row->is_published && ! $row->isLive() ? ' It is not shown yet: add a file or a link.' : '');
    }

    public function removeFile(string $platform): void
    {
        $row = MasterMarketingInstaller::query()->where('platform', $platform)->first();
        if ($row) {
            $this->discardFile($row);
            $row->save();
            Auditor::log('installer.file_removed', null, null, ['platform' => $platform]);
            $this->status = 'File removed.';
        }
    }

    public function saveStores(): void
    {
        $this->validate(['androidStoreUrl' => 'nullable|url|max:2000', 'iosStoreUrl' => 'nullable|url|max:2000']);
        AppExport::save([
            'android_store_url' => $this->androidStoreUrl, 'android_store_live' => $this->androidStoreLive,
            'ios_store_url' => $this->iosStoreUrl, 'ios_store_live' => $this->iosStoreLive, 'download_enabled' => $this->androidApkOn,
        ]);
        Auditor::log('installer.stores_saved');
        $this->status = 'Android and iOS settings saved.';
    }

    private function discardFile(MasterMarketingInstaller $row): void
    {
        if ($row->file_path) {
            Storage::disk(MediaStorage::disk())->delete($row->file_path);
        }
        $row->forceFill(['file_path' => null, 'file_name' => null, 'size_bytes' => null, 'sha256' => null, 'checks' => null]);
    }

    /** Slice size: comfortably under the host's own upload and post limits, never bigger than 8 MB. */
    private function chunkBytes(): int
    {
        $toBytes = function (string $v): int {
            $n = (int) $v;

            return match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
        };
        $limits = array_filter([$toBytes((string) ini_get('upload_max_filesize')), $toBytes((string) ini_get('post_max_size'))]);
        $limit = $limits ? min($limits) : 2097152;

        return (int) max(262144, min(8388608, (int) ($limit * 0.8)));
    }

    public function render()
    {
        return view('livewire.admin.master-marketing.installers', [
            'platforms' => InstallerCatalog::platforms(),
            'rows' => InstallerCatalog::rows(),
            'apkUrl' => (string) AppExport::get('android_apk_url'),
            'release' => InstallerCatalog::consistency(),
            'maxUpload' => ini_get('upload_max_filesize'),
            'dropConfig' => [
                'labels' => collect(InstallerCatalog::platforms())->map(fn ($p) => $p['label'])->all() + ['android' => 'Android (.apk / .aab)'],
                'planUrl' => route('admin.installers.plan'),
                'chunkUrl' => route('admin.installers.chunk', '__P__'),
                'finalizeUrl' => route('admin.installers.finalize', '__P__'),
                'chunkBytes' => $this->chunkBytes(),
                'csrf' => csrf_token(),
            ],
        ]);
    }
}
