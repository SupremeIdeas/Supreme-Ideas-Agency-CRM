<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\CallInterval;
use App\Models\N2N\GroupCall;
use App\Models\Setting;
use App\Services\N2N\Group\GroupAvailability;
use App\Services\N2N\Group\SfuClient;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > N2N > Group calls (MASTER-ONLY): the switch, the caps and the SFU health. Group calls need a VPS (a self-hosted LiveKit SFU); the
 * secret and URL live in the environment and are never shown. Nothing here is an assumption: the measured block reads real rooms.
 */
#[Layout('components.layouts.admin')]
class GroupCallSettings extends Component
{
    public bool $enabled = false;

    public int $maxSpeakers = 5;

    public int $maxCapacity = 20;

    public int $maxRooms = 5;

    public int $linkHours = 24;

    public int $idleMinutes = 3;

    public int $roomsPerDay = 10;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
        $this->enabled = (bool) Setting::getValue('n2n.group_calls_enabled', false);
        $this->maxSpeakers = N2NSettings::groupMaxSpeakers();
        $this->maxCapacity = N2NSettings::groupMaxCapacity();
        $this->maxRooms = N2NSettings::groupMaxRooms();
        $this->linkHours = N2NSettings::groupLinkHours();
        $this->idleMinutes = N2NSettings::groupIdleMinutes();
        $this->roomsPerDay = N2NSettings::groupRoomsPerHostPerDay();
    }

    public function save(): void
    {
        $this->validate([
            'maxSpeakers' => 'required|integer|min:1|max:5', 'maxCapacity' => 'required|integer|min:2|max:100', 'maxRooms' => 'required|integer|min:1|max:200',
            'linkHours' => 'required|integer|min:1|max:720', 'idleMinutes' => 'required|integer|min:1|max:60', 'roomsPerDay' => 'required|integer|min:1|max:100',
        ]);
        foreach (['group_calls_enabled' => $this->enabled, 'group_max_speakers' => $this->maxSpeakers, 'group_max_capacity' => $this->maxCapacity, 'group_max_rooms' => $this->maxRooms,
            'group_link_hours' => $this->linkHours, 'group_idle_minutes' => $this->idleMinutes, 'group_rooms_per_day' => $this->roomsPerDay] as $k => $v) {
            Setting::setValue('n2n.'.$k, $v);
        }
        Auditor::log('n2n.group_settings_updated', Setting::class, null, ['enabled' => $this->enabled, 'by' => Auth::id()]);
        $this->saved = 'Group call settings saved.';
    }

    public function recheck(): void
    {
        SfuClient::health(true);
    }

    public function render()
    {
        $closed = GroupCall::query()->where('state', GroupCall::CLOSED);

        return view('livewire.admin.n2n.group-call-settings', [
            'status' => GroupAvailability::status(),
            'sfu' => ['configured' => SfuClient::configured()],
            'stats' => [
                'open' => GroupCall::query()->where('state', GroupCall::OPEN)->count(), 'total' => GroupCall::query()->count(),
                'ended' => (clone $closed)->whereNotNull('ended_at')->count(),
                'cents' => (int) CallInterval::query()->sum('billed_cents'),
            ],
        ]);
    }
}
