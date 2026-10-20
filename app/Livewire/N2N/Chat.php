<?php

namespace App\Livewire\N2N;

use App\Models\N2N\Attachment;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\MessageReceipt;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveThrottled;
use App\Services\N2N\Media\MediaPolicy;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\ChatLibrary;
use App\Services\N2N\Messaging\ChatThemes;
use App\Services\N2N\Messaging\ConversationPrefs;
use App\Services\N2N\Messaging\Inbox;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Naara chat (N2N, MASTER-ONLY): conversation list, thread, new-chat flow with the "With:" picker, requests, block and report. Sending goes
 * through the composer's HTTP endpoint (MessagingController); this component only reads, accepts, declines, blocks and reports. Every id is
 * resolved through MessageService::mine(), so a missing and a not-yours conversation are indistinguishable.
 */
#[Layout('components.layouts.n2n')]
class Chat extends Component
{
    #[Url(as: 'c')]
    public ?int $c = null;

    #[Url(as: 'f')]
    public string $filter = 'all';

    /** Chat-list order: newest | oldest | name | unread (pinned chats always lead). */
    public string $sort = 'newest';

    /** Deep link from a profile ("Message"): opens the new-chat box with the person already in the To: field. */
    #[Url(as: 'to', except: '')]
    public string $to = '';

    /** `?new=1` (the bar's New chat button) opens the new-chat box straight away. */
    #[Url(as: 'new', except: false)]
    public bool $new = false;

    public string $q = '';

    public bool $showNew = false;

    public string $newTo = '';

    // Long-press menu actions (message and chat row)
    public ?int $forwardId = null;

    /** @var list<int> */
    public array $forwardTo = [];

    public ?int $editId = null;

    public string $editBody = '';

    public ?int $infoId = null;

    public string $with = 'naara';

    /** The Conversation Router's answer for the typed target (channel, who pays, price, blockers). Null until a target is typed. */
    public ?array $route = null;

    /** The person's explicit "send as SMS from my wallet" choice for the current target. */
    public bool $viaSms = false;

    public ?array $newHit = null;

    public ?string $startedTo = null;

    public bool $reporting = false;

    public string $reportReason = 'spam';

    public string $reportNote = '';

    public ?string $notice = null;

    public ?string $error = null;

    // ---- Batch 12 (per-member conversation tools). Everything below is private to the member using it. ----

    /** The side sheet that is open over the thread: search | starred | media | theme | storage | null. */
    public ?string $panel = null;

    public string $chatSearch = '';

    public string $mediaTab = 'media';

    /** @var list<int> attachment ids ticked in the storage manager */
    public array $selected = [];

    /** The message the next send replies to (the composer posts it along with the text). */
    public ?int $replyTo = null;

    public function mount(): void
    {
        if ($this->c !== null && ! $this->openable($this->c)) {
            $this->c = null;
        }
        if ($this->new && $this->to === '' && $this->c === null) {
            $this->new = false;
            $this->openNew();
        }
        if ($this->to !== '' && $this->c === null) {
            $this->openNew();
            $this->newTo = mb_substr(trim($this->to), 0, 120);
            $this->to = '';
            $this->updatedNewTo(app(IdentityResolver::class));
        }
    }

    private function openable(int $id): bool
    {
        try {
            app(MessageService::class)->mine(Auth::user(), $id);
            // A locked chat opens only while the PIN session is open.
            $locked = \App\Models\N2N\Participant::query()->where('conversation_id', $id)->where('user_id', Auth::id())->whereNotNull('locked_at')->exists();

            return ! $locked || \App\Services\N2N\Messaging\ChatLock::unlocked();
        } catch (MessagingException) {
            return false;
        }
    }

    // ---- chat lock ------------------------------------------------------------------------------------------

    /** Which PIN sheet is open: set | unlock | change | remove (null = closed). */
    public ?string $lockSheet = null;

    public string $pin = '';

    public string $pin2 = '';

    public string $pinCurrent = '';

    /** The chat the member was trying to lock or unlock when the sheet opened (applied once the PIN step succeeds). */
    public ?int $pendingLock = null;

    public function openLockSheet(string $mode): void
    {
        $this->reset('pin', 'pin2', 'pinCurrent', 'error');
        $this->lockSheet = in_array($mode, ['set', 'unlock', 'change', 'remove'], true) ? $mode : null;
    }

    public function closeLockSheet(): void
    {
        $this->reset('lockSheet', 'pin', 'pin2', 'pinCurrent', 'pendingLock', 'error');
    }

    public function submitLock(): void
    {
        $user = Auth::user();
        $lock = app(\App\Services\N2N\Messaging\ChatLock::class);
        $this->error = null;
        try {
            match ($this->lockSheet) {
                'set' => $this->pin === $this->pin2 ? $lock->setPin($user, $this->pin) : throw new MessagingException('invalid', 'The two PINs do not match.'),
                'unlock' => $lock->unlock($user, $this->pin),
                'change' => $this->pin === $this->pin2 ? $lock->setPin($user, $this->pin, $this->pinCurrent) : throw new MessagingException('invalid', 'The two new PINs do not match.'),
                'remove' => $lock->removePin($user, $this->pinCurrent),
                default => null,
            };
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
            $this->reset('pin', 'pin2', 'pinCurrent');

            return;
        }
        $mode = $this->lockSheet;
        $pending = $this->pendingLock;
        $this->closeLockSheet();
        if ($mode === 'remove') {
            $this->filter = $this->filter === 'locked' ? 'all' : $this->filter;
            $this->notice = 'Chat lock is off. Your chats are unlocked.';

            return;
        }
        if ($pending !== null) {
            $this->rowLock($pending);
        } else {
            $this->notice = $mode === 'unlock' ? 'Locked chats are open for '.\App\Services\N2N\Messaging\ChatLock::minutes().' minutes.' : 'PIN saved.';
        }
    }

    /** Lock or unlock one chat (row menu or the open chat's menu). Locking needs a PIN, unlocking needs the PIN session. */
    public function rowLock(int $conversationId): void
    {
        $user = Auth::user();
        $lock = app(\App\Services\N2N\Messaging\ChatLock::class);
        $p = $this->prefs()->participant($user, $conversationId);
        $on = $p->locked_at === null;
        if (! \App\Services\N2N\Messaging\ChatLock::hasPin($user)) {
            $this->pendingLock = $conversationId;
            $this->openLockSheet('set');

            return;
        }
        if (! $on && ! \App\Services\N2N\Messaging\ChatLock::unlocked()) {
            $this->pendingLock = $conversationId;
            $this->openLockSheet('unlock');

            return;
        }
        $this->guard(function () use ($lock, $user, $conversationId, $on) {
            $lock->setChat($user, $conversationId, $on, $this->prefs());
            if ($on && $this->c === $conversationId) {
                $this->c = null;
            }
            $this->notice = $on ? 'Chat locked. It is hidden until you enter your PIN (Locked filter).' : 'Chat unlocked.';
        });
    }

    public function lockNow(): void
    {
        \App\Services\N2N\Messaging\ChatLock::relock();
        if ($this->filter === 'locked') {
            $this->filter = 'all';
        }
        $current = $this->c ? \App\Models\N2N\Participant::query()->where('conversation_id', $this->c)->where('user_id', Auth::id())->whereNotNull('locked_at')->exists() : false;
        if ($current) {
            $this->c = null;
        }
        $this->notice = 'Locked chats are hidden again.';
    }

    public function select(int $id, MessageService $svc): void
    {
        $this->reset('error', 'notice', 'reporting', 'showNew', 'startedTo', 'panel', 'chatSearch', 'selected', 'replyTo');
        if (! $this->openable($id)) {
            return;
        }
        $this->c = $id;
        $svc->markRead(Auth::user(), $id);
    }

    public function back(): void
    {
        $this->c = null;
    }

    public function setSort(string $s): void
    {
        $this->sort = array_key_exists($s, Inbox::SORTS) ? $s : 'newest';
    }

    /** Bulk actions on selected chats (the selection comes from the list). Each id is re-checked as the member's own; nothing here touches the other person. */
    public function bulkRows(array $ids, string $action): void
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0))), 0, 200);
        if ($ids === [] || ! in_array($action, ['read', 'unread', 'pin', 'unpin', 'mute', 'unmute', 'archive', 'unarchive', 'clear', 'delete'], true)) {
            return;
        }
        $user = Auth::user();
        $prefs = $this->prefs();
        $done = 0;
        $limit = null;
        foreach ($ids as $id) {
            try {
                match ($action) {
                    'read' => $this->markChatRead($user, $id),
                    'unread' => $prefs->markUnread($user, $id, true),
                    'pin' => $prefs->pinChat($user, $id, true),
                    'unpin' => $prefs->pinChat($user, $id, false),
                    'mute' => $prefs->mute($user, $id, '1w'),
                    'unmute' => $prefs->mute($user, $id, null),
                    'archive' => $prefs->archive($user, $id, true),
                    'unarchive' => $prefs->archive($user, $id, false),
                    'clear' => $prefs->clearChat($user, $id),
                    'delete' => $prefs->deleteChat($user, $id),
                };
                if ($this->c === $id && in_array($action, ['delete', 'archive'], true)) {
                    $this->c = null;
                }
                $done++;
            } catch (MessagingException $e) {
                $limit = $e->getMessage();
            }
        }
        $words = ['read' => 'marked as read', 'unread' => 'marked as unread', 'pin' => 'pinned', 'unpin' => 'unpinned', 'mute' => 'muted for a week', 'unmute' => 'unmuted', 'archive' => 'archived', 'unarchive' => 'moved back to your inbox', 'clear' => 'cleared for you', 'delete' => 'deleted for you'];
        $this->notice = $done.' '.($done === 1 ? 'chat' : 'chats').' '.$words[$action].(in_array($action, ['clear', 'delete'], true) ? '. The other people still have their copies.' : '.').($limit ? ' '.$limit : '');
    }

    private function markChatRead(\App\Models\User $user, int $id): void
    {
        app(MessageService::class)->markRead($user, $id);
        $this->prefs()->markUnread($user, $id, false);
    }

    /** Hide several messages from MY view of the open chat (Delete for me, in bulk). */
    public function bulkHideMessages(array $ids): void
    {
        $user = Auth::user();
        $done = 0;
        foreach (array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 200) as $id) {
            try {
                $m = \App\Models\N2N\Message::query()->whereKey($id)->where('conversation_id', (int) $this->c)->first();
                if ($m !== null) {
                    $this->prefs()->hideMessage($user, $m->id);
                    $done++;
                }
            } catch (MessagingException) {
                // already gone or not visible: skip
            }
        }
        $this->notice = $done.' '.($done === 1 ? 'message' : 'messages').' deleted for you. The other person still has them.';
    }

    public function bulkStarMessages(array $ids, bool $on = true): void
    {
        foreach (array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 200) as $id) {
            try {
                $this->prefs()->star(Auth::user(), $id, $on);
            } catch (MessagingException) {
                // skip
            }
        }
    }

    /** Delete for me every message the current in-chat search found. */
    public function deleteSearchForMe(): void
    {
        $user = Auth::user();
        $hits = $this->prefs()->search($user, (int) $this->c, $this->chatSearch, 100);
        $this->bulkHideMessages(array_column($hits, 'id'));
        $this->reset('panel', 'chatSearch');
    }

    public function setFilter(string $f): void
    {
        $this->reset('panel');
        $this->filter = in_array($f, Inbox::FILTERS, true) ? $f : 'all';
        $this->c = null;
        if ($this->filter === 'locked' && ! \App\Services\N2N\Messaging\ChatLock::unlocked()) {
            $this->openLockSheet('unlock');   // the Locked folder asks for the PIN straight away
        }
    }

    #[On('n2n-refresh')]
    public function refreshFromRealtime(MessageService $svc): void
    {
        if ($this->c !== null && $this->openable($this->c)) {
            $svc->markRead(Auth::user(), $this->c);   // a message that arrives while the thread is open is read
        }
    }

    // ---- new chat -----------------------------------------------------------------------------------------

    public function openNew(): void
    {
        $this->reset('newTo', 'newHit', 'startedTo', 'error', 'route', 'viaSms');
        $this->showNew = true;
        $this->c = null;
    }

    /** Live, debounced validation of the To: field. Shows a name only, never an email or number. */
    public function updatedNewTo(IdentityResolver $resolver): void
    {
        $this->newHit = null;
        $this->route = null;
        $this->viaSms = false;
        if (strlen(trim($this->newTo)) < 3) {
            return;
        }
        try {
            $hit = $resolver->resolve($this->newTo, 'search', Auth::user(), request()->ip());
        } catch (ResolveThrottled $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->newHit = $hit->found ? ['name' => $hit->name, 'username' => $hit->username, 'naara_id' => $hit->naaraId] : ['name' => null];
        $this->planRoute();
    }

    /** The person picks "send as SMS" (wallet) or goes back to Naara chat (credit); the router re-prices. Never automatic. */
    public function chooseSms(bool $on): void
    {
        $this->viaSms = $on;
        $this->planRoute();
    }

    /** Ask the one decision point which channel this travels on and who pays. The router decides only; nothing is sent or charged here. */
    private function planRoute(): void
    {
        $target = trim($this->newTo);
        $this->route = null;
        if ($target === '' || str_contains($target, '@') && ! str_starts_with($target, '@')) {
            return;   // an email address is a Naara lookup only; there is no email channel
        }
        $plan = app(\App\Services\Messaging\ConversationRouter::class)->resolve(Auth::user(), $target, 'message', ['via_sms' => $this->viaSms, 'ip' => request()->ip()]);
        $this->route = $plan->toArray() + ['texts' => array_map(fn ($b) => \App\Services\Messaging\ConversationRouter::BLOCKER_TEXT[$b] ?? $b, $plan->blockers)];
    }

    public function startChat(): void
    {
        $this->error = null;
        if ($this->route !== null && ($this->route['channel'] !== 'n2n' || ! $this->route['allowed'])) {
            $this->error = $this->route['texts'][0] ?? 'This cannot be sent as a Naara chat.';   // SMS goes through the Naara Line pages, never from here

            return;
        }
        if (! ($this->newHit['name'] ?? null) && ! ($this->newHit['naara_id'] ?? null)) {
            $this->error = 'We could not find that person on Naara. You can invite them instead.';

            return;
        }
        $this->startedTo = (string) ($this->newHit['naara_id'] ?? $this->newTo);
        $this->showNew = false;
    }

    /** Called by the page after the composer reports a first message sent: find the new conversation and open it. */
    public function afterStart(IdentityResolver $resolver): void
    {
        if ($this->startedTo === null) {
            return;
        }
        $hit = $resolver->resolve($this->startedTo, 'search', Auth::user(), request()->ip());
        if ($hit->found) {
            $conv = Conversation::query()->where('pair_key', Conversation::pairKey(Auth::id(), (int) $hit->userId))->first();
            if ($conv !== null) {
                $this->c = $conv->id;
                $this->startedTo = null;
            }
        }
    }

    // ---- conversation actions -----------------------------------------------------------------------------

    private function act(callable $fn, string $ok): void
    {
        $this->error = $this->notice = null;
        try {
            $fn();
            $this->notice = $ok;
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function accept(MessageService $svc): void
    {
        $this->act(fn () => $svc->accept(Auth::user(), (int) $this->c), 'Request accepted.');
    }

    public function decline(MessageService $svc): void
    {
        $this->act(fn () => $svc->decline(Auth::user(), (int) $this->c), 'Request declined.');
        $this->c = null;
    }

    public function block(MessageService $svc): void
    {
        $this->act(fn () => $svc->block(Auth::user(), (int) $this->c), 'Blocked. They are not told.');
        $this->c = null;
    }

    public function deleteMessage(int $id, MessageService $svc): void
    {
        $this->act(fn () => $svc->deleteForEveryone(Auth::user(), $id), 'Message deleted for everyone.');
    }

    // ---- Batch 12: mute / archive / pin, stars, pinned message, search, theme, library ---------------------

    private function prefs(): ConversationPrefs
    {
        return app(ConversationPrefs::class);
    }

    public function openPanel(string $panel): void
    {
        $this->panel = in_array($panel, ['search', 'starred', 'media', 'theme', 'storage', 'scheduled', 'disappear', 'event', 'agenda', 'followup'], true) ? $panel : null;
        $this->reset('chatSearch', 'selected', 'error', 'notice');
        if ($panel === 'event') {
            $this->evForm = $this->blankEvent();
        }
        $this->mediaTab = 'media';
    }

    /** Set this chat's disappearing-messages timer (either member can). Applies to new messages only. */
    public function setDisappear(int $seconds): void
    {
        $this->guard(function () use ($seconds) {
            $c = app(\App\Services\N2N\Messaging\Disappearing::class)->set(Auth::user(), (int) $this->c, $seconds);
            $this->notice = $c->disappear_after ? 'Disappearing messages are on: new messages disappear after '.\App\Services\N2N\Messaging\Disappearing::label($c->disappear_after).'.' : 'Disappearing messages are off.';
        });
        $this->panel = null;
    }

    /** Cancel one of the member's own pending scheduled messages. */
    public function cancelScheduled(int $id): void
    {
        app(\App\Services\N2N\Messaging\ScheduledSend::class)->cancel(Auth::user(), $id);
    }

    /** Dismiss a failed scheduled message from the list (it is only ever shown to its sender). */
    public function dismissScheduled(int $id): void
    {
        \App\Models\N2N\ScheduledMessage::query()->whereKey($id)->where('sender_id', Auth::id())->where('status', 'failed')->update(['status' => 'cancelled']);
    }

    // ---- long-press menu: messages and chat rows --------------------------------------------------------

    private function actions(): \App\Services\N2N\Messaging\MessageActions
    {
        return app(\App\Services\N2N\Messaging\MessageActions::class);
    }

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function react(int $messageId, ?string $code): void
    {
        $this->guard(fn () => $this->actions()->react(Auth::user(), $messageId, $code));
    }

    /** Save the member's personal quick-reaction bar (closed catalog, max 8). An empty list or `null` restores the defaults. */
    public function saveReactionBar(?array $codes = null): void
    {
        $this->guard(function () use ($codes) {
            $this->actions()->saveQuickBar(Auth::user(), $codes === null || $codes === [] ? \App\Services\N2N\Messaging\MessageActions::DEFAULT_BAR : array_map('strval', $codes));
        });
    }

    public function deleteForMe(int $messageId): void
    {
        $this->guard(fn () => $this->prefs()->hideMessage(Auth::user(), $messageId));
    }

    /** Clear a chat for me only: the other person keeps everything. */
    public function rowClear(int $conversationId): void
    {
        $this->guard(function () use ($conversationId) {
            $this->prefs()->clearChat(Auth::user(), $conversationId);
            $this->notice = 'Chat cleared for you. The other person still has their copy.';
        });
    }

    /** Delete a chat for me only: gone from my list until a newer message arrives. */
    public function rowDeleteChat(int $conversationId): void
    {
        $this->guard(function () use ($conversationId) {
            $this->prefs()->deleteChat(Auth::user(), $conversationId);
            if ($this->c === $conversationId) {
                $this->c = null;
            }
            $this->notice = 'Chat deleted for you. The other person still has their copy.';
        });
    }

    public function vote(int $pollId, int $optionId): void
    {
        $this->guard(fn () => app(\App\Services\N2N\Messaging\Polls::class)->toggle(Auth::user(), $pollId, $optionId));
    }

    public function closePoll(int $pollId): void
    {
        $this->guard(fn () => app(\App\Services\N2N\Messaging\Polls::class)->close(Auth::user(), $pollId));
    }

    public function openForward(int $messageId): void
    {
        $this->guard(function () use ($messageId) {
            $m = $this->prefs()->visibleMessage(Auth::user(), $messageId);
            if (trim((string) $m->body) === '' || $m->type !== 'text' || Attachment::query()->where('message_id', $m->id)->exists()) {
                throw new MessagingException('invalid', 'Only text messages can be forwarded.');
            }
            $this->forwardId = $messageId;
            $this->forwardTo = [];
            $this->panel = 'forward';
            $this->reset('error', 'notice');
        });
    }

    public function toggleForwardTo(int $conversationId): void
    {
        if (in_array($conversationId, $this->forwardTo, true)) {
            $this->forwardTo = array_values(array_diff($this->forwardTo, [$conversationId]));
        } elseif (count($this->forwardTo) < \App\Services\N2N\Messaging\MessageActions::forwardMaxTargets()) {
            $this->forwardTo[] = $conversationId;
        }
    }

    public function sendForward(): void
    {
        if ($this->forwardId === null) {
            return;
        }
        $this->guard(function () {
            $r = $this->actions()->forward(Auth::user(), $this->forwardId, $this->forwardTo, (string) $this->getId());
            $this->notice = $r['credit'] ? 'Not enough credit to forward to everyone. '.$r['sent'].' sent.' : ($r['failed'] > 0 ? $r['sent'].' sent, '.$r['failed'].' could not be delivered.' : 'Forwarded to '.$r['sent'].' chat'.($r['sent'] === 1 ? '' : 's').'.');
            $this->reset('forwardId', 'forwardTo', 'panel');
        });
    }

    public function startEdit(int $messageId): void
    {
        $this->guard(function () use ($messageId) {
            $m = $this->prefs()->visibleMessage(Auth::user(), $messageId);
            if ((int) $m->sender_id !== Auth::id() || $m->type !== 'text') {
                throw new MessagingException('invalid', 'You can only edit your own text messages.');
            }
            $this->editId = $m->id;
            $this->editBody = (string) $m->body;
            $this->panel = 'edit';
            $this->reset('error', 'notice');
        });
    }

    public function saveEdit(): void
    {
        if ($this->editId === null) {
            return;
        }
        $this->guard(function () {
            $this->actions()->edit(Auth::user(), $this->editId, $this->editBody);
            $this->reset('editId', 'editBody', 'panel');
        });
    }

    public function openInfo(int $messageId): void
    {
        $this->guard(function () use ($messageId) {
            $this->actions()->info(Auth::user(), $messageId);
            $this->infoId = $messageId;
            $this->panel = 'info';
        });
    }

    /** Chat-row actions from the long-press menu. They act on any chat the member is in (ConversationPrefs refuses the rest), not only the open one. */
    public function rowPin(int $conversationId): void
    {
        $this->guard(function () use ($conversationId) {
            $p = $this->prefs()->participant(Auth::user(), $conversationId);
            $this->prefs()->pinChat(Auth::user(), $conversationId, $p->pinned_at === null);
        });
    }

    public function rowMute(int $conversationId, ?string $length = null): void
    {
        $this->guard(fn () => $this->prefs()->mute(Auth::user(), $conversationId, $length));
    }

    public function rowArchive(int $conversationId): void
    {
        $this->guard(function () use ($conversationId) {
            $p = $this->prefs()->participant(Auth::user(), $conversationId);
            $this->prefs()->archive(Auth::user(), $conversationId, $p->archived_at === null);
            if ($this->c === $conversationId && $p->archived_at === null) {
                $this->c = null;
            }
        });
    }

    public function rowUnread(int $conversationId): void
    {
        $this->guard(function () use ($conversationId) {
            $p = $this->prefs()->participant(Auth::user(), $conversationId);
            $this->prefs()->markUnread(Auth::user(), $conversationId, $p->marked_unread_at === null);
        });
    }

    public function rowBlock(int $conversationId, MessageService $svc): void
    {
        $this->guard(function () use ($conversationId, $svc) {
            $svc->block(Auth::user(), $conversationId);
            if ($this->c === $conversationId) {
                $this->c = null;
            }
        });
    }


    // ---- Naara Planner (B13): the event form (create or edit) lives in a side panel; cards in the thread carry the answers. ----

    /** @var array{id: ?int, title: string, description: string, date: string, time: string, tz: string, duration: int, reminder: int} */
    public array $evForm = ['id' => null, 'title' => '', 'description' => '', 'date' => '', 'time' => '', 'tz' => '', 'duration' => 60, 'reminder' => 15];

    private function blankEvent(): array
    {
        return ['id' => null, 'title' => '', 'description' => '', 'date' => '', 'time' => '', 'tz' => '', 'duration' => 60, 'reminder' => 15];
    }

    public function editEvent(int $eventId): void
    {
        $this->guard(function () use ($eventId) {
            $e = app(\App\Services\N2N\Messaging\Planner::class)->visible(Auth::user(), $eventId);
            if ((int) $e->creator_id !== Auth::id()) {
                throw new MessagingException('not_participant', 'Only the person who planned this event can change it.');
            }
            $local = $e->starts_at->copy()->setTimezone($e->timezone);
            $dur = $e->ends_at ? (int) $e->starts_at->diffInMinutes($e->ends_at) : 0;
            $this->evForm = ['id' => $e->id, 'title' => $e->title, 'description' => (string) $e->description, 'date' => $local->format('Y-m-d'), 'time' => $local->format('H:i'), 'tz' => $e->timezone,
                'duration' => in_array($dur, \App\Services\N2N\Messaging\Planner::DURATIONS, true) ? $dur : 60, 'reminder' => (int) $e->reminder_minutes];
            $this->panel = 'event';
            $this->reset('error', 'notice');
        });
    }

    /** Create the event (as a card message in this chat) or save an edit. Creating costs one message; editing is free. */
    public function saveEvent(): void
    {
        $this->reset('error', 'notice');
        $f = $this->evForm;
        try {
            if ($f['id']) {
                app(\App\Services\N2N\Messaging\Planner::class)->edit(Auth::user(), (int) $f['id'], $f);
                $this->notice = 'Event updated.';
            } else {
                app(\App\Services\N2N\Messaging\MessageService::class)->send(Auth::user(), ['conversation_id' => (int) $this->c], 'ev-'.\Illuminate\Support\Str::random(24), '', request()->ip(), [], null, null, null, null, $f);
                $this->notice = 'Event planned. It is in the chat for both of you.';
            }
            $this->evForm = $this->blankEvent();
            $this->panel = null;
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } catch (\App\Services\N2N\Credit\InsufficientUsageCredit) {
            $this->error = 'You need more NaaraCredit to plan an event (it is sent as a message).';
        }
    }

    /** "Remind me to follow up" on this chat: a private reminder (the other person is never told). $iso is the picked moment in UTC. */
    public function addFollowup(string $title, string $iso): void
    {
        $this->reset('error', 'notice');
        try {
            $due = \Illuminate\Support\Carbon::parse($iso)->utc();
            app(\App\Services\N2N\Messaging\Followups::class)->create(Auth::user(), $title, $due, (int) $this->c);
            $this->notice = 'Reminder saved. Only you can see it.';
            $this->panel = null;
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable) {
            $this->error = 'Pick a reminder time from one minute to one year from now.';
        }
    }

    public function answerEvent(int $eventId, string $status): void
    {
        $this->guard(fn () => app(\App\Services\N2N\Messaging\Planner::class)->rsvp(Auth::user(), $eventId, $status));
    }

    public function cancelEvent(int $eventId): void
    {
        $this->guard(function () use ($eventId) {
            app(\App\Services\N2N\Messaging\Planner::class)->cancel(Auth::user(), $eventId);
            $this->notice = 'Event cancelled. The other person sees it as cancelled.';
        });
    }

    /** What an event card needs (never anything the viewer may not see). */
    private function eventCard(?\App\Models\N2N\Event $e, User $user): array
    {
        if ($e === null) {
            return ['gone' => true];
        }
        $mine = $e->rsvps->firstWhere('user_id', $user->id)?->status ?? 'no_response';
        $theirs = $e->rsvps->firstWhere('user_id', '!=', $user->id)?->status ?? 'no_response';
        $now = now();

        return ['gone' => false, 'id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'starts' => $e->starts_at->toIso8601String(), 'ends' => $e->endsAt()->toIso8601String(), 'tz' => $e->timezone,
            'cancelled' => $e->cancelled_at !== null, 'past' => $e->endsAt()->lt($now), 'creator' => (int) $e->creator_id === $user->id, 'mine' => $mine, 'theirs' => $theirs, 'reminder' => (int) $e->reminder_minutes,
            'ics' => route('n2n.event.ics', $e->id), 'gcal' => \App\Services\N2N\Messaging\Planner::googleUrl($e),
            'joinable' => $e->cancelled_at === null && $now->gte($e->starts_at->copy()->subMinutes(10)) && $now->lte($e->endsAt()->addMinutes(30))];
    }

    public function closePanel(): void
    {
        $this->reset('panel', 'chatSearch', 'selected', 'forwardId', 'forwardTo', 'editId', 'editBody', 'infoId');
    }

    public function setMediaTab(string $tab): void
    {
        $this->mediaTab = in_array($tab, ['media', 'voice', 'docs', 'links'], true) ? $tab : 'media';
        $this->selected = [];
    }

    public function dismissChatNotice(int $id): void
    {
        app(\App\Services\N2N\Messaging\ChatNotices::class)->dismiss((int) Auth::id(), $id);
    }

    public function toggleMute(?string $length = null): void
    {
        $words = ['8h' => 'Muted for 8 hours.', '1w' => 'Muted for 1 week.', 'always' => 'Muted. You will still see the unread count.'];
        $this->act(fn () => $this->prefs()->mute(Auth::user(), (int) $this->c, $length), $length === null ? 'Notifications are back on.' : ($words[$length] ?? 'Muted.'));
    }

    public function toggleArchive(): void
    {
        $part = $this->prefs()->participant(Auth::user(), (int) $this->c);
        $on = $part->archived_at === null;
        $this->act(fn () => $this->prefs()->archive(Auth::user(), (int) $this->c, $on), $on ? 'Chat archived. It comes back when they write again (unless it is muted).' : 'Chat moved back to your inbox.');
        if ($on) {
            $this->c = null;
        }
    }

    public function togglePinChat(): void
    {
        $part = $this->prefs()->participant(Auth::user(), (int) $this->c);
        $on = $part->pinned_at === null;
        $this->act(fn () => $this->prefs()->pinChat(Auth::user(), (int) $this->c, $on), $on ? 'Chat pinned to the top.' : 'Chat unpinned.');
    }

    public function toggleStar(int $messageId): void
    {
        $on = ! in_array($messageId, $this->prefs()->starredIds(Auth::user(), (int) $this->c), true);
        $this->act(fn () => $this->prefs()->star(Auth::user(), $messageId, $on), $on ? 'Message starred.' : 'Star removed.');
    }

    public function togglePinMessage(int $messageId): void
    {
        $part = $this->prefs()->participant(Auth::user(), (int) $this->c);
        $same = (int) $part->pinned_message_id === $messageId;
        $this->act(fn () => $this->prefs()->pinMessage(Auth::user(), (int) $this->c, $same ? null : $messageId), $same ? 'Message unpinned.' : 'Message pinned for you.');
    }

    public function setReply(int $messageId): void
    {
        try {
            $this->prefs()->visibleMessage(Auth::user(), $messageId);
            $this->replyTo = $messageId;
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function clearReply(): void
    {
        $this->replyTo = null;
    }

    public function saveTheme(string $accent, string $wallpaper, int $dim = 0): void
    {
        $this->act(fn () => $this->prefs()->setTheme(Auth::user(), (int) $this->c, ['accent' => $accent, 'wallpaper' => $wallpaper, 'dim' => $dim]), 'Chat theme saved. Only you can see it.');
        if ($this->error === null) {
            $this->panel = null;   // show the result straight away
        }
    }

    public function resetTheme(): void
    {
        $this->act(fn () => $this->prefs()->resetTheme(Auth::user(), (int) $this->c), 'Theme reset to the default look.');
        $this->panel = null;
    }

    public function removeWallpaperPhoto(): void
    {
        $this->act(fn () => $this->prefs()->removePhoto(Auth::user(), (int) $this->c), 'Your photo wallpaper was removed.');
    }

    public function hideSelected(): void
    {
        $n = 0;
        $this->act(function () use (&$n) {
            $n = app(ChatLibrary::class)->hideForMe(Auth::user(), (int) $this->c, array_map('intval', $this->selected));
        }, 'Removed for you.');
        $this->notice = $n === 1 ? '1 item removed for you. The other person keeps their copy.' : $n.' items removed for you. The other person keeps their copy.';
        $this->selected = [];
    }

    public function sendReport(MessageService $svc): void
    {
        $this->act(fn () => $svc->report(Auth::user(), (int) $this->c, $this->reportReason, $this->reportNote), 'Reported and blocked. Thank you.');
        $this->reset('reporting', 'reportNote');
        $this->c = null;
    }

    /** The number saved in the viewer's OWN contact that is linked to this member, or null. Never guessed, never taken from anyone else's address book. */
    private function phoneFor(User $user, int $peerId): ?string
    {
        $e = \App\Models\Contact::query()->where('user_id', $user->id)->where('linked_user_id', $peerId)->whereNotNull('e164')->orderBy('id')->value('e164');

        return is_string($e) && preg_match('/^\+[1-9]\d{6,14}$/', $e) ? $e : null;
    }

    /**
     * SMS and calls with the SAME person, drawn inside their Naara thread from the unified timeline. Linked only through the viewer's OWN contacts (a contact whose number
     * matches this member), so a phone number is never revealed or guessed here. @return list<array{at:\Illuminate\Support\Carbon,channel:string,direction:string,summary:?string,status:string}>
     */
    private function channelLines(User $user, int $peerId, \Illuminate\Support\Carbon $since): array
    {
        $keys = \App\Models\Contact::query()->where('user_id', $user->id)->where('linked_user_id', $peerId)->whereNotNull('e164')->pluck('e164')
            ->map(fn ($e) => \App\Services\Messaging\ConversationTimeline::peerKey((string) $e))->unique()->values()->all();
        if ($keys === []) {
            return [];
        }

        return \App\Models\ConversationEvent::query()->where('user_id', $user->id)->whereIn('peer_key', $keys)->whereIn('channel', ['sms', 'call'])->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(100)->get()->reverse()->values()
            ->map(fn ($e) => ['at' => $e->occurred_at, 'channel' => $e->channel, 'direction' => $e->direction, 'summary' => $e->summary, 'status' => $e->status])->all();
    }

    public function render(Inbox $inbox)
    {
        $user = Auth::user();
        $current = null;
        $messages = [];
        $peer = null;
        $tools = ['part' => null, 'muted' => false, 'archived' => false, 'pinnedChat' => false, 'pinnedMessage' => null, 'themeCss' => null, 'themeId' => null, 'theme' => null, 'panelData' => []];
        if ($this->c !== null && $this->openable($this->c)) {
            $current = Conversation::query()->find($this->c);
            $peerId = (int) Participant::query()->where('conversation_id', $current->id)->where('user_id', '!=', $user->id)->value('user_id');
            $peer = User::query()->select('id', 'name', 'username', 'naara_id')->find($peerId);
            $rows = Message::query()->where('conversation_id', $current->id)->forViewer($user->id)
                ->orderByDesc('id')->limit(100)->get()->reverse()->values();
            $files = Attachment::query()->whereIn('message_id', $rows->pluck('id'))->where('status', Attachment::ATTACHED)->get()->groupBy('message_id');
            $window = now()->subHours(MediaPolicy::deleteWindowHours());
            $receipts = MessageReceipt::query()->whereIn('message_id', $rows->pluck('id'))->where('user_id', $peerId)->get()->keyBy('message_id');
            $prefs = $this->prefs();
            $part = $prefs->participant($user, $current->id);
            $starred = array_flip($prefs->starredIds($user, $current->id));
            $hidden = array_flip(app(ChatLibrary::class)->hiddenIds($user, $current->id));
            $replyIds = $rows->pluck('reply_to_id')->filter()->unique();
            $replied = $replyIds->isEmpty() ? collect() : Message::query()->whereIn('id', $replyIds)->where('conversation_id', $current->id)->get()->keyBy('id');
            $reacts = $this->actions()->reactionsFor($user, $rows->pluck('id')->all());
            $events = \App\Models\N2N\Event::query()->whereIn('message_id', $rows->where('type', 'event')->pluck('id'))->with('rsvps')->get()->keyBy('message_id');
            $polls = app(\App\Services\N2N\Messaging\Polls::class)->forMessages($user, $rows->where('type', 'poll')->pluck('id')->all());
            $editCut = now()->subMinutes(\App\Services\N2N\Messaging\MessageActions::editWindowMinutes());
            $theme = ChatThemes::normalise($part->theme_json);
            $isCustom = $part->theme_json !== null;
            $tools = [
                'part' => $part, 'muted' => ConversationPrefs::isMuted($part), 'archived' => $part->archived_at !== null, 'pinnedChat' => $part->pinned_at !== null,
                'pinnedMessage' => $prefs->pinnedMessage($user, $part), 'theme' => $theme, 'themeId' => $isCustom ? $current->id : null,
                'themeCss' => $isCustom ? ChatThemes::css($theme, '.nc__msgs[data-nc-theme="'.$current->id.'"]', $theme['photo_id'] ? route('n2n.wallpaper', $theme['photo_id'], false) : null)['css'] : null,
                'panelData' => $this->panelData($user, $current->id),
                'mentionTokens' => array_merge(\App\Support\N2N\ChatFormat::mentionTokens($peer?->naara_id, $peer?->name), \App\Support\N2N\ChatFormat::mentionTokens($user->naara_id, $user->name)),
            ];
            foreach ($rows as $m) {
                $mine = $m->sender_id === $user->id;
                $rc = $receipts[$m->id] ?? null;
                $rep = $m->reply_to_id ? ($replied[$m->reply_to_id] ?? null) : null;
                $reply = $m->reply_to_id === null ? null : ($rep === null || $rep->deleted_for_all_at !== null || ($rep->suppressed && (int) $rep->sender_id !== $user->id)
                    ? ['id' => $m->reply_to_id, 'who' => '', 'text' => 'Original message deleted', 'gone' => true]
                    : ['id' => $rep->id, 'who' => (int) $rep->sender_id === $user->id ? 'You' : ($peer?->name ?: 'Them'), 'text' => trim((string) $rep->body) !== '' ? mb_substr(\App\Support\N2N\ChatFormat::plain((string) $rep->body), 0, 120) : (['image' => 'Photo', 'voice' => 'Voice message', 'video' => 'Video', 'audio' => 'Audio file', 'doc' => 'Document'][$rep->type] ?? 'Message'), 'gone' => false]);
                $messages[] = [
                    'starred' => isset($starred[$m->id]), 'reply' => $reply, 'pinned' => (int) $part->pinned_message_id === $m->id,
                    'id' => $m->id, 'mine' => $mine, 'body' => $m->body, 'quote' => $m->quote_json, 'at' => $m->created_at, 'type' => $m->type, 'deleted' => $m->deleted_for_all_at !== null,
                    'files' => ($files[$m->id] ?? collect())->reject(fn ($a) => isset($hidden[$a->id]))->map(fn ($a) => ['id' => $a->id, 'kind' => $a->kind, 'seconds' => $a->duration_s, 'transcript' => $a->meta['transcript'] ?? null, 'peaks' => $a->meta['peaks'] ?? null, 'name' => $a->meta['name'] ?? null, 'ext' => strtoupper(pathinfo((string) ($a->meta['name'] ?? ''), PATHINFO_EXTENSION)), 'mb' => round($a->bytes / 1048576, 1), 'gif' => $a->mime === 'image/gif'])->all(),
                    'canDelete' => $mine && $m->deleted_for_all_at === null && $m->created_at->gt($window),
                    'event' => $m->type === 'event' ? $this->eventCard($events[$m->id] ?? null, $user) : null, 'poll' => $polls[$m->id] ?? null, 'forwarded' => (bool) $m->forwarded, 'edited' => $m->edited_at !== null, 'reactions' => $reacts[$m->id]['counts'] ?? [], 'myReaction' => $reacts[$m->id]['mine'] ?? null,
                    'hasFiles' => isset($files[$m->id]),
                    'canEdit' => $mine && $m->type === 'text' && $m->deleted_for_all_at === null && ! isset($files[$m->id]) && $m->created_at->gt($editCut),
                    'canForward' => $m->type === 'text' && $m->deleted_for_all_at === null && trim((string) $m->body) !== '' && ! isset($files[$m->id]),
                    // A dropped message looks delivered to its sender; read receipts show only if the OTHER person allows them.
                    'state' => ! $mine ? null : (($rc?->read_at && $peer?->n2n_read_receipts) ? 'read' : (($rc?->delivered_at || $rc?->read_at) ? 'delivered' : 'sent')),
                ];
            }
        }
        // "By phone": two plain links to the EXISTING Messages page and dialer for the number saved in the viewer's own contact. Nothing is sent or priced from here.
        $phone = null;
        if ($peer && ($e164 = $this->phoneFor($user, (int) $peer->id))) {
            $phone = ['sms' => route('numbers.messages', ['to' => $e164]), 'call' => \Illuminate\Support\Facades\Route::has('numbers.dialer') && \App\Support\ProviderStatus::isActive('twilio') ? route('numbers.dialer', ['to' => $e164]) : null];
        }
        $channelLines = $peer ? $this->channelLines($user, (int) $peer->id, min($rows->first()?->created_at ?? now(), now()->subDays(30))) : [];
        $hasChannelLines = $channelLines !== [];
        // Interleave by time: each SMS/call line sits just before the first message that came after it; the rest close the thread.
        foreach ($messages as $i => $m) {
            $take = 0;
            while ($take < count($channelLines) && $channelLines[$take]['at']->lte($m['at'])) {
                $take++;
            }
            $messages[$i]['lines'] = array_splice($channelLines, 0, $take);
        }
        $tailLines = $channelLines;
        $unlocked = \App\Services\N2N\Messaging\ChatLock::unlocked();
        $hasPin = \App\Services\N2N\Messaging\ChatLock::hasPin($user);
        $entries = $inbox->entries($user, $this->filter, $this->q, $this->sort, $unlocked);
        $pendingRequests = count($inbox->entries($user, 'requests'));
        $hasLine = VirtualNumber::query()->where('user_id', $user->id)->where('status', 'active')->exists();
        $composerOn = $current !== null || $this->startedTo !== null;

        return view('livewire.n2n.chat', [
            'chatNotices' => app(\App\Services\N2N\Messaging\ChatNotices::class)->forMember((int) $user->id),
            'phone' => $phone, 'entries' => $entries, 'hasChannelLines' => $hasChannelLines, 'tailLines' => $tailLines,
            'reactionBar' => \App\Services\N2N\Messaging\MessageActions::quickBar($user),
            'reactionCatalog' => \App\Services\N2N\Messaging\MessageActions::REACTIONS,
            'filters' => ['all' => 'All', 'unread' => 'Unread', 'known' => 'Known', 'unknown' => 'Unknown', 'naara' => 'Naara', 'line' => 'Line', 'links' => 'Links', 'images' => 'Photos', 'videos' => 'Videos', 'requests' => 'Requests', 'archived' => 'Archived'] + ($hasPin ? ['locked' => 'Locked'] : []),
            'hasPin' => $hasPin, 'unlocked' => $unlocked,
            'pendingRequests' => $pendingRequests,
            'current' => $current,
            'peer' => $peer,
            'messages' => $messages,
            'isRequestForMe' => $current !== null && $current->status === Conversation::REQUEST && (int) $current->requested_by !== $user->id,
            'isRequestFromMe' => $current !== null && $current->status === Conversation::REQUEST && (int) $current->requested_by === $user->id,
            'hasLine' => $hasLine,
            'cost' => Money::credits(N2NPricing::messageCostCents()),
            'available' => Money::credits(app(UsageMeter::class)->available($user)),
            'composerOn' => $composerOn,
            'pro' => NaaraPro::active($user),
            'endpoint' => $this->startedTo !== null ? route('n2n.api.start', ['to' => $this->startedTo]) : ($current ? route('n2n.api.send', ['conversationId' => $current->id]) : ''),
            'convoKey' => $this->startedTo !== null ? 'n2n-new-'.md5($this->startedTo) : ($current ? 'n2n-'.$current->id : ''),
            'tools' => $tools,
            'accents' => ChatThemes::ACCENTS,
            'wallpapers' => ChatThemes::wallpapers(),
            'replyBar' => $this->replyTo !== null && $current !== null ? $this->replyPreview($user, $peer) : null,
            'bubbleMap' => $this->panel === 'theme' ? collect(ChatThemes::ACCENTS)->map(fn ($a, $k) => ['light' => ChatThemes::bubbles($k, false), 'dark' => ChatThemes::bubbles($k, true)])->all() : [],
            'wallMap' => $this->panel === 'theme' ? collect(ChatThemes::wallpapers())->map(fn ($w) => ['l' => $w['light'], 'd' => $w['dark']])->all() : [],
        ]);
    }

    /** The data the open side sheet needs (nothing is loaded while it is closed). @return array<string, mixed> */
    /** The member's own active Naara chats (not requests, not Line), newest first: where a message can be forwarded. @return list<array{id:int,title:string,naara_id:?string}> */
    private function forwardTargets(User $user): array
    {
        if ($this->panel !== 'forward') {
            return [];
        }
        $out = [];
        foreach (app(Inbox::class)->entries($user, 'all') as $e) {
            if ($e['kind'] === 'naara' && ! $e['request']) {
                $out[] = ['id' => $e['id'], 'title' => $e['title'], 'naara_id' => $e['naara_id']];
            }
        }

        return array_slice($out, 0, 60);
    }

    private function safeInfo(User $user, int $id): array
    {
        try {
            return $this->actions()->info($user, $id);
        } catch (MessagingException) {
            return [];
        }
    }

    private function panelData(User $user, int $cid): array
    {
        $prefs = $this->prefs();
        $lib = app(ChatLibrary::class);

        return match ($this->panel) {
            'search' => ['results' => $prefs->search($user, $cid, $this->chatSearch)],
            'starred' => ['items' => $prefs->starred($user, $cid)],
            'media' => $this->mediaTab === 'links' ? ['links' => $lib->links($user, $cid)] : ['items' => $lib->items($user, $cid, $this->mediaTab)],
            'scheduled' => ['items' => \App\Models\N2N\ScheduledMessage::query()->where('sender_id', $user->id)->where('conversation_id', $cid)->whereIn('status', ['pending', 'sending', 'failed'])->orderBy('send_at')->limit(50)->get()],
            'forward' => ['targets' => collect($this->forwardTargets($user))],
            'agenda' => ['items' => app(\App\Services\N2N\Messaging\Planner::class)->agenda($user, $cid)],
            'info' => $this->infoId !== null ? $this->safeInfo($user, $this->infoId) : [],
            'storage' => ['usage' => $lib->usage($user, $cid), 'items' => array_merge($lib->items($user, $cid, 'media'), $lib->items($user, $cid, 'voice'))],
            default => [],
        };
    }

    /** @return array{id: int, who: string, text: string}|null */
    private function replyPreview(User $user, ?User $peer): ?array
    {
        try {
            $m = $this->prefs()->visibleMessage($user, (int) $this->replyTo);
        } catch (MessagingException) {
            $this->replyTo = null;

            return null;
        }

        return ['id' => $m->id, 'who' => (int) $m->sender_id === $user->id ? 'yourself' : ($peer?->name ?: 'them'), 'text' => trim((string) $m->body) !== '' ? mb_substr(\App\Support\N2N\ChatFormat::plain((string) $m->body), 0, 100) : (['image' => 'Photo', 'voice' => 'Voice message', 'video' => 'Video', 'audio' => 'Audio file', 'doc' => 'Document'][$m->type] ?? 'Message')];
    }
}
