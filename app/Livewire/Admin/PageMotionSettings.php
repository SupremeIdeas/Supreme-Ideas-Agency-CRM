<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\PageMotion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Page transitions (MASTER-ONLY): the on/off switch, style and speed of the dashboard page transitions. */
#[Layout('components.layouts.admin')]
class PageMotionSettings extends Component
{
    public bool $enabled = true;

    public string $style = 'fluid';

    public string $speed = 'normal';

    public ?string $saved = null;

    public function mount(): void
    {
        $c = PageMotion::current();
        [$this->enabled, $this->style, $this->speed] = [$c['enabled'], $c['style'], $c['speed']];
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['style' => 'in:'.implode(',', array_keys(PageMotion::STYLES)), 'speed' => 'in:'.implode(',', array_keys(PageMotion::SPEEDS))]);
        PageMotion::save($this->enabled, $this->style, $this->speed);
        Auditor::log('platform.page_motion_saved', self::class, null, ['enabled' => $this->enabled, 'style' => $this->style, 'speed' => $this->speed, 'by' => Auth::id()]);
        $this->saved = $this->enabled ? 'Saved. Page transitions are on.' : 'Saved. Page transitions are off.';
    }

    public function render()
    {
        return view('livewire.admin.page-motion-settings', ['styles' => PageMotion::STYLES, 'speeds' => PageMotion::SPEEDS]);
    }
}
