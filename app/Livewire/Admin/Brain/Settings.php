<?php

namespace App\Livewire\Admin\Brain;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\Brain\BrainSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin > NaaraBrain (MASTER-ONLY): its own page. The master switch, the per-person daily ceiling, and the floating widget: on/off, which side, how high above
 * the bottom nav, and which sections show it. Cost numbers live on Admin > AI costs; this page links there.
 */
class Settings extends Component
{
    public bool $on = false;

    public int $daily = 30;

    /** Access by plan: free members and Naara Pro (a plan can set its own numbers in the plan editor). */
    public bool $freeAccess = true;

    public int $proDaily = 100;

    /** @var list<string> */
    public array $freeDepths = ['quick'];

    /** @var list<string> */
    public array $proDepths = ['quick', 'balanced', 'deep'];

    public bool $widget = true;

    public string $side = 'left';

    public int $offset = 96;

    public int $hours = 24;

    /** @var list<string> */
    public array $sections = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->on = BrainSettings::enabled();
        $this->daily = BrainSettings::int('daily_messages');
        $this->freeAccess = (bool) Setting::getValue('brain.free_access', config('brain.free_access'));
        $this->proDaily = BrainSettings::int('pro_daily');
        $this->freeDepths = BrainSettings::list('free_depths');
        $this->proDepths = BrainSettings::list('pro_depths');
        $this->widget = (bool) Setting::getValue('brain.widget_enabled', true);
        $this->side = BrainSettings::widgetSide();
        $this->offset = BrainSettings::widgetOffset();
        $this->hours = BrainSettings::dismissHours();
        $this->sections = BrainSettings::widgetSections();
    }

    public function save(): void
    {
        $this->validate([
            'daily' => 'required|integer|min:1|max:1000',
            'proDaily' => 'required|integer|min:1|max:100000',
            'freeDepths' => 'array|min:1',
            'proDepths' => 'array|min:1',
            'freeDepths.*' => 'in:quick,balanced,deep',
            'proDepths.*' => 'in:quick,balanced,deep',
            'side' => 'required|in:left,right',
            'offset' => 'required|integer|min:60|max:320',
            'hours' => 'required|integer|min:1|max:720',
            'sections' => 'array',
            'sections.*' => 'in:'.implode(',', array_keys(BrainSettings::SECTIONS)),
        ]);
        Setting::setValue(BrainSettings::FLAG, $this->on, 'brain');
        Setting::setValue('brain.daily_messages', $this->daily, 'brain');
        Setting::setValue('brain.free_access', $this->freeAccess, 'brain');
        Setting::setValue('brain.pro_daily', $this->proDaily, 'brain');
        Setting::setValue('brain.free_depths', array_values($this->freeDepths), 'brain');
        Setting::setValue('brain.pro_depths', array_values($this->proDepths), 'brain');
        Setting::setValue('brain.widget_enabled', $this->widget, 'brain');
        Setting::setValue('brain.widget_side', $this->side, 'brain');
        Setting::setValue('brain.widget_offset', $this->offset, 'brain');
        Setting::setValue('brain.widget_dismiss_hours', $this->hours, 'brain');
        Setting::setValue('brain.widget_sections', array_values(array_unique($this->sections)), 'brain');
        Auditor::log('brain.settings_updated', 'Setting', 0, ['enabled' => $this->on, 'widget' => $this->widget, 'side' => $this->side, 'offset' => $this->offset, 'sections' => $this->sections]);
        $this->saved = $this->on ? 'Saved. NaaraBrain is on.' : 'Saved. NaaraBrain is off.';
    }

    public function render()
    {
        return view('livewire.admin.brain.settings', ['sectionList' => BrainSettings::SECTIONS]);
    }
}
