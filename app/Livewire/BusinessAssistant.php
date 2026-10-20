<?php

namespace App\Livewire;

use App\Models\AiVoiceAgentConfig;
use App\Models\AiVoiceAgentSession;
use App\Models\BusinessHoursSchedule;
use App\Models\RingGroup;
use App\Models\WalletGroup;
use App\Services\Business\AiVoice\AgentBilling;
use App\Services\Business\AiVoice\AgentGreeting;
use App\Support\Auditor;
use App\Support\Business\BusinessSettings;
use App\Support\Business\WorkspaceAdminAccess;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Business Suite > AI voice assistant (MASTER-ONLY, Prompt 17): the workspace's agent settings and what it has handled. Owner and admins only. Off until switched
 * on; the caller always hears that this is an automated assistant. To put it on a number, add an "AI voice assistant" step in Call flows.
 */
#[Layout('components.layouts.business')]
class BusinessAssistant extends Component
{
    public bool $enabled = false;

    public string $agentName = 'Assistant';

    public string $persona = '';

    public string $greeting = '';

    public string $language = 'en-US';

    public ?int $ringGroupId = null;

    public ?int $scheduleId = null;

    public int $maxClarifications = 3;

    public ?string $notice = null;

    public function mount(): void
    {
        $c = AiVoiceAgentConfig::forGroup($this->group());
        $this->enabled = (bool) $c->enabled;
        $this->agentName = (string) ($c->agent_name ?: 'Assistant');
        $this->persona = (string) $c->persona;
        $this->greeting = (string) $c->greeting;
        $this->language = (string) ($c->language ?: 'en-US');
        $this->ringGroupId = $c->ring_group_id;
        $this->scheduleId = $c->business_hours_schedule_id;
        $this->maxClarifications = (int) ($c->max_clarifications ?: 3);
    }

    private function group(): WalletGroup
    {
        $g = WorkspaceAdminAccess::groupFor(auth()->user());
        abort_unless($g !== null, 404);

        return $g;
    }

    public function save(): void
    {
        $g = $this->group();
        $this->validate([
            'agentName' => 'required|string|max:60',
            'persona' => 'nullable|string|max:4000',
            'greeting' => 'nullable|string|max:250',
            'language' => ['required', Rule::in(array_keys(AiVoiceAgentConfig::LANGUAGES))],
            'ringGroupId' => ['nullable', 'integer', Rule::exists('ring_groups', 'id')->where('wallet_group_id', $g->id)],
            'scheduleId' => ['nullable', 'integer', Rule::exists('business_hours_schedules', 'id')->where('wallet_group_id', $g->id)],
            'maxClarifications' => 'required|integer|min:1|max:6',
        ]);

        $row = AiVoiceAgentConfig::updateOrCreate(['wallet_group_id' => $g->id], [
            'enabled' => $this->enabled && BusinessSettings::module('ai_voice'),
            'agent_name' => trim($this->agentName), 'persona' => trim($this->persona) ?: null, 'greeting' => trim($this->greeting) ?: null,
            'language' => $this->language, 'ring_group_id' => $this->ringGroupId ?: null, 'business_hours_schedule_id' => $this->scheduleId ?: null,
            'max_clarifications' => $this->maxClarifications,
        ]);
        Auditor::log('business.ai_agent_settings_changed', AiVoiceAgentConfig::class, $row->id, ['wallet_group_id' => $g->id, 'enabled' => $row->enabled]);
        $this->notice = 'Saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Assistant settings saved.');
    }

    public function render(AgentBilling $billing)
    {
        $g = $this->group();

        return view('livewire.business-assistant', [
            'group' => $g,
            'ready' => BusinessSettings::aiVoiceReady(),
            'languages' => AiVoiceAgentConfig::LANGUAGES,
            'ringGroups' => RingGroup::where('wallet_group_id', $g->id)->orderBy('name')->get(['id', 'name']),
            'schedules' => BusinessHoursSchedule::where('wallet_group_id', $g->id)->orderBy('name')->get(['id', 'name']),
            'previewGreeting' => AgentGreeting::for(new AiVoiceAgentConfig(['greeting' => $this->greeting]), (string) $g->name),
            'perMinute' => $billing->retailPerMinute(),
            'sessions' => AiVoiceAgentSession::where('wallet_group_id', $g->id)->latest('id')->limit(30)->get(),
        ]);
    }
}
