<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Services\Brands\BrandSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Get Listed" landing (BUILD-9 §9). Explains the value honestly, shows a live
 * plan-comparison table, and starts the subscription immediately on plan pick —
 * no "contact us" step. Choosing a plan charges the first month and drops the
 * business straight into guided setup.
 */
#[Layout('components.layouts.customer')]
class GetListed extends Component
{
    public ?string $error = null;

    /** Free-trial confirmation (master only; both stay inert where the trial module is absent). */
    public ?int $trialPlanId = null;

    public bool $trialAccept = false;

    public function booted(): void
    {
        // Batch 8: the "get listed" surface is part of the Brand Hunt feature —
        // locked with it. Inert on the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);
    }

    public function choose(int $planId, BrandSubscriptionService $subs)
    {
        $plan = BrandSubscriptionPlan::active()->find($planId);
        if (! $plan) {
            $this->error = 'That plan is not available.';

            return null;
        }

        try {
            $subs->subscribe(Auth::user(), $plan);
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet is short for the first month ($'.number_format((float) $plan->price_usd_per_month, 2).'). Top up your wallet, then choose a plan.';

            return null;
        }

        return $this->redirect(route('brand.manage'), navigate: true);
    }

    public function askTrial(int $planId): void
    {
        $this->error = null;
        $this->trialAccept = false;
        $this->trialPlanId = BrandSubscriptionPlan::active()->whereKey($planId)->exists() ? $planId : null;
    }

    public function startTrial()
    {
        abort_unless(class_exists(\App\Services\Brands\BrandTrialService::class), 404);
        $plan = $this->trialPlanId ? BrandSubscriptionPlan::active()->find($this->trialPlanId) : null;
        if (! $plan) {
            $this->error = 'That plan is not available.';

            return null;
        }
        try {
            app(\App\Services\Brands\BrandTrialService::class)->start(Auth::user(), $plan, $this->trialAccept);
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirect(route('brand.manage'), navigate: true);
    }

    public function render()
    {
        // NaaraBusiness Suite (master-only; both flags are false in a white-label install, where this page stays exactly "Get Listed").
        $suite = class_exists(\App\Support\Business\BusinessSuiteSettings::class) && \App\Support\Business\BusinessSuiteSettings::enabled();
        $plans = BrandSubscriptionPlan::active()->ordered()->get();
        $perks = [];
        if ($suite) {
            $live = \App\Support\Business\BusinessPlanFeatures::assignable();
            foreach ($plans as $p) {
                $perks[$p->id] = [
                    'features' => collect($p->grantedFeatures())->filter(fn ($k) => in_array($k, $live, true))->map(fn ($k) => \App\Support\Business\BusinessPlanFeatures::FEATURES[$k]['label'])->values()->all(),
                    'credits' => $p->monthlyCredits(), 'skins' => $p->grantsPremiumSkins(),
                ];
            }
        }

        $trial = null;
        if (class_exists(\App\Services\Brands\BrandTrialService::class) && \App\Services\Brands\BrandTrialService::enabled() && Auth::check()) {
            $svc = app(\App\Services\Brands\BrandTrialService::class);
            $code = $svc->blocker(Auth::user());
            $trial = ['days' => \App\Services\Brands\BrandTrialService::days(), 'blocked' => $code && $code !== 'trial_used' && $code !== 'already_pro' && $code !== 'already_subscribed' ? $svc->message($code) : null, 'available' => $code === null];
        }

        return view('livewire.get-listed', [
            'trial' => $trial,
            'suite' => $suite, 'perks' => $perks,
            'plans' => $plans,
            'alreadyListed' => Auth::check() && BrandPartner::where('owner_user_id', Auth::id())->exists(),
        ]);
    }
}
