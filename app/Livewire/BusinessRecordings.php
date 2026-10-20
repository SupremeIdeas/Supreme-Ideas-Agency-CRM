<?php

namespace App\Livewire;

use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Services\Business\CallAddonBilling;
use App\Services\Business\RecordingRetention;
use App\Support\Auditor;
use App\Support\Business\BusinessSettings;
use App\Support\Business\WorkspaceAdminAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Business Suite > Call recordings (MASTER-ONLY, Prompt 15): the workspace's recording choices and the recordings themselves. Owner and admins only.
 * Everything is off until a person here turns it on; every recorded call is announced; the settings below only narrow the platform rules.
 */
#[Layout('components.layouts.business')]
class BusinessRecordings extends Component
{
    public bool $recording = false;

    public bool $intelligence = false;

    public int $retention = 30;

    public string $sensitive = '';

    public bool $legalHold = false;

    /** @var array<int,bool> number id => recording on */
    public array $numbers = [];

    public ?int $open = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $g = $this->group();
        $s = CallRecordingSetting::forGroup($g);
        $this->recording = (bool) $s->recording_enabled;
        $this->intelligence = (bool) $s->intelligence_enabled;
        $this->retention = $s->effectiveRetentionDays();
        $this->sensitive = (string) $s->sensitive_numbers;
        $this->legalHold = $s->legal_hold_at !== null;
        foreach ($this->lines($g) as $n) {
            $this->numbers[$n->id] = (bool) $n->recording_enabled;
        }
    }

    private function group(): WalletGroup
    {
        $g = WorkspaceAdminAccess::groupFor(auth()->user());
        abort_unless($g !== null, 404);

        return $g;
    }

    /** @return \Illuminate\Support\Collection<int,VirtualNumber> */
    private function lines(WalletGroup $g)
    {
        return VirtualNumber::query()->where('wallet_group_id', $g->id)->where('status', 'active')->orderBy('id')->get()->filter(fn (VirtualNumber $n) => $n->supportsCallFlows());
    }

    /** The retention choices on offer, never above the platform ceiling. @return list<int> */
    public function retentionOptions(): array
    {
        $ceiling = BusinessSettings::recordingRetentionCeiling();
        $opts = array_values(array_filter([7, 14, 30, 60, 90, 180, 365, 730], fn (int $d) => $d <= $ceiling));

        return in_array($ceiling, $opts, true) ? $opts : [...$opts, $ceiling];
    }

    public function save(): void
    {
        $g = $this->group();
        $this->validate([
            'retention' => 'required|integer|min:1|max:'.BusinessSettings::recordingRetentionCeiling(),
            'sensitive' => ['nullable', 'string', 'max:1000', 'regex:/^\s*(\+[1-9]\d{6,14}\s*(,\s*\+[1-9]\d{6,14}\s*)*)?$/'],
        ], ['sensitive.regex' => 'Enter numbers in international format, separated by commas.']);

        $before = CallRecordingSetting::forGroup($g);
        $row = CallRecordingSetting::updateOrCreate(['wallet_group_id' => $g->id], [
            'recording_enabled' => $this->recording && BusinessSettings::module('recording'),
            'intelligence_enabled' => $this->recording && $this->intelligence && BusinessSettings::module('intelligence'),
            'retention_days' => $this->retention,
            'sensitive_numbers' => trim($this->sensitive) !== '' ? implode(', ', array_map('trim', explode(',', $this->sensitive))) : null,
            'legal_hold_at' => $this->legalHold ? ($before->legal_hold_at ?? now()) : null,
        ]);

        foreach ($this->lines($g) as $n) {
            $n->forceFill(['recording_enabled' => (bool) ($this->numbers[$n->id] ?? false)])->save();
        }

        Auditor::log('business.recording_settings_changed', CallRecordingSetting::class, $row->id, [
            'wallet_group_id' => $g->id, 'recording' => $row->recording_enabled, 'intelligence' => $row->intelligence_enabled,
            'retention_days' => $row->retention_days, 'legal_hold' => $row->legal_hold_at !== null,
            'numbers_on' => array_keys(array_filter($this->numbers)),
        ]);
        $this->notice = 'Saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Recording settings saved.');
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    public function delete(int $id, RecordingRetention $retention): void
    {
        $g = $this->group();
        $rec = CallRecording::where('wallet_group_id', $g->id)->find($id);
        if ($rec) {
            $retention->purge($rec, 'deleted_by_workspace');
            $this->dispatch('nx-toast', type: 'success', message: 'Recording deleted.');
        }
    }

    public function render(CallAddonBilling $billing)
    {
        $g = $this->group();

        return view('livewire.business-recordings', [
            'group' => $g,
            'lines' => $this->lines($g),
            'recordings' => CallRecording::query()->where('wallet_group_id', $g->id)->with(['transcript', 'intelligence', 'virtualNumber'])->latest('id')->limit(50)->get(),
            'recordingLive' => BusinessSettings::module('recording'),
            'intelligenceLive' => BusinessSettings::module('intelligence'),
            'prices' => [
                'record' => $billing->price(CallAddonBilling::RECORD, 60),
                'transcribe' => $billing->price(CallAddonBilling::TRANSCRIBE, 60),
                'intelligence' => $billing->price(CallAddonBilling::INTELLIGENCE, 60),
            ],
        ]);
    }
}
