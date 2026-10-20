<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Models\SocialFollowClaim;
use App\Services\Social\FollowRewardClawback;
use App\Services\Social\FollowRewardGate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Follow rewards (Brand-Growth Phase 7, MASTER-ONLY): the gate's switches and ceilings, the recent claims, and clawback. */
#[Layout('components.layouts.admin')]
class FollowRewards extends Component
{
    public bool $enabled = true;

    public bool $verifiedEmail = true;

    public string $seconds = '8';

    public string $accountHours = '0';

    public string $perIp = '10';

    public string $perUser = '20';

    /** @var array<int, string> claim id => reason */
    public array $reason = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->enabled = FollowRewardGate::enabled();
        $this->verifiedEmail = FollowRewardGate::requireVerifiedEmail();
        $this->seconds = (string) FollowRewardGate::minSeconds();
        $this->accountHours = (string) FollowRewardGate::minAccountHours();
        $this->perIp = (string) FollowRewardGate::maxPerIpHour();
        $this->perUser = (string) FollowRewardGate::maxPerUserHour();
    }

    public function save(): void
    {
        $this->guard();
        $this->validate(['seconds' => ['required', 'integer', 'min:0', 'max:120'], 'accountHours' => ['required', 'integer', 'min:0', 'max:720'], 'perIp' => ['required', 'integer', 'min:1', 'max:500'], 'perUser' => ['required', 'integer', 'min:1', 'max:500']]);
        Setting::setValue('brand.follow_reward.enabled', $this->enabled, 'brand');
        Setting::setValue('brand.follow_reward.require_verified_email', $this->verifiedEmail, 'brand');
        Setting::setValue('brand.follow_reward.min_seconds', (int) $this->seconds, 'brand');
        Setting::setValue('brand.follow_reward.min_account_hours', (int) $this->accountHours, 'brand');
        Setting::setValue('brand.follow_reward.max_per_ip_hour', (int) $this->perIp, 'brand');
        Setting::setValue('brand.follow_reward.max_per_user_hour', (int) $this->perUser, 'brand');
        \App\Support\Auditor::log('follow_reward.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function clawback(int $claimId, FollowRewardClawback $svc): void
    {
        $this->guard();
        $reason = trim((string) ($this->reason[$claimId] ?? ''));
        if ($reason === '') {
            $this->addError('reason.'.$claimId, 'Give a reason (it goes in the audit log).');

            return;
        }
        $r = $svc->reverse(SocialFollowClaim::findOrFail($claimId), Auth::user(), $reason);
        $this->dispatch('nx-toast', type: 'success', message: 'Took back '.$r['taken'].' credits'.($r['shortfall'] > 0 ? ' ('.$r['shortfall'].' had already been spent).' : '.'));
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function render()
    {
        return view('livewire.admin.follow-rewards', [
            'claims' => SocialFollowClaim::with(['user:id,name,email', 'brandPartnerHandle:id,handle_label,brand_partner_id', 'handle:id,handle_label'])->latest('claimed_at')->limit(50)->get(),
        ]);
    }
}
