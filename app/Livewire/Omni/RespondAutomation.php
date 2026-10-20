<?php

namespace App\Livewire\Omni;

use App\Models\ChannelConnection;
use App\Models\Omni\OmniAgentRun;
use App\Models\Omni\OmniAgentSetting;
use App\Models\Omni\OmniBroadcast;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniFlow;
use App\Models\Omni\OmniKbSource;
use App\Models\Omni\OmniWebhook;
use App\Models\WalletGroup;
use App\Services\Business\Brain\BusinessBrain;
use App\Services\Omni\Agent\AgentRunner;
use App\Services\Omni\Broadcast\BroadcastService;
use App\Services\Omni\Flows\FlowEngine;
use App\Services\Omni\Kb\KnowledgeBase;
use App\Services\Omni\OmniAnalytics;
use App\Services\Omni\Webhooks\OutboundWebhooks;
use App\Support\Business\WorkspaceAdminAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Naara Respond automation (MASTER-ONLY, blueprint sections 5 to 13): knowledge base, AI agent, flows, broadcasts, outbound webhooks, insights and channel health in
 * one place. Owner and admins of the active workspace only. Every read and write goes through the workspace; secrets are shown once.
 */
#[Layout('components.layouts.business')]
class RespondAutomation extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $tab = 'knowledge';

    public ?string $error = null;

    public ?string $ok = null;

    // knowledge
    public string $kbTitle = '';

    public string $kbText = '';

    public string $kbKind = 'faq';

    public string $kbUrl = '';

    // document upload and the test panel
    /** @var array<int,mixed> */
    public array $uploads = [];

    /** @var list<array{name:string,ok:bool,message:string}> one line per file of the last batch */
    public array $results = [];

    public string $uploadRole = 'facts';

    /** @var array<string,mixed>|null what the reader found in the last upload */
    public ?array $report = null;

    public string $ask = '';

    /** @var array<string,mixed>|null */
    public ?array $answer = null;

    // agent
    public string $mode = 'off';

    public string $tone = 'friendly';

    public string $summary = '';

    public int $cap = 200;

    public string $handoff = '';

    // flows
    public string $fName = '';

    public string $fTrigger = 'new_conversation';

    public string $fValue = '';

    /** @var list<array<string,mixed>> */
    public array $fSteps = [];

    public string $stepType = 'send_text';

    public string $stepValue = '';

    /** @var list<array<string,mixed>> */
    public array $trace = [];

    // broadcasts
    public string $bName = '';

    public ?int $bConn = null;

    public string $bBody = '';

    public string $bTag = '';

    public string $bStage = '';

    public string $bWhen = '';

    /** @var array<string,mixed>|null */
    public ?array $dry = null;

    // webhooks
    public string $whUrl = '';

    /** @var list<string> */
    public array $whEvents = ['message.received'];

    public ?string $newSecret = null;

    public int $days = 7;

    public function mount(): void
    {
        $g = $this->ws();
        $s = AgentRunner::settings((int) $g->id);
        $this->mode = (string) $s->mode;
        $this->tone = (string) ($s->tone ?: 'friendly');
        $this->summary = (string) $s->business_summary;
        $this->cap = (int) ($s->daily_cap ?: 200);
        $this->handoff = implode(', ', (array) ($s->handoff_words ?: AgentRunner::DEFAULT_HANDOFF));
    }

    private function ws(): WalletGroup
    {
        $g = WorkspaceAdminAccess::groupFor(Auth::user());
        abort_unless($g !== null, 404);

        return $g;
    }

    private function wsId(): int
    {
        return (int) $this->ws()->id;
    }

    public function setTab(string $t): void
    {
        $this->tab = in_array($t, ['knowledge', 'agent', 'flows', 'broadcasts', 'integrations', 'insights'], true) ? $t : 'knowledge';
        $this->error = $this->ok = null;
    }

    private function run(\Closure $fn, ?string $ok = null): bool
    {
        $this->error = $this->ok = null;
        try {
            $fn();
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return false;
        }
        $this->ok = $ok;

        return true;
    }

    // ---- knowledge ----
    public function addKbText(KnowledgeBase $kb): void
    {
        if ($this->run(fn () => $kb->addText($this->wsId(), $this->kbKind, $this->kbTitle, $this->kbText), 'Added to the knowledge base.')) {
            $this->reset(['kbTitle', 'kbText']);
        }
    }

    public function addKbUrl(KnowledgeBase $kb): void
    {
        if ($this->run(function () use ($kb) {
            $s = $kb->addUrl($this->wsId(), $this->kbUrl);
            if ($s->status === 'failed') {
                throw new \RuntimeException('We could not read that page: '.$s->error);
            }
        }, 'Page read and added.')) {
            $this->reset('kbUrl');
        }
    }

    public function refreshKb(int $id, KnowledgeBase $kb): void
    {
        $s = OmniKbSource::query()->forWorkspace($this->wsId())->findOrFail($id);
        $kb->refresh($s);
    }

    public function removeKb(int $id, KnowledgeBase $kb): void
    {
        $kb->remove($this->wsId(), $id);
    }

    public function uploadDocument(KnowledgeBase $kb): void
    {
        $this->error = $this->ok = $this->report = null;
        $this->results = [];
        $max = \App\Services\Omni\Kb\DocumentLimits::maxFiles();
        $mb = \App\Services\Omni\Kb\DocumentLimits::maxBytes() / 1048576;
        $this->validate([
            'uploads' => 'required|array|min:1|max:'.$max,
            'uploads.*' => 'file|max:'.($mb * 1024),
            'uploadRole' => 'in:facts,voice',
        ], [
            'uploads.required' => 'Choose at least one file first.',
            'uploads.min' => 'Choose at least one file first.',
            'uploads.max' => 'You can upload up to '.$max.' files at a time.',
            'uploads.*.max' => 'A file is larger than '.$mb.' MB. Split it into smaller files.',
        ]);
        $learned = 0;
        foreach ($this->uploads as $file) {
            $name = $file->getClientOriginalName();
            try {
                $src = $kb->addDocument($this->wsId(), $file->getRealPath(), $name, $this->uploadRole);
            } catch (\App\Services\Omni\Kb\DocumentRejected $e) {
                $this->results[] = ['name' => $name, 'ok' => false, 'message' => $e->getMessage()];

                continue;
            }
            $learned++;
            $this->results[] = ['name' => $name, 'ok' => true, 'message' => $src->role === 'voice' ? 'Learned as brand voice.' : ($src->check_total ? 'Learned. Learning check '.$src->check_passed.' of '.$src->check_total.'.' : 'Learned.')];
            $this->report = ['id' => $src->id, 'title' => $src->title, 'role' => $src->role, 'words' => $src->word_count, 'chunks' => $src->chunk_count, 'passed' => $src->check_passed, 'total' => $src->check_total,
                'facts' => (array) $src->facts, 'notes' => (array) $src->notes];
        }
        $this->reset('uploads');
        $total = count($this->results);
        if ($learned === 0) {
            $this->error = $total === 1 ? $this->results[0]['message'] : 'None of the '.$total.' files could be learned. See the reason for each one below.';
            $this->report = null;
        } elseif ($total === 1) {
            $this->ok = $this->report['role'] === 'voice' ? 'Brand voice learned. Replies will follow this writing style.' : 'Document learned. Check the report below, then test it.';
        } else {
            $this->ok = $learned.' of '.$total.' files learned.'.($learned < $total ? ' The others were refused, with the reason for each below.' : ' Open each one in Sources to see what it contributed, then test the assistant.');
        }
    }

    public function recheck(int $id, KnowledgeBase $kb): void
    {
        $s = OmniKbSource::query()->forWorkspace($this->wsId())->findOrFail($id);
        $kb->recallCheck($s);
    }

    public function askAssistant(AgentRunner $agent): void
    {
        $this->error = null;
        $this->answer = $agent->preview($this->wsId(), $this->ask);
    }

    public function tryQuestion(string $q, AgentRunner $agent): void
    {
        $this->ask = mb_substr($q, 0, 500);
        $this->askAssistant($agent);
    }

    // ---- agent ----
    public function saveAgent(): void
    {
        $ws = $this->wsId();
        $this->validate(['mode' => 'in:off,suggest,auto', 'tone' => 'in:friendly,formal,short', 'summary' => 'nullable|string|max:1000', 'cap' => 'integer|min:1|max:5000']);
        if ($this->mode !== 'off' && ! app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($this->ws(), 'brain_assist')) {
            $this->error = 'The AI assistant is not part of your current Naara Pro plan. Upgrade your plan to turn it on.';
            $this->ok = null;

            return;
        }
        if ($this->mode !== 'off' && ! OmniKbSource::query()->forWorkspace($ws)->where('status', 'ready')->exists()) {
            $this->error = 'Teach the assistant first: add at least one FAQ, text or web page in the Knowledge tab.';
            $this->ok = null;

            return;
        }
        $words = array_values(array_filter(array_map(fn ($w) => mb_substr(trim($w), 0, 40), explode(',', $this->handoff))));
        OmniAgentSetting::updateOrCreate(['workspace_id' => $ws], ['mode' => $this->mode, 'tone' => $this->tone, 'business_summary' => trim($this->summary) ?: null, 'daily_cap' => $this->cap, 'handoff_words' => array_slice($words, 0, 30)]);
        $this->error = null;
        $this->ok = $this->mode === 'off' ? 'The assistant is off.' : ($this->mode === 'suggest' ? 'The assistant will suggest replies for your team to approve.' : 'The assistant will answer by itself from your knowledge base.');
    }

    // ---- flows ----
    public function addStep(): void
    {
        $v = trim($this->stepValue);
        $step = match ($this->stepType) {
            'send_text', 'note' => ['type' => $this->stepType, 'text' => $v],
            'delay' => ['type' => 'delay', 'minutes' => (int) $v],
            'set_status' => ['type' => 'set_status', 'status' => $v],
            'add_tag' => ['type' => 'add_tag', 'tag' => $v],
            default => null,
        };
        if ($step && count($this->fSteps) < FlowEngine::MAX_STEPS) {
            $this->fSteps[] = $step;
            $this->stepValue = '';
        }
    }

    public function removeStep(int $i): void
    {
        unset($this->fSteps[$i]);
        $this->fSteps = array_values($this->fSteps);
    }

    public function saveFlow(FlowEngine $engine): void
    {
        $ws = $this->wsId();
        if (trim($this->fName) === '') {
            $this->error = 'Give the flow a name.';

            return;
        }
        $problems = $engine->validate($this->fTrigger, $this->fValue, $this->fSteps);
        if ($problems !== []) {
            $this->error = implode(' ', $problems);

            return;
        }
        if (OmniFlow::query()->forWorkspace($ws)->count() >= 30) {
            $this->error = 'You can keep up to 30 flows.';

            return;
        }
        OmniFlow::create(['workspace_id' => $ws, 'name' => mb_substr(trim($this->fName), 0, 120), 'trigger_type' => $this->fTrigger, 'trigger_value' => trim($this->fValue) ?: null, 'steps' => $this->fSteps, 'status' => 'draft']);
        $this->reset(['fName', 'fValue', 'fSteps', 'stepValue']);
        $this->error = null;
        $this->ok = 'Flow saved as a draft. Test it, then turn it on.';
    }

    public function installStarters(): void
    {
        $ws = $this->wsId();
        foreach (FlowEngine::starters() as $s) {
            if (! OmniFlow::query()->forWorkspace($ws)->where('name', $s['name'])->exists()) {
                OmniFlow::create(['workspace_id' => $ws, 'name' => $s['name'], 'trigger_type' => $s['trigger_type'], 'trigger_value' => $s['trigger_value'], 'steps' => $s['steps'], 'status' => 'draft']);
            }
        }
        $this->ok = 'Starter flows added as drafts.';
    }

    public function testFlow(int $id, FlowEngine $engine): void
    {
        $this->trace = $engine->test(OmniFlow::query()->forWorkspace($this->wsId())->findOrFail($id));
    }

    public function toggleFlow(int $id, FlowEngine $engine): void
    {
        $f = OmniFlow::query()->forWorkspace($this->wsId())->findOrFail($id);
        if ($f->status === 'live') {
            $f->forceFill(['status' => 'draft'])->save();

            return;
        }
        $problems = $engine->validate((string) $f->trigger_type, $f->trigger_value, (array) $f->steps);
        if ($problems !== []) {
            $this->error = implode(' ', $problems);

            return;
        }
        $f->forceFill(['status' => 'live', 'version' => $f->version + 1])->save();
        $this->error = null;
    }

    public function deleteFlow(int $id): void
    {
        OmniFlow::query()->forWorkspace($this->wsId())->whereKey($id)->delete();
    }

    // ---- broadcasts ----
    private function draftBroadcast(): OmniBroadcast
    {
        $ws = $this->wsId();
        abort_unless($this->bConn && ChannelConnection::query()->where('wallet_group_id', $ws)->whereKey($this->bConn)->exists(), 422);
        $segment = array_filter(['tag' => trim($this->bTag), 'stage' => trim($this->bStage)]);

        return new OmniBroadcast(['workspace_id' => $ws, 'name' => mb_substr(trim($this->bName) ?: 'Broadcast', 0, 120), 'connection_id' => $this->bConn, 'body' => mb_substr(trim($this->bBody), 0, 1000), 'segment' => $segment ?: null, 'status' => 'draft']);
    }

    public function previewBroadcast(BroadcastService $svc): void
    {
        $this->error = null;
        $this->dry = $this->bConn ? $svc->dryRun($this->draftBroadcast()) : null;
        if (! $this->bConn) {
            $this->error = 'Pick the channel this broadcast goes through.';
        }
    }

    public function sendBroadcast(BroadcastService $svc): void
    {
        $this->run(function () use ($svc) {
            if (! $this->bConn) {
                throw new \RuntimeException('Pick the channel this broadcast goes through.');
            }
            $b = $this->draftBroadcast();
            $b->save();
            $at = trim($this->bWhen) !== '' ? \Illuminate\Support\Carbon::parse($this->bWhen) : null;
            try {
                $svc->start($b, $at);
            } catch (\RuntimeException $e) {
                $b->delete();

                throw $e;
            }
            $this->reset(['bName', 'bBody', 'bTag', 'bStage', 'bWhen', 'dry']);
        }, 'Broadcast on its way.');
    }

    public function cancelBroadcast(int $id, BroadcastService $svc): void
    {
        $svc->cancel(OmniBroadcast::query()->forWorkspace($this->wsId())->findOrFail($id));
    }

    // ---- integrations ----
    public function addWebhook(OutboundWebhooks $hooks): void
    {
        $this->newSecret = null;
        $this->run(function () use ($hooks) {
            $h = $hooks->create($this->wsId(), $this->whUrl, $this->whEvents);
            $this->newSecret = $h->secret;
            $this->reset('whUrl');
        }, 'Webhook added. Copy the signing secret now.');
    }

    public function deleteWebhook(int $id): void
    {
        OmniWebhook::query()->forWorkspace($this->wsId())->whereKey($id)->delete();
    }

    // ---- insights ----
    public function setDays(int $d): void
    {
        $this->days = in_array($d, [1, 7, 30], true) ? $d : 7;
    }

    /** Questions found in the owner's own documents (lines ending in a question mark): one-click tests that prove the answers are learned. @return list<string> */
    private function suggestedQuestions(int $ws): array
    {
        $out = [];
        foreach (OmniKbSource::query()->forWorkspace($ws)->where('role', 'facts')->whereNotNull('facts')->orderByDesc('id')->limit(10)->get(['facts']) as $s) {
            foreach ((array) ($s->facts['questions'] ?? []) as $q) {
                $out[] = (string) $q;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 5);
    }

    public function render(OmniAnalytics $analytics)
    {
        $ws = $this->wsId();
        $t = $this->tab;

        return view('livewire.omni.respond-automation', [
            'sources' => $t === 'knowledge' || $t === 'agent' ? OmniKbSource::query()->forWorkspace($ws)->orderByDesc('id')->limit(100)->get(['id', 'kind', 'role', 'title', 'url', 'status', 'error', 'fetched_at', 'file_name', 'word_count', 'chunk_count', 'check_passed', 'check_total', 'checked_at', 'facts', 'notes']) : collect(),
            'suggested' => $t === 'knowledge' ? $this->suggestedQuestions($ws) : [],
            'limits' => \App\Services\Omni\Kb\DocumentLimits::all(),
            'runs' => $t === 'agent' ? OmniAgentRun::query()->forWorkspace($ws)->orderByDesc('id')->limit(15)->get() : collect(),
            'flows' => $t === 'flows' ? OmniFlow::query()->forWorkspace($ws)->orderBy('id')->get() : collect(),
            'connections' => in_array($t, ['broadcasts', 'insights'], true) ? ChannelConnection::query()->where('wallet_group_id', $ws)->orderBy('channel')->get(['id', 'channel', 'label', 'status']) : collect(),
            'broadcasts' => $t === 'broadcasts' ? OmniBroadcast::query()->forWorkspace($ws)->orderByDesc('id')->limit(20)->get() : collect(),
            'optedIn' => $t === 'broadcasts' ? OmniContact::query()->forWorkspace($ws)->where('marketing_opt_in', true)->whereNull('opted_out_at')->count() : 0,
            'webhooks' => $t === 'integrations' ? OmniWebhook::query()->forWorkspace($ws)->orderBy('id')->get() : collect(),
            'events' => OutboundWebhooks::EVENTS,
            'allowance' => $t === 'agent' ? app(BusinessBrain::class)->status($this->ws()) : null,
            'stats' => $t === 'insights' ? $analytics->summary($ws, $this->days) : null,
            'health' => $t === 'insights' ? $analytics->health($ws) : [],
            'flowTriggers' => ['new_conversation' => 'A customer writes for the first time', 'keyword' => 'A message contains a keyword', 'outside_hours' => 'A message arrives outside opening hours'],
        ]);
    }
}
