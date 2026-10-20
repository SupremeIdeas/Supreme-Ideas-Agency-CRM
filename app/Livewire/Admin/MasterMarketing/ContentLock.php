<?php

namespace App\Livewire\Admin\MasterMarketing;

use App\Support\Auditor;
use App\Support\MasterMarketing\ContentLock as Lock;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** MASTER ONLY. Admin > Content protection: switch the image and video lock on or off, part by part. */
#[Layout('components.layouts.admin')]
class ContentLock extends Component
{
    public bool $enabled = true;

    public bool $images = true;

    public bool $videos = true;

    public bool $keys = true;

    public bool $shield = false;

    public string $scope = 'marketing';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        foreach (Lock::settings() as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['scope' => 'in:marketing,all']);
        Lock::save(['enabled' => $this->enabled, 'images' => $this->images, 'videos' => $this->videos, 'keys' => $this->keys, 'shield' => $this->shield, 'scope' => $this->scope]);
        Auditor::log('content_lock.saved', null, null, ['enabled' => $this->enabled, 'scope' => $this->scope]);
        $this->dispatch('nx-toast', type: 'success', message: $this->enabled ? 'Content protection is on.' : 'Content protection is off.');
    }

    public function render()
    {
        return view('livewire.admin.master-marketing.content-lock');
    }
}
