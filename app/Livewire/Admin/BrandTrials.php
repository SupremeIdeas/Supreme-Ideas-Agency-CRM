<?php

namespace App\Livewire\Admin;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\Setting;
use App\Services\Brands\BrandTrialService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Naara Pro trials (Brand-Growth Phase 4, MASTER-ONLY): the on/off switch, the knobs, every trial and where it stands. */
#[Layout('components.layouts.admin')]
class BrandTrials extends Component
{
    public bool $enabled = false;

    public string $days = '3';

    public string $retryDays = '2';

    public string $maxRetries = '3';

    /** Which referral link wins when a visitor met several (last | first | brand). */
    public string $precedence = 'last';

    /** Brand Connect: connection requests, owner approval and the welcome message (off by default). */
    public bool $connectEnabled = false;

    public function mount(): void
    {
        $this->guard(404);
        $this->enabled = BrandTrialService::enabled();
        $this->days = (string) BrandTrialService::days();
        $this->retryDays = (string) BrandTrialService::retryDays();
        $this->maxRetries = (string) BrandTrialService::maxRetries();
        $this->precedence = \App\Support\BusinessCardReferralPrecedence::mode();
        $this->connectEnabled = \App\Services\Brands\BrandConnectService::enabled();
    }

    public function save(): void
    {
        $this->guard();
        $this->validate(['days' => ['required', 'integer', 'min:1', 'max:30'], 'retryDays' => ['required', 'integer', 'min:1', 'max:14'], 'maxRetries' => ['required', 'integer', 'min:0', 'max:10'], 'precedence' => ['required', 'in:'.implode(',', array_keys(\App\Support\BusinessCardReferralPrecedence::MODES))]]);
        Setting::setValue('referral.precedence', $this->precedence, 'brand');
        Setting::setValue('brand.connect.enabled', $this->connectEnabled, 'brand');
        Setting::setValue('brand.trial.enabled', $this->enabled, 'brand');
        Setting::setValue('brand.trial.days', (int) $this->days, 'brand');
        Setting::setValue('brand.trial.retry_days', (int) $this->retryDays, 'brand');
        Setting::setValue('brand.trial.max_retries', (int) $this->maxRetries, 'brand');
        \App\Support\Auditor::log('brand_trials.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved. Trials already running keep the length they started with.');
    }

    public function reset_(int $brandId, BrandTrialService $svc): void
    {
        $this->guard();
        $svc->resetEligibility(BrandPartner::findOrFail($brandId), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'This person can take another trial.');
    }

    public function end(int $subId, BrandTrialService $svc): void
    {
        $this->guard();
        $svc->cancel(BrandSubscription::findOrFail($subId));
        $this->dispatch('nx-toast', type: 'success', message: 'Trial ended. Nothing was charged.');
    }

    private function guard(int $code = 403): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), $code);
    }

    public function render()
    {
        $base = BrandSubscription::whereNotNull('trial_started_at');

        return view('livewire.admin.brand-trials', [
            'counts' => [
                'running' => (clone $base)->where('status', BrandSubscription::TRIALING)->count(),
                'unfunded' => (clone $base)->where('status', BrandSubscription::PAST_DUE)->whereNull('converted_at')->count(),
                'converted' => (clone $base)->whereNotNull('converted_at')->count(),
                'ended' => (clone $base)->where('status', BrandSubscription::CANCELLED)->whereNull('converted_at')->count(),
            ],
            'rows' => (clone $base)->with('brandPartner:id,brand_name,owner_user_id', 'brandPartner.owner:id,email')->latest('trial_started_at')->limit(60)->get(),
        ]);
    }
}
