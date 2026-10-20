<?php

namespace App\Livewire;

use App\Models\ConnectionIntent;
use App\Services\Brands\ConnectionIntentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Dashboard popup: "You came from {brand}. Connect with them?" (Brand-Growth Phase 6, MASTER-ONLY). Shown at most 3 times a day apart; Continue opens the brand profile, Not now closes it. Connecting is always the person's own tap on the profile. */
class ConnectPromptPopup extends Component
{
    public ?int $intentId = null;

    public ?string $brandName = null;

    public ?string $logo = null;

    public ?int $brandId = null;

    public function mount(ConnectionIntentService $svc): void
    {
        $intent = Auth::user() ? $svc->nextPopup(Auth::user(), false) : null;
        if ($intent) {
            $this->intentId = $intent->id;
            $this->brandId = $intent->brand_partner_id;
            $this->brandName = (string) $intent->brand->brand_name;
            $this->logo = $intent->brand->logo_path;
        }
    }

    /** The sheet opened for real: count it (once per intent per load). */
    public function shown(ConnectionIntentService $svc): void
    {
        $intent = $this->intentId ? ConnectionIntent::query()->where('user_id', Auth::id())->where('status', ConnectionIntent::PENDING)->find($this->intentId) : null;
        if ($intent) {
            $svc->markShown($intent);
        }
    }

    public function notNow(ConnectionIntentService $svc): void
    {
        if ($this->intentId) {
            $svc->notNow(Auth::user(), $this->intentId);
        }
        $this->reset('intentId', 'brandName', 'logo', 'brandId');
    }

    public function render()
    {
        return view('livewire.connect-prompt-popup', ['intent' => $this->intentId ? ConnectionIntent::query()->find($this->intentId) : null]);
    }
}
