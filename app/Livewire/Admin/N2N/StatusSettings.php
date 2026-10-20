<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\StatusPost;
use App\Models\Setting;
use App\Support\N2N\N2NStatusSettings as S;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Naara Chat > Status (N2N, MASTER-ONLY): switch Naara Chat status on or off and set the default daily limit. The brand-profile Highlights never depend on this switch. */
#[Layout('components.layouts.admin')]
class StatusSettings extends Component
{
    public string $mode = 'auto';

    public int $dailyLimit = 5;

    public bool $mentions = true;

    public bool $camera = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->mode = S::mode();
        $this->dailyLimit = S::dailyLimit();
        $this->mentions = S::mentionsEnabled();
        $this->camera = S::cameraEnabled();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['mode' => ['required', 'in:auto,on,off'], 'dailyLimit' => ['required', 'integer', 'min:1', 'max:100']]);
        Setting::setValue('n2n.status.mode', $this->mode, 'n2n');
        Setting::setValue('n2n.status.daily_limit', $this->dailyLimit, 'n2n');
        Setting::setValue('n2n.status.mentions', $this->mentions, 'n2n');
        Setting::setValue('n2n.status.camera', $this->camera, 'n2n');
        \App\Support\Auditor::log('n2n.status_settings_saved', null, null, ['mode' => $this->mode]);
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function render()
    {
        return view('livewire.admin.n2n.status-settings', [
            'cloud' => S::cloudConfigured(), 'effective' => S::enabled(), 'kinds' => S::allowedKinds(),
            'live' => StatusPost::query()->active()->count(), 'stuck' => StatusPost::query()->whereNotNull('purge_failed_at')->count(),
        ]);
    }
}
