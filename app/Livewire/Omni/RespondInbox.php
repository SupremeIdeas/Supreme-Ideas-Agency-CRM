<?php

namespace App\Livewire\Omni;

use App\Models\ChannelConnection;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniAgentRun;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniNote;
use App\Models\Omni\OmniSnippet;
use App\Models\WalletGroup;
use App\Services\Omni\Outbox;
use App\Services\Omni\WindowClosedException;
use App\Support\Business\InboxAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Naara Respond inbox (MASTER-ONLY, blueprint Phase 1 inbox shell): the workspace's conversations on the omni core, with a thread, a composer that obeys the
 * 24-hour window, status and assignment. Every query goes through the active workspace, so a person can never open another workspace's thread by guessing an id.
 * Polls every few seconds (ADR-S5); the send button is disabled while a reply is in flight and a double click is one message (idempotency key).
 */
#[Layout('components.layouts.business')]
class RespondInbox extends Component
{
    public ?int $selected = null;

    public string $filter = 'open';

    public string $q = '';

    public string $draft = '';

    public string $sendKey = '';

    public string $scope = 'all';

    /** @var list<int> */
    public array $picked = [];

    public string $note = '';

    public string $snipTitle = '';

    public string $snipBody = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->ws();
        $this->sendKey = Str::random(24);
    }

    private function ws(): WalletGroup
    {
        $g = InboxAccess::workspaceFor(Auth::user());
        abort_unless($g !== null, 404);

        return $g;
    }

    private function conversation(int $id): OmniConversation
    {
        return OmniConversation::query()->forWorkspace((int) $this->ws()->id)->findOrFail($id);
    }

    public function select(int $id): void
    {
        $c = $this->conversation($id);
        $this->selected = $c->id;
        $this->error = null;
        $this->draft = '';
        $this->sendKey = Str::random(24);
        if ($c->unread > 0) {
            $c->forceFill(['unread' => 0])->save();
        }
    }

    public function back(): void
    {
        $this->selected = null;
    }

    public function setFilter(string $f): void
    {
        $this->filter = in_array($f, ['open', 'pending', 'resolved', 'all'], true) ? $f : 'open';
    }

    public function setScope(string $s): void
    {
        $this->scope = in_array($s, ['all', 'mine', 'unassigned'], true) ? $s : 'all';
    }

    /** Minutes a customer may wait for a first answer before the list marks the conversation overdue. */
    public const SLA_MINUTES = 15;

    /** Bulk actions touch only this workspace's conversations, whatever ids the browser sends. */
    private function pickedIds(): array
    {
        $ids = array_values(array_unique(array_map('intval', $this->picked)));

        return $ids === [] ? [] : OmniConversation::query()->forWorkspace((int) $this->ws()->id)->whereIn('id', array_slice($ids, 0, 100))->pluck('id')->all();
    }

    public function bulkSetStatus(string $status): void
    {
        abort_unless(in_array($status, ['open', 'pending', 'resolved'], true), 422);
        $ids = $this->pickedIds();
        if ($ids !== []) {
            OmniConversation::query()->forWorkspace((int) $this->ws()->id)->whereIn('id', $ids)->update(['status' => $status]);
        }
        $this->picked = [];
    }

    public function bulkAssignToMe(): void
    {
        $ids = $this->pickedIds();
        if ($ids !== []) {
            OmniConversation::query()->forWorkspace((int) $this->ws()->id)->whereIn('id', $ids)->update(['assignee_id' => Auth::id()]);
        }
        $this->picked = [];
    }

    public function setStatus(string $status): void
    {
        abort_unless($this->selected && in_array($status, ['open', 'pending', 'resolved'], true), 422);
        $this->conversation($this->selected)->forceFill(['status' => $status])->save();
    }

    public function assignToMe(): void
    {
        abort_unless($this->selected, 422);
        $this->conversation($this->selected)->forceFill(['assignee_id' => Auth::id()])->save();
    }

    /** A private note: stored for the team, never sent to the customer. */
    public function addNote(): void
    {
        abort_unless($this->selected, 422);
        $this->validate(['note' => 'required|string|max:2000']);
        $c = $this->conversation($this->selected);
        OmniNote::create(['workspace_id' => (int) $this->ws()->id, 'conversation_id' => $c->id, 'author_id' => Auth::id(), 'body' => trim($this->note)]);
        $this->note = '';
    }

    public function saveSnippet(): void
    {
        $this->validate(['snipTitle' => 'required|string|max:80', 'snipBody' => 'required|string|max:2000']);
        $ws = (int) $this->ws()->id;
        if (OmniSnippet::query()->forWorkspace($ws)->count() >= 50) {
            $this->error = 'You can keep up to 50 saved replies. Delete one first.';

            return;
        }
        OmniSnippet::create(['workspace_id' => $ws, 'title' => trim($this->snipTitle), 'body' => trim($this->snipBody)]);
        $this->reset(['snipTitle', 'snipBody']);
    }

    public function deleteSnippet(int $id): void
    {
        OmniSnippet::query()->forWorkspace((int) $this->ws()->id)->whereKey($id)->delete();
    }

    public function useSnippet(int $id): void
    {
        $s = OmniSnippet::query()->forWorkspace((int) $this->ws()->id)->findOrFail($id);
        $this->draft = trim($this->draft.' '.$s->body);
    }

    /** Puts the AI's suggested reply in the composer for a person to read, edit and send. */
    public function useSuggestion(int $runId): void
    {
        abort_unless($this->selected, 422);
        $run = OmniAgentRun::query()->forWorkspace((int) $this->ws()->id)->where('conversation_id', $this->selected)->where('outcome', 'suggested')->findOrFail($runId);
        $this->draft = (string) $run->reply;
    }

    /** Records whether this customer agreed to receive broadcasts (their own say-so, noted by the team). */
    public function toggleOptIn(): void
    {
        abort_unless($this->selected, 422);
        $c = $this->conversation($this->selected);
        $contact = OmniContact::query()->forWorkspace((int) $this->ws()->id)->findOrFail($c->contact_id);
        $on = ! $contact->marketing_opt_in || $contact->opted_out_at !== null;
        $contact->forceFill(['marketing_opt_in' => $on, 'opted_out_at' => $on ? null : now()])->save();
    }

    /** The approved WhatsApp template picked for a conversation whose 24-hour window is closed, as "name|language", and the values for {{1}}, {{2}}... */
    public string $tplKey = '';

    /** @var list<string> */
    public array $tplParams = [];

    public function updatedTplKey(): void
    {
        $this->tplParams = [];
        $this->error = null;
    }

    /** @return list<array<string,mixed>> approved templates of the open WhatsApp conversation's number, [] when none or Meta cannot be reached */
    private function approvedTemplates(OmniConversation $c): array
    {
        if ($c->channel !== ChannelConnection::WHATSAPP || ! $c->connection_id) {
            return [];
        }
        $conn = ChannelConnection::query()->where('wallet_group_id', $this->ws()->id)->find($c->connection_id);
        if (! $conn || \App\Services\Omni\Meta\WhatsAppTemplates::wabaId($conn) === null) {
            return [];
        }
        try {
            return app(\App\Services\Omni\Meta\WhatsAppTemplates::class)->approved($conn);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Re-open a closed WhatsApp conversation with an approved template. Marketing templates also need the person's opt-in; the idempotency key stops a double click sending twice. */
    public function sendTemplate(Outbox $outbox): void
    {
        $this->error = null;
        abort_unless($this->selected, 422);
        $c = $this->conversation($this->selected);
        $chosen = null;
        foreach ($this->approvedTemplates($c) as $t) {
            if ($t['name'].'|'.$t['language'] === $this->tplKey) {
                $chosen = $t;
            }
        }
        if (! $chosen) {
            $this->error = 'Pick one of your approved templates first.';

            return;
        }
        $params = array_values(array_map(fn ($p) => trim((string) $p), array_slice($this->tplParams, 0, $chosen['vars'])));
        $params = array_pad($params, $chosen['vars'], '');
        foreach ($params as $p) {
            if ($p === '' || mb_strlen($p) > 200) {
                $this->error = 'Fill in every value of the template (up to 200 characters each).';

                return;
            }
        }
        if ($chosen['category'] === 'MARKETING') {
            $contact = OmniContact::query()->forWorkspace((int) $this->ws()->id)->findOrFail($c->contact_id);
            if (! $contact->marketing_opt_in || $contact->opted_out_at !== null) {
                $this->error = 'This person has not agreed to marketing messages, so a marketing template cannot be sent. Use a utility template, or ask them to opt in.';

                return;
            }
        }
        $rendered = \App\Services\Omni\Meta\WhatsAppTemplates::render($chosen['body'], $params);
        $outbox->queueTemplate((int) $this->ws()->id, $c, $chosen['name'], $chosen['language'], $params, $rendered, Auth::id(), 'tpl-'.$this->sendKey);
        $this->reset('tplKey', 'tplParams');
        $this->sendKey = Str::random(24);
    }

    public function send(Outbox $outbox): void
    {
        $this->error = null;
        abort_unless($this->selected, 422);
        $this->validate(['draft' => 'required|string|max:2000']);
        $c = $this->conversation($this->selected);
        try {
            $outbox->queue((int) $this->ws()->id, $c, trim($this->draft), [], Auth::id(), $this->sendKey);
        } catch (WindowClosedException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->draft = '';
        $this->sendKey = Str::random(24);
    }

    public function render()
    {
        $ws = (int) $this->ws()->id;
        $base = OmniConversation::query()->forWorkspace($ws);
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $list = (clone $base)->with('contact:id,name,email,phone');
        if ($this->filter !== 'all') {
            $list->where('status', $this->filter);
        }
        if ($this->scope === 'mine') {
            $list->where('assignee_id', Auth::id());
        } elseif ($this->scope === 'unassigned') {
            $list->whereNull('assignee_id');
        }
        if (trim($this->q) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->q)).'%';
            $list->where(fn ($w) => $w->where('last_preview', 'like', $like)->orWhereIn('contact_id', \App\Models\Omni\OmniContact::query()->forWorkspace($ws)
                ->where(fn ($c) => $c->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))->select('id')));
        }
        $conversations = $list->orderByDesc('last_message_at')->limit(60)->get();

        $thread = null;
        $messages = collect();
        $notes = collect();
        if ($this->selected) {
            $thread = (clone $base)->with('contact')->find($this->selected);
            if ($thread) {
                $messages = OmniMessage::query()->forWorkspace($ws)->where('conversation_id', $thread->id)->orderByDesc('id')->limit(200)->get()->reverse()->values();
                $notes = OmniNote::query()->forWorkspace($ws)->where('conversation_id', $thread->id)->orderBy('id')->limit(100)->get();
                if ($thread->unread > 0) {
                    $thread->forceFill(['unread' => 0])->save();   // a poll while the thread is open keeps it read
                }
            } else {
                $this->selected = null;
            }
        }

        return view('livewire.omni.respond-inbox', [
            'conversations' => $conversations,
            'counts' => $counts,
            'thread' => $thread,
            'messages' => $messages,
            'notes' => $notes,
            'sourceTitles' => fn (?array $ids) => $ids ? \App\Models\Omni\OmniKbSource::query()->forWorkspace($ws)->whereIn('id', $ids)->pluck('title')->all() : [],
            'suggestion' => $thread ? OmniAgentRun::query()->forWorkspace($ws)->where('conversation_id', $thread->id)->where('outcome', 'suggested')->whereNotNull('reply')
                ->where('message_id', OmniMessage::query()->forWorkspace($ws)->where('conversation_id', $thread->id)->where('direction', 'in')->max('id'))->latest('id')->first() : null,
            'templates' => $thread && $thread->channel === ChannelConnection::WHATSAPP && ! $thread->windowOpen() ? $this->approvedTemplates($thread) : [],
            'overdueBefore' => now()->subMinutes(self::SLA_MINUTES),
            'snippets' => OmniSnippet::query()->forWorkspace($ws)->orderBy('title')->limit(50)->get(),
            'channelName' => fn (string $ch) => ChannelConnection::label($ch),
        ]);
    }
}
