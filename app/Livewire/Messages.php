<?php

namespace App\Livewire;

use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\WalletGroup;
use App\Models\ScheduledChannelMessage;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\InboxMedia;
use App\Services\Inbox\MessageDispatcher;
use App\Support\Business\BusinessNav;
use App\Support\Business\BusinessSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Messages: a three-panel workspace (owner direction, 2026-10-03; layout only, no third-party look). Left: conversations and a Contacts
 * tab so anyone in the address book is one tap from a new message. Centre: the thread. Right: who this is, with call / message / save
 * and a few facts. The panels are resizable by dragging (handled client-side, remembered per browser). On phones it is one pane at a
 * time with back navigation. Replies reuse the existing SendMessage modal: no second sending pipeline, so billing, provider routing
 * and idempotency are untouched.
 */
class Messages extends Component
{
    /** The active conversation's counterpart number (deep-linkable). */
    #[Url(as: 'to')]
    public ?string $active = null;

    /** The active conversation's channel (sms | whatsapp | telegram | instagram). Only sms exists unless the shared inbox is on. */
    #[Url(as: 'ch')]
    public string $activeChannel = 'sms';

    /** Channel filter on the chat list: all | sms | whatsapp | telegram | instagram. */
    public string $channel = 'all';

    /** Left panel tab: chats | contacts. */
    public string $tab = 'chats';

    /** Reply text for a non-SMS conversation (SMS replies keep using the send modal, with its own billing). */
    public string $reply = '';

    public ?string $replyError = null;

    public string $search = '';

    /** `?compose=1` (the Business shell's centre button): open the new-message composer on arrival. */
    #[Url(as: 'compose', except: false)]
    public bool $compose = false;

    public function mount(): void
    {
        if ($this->compose) {
            $this->compose = false;
            $this->dispatch('open-send-message', to: '', name: '');
        }
        if ($this->active) {
            $this->openThread($this->active, $this->activeChannel);
        }
    }

    /**
     * The workspace whose shared inbox this session is looking at, or null (= the person's own SMS inbox, exactly as before).
     * Only when the inbox module is live, a business workspace is active, and the person may use it.
     */
    private function workspace(): ?WalletGroup
    {
        return \App\Support\Business\InboxAccess::workspaceFor(Auth::user());
    }

    /** Every conversation this session may see: the workspace's when in a workspace, else the person's own (which never include workspace channel threads). */
    private function threads()
    {
        $ws = $this->workspace();

        return $ws
            ? MessageThread::query()->where('wallet_group_id', $ws->id)
            : MessageThread::query()->where('user_id', Auth::id())->whereNull('wallet_group_id');
    }

    public function openThread(string $counterpart, string $channel = 'sms'): void
    {
        $this->activeChannel = in_array($channel, ['sms', ...ChannelConnection::CHANNELS], true) ? $channel : 'sms';
        // Phone-number normalising only makes sense for SMS; a Telegram chat id or an Instagram id is kept as it came.
        $this->active = $this->activeChannel === 'sms' ? (Contact::normalizePhone($counterpart) ?: $counterpart) : $counterpart;
        $this->reply = '';
        $this->replyError = null;

        $this->threads()->where('channel', $this->activeChannel)->where('counterpart_number', $this->active)->first()?->markRead();
    }

    /** Reply on a WhatsApp / Telegram / Instagram conversation, routed by the thread's channel (MessageDispatcher). */
    public function sendChannelReply(MessageDispatcher $dispatcher): void
    {
        $this->replyError = null;
        $thread = $this->active ? $this->threads()->where('channel', $this->activeChannel)->where('counterpart_number', $this->active)->first() : null;
        if (! $thread || $thread->channel === 'sms') {
            return;
        }

        try {
            $dispatcher->sendReply($thread, $this->reply, Auth::user());
            $this->reset('reply');
        } catch (ChannelSendException $e) {
            $this->replyError = $e->getMessage();
        }
    }

    public ?string $brainNotice = null;

    /** NaaraBrain on the open conversation (Draft reply / Summarise / Suggest follow-ups). Only in a business workspace whose plan includes it. */
    public function brainAsk(string $action): void
    {
        $this->brainNotice = null;
        $ws = $this->workspace();
        if (! $ws || ! $this->active) {
            return;
        }
        try {
            app(\App\Services\Business\Brain\BusinessBrain::class)->request($ws, Auth::user(), $this->activeChannel, (string) $this->active, $action);
        } catch (\App\Services\Business\Brain\BusinessBrainException $e) {
            $this->brainNotice = $e->getMessage();
        }
    }

    /** Put a drafted reply into the reply box (a person still reviews and presses send). */
    public function useDraft(int $runId): void
    {
        $ws = $this->workspace();
        $run = $ws ? \App\Models\BusinessBrainRun::query()->where('wallet_group_id', $ws->id)->where('status', 'done')->where('action', 'draft')->find($runId) : null;
        if ($run && $this->activeChannel !== 'sms') {
            $this->reply = (string) $run->output;
        }
    }

    /** Cancel a scheduled reply that has not gone out yet, or dismiss one that failed (only in this workspace). */
    public function cancelScheduled(int $id): void
    {
        $ws = $this->workspace();
        $row = $ws ? ScheduledChannelMessage::query()->where('wallet_group_id', $ws->id)->find($id) : null;
        if (! $row) {
            return;
        }
        // claim by status so a send that is just starting cannot also be cancelled
        if (ScheduledChannelMessage::query()->whereKey($id)->whereIn('status', [ScheduledChannelMessage::PENDING, ScheduledChannelMessage::FAILED])->update(['status' => ScheduledChannelMessage::CANCELLED]) === 1) {
            app(InboxMedia::class)->forget((array) ($row->payload['files'] ?? []));
        }
    }

    /** Start (or resume) a conversation with someone from the Contacts tab. */
    public function startWith(string $phone): void
    {
        $this->openThread($phone);
        $this->tab = 'chats';
        $this->search = '';
    }

    public function closeThread(): void
    {
        $this->active = null;
        $this->activeChannel = 'sms';
    }

    /** A merged, chronological timeline for the active conversation. */
    private function timeline(): \Illuminate\Support\Collection
    {
        if (! $this->active) {
            return collect();
        }
        $ws = $this->workspace();
        $scope = fn ($q) => $ws ? $q->where('wallet_group_id', $ws->id) : $q->where('user_id', Auth::id())->whereNull('wallet_group_id');

        $in = $scope(InboundMessage::query())->where('channel', $this->activeChannel)->where('from_number', $this->active)
            ->get()->map(fn ($m) => (object) [
                'direction' => 'in', 'body' => $m->body, 'attachment_url' => $m->attachment_url, 'kind' => null, 'name' => null,
                'is_voicemail' => $m->voicemail_path !== null,
                'failed' => false,
                'at' => $m->received_at ?? $m->created_at,
            ]);
        $out = $scope(OutboundMessage::query())->where('channel', $this->activeChannel)->where('to_number', $this->active)
            ->get()->map(fn ($m) => (object) [
                'direction' => 'out', 'body' => $m->body,
                // composer files are stored privately: show them to the team through a short signed link
                'attachment_url' => $m->attachment_kind && $m->attachment_url ? InboxMedia::url($m->attachment_url, 60) : $m->attachment_url,
                'kind' => $m->attachment_kind, 'name' => $m->attachment_name,
                'is_voicemail' => false,
                'failed' => $m->status === 'failed',
                'at' => $m->created_at,
            ]);

        return $in->concat($out)->sortBy('at')->values();
    }

    public function render()
    {
        $uid = Auth::id();
        $contacts = Contact::where('user_id', $uid)->orderByDesc('is_favorite')->orderBy('name')->get();
        $names = $contacts->pluck('name', 'phone_number');
        $needle = mb_strtolower(trim($this->search));

        $inboxLive = BusinessSettings::module('inbox');
        $threads = $this->threads()->when($this->channel !== 'all', fn ($q) => $q->where('channel', $this->channel))->orderByDesc('last_at')->limit(100)->get();
        // WhatsApp ids are digits without the "+"; look those up in the address book the same way as an SMS number.
        foreach ($threads as $t) {
            if ($t->channel === 'whatsapp' && ! isset($names[$t->counterpart_number]) && isset($names['+'.$t->counterpart_number])) {
                $names[$t->counterpart_number] = $names['+'.$t->counterpart_number];
            }
        }
        if ($needle !== '') {
            $threads = $threads->filter(fn ($t) => str_contains(mb_strtolower($t->counterpart_number.' '.($names[$t->counterpart_number] ?? '').' '.$t->last_body), $needle))->values();
        }
        $shownContacts = $needle === '' ? $contacts : $contacts->filter(fn ($c) => str_contains(mb_strtolower($c->name.' '.$c->phone_number), $needle))->values();

        $timeline = $this->timeline();
        $info = null;
        if ($this->active) {
            $info = [
                'contact' => in_array($this->activeChannel, ['sms', 'whatsapp'], true) ? $contacts->firstWhere('phone_number', $this->activeChannel === 'sms' ? $this->active : '+'.$this->active) : null,
                'incoming' => $timeline->where('direction', 'in')->count(),
                'outgoing' => $timeline->where('direction', 'out')->count(),
                'first' => optional($timeline->first())->at,
                'last' => optional($timeline->last())->at,
            ];
        }

        $ws = $this->workspace();
        $inBusinessShell = $ws !== null && BusinessNav::wants();
        // Unread per channel for the channel strip (the workspace's, or the person's own SMS).
        $unreadByChannel = $this->threads()->selectRaw('channel, SUM(unread_count) as n')->groupBy('channel')->pluck('n', 'channel')->map(fn ($n) => (int) $n)->all();

        return view('livewire.messages', [
            'shell' => $inBusinessShell ? 'business' : 'customer',
            'workspaceName' => $ws?->name,
            'unreadByChannel' => $unreadByChannel,
            'threads' => $threads,
            'contacts' => $shownContacts,
            'names' => $names,
            'timeline' => $timeline,
            'info' => $info,
            'inboxLive' => $inboxLive,
            'channelTabs' => ['all' => 'All', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram'],
            'scheduled' => $ws && $this->active && $this->activeChannel !== 'sms'
                ? ScheduledChannelMessage::query()->where('wallet_group_id', $ws->id)
                    ->whereHas('thread', fn ($q) => $q->where('channel', $this->activeChannel)->where('counterpart_number', $this->active))
                    ->whereIn('status', [ScheduledChannelMessage::PENDING, ScheduledChannelMessage::SENDING, ScheduledChannelMessage::FAILED])
                    ->where('updated_at', '>', now()->subDays(2))->orderBy('send_at')->get()
                : collect(),
            'brain' => $this->brainPanel($ws),
            'activeThread' => $this->active ? $this->threads()->where('channel', $this->activeChannel)->where('counterpart_number', $this->active)->first() : null,
        ])->layout($inBusinessShell ? 'components.layouts.business' : 'components.layouts.customer');
    }

    /** @return array{status: array, runs: \Illuminate\Support\Collection, pending: bool}|null */
    private function brainPanel(?WalletGroup $ws): ?array
    {
        if (! $ws || ! $this->active) {
            return null;
        }
        $svc = app(\App\Services\Business\Brain\BusinessBrain::class);
        $status = $svc->status($ws);
        if (! $status['available']) {
            return null;
        }
        $runs = \App\Models\BusinessBrainRun::query()->where('wallet_group_id', $ws->id)->where('channel', $this->activeChannel)->where('counterpart', $this->active)
            ->latest('id')->limit(3)->get();

        return ['status' => $status, 'runs' => $runs, 'pending' => $runs->contains('status', 'pending')];
    }
}
