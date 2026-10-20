<?php

namespace App\Livewire\Admin;

use App\Services\Business\Brain\BusinessBrainEconomics;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Business Suite > Brain economics (MASTER-ONLY): AI cost against Naara Pro revenue, per plan and per heavy user. Read-only, from measured rows. */
#[Layout('components.layouts.admin')]
class BusinessBrainEconomicsPage extends Component
{
    public int $days = 30;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
    }

    public function setDays(int $d): void
    {
        $this->days = in_array($d, [7, 30, 90], true) ? $d : 30;
    }

    public function render()
    {
        return view('livewire.admin.business-brain-economics', ['report' => app(BusinessBrainEconomics::class)->report($this->days)]);
    }
}
