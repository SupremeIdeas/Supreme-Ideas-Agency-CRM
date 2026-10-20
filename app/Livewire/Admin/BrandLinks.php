<?php

namespace App\Livewire\Admin;

use App\Models\BrandLink;
use App\Services\BrandLinks\BrandLinkPolicy;
use App\Services\BrandLinks\BrandLinkService;
use App\Services\BrandLinks\BrandLinkSettings;
use App\Services\BrandLinks\PublicSuffixUpdater;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Business links (Brand-Growth Phase 2, MASTER-ONLY): a link tester that shows every check, the review queue, the policy lists and the pinned Public Suffix List. */
#[Layout('components.layouts.admin')]
class BrandLinks extends Component
{
    public string $tBrand = '';

    public string $tLabel = 'Our website';

    public string $tUrl = '';

    public ?array $result = null;

    public array $rejectReason = [];

    public string $threshold = '';

    public string $reviewUpper = '';

    public string $perBrand = '';

    public string $recheckDays = '';

    /** @var array<string, string> list key => newline-separated values */
    public array $lists = [];

    public ?string $dataMessage = null;

    private const LISTS = ['tld_deny', 'tld_allow', 'shorteners', 'aggregators', 'trust_bait', 'protected_brands', 'stopwords'];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->threshold = (string) BrandLinkSettings::int('resemblance_threshold', 40);
        $this->reviewUpper = (string) BrandLinkSettings::int('review_band_upper', 60);
        $this->perBrand = (string) BrandLinkSettings::int('links_per_brand', 5);
        $this->recheckDays = (string) BrandLinkSettings::int('recheck_days', 7);
        foreach (self::LISTS as $k) {
            $this->lists[$k] = implode("\n", BrandLinkSettings::list($k));
        }
    }

    public function test(BrandLinkPolicy $policy): void
    {
        $this->guard();
        $this->validate(['tBrand' => ['required', 'string', 'max:120'], 'tLabel' => ['required', 'string', 'max:60'], 'tUrl' => ['required', 'string', 'max:500']]);
        $v = $policy->evaluate($this->tBrand, $this->tLabel, $this->tUrl);
        $this->result = ['status' => $v->status, 'score' => $v->score, 'checks' => $v->checks, 'reason' => $v->reason, 'host' => $v->host, 'registrable' => $v->registrable, 'final' => $v->finalHost, 'http' => $v->httpStatus, 'sb' => $v->safeBrowsing];
    }

    public function approve(int $id, BrandLinkService $svc): void
    {
        $this->guard();
        $svc->approve(BrandLink::findOrFail($id), Auth::id());
    }

    public function reject(int $id, BrandLinkService $svc): void
    {
        $this->guard();
        $svc->reject(BrandLink::findOrFail($id), trim((string) ($this->rejectReason[$id] ?? '')) ?: 'This link does not meet our link policy.', Auth::id());
    }

    public function recheck(int $id, BrandLinkService $svc): void
    {
        $this->guard();
        $svc->recheck(BrandLink::findOrFail($id));
        $this->dispatch('nx-toast', type: 'success', message: 'Checked.');
    }

    public function saveSettings(): void
    {
        $this->guard();
        $this->validate(['threshold' => ['required', 'integer', 'min:0', 'max:100'], 'reviewUpper' => ['required', 'integer', 'min:0', 'max:100'], 'perBrand' => ['required', 'integer', 'min:1', 'max:30'], 'recheckDays' => ['required', 'integer', 'min:1', 'max:90']]);
        BrandLinkSettings::save('resemblance_threshold', (int) $this->threshold);
        BrandLinkSettings::save('review_band_upper', max((int) $this->reviewUpper, (int) $this->threshold));
        BrandLinkSettings::save('links_per_brand', (int) $this->perBrand);
        BrandLinkSettings::save('recheck_days', (int) $this->recheckDays);
        foreach (self::LISTS as $k) {
            $vals = array_values(array_unique(array_filter(array_map(fn ($x) => strtolower(trim($x)), preg_split('/[\r\n,]+/', (string) ($this->lists[$k] ?? '')) ?: []))));
            BrandLinkSettings::save($k, $vals);
        }
        \App\Support\Auditor::log('brand_links.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function refreshData(): void
    {
        $this->guard();
        $r = PublicSuffixUpdater::refresh();
        $this->dataMessage = $r['message'];
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function render()
    {
        return view('livewire.admin.brand-links', [
            'queue' => BrandLink::with('brand:id,brand_name')->whereIn('status', [BrandLink::PENDING, BrandLink::SUSPENDED])->orderByDesc('review_requested')->latest('updated_at')->limit(50)->get(),
            'live' => BrandLink::approved()->count(),
            'pslVersion' => PublicSuffixUpdater::version(),
            'verifiedOn' => (string) config('brand_links.psl.verified_on'),
        ]);
    }
}
