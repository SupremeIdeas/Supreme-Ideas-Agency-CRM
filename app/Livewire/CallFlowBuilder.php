<?php

namespace App\Livewire;

use App\Jobs\SyncVoiceWebhookJob;
use App\Models\BusinessHoursSchedule;
use App\Models\CallFlow;
use App\Models\CallFlowNode;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Auditor;
use App\Support\Business\BusinessSettings;
use App\Support\Permissions\Permissions;
use App\Support\ProviderStatus;
use App\Support\Workspace;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Business Suite Prompt 14 §5 — the step-based builder an org owner/admin
 * uses to assemble an IVR: pick a Twilio line → define ring groups + opening
 * hours → build the menu/queue/forward/voicemail steps → review + go live.
 * Every id that arrives from the browser is re-resolved INSIDE the caller's
 * own workspace; nothing is trusted by id alone.
 */
#[Layout('components.layouts.business')]
class CallFlowBuilder extends Component
{
    public const PHONE_RULE = '/^\+[1-9]\d{6,14}$/';

    /** numbers | team | steps | review */
    public string $step = 'numbers';

    public ?int $flowId = null;

    public ?string $error = null;

    // Ring group form
    public string $rgName = '';

    public string $rgStrategy = RingGroup::SIMULTANEOUS;

    /** @var array<int,array{member:string,priority:string,number:string,browser:bool}> keyed by ring group id */
    public array $seatForm = [];

    // Business-hours form
    public string $bhName = 'Business hours';

    public string $bhTimezone = 'Africa/Lagos';

    /** @var array<string,array{on:bool,start:string,end:string}> */
    public array $bhDays = [];

    public string $bhClosedDates = '';

    // Node form
    public ?int $editingNodeId = null;

    public string $nodeType = CallFlowNode::MENU;

    public string $nodeLabel = '';

    /** @var array<string,mixed> */
    public array $nodeCfg = [];

    public function mount(): void
    {
        abort_unless(ProviderStatus::isActive('twilio'), 404);
        abort_unless($this->group() !== null, 404);

        foreach (BusinessHoursSchedule::DAYS as $d) {
            $this->bhDays[$d] = ['on' => in_array($d, ['mon', 'tue', 'wed', 'thu', 'fri'], true), 'start' => '09:00', 'end' => '17:00'];
        }
        $this->resetNodeForm();

        // Pre-fill the hours form from the saved schedule so editing is non-destructive.
        $saved = BusinessHoursSchedule::where('wallet_group_id', $this->group()->id)->first();
        if ($saved) {
            $this->bhName = $saved->name;
            $this->bhTimezone = $saved->timezone;
            foreach ($this->bhDays as $d => $row) {
                $this->bhDays[$d]['on'] = false;
            }
            foreach ((array) $saved->windows as $w) {
                if (isset($w['day'], $this->bhDays[$w['day']])) {
                    $this->bhDays[$w['day']] = ['on' => true, 'start' => $w['start'], 'end' => $w['end']];
                }
            }
            $this->bhClosedDates = collect((array) $saved->holiday_overrides)->where('closed', true)->pluck('date')->implode(', ');
        }
    }

    // ── workspace + authorization ───────────────────────────────────────────

    /** The business workspace this user may manage numbers in, or null. */
    private function group(): ?WalletGroup
    {
        $user = auth()->user();
        $active = Workspace::active();
        $candidates = collect([$active])->filter()
            ->concat($user->ownedWalletGroups()->where('kind', WalletGroup::KIND_BUSINESS)->get())
            ->concat(Workspace::switchableFor($user));

        return $candidates->first(fn (WalletGroup $g) => $g->isBusiness() && $this->may($g));
    }

    private function may(WalletGroup $g): bool
    {
        $user = auth()->user();

        return $g->owner_user_id === $user->id
            || app(WorkspaceRoleService::class)->hasPermission($g, $user, Permissions::MANAGE_NUMBERS);
    }

    private function guard(): WalletGroup
    {
        $g = $this->group();
        abort_unless($g !== null, 404);
        abort_unless(ProviderStatus::isActive('twilio'), 404);
        abort_unless(BusinessSettings::module('call_flows'), 404);

        return $g;
    }

    private function flow(WalletGroup $g): ?CallFlow
    {
        return $this->flowId ? CallFlow::where('wallet_group_id', $g->id)->find($this->flowId) : null;
    }

    public function goto(string $step): void
    {
        if (in_array($step, ['numbers', 'team', 'steps', 'review'], true) && ($step === 'numbers' || $this->flowId)) {
            $this->step = $step;
            $this->error = null;
        }
    }

    // ── step 1: numbers ─────────────────────────────────────────────────────

    /** Bring one of the caller's own Twilio lines into the workspace. */
    public function assignNumber(int $numberId): void
    {
        $g = $this->guard();
        $number = VirtualNumber::where('user_id', auth()->id())->whereNull('wallet_group_id')->where('status', 'active')->find($numberId);

        if (! $number || ! $number->supportsCallFlows()) {
            $this->error = 'That line can\'t be added to the workspace.';

            return;
        }

        $numberLimit = app(\App\Services\Business\BusinessAccess::class)->limit($g, 'numbers');
        if (VirtualNumber::where('wallet_group_id', $g->id)->count() >= $numberLimit) {
            $this->error = 'This workspace has reached its limit of '.$numberLimit.' numbers.';

            return;
        }

        $number->forceFill(['wallet_group_id' => $g->id])->save();
        Auditor::log('business.number_assigned', VirtualNumber::class, $number->id, ['wallet_group_id' => $g->id]);
    }

    public function selectNumber(int $numberId): void
    {
        $g = $this->guard();
        $number = VirtualNumber::where('wallet_group_id', $g->id)->find($numberId);

        if (! $number || ! $number->supportsCallFlows()) {
            $this->error = 'Call flows aren\'t available on that line.';

            return;
        }

        $flow = CallFlow::firstOrCreate(
            ['virtual_number_id' => $number->id],
            ['wallet_group_id' => $g->id, 'status' => CallFlow::PAUSED],
        );
        $this->flowId = $flow->id;
        $this->step = 'team';
        $this->error = null;
    }

    // ── step 2: ring groups + hours ─────────────────────────────────────────

    public function addRingGroup(): void
    {
        $g = $this->guard();
        $this->validate(['rgName' => 'required|string|max:80', 'rgStrategy' => 'required|in:simultaneous,sequential']);

        if (RingGroup::where('wallet_group_id', $g->id)->count() >= BusinessSettings::limit('ring_groups')) {
            $this->error = 'This workspace has reached its limit of '.BusinessSettings::limit('ring_groups').' ring groups.';

            return;
        }

        RingGroup::create(['wallet_group_id' => $g->id, 'name' => $this->rgName, 'strategy' => $this->rgStrategy]);
        $this->reset('rgName');
    }

    public function deleteRingGroup(int $id): void
    {
        $g = $this->guard();
        $group = RingGroup::where('wallet_group_id', $g->id)->find($id);
        if (! $group) {
            return;
        }
        $this->scrubNodes($g, 'ring_group_id', $id);
        $group->delete();
    }

    public function addSeat(int $ringGroupId): void
    {
        $g = $this->guard();
        $group = RingGroup::where('wallet_group_id', $g->id)->find($ringGroupId);
        $form = $this->seatForm[$ringGroupId] ?? [];
        $number = trim((string) ($form['number'] ?? ''));

        $member = $g->members()->where('status', '!=', 'removed')->whereNotNull('user_id')->find((int) ($form['member'] ?? 0));
        if (! $group || ! $member) {
            $this->error = 'Pick a seat from your workspace.';

            return;
        }
        if ($number !== '' && ! preg_match(self::PHONE_RULE, $number)) {
            $this->error = 'Enter the phone number in international format, e.g. +2348012345678.';

            return;
        }

        $isNew = ! RingGroupMember::where('ring_group_id', $group->id)->where('wallet_group_member_id', $member->id)->exists();
        if ($isNew && RingGroupMember::where('ring_group_id', $group->id)->count() >= BusinessSettings::limit('ring_group_size')) {
            $this->error = 'A ring group can hold at most '.BusinessSettings::limit('ring_group_size').' people.';

            return;
        }

        RingGroupMember::updateOrCreate(
            ['ring_group_id' => $group->id, 'wallet_group_member_id' => $member->id],
            [
                'priority' => max(0, min(999, (int) ($form['priority'] ?? 0))),
                'destination_number' => $number !== '' ? $number : null,
                'ring_in_browser' => (bool) ($form['browser'] ?? false),
            ],
        );
        unset($this->seatForm[$ringGroupId]);
        $this->error = null;
    }

    public function removeSeat(int $ringGroupMemberId): void
    {
        $g = $this->guard();
        RingGroupMember::whereKey($ringGroupMemberId)
            ->whereHas('ringGroup', fn ($q) => $q->where('wallet_group_id', $g->id))
            ->delete();
    }

    public function saveSchedule(): void
    {
        $g = $this->guard();
        $this->validate(['bhName' => 'required|string|max:80', 'bhTimezone' => 'required|timezone:all']);

        $windows = [];
        foreach ($this->bhDays as $day => $row) {
            if (empty($row['on']) || ! in_array($day, BusinessHoursSchedule::DAYS, true)) {
                continue;
            }
            if (! preg_match('/^\d{2}:\d{2}$/', (string) $row['start']) || ! preg_match('/^\d{2}:\d{2}$/', (string) $row['end'])) {
                $this->error = 'Use HH:MM times for opening hours.';

                return;
            }
            $windows[] = ['day' => $day, 'start' => $row['start'], 'end' => $row['end']];
        }

        $holidays = [];
        foreach (preg_split('/[\s,;]+/', trim($this->bhClosedDates), -1, PREG_SPLIT_NO_EMPTY) as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $this->error = "\"{$date}\" isn't a valid date — use YYYY-MM-DD.";

                return;
            }
            $holidays[] = ['date' => $date, 'closed' => true];
        }

        BusinessHoursSchedule::updateOrCreate(
            ['wallet_group_id' => $g->id],
            ['name' => $this->bhName, 'timezone' => $this->bhTimezone, 'windows' => $windows, 'holiday_overrides' => $holidays],
        );
        $this->error = null;
        $this->dispatch('nx-toast', type: 'success', message: 'Business hours saved.');
    }

    // ── step 3: flow steps ──────────────────────────────────────────────────

    private function resetNodeForm(): void
    {
        $this->editingNodeId = null;
        $this->nodeType = CallFlowNode::MENU;
        $this->nodeLabel = '';
        $this->nodeCfg = $this->blankCfg(CallFlowNode::MENU);
    }

    /** @return array<string,mixed> */
    private function blankCfg(string $type): array
    {
        return match ($type) {
            CallFlowNode::MENU => ['prompt_text' => '', 'digits' => [], 'invalid_retry_node_id' => '', 'timeout_seconds' => 5, 'business_hours_schedule_id' => '', 'closed_node_id' => ''],
            CallFlowNode::QUEUE => ['ring_group_id' => '', 'hold_music_url' => '', 'max_wait_seconds' => 60, 'overflow_node_id' => ''],
            CallFlowNode::FORWARD => ['target_number' => '', 'fallback_number' => ''],
            CallFlowNode::AI_AGENT => ['overflow_node_id' => ''],
            default => ['greeting_url' => '', 'transcribe' => true],
        };
    }

    public function updatedNodeType(string $type): void
    {
        if (in_array($type, CallFlowNode::TYPES, true)) {
            $this->nodeCfg = $this->blankCfg($type);
        }
    }

    public function editNode(int $id): void
    {
        $g = $this->guard();
        $node = $this->flow($g)?->nodes()->find($id);
        if (! $node) {
            return;
        }
        $this->editingNodeId = $node->id;
        $this->nodeType = $node->type;
        $this->nodeLabel = (string) $node->label;
        $this->nodeCfg = array_replace($this->blankCfg($node->type), (array) $node->config);
        $this->error = null;
    }

    public function cancelNode(): void
    {
        $this->resetNodeForm();
        $this->error = null;
    }

    public function saveNode(): void
    {
        $g = $this->guard();
        $flow = $this->flow($g);
        if (! $flow || ! in_array($this->nodeType, CallFlowNode::TYPES, true)) {
            return;
        }

        try {
            $config = $this->cleanConfig($g, $flow);
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();

            return;
        }

        $this->validate(['nodeLabel' => 'nullable|string|max:80']);

        if (! $this->editingNodeId && $flow->nodes()->count() >= BusinessSettings::limit('flow_nodes')) {
            $this->error = 'A call flow can have at most '.BusinessSettings::limit('flow_nodes').' steps.';

            return;
        }

        $node = $this->editingNodeId ? $flow->nodes()->find($this->editingNodeId) : new CallFlowNode(['call_flow_id' => $flow->id]);
        if (! $node) {
            return;
        }
        $node->fill(['type' => $this->nodeType, 'label' => $this->nodeLabel ?: null, 'config' => $config])->save();

        // First node ever becomes the starting step.
        if (! $flow->root_node_id) {
            $flow->update(['root_node_id' => $node->id]);
        }

        $this->resetNodeForm();
        $this->error = null;
    }

    /**
     * Validate + normalise the type-specific config. Every cross-reference
     * (child node, ring group, schedule) must resolve inside THIS flow /
     * workspace, otherwise the save is refused.
     *
     * @return array<string,mixed>
     */
    private function cleanConfig(WalletGroup $g, CallFlow $flow): array
    {
        $c = $this->nodeCfg;
        $fail = fn (string $msg) => throw ValidationException::withMessages(['nodeCfg' => $msg]);
        $ownNodeIds = $flow->nodes()->pluck('id')->map(fn ($i) => (int) $i)->all();
        $nodeRef = function ($v) use ($ownNodeIds, $fail) {
            if ($v === '' || $v === null) {
                return null;
            }

            return in_array((int) $v, $ownNodeIds, true) ? (int) $v : $fail('A step can only link to another step in this flow.');
        };
        $https = function ($v) use ($fail) {
            $v = trim((string) $v);

            return $v === '' ? '' : (preg_match('~^https://~i', $v) && filter_var($v, FILTER_VALIDATE_URL) ? $v : $fail('Audio links must be a full https:// URL.'));
        };
        $phone = function ($v, bool $required) use ($fail) {
            $v = trim((string) $v);
            if ($v === '') {
                return $required ? $fail('Enter the number in international format, e.g. +2348012345678.') : null;
            }

            return preg_match(self::PHONE_RULE, $v) ? $v : $fail('Enter the number in international format, e.g. +2348012345678.');
        };

        switch ($this->nodeType) {
            case CallFlowNode::MENU:
                $prompt = trim((string) ($c['prompt_text'] ?? ''));
                $prompt !== '' || $fail('Write what callers should hear.');
                mb_strlen($prompt) <= 500 || $fail('Keep the greeting under 500 characters.');

                $digits = [];
                foreach ((array) ($c['digits'] ?? []) as $digit => $target) {
                    if ($target === '' || $target === null) {
                        continue;
                    }
                    preg_match('/^[0-9*#]$/', (string) $digit) || $fail('Menu keys must be 0-9, * or #.');
                    $digits[(string) $digit] = $nodeRef($target);
                }

                $scheduleId = $c['business_hours_schedule_id'] ?? '';
                if ($scheduleId !== '' && $scheduleId !== null) {
                    BusinessHoursSchedule::where('wallet_group_id', $g->id)->whereKey((int) $scheduleId)->exists() || $fail('Choose one of your saved business-hours schedules.');
                }

                return [
                    'prompt_text' => $prompt,
                    'digits' => $digits,
                    'invalid_retry_node_id' => $nodeRef($c['invalid_retry_node_id'] ?? null),
                    'timeout_seconds' => max(2, min(30, (int) ($c['timeout_seconds'] ?? 5))),
                    'business_hours_schedule_id' => $scheduleId !== '' && $scheduleId !== null ? (int) $scheduleId : null,
                    'closed_node_id' => $nodeRef($c['closed_node_id'] ?? null),
                ];

            case CallFlowNode::QUEUE:
                RingGroup::where('wallet_group_id', $g->id)->whereKey((int) ($c['ring_group_id'] ?? 0))->exists() || $fail('Choose a ring group for this queue.');

                return [
                    'ring_group_id' => (int) $c['ring_group_id'],
                    'hold_music_url' => $https($c['hold_music_url'] ?? ''),
                    'max_wait_seconds' => max(5, min(120, (int) ($c['max_wait_seconds'] ?? 60))),
                    'overflow_node_id' => $nodeRef($c['overflow_node_id'] ?? null),
                ];

            case CallFlowNode::FORWARD:
                return [
                    'target_number' => $phone($c['target_number'] ?? '', true),
                    'fallback_number' => $phone($c['fallback_number'] ?? '', false),
                ];

            case CallFlowNode::AI_AGENT:
                BusinessSettings::module('ai_voice') || $fail('The AI voice agent is not available yet.');

                return ['overflow_node_id' => $nodeRef($c['overflow_node_id'] ?? null)];

            default:
                return ['greeting_url' => $https($c['greeting_url'] ?? ''), 'transcribe' => (bool) ($c['transcribe'] ?? true)];
        }
    }

    public function deleteNode(int $id): void
    {
        $g = $this->guard();
        $flow = $this->flow($g);
        $node = $flow?->nodes()->find($id);
        if (! $node) {
            return;
        }

        // Drop every reference to it so no step points at a node that's gone.
        foreach ($flow->nodes()->where('id', '!=', $id)->get() as $other) {
            $cfg = (array) $other->config;
            $changed = false;
            foreach (['invalid_retry_node_id', 'closed_node_id', 'overflow_node_id'] as $key) {
                if (isset($cfg[$key]) && (int) $cfg[$key] === $id) {
                    $cfg[$key] = null;
                    $changed = true;
                }
            }
            foreach ((array) ($cfg['digits'] ?? []) as $digit => $target) {
                if ((int) $target === $id) {
                    unset($cfg['digits'][$digit]);
                    $changed = true;
                }
            }
            if ($changed) {
                $other->update(['config' => $cfg]);
            }
        }

        if ((int) $flow->root_node_id === $id) {
            $flow->update(['root_node_id' => null, 'status' => CallFlow::PAUSED]);
        }
        $node->delete();
        $this->resetNodeForm();
    }

    public function makeRoot(int $id): void
    {
        $g = $this->guard();
        $flow = $this->flow($g);
        if ($flow && $flow->nodes()->whereKey($id)->exists()) {
            $flow->update(['root_node_id' => $id]);
        }
    }

    /** Remove a deleted ring group / schedule id from every node config in the workspace. */
    private function scrubNodes(WalletGroup $g, string $key, int $id): void
    {
        CallFlowNode::whereHas('callFlow', fn ($q) => $q->where('wallet_group_id', $g->id))->get()
            ->each(function (CallFlowNode $n) use ($key, $id) {
                $cfg = (array) $n->config;
                if (isset($cfg[$key]) && (int) $cfg[$key] === $id) {
                    $cfg[$key] = null;
                    $n->update(['config' => $cfg]);
                }
            });
    }

    // ── step 4: go live ─────────────────────────────────────────────────────

    public function activate(): void
    {
        $g = $this->guard();
        $flow = $this->flow($g);
        if (! $flow) {
            return;
        }
        if (! $flow->root_node_id) {
            $this->error = 'Add at least one step before going live.';

            return;
        }

        $number = $flow->virtualNumber;
        $flow->update(['status' => CallFlow::ACTIVE]);

        // Point the line's voice URL at our webhook — queued, never inline (rule 8).
        if ($number?->sid) {
            SyncVoiceWebhookJob::dispatch($number->sid, route('webhooks.twilio.voice'), attach: true);
        }
        Auditor::log('business.call_flow_activated', CallFlow::class, $flow->id, ['number' => $number?->phone_number]);
        $this->error = null;
        $this->dispatch('nx-toast', type: 'success', message: 'Call flow is live.');
    }

    public function pause(): void
    {
        $g = $this->guard();
        $flow = $this->flow($g);
        if ($flow) {
            $flow->update(['status' => CallFlow::PAUSED]);
            Auditor::log('business.call_flow_paused', CallFlow::class, $flow->id);
        }
    }

    public function render()
    {
        $g = $this->group();
        $flow = $g ? $this->flow($g) : null;

        $groupNumbers = $g ? VirtualNumber::where('wallet_group_id', $g->id)->orderBy('id')->get() : collect();

        return view('livewire.call-flow-builder', [
            'group' => $g,
            'flow' => $flow,
            'flows' => $g ? CallFlow::where('wallet_group_id', $g->id)->get()->keyBy('virtual_number_id') : collect(),
            'supported' => $groupNumbers->filter->supportsCallFlows(),
            'unsupportedCount' => $groupNumbers->reject->supportsCallFlows()->count(),
            'mine' => $g ? VirtualNumber::where('user_id', auth()->id())->whereNull('wallet_group_id')->where('provider', 'twilio')->where('status', 'active')->get() : collect(),
            'ringGroups' => $g ? RingGroup::where('wallet_group_id', $g->id)->with('members.member.user')->get() : collect(),
            'seats' => $g ? $g->members()->where('status', '!=', 'removed')->whereNotNull('user_id')->with('user')->get() : collect(),
            'schedule' => $g ? BusinessHoursSchedule::where('wallet_group_id', $g->id)->first() : null,
            'nodes' => $flow ? $flow->nodes()->orderBy('id')->get() : collect(),
            'nodeTypes' => array_values(array_filter(CallFlowNode::TYPES, fn ($t) => $t !== CallFlowNode::AI_AGENT || BusinessSettings::module('ai_voice') || $this->nodeType === $t)),
        ]);
    }
}
