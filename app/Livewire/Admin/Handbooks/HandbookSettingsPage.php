<?php

namespace App\Livewire\Admin\Handbooks;

use App\Models\Handbook;
use App\Support\Auditor;
use App\Support\Handbooks\HandbookSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Handbooks > Store and unlock settings (MASTER-ONLY): what an eSIM, a Pro term and a purchase do, and the storefront copy. Every switch takes effect at once. */
#[Layout('components.layouts.admin')]
class HandbookSettingsPage extends Component
{
    public bool $storeEnabled = true;

    public bool $esimUnlockEnabled = true;

    /** @var list<string> */
    public array $esimCoverage = [];

    public int $esimRegionalPicks = 1;

    public string $esimMinPriceUsd = '0';

    public bool $esimFlagshipOnly = true;

    public bool $proUnlockEnabled = true;

    public int $proSlots = 3;

    public bool $purchaseEnabled = true;

    public bool $upgradeEnabled = true;

    public bool $esimBannerEnabled = true;

    public int $popularCount = 8;

    public int $imageMaxWidth = 1400;

    public int $imageQuality = 82;

    public int $imageFlagKb = 400;

    public string $heroEyebrow = '';

    public string $heroHeadline = '';

    public string $heroAccent = '';

    public string $heroSubtext = '';

    public string $ctaPrimary = '';

    public string $ctaSecondary = '';

    public ?string $flash = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $a = HandbookSettings::all();
        $this->storeEnabled = $a['store_enabled'];
        $this->esimUnlockEnabled = $a['esim_unlock_enabled'];
        $this->esimCoverage = $a['esim_unlock_coverage'];
        $this->esimRegionalPicks = $a['esim_regional_picks'];
        $this->esimMinPriceUsd = number_format($a['esim_min_price_usd'], 2, '.', '');
        $this->esimFlagshipOnly = $a['esim_unlock_flagship_only'];
        $this->proUnlockEnabled = $a['pro_unlock_enabled'];
        $this->proSlots = $a['pro_slots'];
        $this->purchaseEnabled = $a['purchase_enabled'];
        $this->upgradeEnabled = $a['upgrade_enabled'];
        $this->esimBannerEnabled = $a['esim_banner_enabled'];
        $this->popularCount = $a['popular_count'];
        $this->imageMaxWidth = $a['image_max_width'];
        $this->imageQuality = $a['image_quality'];
        $this->imageFlagKb = $a['image_flag_kb'];
        $this->heroEyebrow = $a['hero_eyebrow'];
        $this->heroHeadline = $a['hero_headline'];
        $this->heroAccent = $a['hero_accent'];
        $this->heroSubtext = $a['hero_subtext'];
        $this->ctaPrimary = $a['cta_primary'];
        $this->ctaSecondary = $a['cta_secondary'];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'esimCoverage' => ['array'], 'esimCoverage.*' => ['in:local,regional,global'],
            'esimRegionalPicks' => ['integer', 'min:1', 'max:10'], 'esimMinPriceUsd' => ['numeric', 'min:0', 'max:999'],
            'proSlots' => ['integer', 'min:0', 'max:50'], 'popularCount' => ['integer', 'min:3', 'max:24'],
            'imageMaxWidth' => ['integer', 'min:480', 'max:4000'], 'imageQuality' => ['integer', 'min:50', 'max:95'], 'imageFlagKb' => ['integer', 'min:50', 'max:8000'],
            'heroEyebrow' => ['required', 'string', 'max:60'], 'heroHeadline' => ['required', 'string', 'max:90'], 'heroAccent' => ['nullable', 'string', 'max:90'],
            'heroSubtext' => ['required', 'string', 'max:320'], 'ctaPrimary' => ['required', 'string', 'max:40'], 'ctaSecondary' => ['required', 'string', 'max:40'],
        ]);
        $map = [
            'store_enabled' => $this->storeEnabled, 'esim_unlock_enabled' => $this->esimUnlockEnabled, 'esim_unlock_coverage' => array_values(array_unique($this->esimCoverage)),
            'esim_regional_picks' => $this->esimRegionalPicks, 'esim_min_price_usd' => round((float) $this->esimMinPriceUsd, 2), 'esim_unlock_flagship_only' => $this->esimFlagshipOnly,
            'pro_unlock_enabled' => $this->proUnlockEnabled, 'pro_slots' => $this->proSlots, 'purchase_enabled' => $this->purchaseEnabled, 'upgrade_enabled' => $this->upgradeEnabled,
            'esim_banner_enabled' => $this->esimBannerEnabled, 'popular_count' => $this->popularCount, 'image_max_width' => $this->imageMaxWidth, 'image_quality' => $this->imageQuality, 'image_flag_kb' => $this->imageFlagKb, 'hero_eyebrow' => trim($this->heroEyebrow), 'hero_headline' => trim($this->heroHeadline),
            'hero_accent' => trim($this->heroAccent), 'hero_subtext' => trim($this->heroSubtext), 'cta_primary' => trim($this->ctaPrimary), 'cta_secondary' => trim($this->ctaSecondary),
        ];
        foreach ($map as $k => $v) {
            HandbookSettings::put($k, $v);
        }
        \Illuminate\Support\Facades\Cache::forget('handbooks.esim_banner');
        Auditor::log('handbook.settings', Handbook::class, 0, ['by' => Auth::id()]);
        $this->flash = 'Saved. The change is live now.';
    }

    public function render()
    {
        return view('livewire.admin.handbooks.settings', ['coverage' => HandbookSettings::COVERAGE]);
    }
}
