<?php

namespace App\Livewire;

use App\Models\BrandConnectRequest;
use App\Models\BrandPartner;
use App\Services\Brands\BrandConnectService;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Brand Connect for the brand owner (Brand-Growth Phase 6, MASTER-ONLY): who connected, accept or decline, and the welcome message settings. */
#[Layout('components.layouts.customer')]
class BrandConnections extends Component
{
    public string $tab = 'pending';

    public string $mode = 'open';

    public bool $introEnabled = false;

    public string $introText = '';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(BrandConnectService::enabled(), 404);
        $brand = $this->brand();
        $this->mode = (string) $brand->connect_mode;
        $this->introEnabled = (bool) $brand->intro_enabled;
        $this->introText = (string) $brand->intro_text;
        $this->tab = $brand->connect_mode === 'approve' ? 'pending' : 'accepted';
    }

    private function brand(): BrandPartner
    {
        return BrandPartner::query()->where('owner_user_id', Auth::id())->firstOrFail();
    }

    public function save(BrandConnectService $svc): void
    {
        $this->flash = $this->error = null;
        try {
            $svc->saveSettings($this->brand(), $this->mode, $this->introEnabled, $this->introText);
            $this->flash = 'Saved.';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function accept(int $id, BrandConnectService $svc): void
    {
        $svc->accept($this->brand(), $id, Auth::user());
        $this->flash = 'Accepted.';
    }

    public function decline(int $id, BrandConnectService $svc): void
    {
        $svc->decline($this->brand(), $id, Auth::user());
        $this->flash = 'Declined. They were not told.';
    }

    public function render()
    {
        $brand = $this->brand();
        $tab = in_array($this->tab, ['pending', 'accepted', 'declined'], true) ? $this->tab : 'pending';
        $counts = BrandConnectRequest::query()->where('brand_partner_id', $brand->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $rows = BrandConnectRequest::query()->with('user:id,name,username')->where('brand_partner_id', $brand->id)->where('status', $tab)->latest('id')->limit(100)->get();

        return view('livewire.brand-connections', [
            'brand' => $brand, 'rows' => $rows, 'counts' => $counts, 'tab' => $tab,
            'cost' => Money::credits(N2NPricing::messageCostCents()),
            'available' => Money::credits(app(UsageMeter::class)->available(Auth::user())),
        ]);
    }
}
