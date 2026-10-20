<?php

namespace App\Livewire;

use App\Models\BrandLink;
use App\Services\BrandLinks\BrandLinkService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/** The approved links on a public brand profile (Brand-Growth Phase 2, MASTER-ONLY): label plus the real host, an interstitial the first time per domain, and a report button. */
class BrandLinksList extends Component
{
    public int $brandId;

    public ?string $flash = null;

    public function mount(int $brandId): void
    {
        $this->brandId = $brandId;
    }

    public function report(int $id, BrandLinkService $svc): void
    {
        $key = 'brand-link-report:'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }
        RateLimiter::hit($key, 3600);
        $link = BrandLink::approved()->where('brand_partner_id', $this->brandId)->find($id);
        if ($link) {
            $svc->report($link);
            $this->flash = 'Thanks. We will take a look.';
        }
    }

    public function render()
    {
        return view('livewire.brand-links-list', ['links' => BrandLink::approved()->where('brand_partner_id', $this->brandId)->orderBy('sort_order')->get()]);
    }
}
