<?php

namespace App\Livewire\Admin;

use App\Jobs\RunGiftCardLogoWizardJob;
use App\Models\GiftCardBrandLogo;
use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\Setting;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Services\GiftCards\GiftCardLogoWizard;
use App\Services\GiftCards\GiftCardOrderService;
use App\Support\Auditor;
use App\Support\GiftCardFraud;
use App\Support\MediaStorage;
use App\Support\ProviderStatus;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Naara Gift. One screen where Reloadly and Zendit sit side by side as
 * "brothers and sisters": both provider statuses + last sync, the merged
 * catalogue (enable/disable/feature per brand), the fraud-control thresholds,
 * and the manual review queue (approve → fulfil, reject → refund). Reloadly is
 * primary; Zendit fills the gaps — the storefront only ever sells the primary.
 */
#[Layout('components.layouts.admin')]
class GiftCards extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** Per-brand logo uploads (for products that synced without a logo). */
    public array $logoUploads = [];

    /** Logo Wizard review queue: official domain typed per brand, and replacement uploads per brand. */
    public array $logoDomain = [];

    public array $brandUploads = [];

    /** Fraud thresholds (admin-editable — no hardcoded limits). */
    public float $max_amount_24h = 500;

    public int $max_count_24h = 6;

    public float $max_amount_7d = 2000;

    public int $cooloff_hours = 24;

    public float $cooloff_value = 100;

    public float $review_threshold = 250;

    /** Catalogue browser filters. */
    public string $search = '';

    public string $providerFilter = '';

    /** Live connection-test results, keyed by provider. */
    public array $probe = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach (GiftCardFraud::settings() as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function updated($name): void
    {
        if (in_array($name, ['search', 'providerFilter'], true)) {
            $this->resetPage('cat');
        }
    }

    public function saveFraud(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'max_amount_24h' => 'required|numeric|min:0',
            'max_count_24h' => 'required|integer|min:1',
            'max_amount_7d' => 'required|numeric|min:0',
            'cooloff_hours' => 'required|integer|min:0',
            'cooloff_value' => 'required|numeric|min:0',
            'review_threshold' => 'required|numeric|min:0',
        ]);

        foreach (['max_amount_24h', 'max_count_24h', 'max_amount_7d', 'cooloff_hours', 'cooloff_value', 'review_threshold'] as $k) {
            Setting::setValue('giftcards.'.$k, $this->{$k}, 'giftcards');
        }
        Auditor::log('giftcard.fraud_updated', null, null, ['review_threshold' => $this->review_threshold]);
        $this->dispatch('nx-toast', type: 'success', message: 'Fraud controls saved.');
    }

    /** Upload a logo for a brand that synced without one (or replace it). */
    public function updatedLogoUploads($value, $key): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['logoUploads.'.$key => 'image|max:1024']);

        $product = GiftCardProduct::findOrFail((int) $key);
        $url = MediaStorage::storePublic($this->logoUploads[$key], 'giftcards');
        $product->update(['logo_url' => $url]);
        unset($this->logoUploads[$key]);

        Auditor::log('giftcard.logo_set', GiftCardProduct::class, $product->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Logo updated.');
    }

    /** Toggle a per-brand admin switch (enable / feature). */
    public function toggle(int $id, string $field): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($field, ['admin_enabled', 'featured'], true), 422);

        $product = GiftCardProduct::findOrFail($id);
        $product->update([$field => ! $product->{$field}]);
        $this->dispatch('nx-toast', type: 'success', message: 'Updated.');
    }

    /** Sync one provider's catalogue now. */
    public function sync(string $provider, GiftCardCatalogueSyncService $svc): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($provider, $svc->providerKeys(), true), 422);

        $count = $svc->sync($provider);
        SyncStatus::flush();
        $this->dispatch('nx-toast', type: $count > 0 ? 'success' : 'error',
            message: $count > 0 ? ucfirst($provider).": synced {$count} products." : ucfirst($provider).': sync returned nothing (check keys).');
    }

    /** Logo Wizard: queue a run over every brand that has no approved logo yet. */
    public function runLogoWizard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        RunGiftCardLogoWizardJob::dispatch();
        Auditor::log('giftcard.logo_wizard_run', null, null, []);
        $this->dispatch('nx-toast', type: 'success', message: 'Logo Wizard queued. Refresh in a few minutes.');
    }

    /** Retry one review/fallback brand from its official domain. */
    public function fetchLogo(string $brandKey, GiftCardLogoWizard $wizard): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $domain = trim((string) ($this->logoDomain[$brandKey] ?? ''));
        $row = GiftCardBrandLogo::where('brand_key', $brandKey)->firstOrFail();
        $out = $wizard->resolve($brandKey, $row->brand_name, null, $domain !== '' ? $domain : null);
        $wizard->applyToProducts($brandKey);
        $this->dispatch('nx-toast', type: $out->status === GiftCardBrandLogo::APPROVED ? 'success' : 'error', message: $out->status === GiftCardBrandLogo::APPROVED ? 'Logo found and saved.' : 'No logo found at that domain.');
    }

    /** Accept the logo the wizard proposed (a human confirmed it is the right brand). */
    public function approveLogo(string $brandKey): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        GiftCardBrandLogo::where('brand_key', $brandKey)->whereNotNull('path')->update(['status' => GiftCardBrandLogo::APPROVED]);
        Auditor::log('giftcard.logo_approved', null, null, ['brand' => $brandKey]);
    }

    public function updatedBrandUploads($value, $key): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['brandUploads.'.$key => 'image|max:2048']);
        $row = GiftCardBrandLogo::where('brand_key', $key)->firstOrFail();
        $stored = app(GiftCardLogoWizard::class)->storeUpload($key, $row->brand_name, (string) file_get_contents($this->brandUploads[$key]->getRealPath()));
        unset($this->brandUploads[$key]);
        $this->dispatch('nx-toast', type: $stored ? 'success' : 'error', message: $stored ? 'Logo uploaded.' : 'That image is too small or not readable (use at least 48px).');
    }

    /** Live self-test: actually authenticate + reach the API with the current keys. */
    public function preflight(string $provider, GiftCardCatalogueSyncService $svc): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($provider, $svc->providerKeys(), true), 422);

        $this->probe[$provider] = $svc->provider($provider)->preflight();
        $this->dispatch('nx-toast', type: $this->probe[$provider]['ok'] ? 'success' : 'error',
            message: $this->probe[$provider]['ok'] ? ucfirst($provider).' connected.' : ucfirst($provider).' test failed.');
    }

    /** Reconciliation export — retail only, never provider cost. */
    public function exportCsv(): StreamedResponse
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $rows = GiftCardOrder::with('user:id,email')->latest()->limit(10000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'buyer', 'brand', 'face_value', 'currency', 'price_charged_usd', 'status', 'reference']);
            foreach ($rows as $o) {
                fputcsv($out, [
                    $o->id, $o->created_at, $o->user?->email, $o->brand_name,
                    $o->face_value, $o->currency, $o->price_charged, $o->status, $o->transaction_ref,
                ]);
            }
            fclose($out);
        }, 'naara-gift-orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function approve(int $id, GiftCardOrderService $orders): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        try {
            $orders->approveReview(GiftCardOrder::findOrFail($id));
            $this->dispatch('nx-toast', type: 'success', message: 'Order approved and delivered.');
        } catch (\Throwable $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Could not fulfil — the wallet was refunded.');
        }
    }

    public function reject(int $id, GiftCardOrderService $orders): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $orders->rejectReview(GiftCardOrder::findOrFail($id), 'Declined in manual review.');
        $this->dispatch('nx-toast', type: 'success', message: 'Order declined — buyer refunded.');
    }

    public function render(GiftCardCatalogueSyncService $svc)
    {
        // Every registered provider, side by side — status + last sync +
        // catalogue depth. Order (and therefore the Primary/Secondary/…
        // role label) is the same priority ranking recomputePrimary() routes
        // by, so a newly-registered provider only needs adding to
        // GiftCardCatalogueSyncService::PROVIDERS to get a full admin card.
        $roles = ['Primary', 'Secondary', 'Tertiary', 'Quaternary', 'Backup'];
        $providers = collect($svc->providerKeys())->values()->map(fn ($p, $i) => [
            'key' => $p,
            'label' => ucfirst($p),
            'role' => $roles[$i] ?? 'Backup',
            'configured' => ProviderStatus::isActive($p),
            'sync' => SyncStatus::for('giftcards:'.$p),
            'total' => GiftCardProduct::where('provider', $p)->count(),
            'live' => GiftCardProduct::where('provider', $p)->where('is_primary', true)->where('admin_enabled', true)->count(),
        ])->all();

        $logoStats = GiftCardBrandLogo::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $logoQueue = GiftCardBrandLogo::query()->whereIn('status', [GiftCardBrandLogo::REVIEW, GiftCardBrandLogo::FALLBACK])->orderBy('brand_name')->limit(12)->get();
        $logoUrls = app(GiftCardLogoWizard::class);
        $catalogue = GiftCardProduct::query()
            ->when($this->search !== '', fn ($q) => $q->where('brand_name', 'like', '%'.$this->search.'%'))
            ->when($this->providerFilter !== '', fn ($q) => $q->where('provider', $this->providerFilter))
            ->orderByDesc('featured')->orderByDesc('is_primary')->orderBy('brand_name')
            ->paginate(15, pageName: 'cat');

        $review = GiftCardOrder::with('user:id,name,email')
            ->where('status', GiftCardOrder::STATUS_REVIEW)->latest()->get();

        $recent = GiftCardOrder::with('user:id,name,email')
            ->whereIn('status', [GiftCardOrder::STATUS_DELIVERED, GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED])
            ->latest()->limit(20)->get();

        return view('livewire.admin.gift-cards', compact('providers', 'catalogue', 'review', 'recent', 'logoStats', 'logoQueue', 'logoUrls'));
    }
}
