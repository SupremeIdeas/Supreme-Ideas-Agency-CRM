<?php

namespace App\Livewire\Admin;

use App\Models\BrandSubscriptionPlan;
use App\Support\Auditor;
use App\Support\Business\BusinessPlanFeatures;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin > Brand directory > one plan: what this Naara Pro plan grants from the NaaraBusiness Suite, plus the Pro perks (premium skins, a monthly
 * NaaraCredit refill) and per-plan workspace limits (MASTER-ONLY). Only modules the admin has switched on in Business Suite settings can be
 * ticked; a feature already on the plan whose module is off today stays stored (and shows as paused) so switching the module back on restores it.
 */
class BusinessPlanCapabilities extends Component
{
    public int $planId;

    /** @var list<string> */
    public array $features = [];

    public bool $premiumSkins = false;

    public int $monthlyCredits = 0;

    /** @var array<string, int|string> */
    public array $limits = ['seats' => '', 'numbers' => '', 'channels' => ''];

    /** NaaraBrain for this plan (master only): blank = the Pro defaults from Admin > NaaraBrain. */
    public int|string $brainDaily = '';

    /** @var list<string> */
    public array $brainDepths = [];

    /** The inbox assistant's monthly allowance on this plan (blank = the platform default). */
    public int|string $brainBusinessActions = '';

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(FeatureEntitlements::isMaster() && Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(int $planId): void
    {
        $this->planId = $planId;
        $c = BrandSubscriptionPlan::findOrFail($planId)->capabilities ?? [];
        $this->features = array_values((array) ($c['features'] ?? []));
        $this->premiumSkins = (bool) ($c['premium_skins'] ?? false);
        $this->monthlyCredits = (int) ($c['monthly_credits'] ?? 0);
        foreach (array_keys(BusinessPlanFeatures::LIMITS) as $name) {
            $this->limits[$name] = ($c['limits'][$name] ?? '') ?: '';
        }
        $this->brainBusinessActions = ($c['brain_business']['monthly_actions'] ?? '') ?: '';
        $this->brainDaily = ($c['brain']['daily'] ?? '') ?: '';
        $this->brainDepths = array_values((array) ($c['brain']['depths'] ?? []));
    }

    public function save(): void
    {
        $this->validate([
            'monthlyCredits' => 'required|integer|min:0|max:100000',
            'limits.seats' => 'nullable|integer|min:0|max:10000',
            'limits.numbers' => 'nullable|integer|min:0|max:10000',
            'limits.channels' => 'nullable|integer|min:0|max:10000',
            'brainDaily' => 'nullable|integer|min:0|max:100000',
            'brainBusinessActions' => 'nullable|integer|min:0|max:100000',
            'brainDepths.*' => 'in:quick,balanced,deep',
        ]);

        $plan = BrandSubscriptionPlan::findOrFail($this->planId);
        $assignable = BusinessPlanFeatures::assignable();
        // What the admin can tick today comes from the form; a feature whose module is off is kept exactly as it was stored.
        $kept = array_diff((array) ($plan->capabilities['features'] ?? []), $assignable);
        $chosen = array_intersect($this->features, $assignable);

        $clean = BusinessPlanFeatures::clean([
            'features' => array_merge($kept, $chosen),
            'premium_skins' => $this->premiumSkins,
            'monthly_credits' => $this->monthlyCredits,
            'limits' => $this->limits,
            'brain' => ['daily' => $this->brainDaily, 'depths' => $this->brainDepths],
            'brain_business' => ['monthly_actions' => $this->brainBusinessActions],
        ]);
        $plan->forceFill(['capabilities' => $clean])->save();
        Auditor::log('business.plan_capabilities', BrandSubscriptionPlan::class, $plan->id, $clean);

        $this->features = array_values(array_intersect($clean['features'], $assignable));
        $this->saved = 'Saved.';
    }

    public function render()
    {
        $plan = BrandSubscriptionPlan::find($this->planId);
        $stored = (array) ($plan?->capabilities['features'] ?? []);

        return view('livewire.admin.business-plan-capabilities', [
            'catalogue' => BusinessPlanFeatures::FEATURES,
            'live' => BusinessPlanFeatures::assignable(),
            'limitDefs' => BusinessPlanFeatures::LIMITS,
            'pausedStored' => array_values(array_diff($stored, BusinessPlanFeatures::assignable())),
            'count' => count($stored),
            'brainOn' => class_exists(\App\Support\Brain\BrainAccess::class),
        ]);
    }
}
