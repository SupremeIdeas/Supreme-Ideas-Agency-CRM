<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\Call;
use App\Models\Setting;
use App\Services\N2N\Calls\CallsAvailability;
use App\Services\N2N\Calls\TurnCredentials;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > N2N > Calls (MASTER-ONLY): switch, billing slices, timeouts and the measured picture (relay share, call minutes). It reports
 * "not measured" until real calls have reported; no figure here is an assumption. TURN secrets live in the environment and are never shown.
 */
#[Layout('components.layouts.admin')]
class CallSettings extends Component
{
    public bool $enabled = false;

    public bool $devMode = false;

    public bool $publicStun = false;

    public bool $reviewEnabled = false;

    public int $reviewMinS = 20;

    public int $reviewCooldownDays = 7;

    public bool $videoEnabled = true;

    public string $videoMultiplier = '2';

    public int $holdMinutes = 2;

    public int $incrementS = 60;

    public int $ringS = 40;

    public int $heartbeatS = 8;

    public int $staleS = 30;

    public int $attemptsPerMin = 6;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
        $this->enabled = (bool) Setting::getValue('n2n.calls_enabled', false);
        $this->devMode = CallsAvailability::devMode();
        $this->publicStun = (bool) Setting::getValue('n2n.calls_public_stun', false);
        $this->reviewEnabled = (bool) Setting::getValue('n2n.call_review_enabled', false);
        $this->reviewMinS = \App\Services\N2N\Calls\CallReviewPrompt::minSeconds();
        $this->reviewCooldownDays = \App\Services\N2N\Calls\CallReviewPrompt::cooldownDays();
        $this->videoEnabled = (bool) Setting::getValue('n2n.calls_video_enabled', true);
        $this->videoMultiplier = (string) \App\Services\N2N\Credit\N2NPricing::videoMultiplier();
        $this->holdMinutes = N2NSettings::callHoldMinutes();
        $this->incrementS = N2NSettings::callIncrementSeconds();
        $this->ringS = N2NSettings::callRingSeconds();
        $this->heartbeatS = N2NSettings::callHeartbeatSeconds();
        $this->staleS = N2NSettings::callStaleSeconds();
        $this->attemptsPerMin = N2NSettings::callAttemptsPerMinute();
    }

    public function save(): void
    {
        $this->validate([
            'videoMultiplier' => 'required|numeric|min:1|max:10', 'reviewMinS' => 'required|integer|min:0|max:600', 'reviewCooldownDays' => 'required|integer|min:0|max:90', 'holdMinutes' => 'required|integer|min:1|max:30', 'incrementS' => 'required|integer|min:1|max:600', 'ringS' => 'required|integer|min:10|max:120',
            'heartbeatS' => 'required|integer|min:3|max:30', 'staleS' => 'required|integer|min:10|max:300', 'attemptsPerMin' => 'required|integer|min:1|max:60',
        ]);
        foreach (['calls_enabled' => $this->enabled, 'calls_dev_mode' => $this->devMode, 'calls_video_enabled' => $this->videoEnabled, 'call_review_enabled' => $this->reviewEnabled, 'call_review_min_s' => $this->reviewMinS, 'call_review_cooldown_days' => $this->reviewCooldownDays, 'call_video_multiplier' => round((float) $this->videoMultiplier, 2), 'calls_public_stun' => $this->publicStun, 'call_hold_minutes' => $this->holdMinutes, 'call_increment_s' => $this->incrementS,
            'call_ring_s' => $this->ringS, 'call_heartbeat_s' => $this->heartbeatS, 'call_stale_s' => max($this->staleS, $this->heartbeatS * 3), 'call_attempts_per_min' => $this->attemptsPerMin] as $k => $v) {
            Setting::setValue('n2n.'.$k, $v);
        }
        Auditor::log('n2n.calls_settings_updated', Setting::class, null, ['enabled' => $this->enabled, 'dev_mode' => $this->devMode, 'by' => Auth::id()]);
        $this->saved = 'Call settings saved.';
    }

    public function render()
    {
        $ended = Call::query()->where('state', 'ended');
        $reported = (clone $ended)->whereNotNull('relay_used')->count();

        return view('livewire.admin.n2n.call-settings', [
            'status' => CallsAvailability::status(), 'reviewUrl' => \App\Services\N2N\Calls\CallReviewPrompt::reviewUrl(),
            'turn' => ['urls' => count(TurnCredentials::turnUrls()), 'secret' => TurnCredentials::secret() !== ''],
            'stats' => [
                'calls' => (clone $ended)->count(), 'minutes' => round((clone $ended)->sum('billed_seconds') / 60, 1), 'cents' => (int) (clone $ended)->sum('billed_cents'),
                'reported' => $reported, 'relay_share' => $reported > 0 ? round((clone $ended)->where('relay_used', true)->count() / $reported * 100, 1) : null,
                'missed' => Call::query()->where('state', 'missed')->count(),
            ],
        ]);
    }
}
