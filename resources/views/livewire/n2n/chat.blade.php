{{-- nx:converted --}}
{{-- Naara chat (N2N, MASTER-ONLY). Tokens only; RTL-safe (logical spacing); reduced-motion safe. Copy never claims end-to-end encryption. --}}
<div class="nc" data-n2n-chat @if ($current || $startedTo) data-n2n-thread @endif>
@vite('resources/js/n2n/chat.js')
@include('n2n.gesture-config')

    {{-- Long-press / right-click menu (one for the whole screen). State comes from the pressed element's data-ctx; every action is a server call that re-checks the member. --}}
    <div class="n2nx" wire:ignore x-data="window.n2nCtx ? n2nCtx(@js($reactionBar), @js($reactionCatalog)) : {}" x-cloak x-show="open" style="display:none" @keydown.escape.window="close()" @n2n-ctx.window="show($event.detail)" role="presentation">
        <div class="n2nx__scrim" @click="close()" @contextmenu.prevent="close()"></div>
        <div class="n2nx__peek" x-show="d.k === 'row' && peek.state" data-peek aria-live="polite">
            <p class="n2nx__peekhead"><b x-text="d.name"></b><small x-show="peek.state === 'loading'">Loading...</small></p>
            <ul x-show="peek.state === 'ready'">
                <template x-for="(m, i) in peek.messages" :key="i">
                    <li :class="m.mine ? 'is-mine' : ''"><span x-text="m.text"></span><small x-text="m.at"></small></li>
                </template>
            </ul>
            <p class="n2nx__peekmsg" x-show="peek.state === 'empty'">No messages to show yet.</p>
            <p class="n2nx__peekmsg" x-show="peek.state === 'error'">Could not load a preview. Open the chat to read it.</p>
        </div>
        <div class="n2nx__preview" x-ref="preview" :style="previewStyle" :class="{ 'is-open': previewOpen, 'is-clamped': clamped && !previewOpen }" aria-hidden="true" x-show="d.k === 'msg'"></div>
        <button type="button" class="n2nx__more" x-show="d.k === 'msg' && clamped" @click="previewOpen = !previewOpen" x-text="previewOpen ? 'Show less' : 'Show more'"></button>
        <div class="n2nx__menu" x-ref="menu" :style="menuStyle" role="menu" :aria-label="d.k === 'row' ? 'Chat actions' : 'Message actions'">
            <div class="n2nx__react" x-show="d.k === 'msg'" role="group" aria-label="React">
                <div class="n2nx__rail" role="group" aria-label="Your reactions (scroll sideways for more; press and hold to edit)">
                    <template x-for="(code, i) in bar" :key="code"><button type="button" :style="'--i:' + Math.min(i, 8)" :class="[d.reaction === code ? 'is-on' : '', editing ? 'is-edit' : '']" @pointerdown="holdStart()" @pointerup="holdEnd()" @pointerleave="holdEnd()" @pointercancel="holdEnd()" @contextmenu.prevent @click="barTap(code)" :aria-label="(editing ? 'Remove ' : 'React ') + code"><span x-text="cat[code]"></span><i class="n2nx__x" x-show="editing" aria-hidden="true"></i></button></template>
                </div>
                <button type="button" class="n2nx__plus" :class="picker ? 'is-open' : ''" style="--i: 8" @click="picker = !picker; if (!picker) { editing = false }" :aria-expanded="picker ? 'true' : 'false'" aria-label="More reactions and edit your bar"><x-nx.icon name="plus" /></button>
            </div>
            <div class="n2nx__picker" x-show="d.k === 'msg' && picker">
                <div class="n2nx__phead">
                    <span x-text="editing ? ('Tap to add or remove (' + bar.length + '/' + max + ')') : 'React with any emoji'"></span>
                    <button type="button" x-show="editing" @click="resetBar()">Reset</button>
                    <button type="button" :class="editing ? 'is-primary' : ''" @click="editing = !editing" x-text="editing ? 'Done' : 'Edit bar'"></button>
                </div>
                <div class="n2nx__grid" :class="editing ? 'is-editing' : ''">
                    <template x-for="code in codes" :key="code"><button type="button" :class="[bar.includes(code) ? 'is-in' : '', d.reaction === code ? 'is-on' : '']" @click="pick(code)" :aria-label="code" x-text="cat[code]"></button></template>
                </div>
            </div>
            <div class="n2nx__list" x-show="!(d.k === 'msg' && picker)">
                <template x-if="d.k === 'msg'"><div class="n2nx__group">
                    <button type="button" role="menuitem" style="--i: 1" @click="$wire.setReply(d.id); close()"><x-nx.icon name="reply" /> <span>Reply</span></button>
                    <button type="button" role="menuitem" style="--i: 1" @click="window.dispatchEvent(new CustomEvent('n2n-msgsel-start', { detail: { id: d.id } })); close()"><x-nx.icon name="check" /> <span>Select</span></button>
                    <button type="button" role="menuitem" style="--i: 2" x-show="d.text" @click="copy()"><x-nx.icon name="copy" /> <span>Copy</span></button>
                    <button type="button" role="menuitem" style="--i: 3" x-show="d.canForward" @click="$wire.openForward(d.id); close()"><x-nx.icon name="share" /> <span>Forward</span></button>
                    <button type="button" role="menuitem" style="--i: 4" @click="$wire.toggleStar(d.id); close()"><x-nx.icon name="star" /> <span x-text="d.starred ? 'Remove star' : 'Star'"></span></button>
                    <button type="button" role="menuitem" style="--i: 5" @click="$wire.togglePinMessage(d.id); close()"><x-nx.icon name="pin" /> <span x-text="d.pinned ? 'Unpin' : 'Pin for me'"></span></button>
                    <button type="button" role="menuitem" style="--i: 6" x-show="d.canEdit" @click="$wire.startEdit(d.id); close()"><x-nx.icon name="edit" /> <span>Edit</span></button>
                    <button type="button" role="menuitem" style="--i: 7" x-show="d.mine" @click="$wire.openInfo(d.id); close()"><x-nx.icon name="info" /> <span>Info</span></button>
                    <button type="button" role="menuitem" style="--i: 8" class="is-danger" @click="if (confirm('Delete this message for you? The other person keeps it.')) { $wire.deleteForMe(d.id); } close()"><x-nx.icon name="trash" /> <span>Delete for me</span></button>
                    <button type="button" role="menuitem" style="--i: 9" x-show="d.canDelete" class="is-danger" @click="if (confirm('Delete this message for everyone?')) { $wire.deleteMessage(d.id); } close()"><x-nx.icon name="trash" /> <span>Delete for everyone</span></button>
                </div></template>
                <template x-if="d.k === 'row'"><div class="n2nx__group">
                    <button type="button" role="menuitem" style="--i: 0" @click="window.dispatchEvent(new CustomEvent('n2n-sel-start', { detail: { id: d.id } })); close()"><x-nx.icon name="check" /> <span>Select</span></button>
                    <button type="button" role="menuitem" style="--i: 1" @click="$wire.rowPin(d.id); close()"><x-nx.icon name="pin" /> <span x-text="d.pinned ? 'Unpin chat' : 'Pin chat'"></span></button>
                    <button type="button" role="menuitem" style="--i: 2" x-show="d.muted" @click="$wire.rowMute(d.id, null); close()"><x-nx.icon name="bell" /> <span>Unmute</span></button>
                    <div class="n2nx__chips" style="--i: 2" x-show="!d.muted"><span>Mute</span>
                        <button type="button" @click="$wire.rowMute(d.id, '8h'); close()">8 hours</button><button type="button" @click="$wire.rowMute(d.id, '1w'); close()">1 week</button><button type="button" @click="$wire.rowMute(d.id, 'always'); close()">Always</button></div>
                    <button type="button" role="menuitem" style="--i: 3" @click="$wire.rowUnread(d.id); close()"><x-nx.icon name="message-circle" /> <span x-text="(d.unread || d.marked) ? 'Mark as read' : 'Mark as unread'"></span></button>
                    <button type="button" role="menuitem" style="--i: 4" @click="$wire.rowLock(d.id); close()"><x-nx.icon name="lock" /> <span x-text="d.locked ? 'Unlock chat' : 'Lock chat'"></span></button>
                    <button type="button" role="menuitem" style="--i: 4" @click="$wire.rowArchive(d.id); close()"><x-nx.icon name="archive" /> <span x-text="d.archived ? 'Unarchive' : 'Archive'"></span></button>
                    <a role="menuitem" style="--i: 5" x-show="d.profile" :href="d.profile" wire:navigate><x-nx.icon name="user" /> <span>View profile</span></a>
                    <button type="button" role="menuitem" style="--i: 6" class="is-danger" @click="if (confirm('Clear this chat for you? Messages disappear from your side only; the other person keeps them.')) { $wire.rowClear(d.id); } close()"><x-nx.icon name="trash" /> <span>Clear chat</span></button>
                    <button type="button" role="menuitem" style="--i: 7" class="is-danger" @click="if (confirm('Delete this chat for you? It leaves your list until a new message arrives. The other person keeps their copy.')) { $wire.rowDeleteChat(d.id); } close()"><x-nx.icon name="trash" /> <span>Delete chat</span></button>
                    <button type="button" role="menuitem" style="--i: 8" class="is-danger" @click="if (confirm('Block this person?')) { $wire.rowBlock(d.id); } close()"><x-nx.icon name="shield-alert" /> <span>Block</span></button>
                </div></template>
            </div>
        </div>
    </div>

    {{-- PIN sheet (chat lock): set, unlock, change or remove. The PIN is only ever compared against a hash on the server. --}}
    @if ($lockSheet)
        <div class="nc__sheetwrap" role="dialog" aria-modal="true" aria-label="Chat lock" @keydown.escape.window="$wire.closeLockSheet()">
            <div class="nc__sheetscrim" wire:click="closeLockSheet"></div>
            <form class="nc__sheet" wire:submit="submitLock" autocomplete="off">
                <h3>{{ ['set' => 'Set a chat PIN', 'unlock' => 'Enter your PIN', 'change' => 'Change your PIN', 'remove' => 'Remove chat lock'][$lockSheet] }}</h3>
                <p class="nc__sheethint">{{ ['set' => 'Use 4 to 8 digits. Locked chats are hidden from lists and search until you enter it. It does not replace your phone screen lock.', 'unlock' => 'Locked chats stay open for a few minutes, then hide again.', 'change' => 'Enter your current PIN, then a new one.', 'remove' => 'Enter your PIN. All your chats will be unlocked.'][$lockSheet] }}</p>
                @if ($error)<p class="nc__sheeterr" role="alert">{{ $error }}</p>@endif
                @if (in_array($lockSheet, ['change', 'remove'], true))<label>Current PIN<input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" wire:model="pinCurrent" autocomplete="off" required></label>@endif
                @if (in_array($lockSheet, ['set', 'unlock', 'change'], true))<label>{{ $lockSheet === 'unlock' ? 'PIN' : 'New PIN' }}<input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" wire:model="pin" autocomplete="off" required autofocus></label>@endif
                @if (in_array($lockSheet, ['set', 'change'], true))<label>Repeat PIN<input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" wire:model="pin2" autocomplete="off" required></label>@endif
                <div class="nc__sheetacts"><button type="button" class="nc__chip" wire:click="closeLockSheet">Cancel</button><button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="submitLock">Continue</button></div>
            </form>
        </div>
    @endif

    {{-- ===== Conversation list ===== --}}
    <aside data-tour="chat.list" class="nc__list {{ ($current || $startedTo || $showNew) ? 'is-hidden' : '' }}" aria-label="Conversations" x-data="n2nSel()" @n2n-sel-start.window="start($event.detail.id)" @keydown.escape.window="if (on) { stop() }">
        {{-- Selection toolbar: replaces the search and filters while picking chats. --}}
        <div class="nc__selbar" x-show="on" x-cloak style="display:none" role="toolbar" aria-label="Selected chats">
            <div class="nc__selrow">
                <button type="button" class="nn__iconbtn" @click="stop()" aria-label="Cancel selection"><x-nx.icon name="x" /></button>
                <b class="nc__selcount" aria-live="polite"><span x-text="count"></span> selected</b>
                <button type="button" class="nc__chip" @click="count === rows().length ? none() : all()" x-text="count === rows().length && count > 0 ? 'Clear all' : 'Select all'"></button>
            </div>
            <div class="nc__selchips" role="group" aria-label="Choose by time">
                <span>Pick</span>
                <button type="button" class="nc__chip" @click="invert()">Invert</button>
                <button type="button" class="nc__chip" @click="unreadOnly()">Unread</button>
                <button type="button" class="nc__chip" @click="older(7)">Older than 7 days</button>
                <button type="button" class="nc__chip" @click="older(30)">30 days</button>
                <button type="button" class="nc__chip" @click="older(90)">90 days</button>
                <button type="button" class="nc__chip" @click="older(365)">1 year</button>
            </div>
            <div class="nc__selchips" role="group" aria-label="Choose a date range">
                <span>Dates</span>
                <label class="nc__date"><span class="sr-only">From</span><input type="date" x-model="from"></label>
                <label class="nc__date"><span class="sr-only">To</span><input type="date" x-model="to"></label>
                <button type="button" class="nc__chip" @click="range()">Select range</button>
            </div>
            <div class="nc__selchips nc__selacts" role="group" aria-label="Actions on selected chats">
                <button type="button" class="nc__chip" :disabled="!count" @click="run('read')">Mark read</button>
                <button type="button" class="nc__chip" :disabled="!count" @click="run('unread')">Mark unread</button>
                <button type="button" class="nc__chip" :disabled="!count" @click="run('pin')">Pin</button>
                <button type="button" class="nc__chip" :disabled="!count" @click="run('mute')">Mute</button>
                <button type="button" class="nc__chip" :disabled="!count" @click="run('archive')">Archive</button>
                <button type="button" class="nc__chip is-danger" :disabled="!count" @click="run('clear', 'Clear {n} selected chat(s) for you? The other people keep their copies.')">Clear</button>
                <button type="button" class="nc__chip is-danger" :disabled="!count" @click="run('delete', 'Delete {n} selected chat(s) for you? They leave your list until a new message arrives. The other people keep their copies.')">Delete</button>
            </div>
        </div>
        <div class="nc__search" x-show="!on">
            <label class="relative block">
                <span class="sr-only">Search messages</span>
                <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search messages" class="w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] py-2 ps-4 pe-4 text-sm text-[rgb(var(--nx-text))]">
            </label>
        </div>
        <div class="nc__chips" data-tour="chat.filters" role="group" aria-label="Filters" x-show="!on">
            <button type="button" class="nc__chip nc__chip--sel" @click="start()"><x-nx.icon name="check" style="font-size:13px" /> Select</button>
            <button type="button" class="nc__chip nc__chip--search" data-pull-search><x-nx.icon name="search" style="font-size:13px" /> Search</button>
            <label class="nc__sort"><span class="sr-only">Sort chats</span>
                <select wire:model.live="sort" aria-label="Sort chats">
                    @foreach (\App\Services\N2N\Messaging\Inbox::SORTS as $sk => $sl)<option value="{{ $sk }}">{{ $sl }}</option>@endforeach
                </select></label>
            @foreach ($filters as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" class="nc__chip {{ $filter === $key ? 'is-on' : '' }}">
                    {{ $label }}@if ($key === 'requests' && $pendingRequests > 0) ({{ $pendingRequests }})@endif
                </button>
            @endforeach
        </div>

        @if ($filter === 'locked' && ! $unlocked)
            <div class="nc__lockform">
                <x-nx.icon name="lock" style="font-size:28px" />
                <p>Locked chats are hidden until you enter your PIN.</p>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" wire:click="openLockSheet('unlock')">Enter PIN</button>
            </div>
        @elseif ($filter === 'locked')
            <div class="nc__lockbar">
                <span><x-nx.icon name="lock" style="font-size:14px" /> Locked chats are open for a few minutes</span>
                <button type="button" class="nc__chip" wire:click="lockNow">Lock now</button>
                <button type="button" class="nc__chip" wire:click="openLockSheet('change')">Change PIN</button>
                <button type="button" class="nc__chip is-danger" wire:click="openLockSheet('remove')">Remove PIN</button>
            </div>
        @endif
        <ul class="nc__rows" wire:loading.class="opacity-60" wire:target="setFilter,q">
            @forelse ($entries as $e)
                <li wire:key="e-{{ $e['kind'] }}-{{ $e['id'] }}" @if ($e['kind'] === 'naara' && empty($e['request'])) data-sel="{{ $e['id'] }}" data-at="{{ (int) $e['at'] }}" data-unread="{{ ((int) $e['unread'] > 0 || ! empty($e['marked'])) ? 1 : 0 }}" :class="on && has({{ $e['id'] }}) ? 'is-selected' : ''" @click.capture="if (on) { $event.preventDefault(); $event.stopPropagation(); toggle({{ $e['id'] }}) }" data-ctx="{{ json_encode(['k' => 'row', 'id' => $e['id'], 'name' => $e['title'], 'pinned' => (bool) $e['pinned'], 'muted' => (bool) ($e['muted'] ?? false), 'archived' => (bool) ($e['archived'] ?? false), 'unread' => ((int) $e['unread'] > 0), 'marked' => (bool) ($e['marked'] ?? false), 'locked' => (bool) ($e['locked'] ?? false), 'profile' => $e['username'] ? route('n2n.profile', $e['username']) : null], JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endif>
                    @if ($e['kind'] === 'line')
                        <a href="{{ route('numbers.messages') }}" wire:navigate class="flex items-center gap-3 px-4 py-3 hover:bg-[rgb(var(--nx-surface-2))]">
                    @else
                        <button type="button" wire:click="select({{ $e['id'] }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-[rgb(var(--nx-surface-2))] {{ $c === $e['id'] ? 'bg-[rgb(var(--nx-surface-2))]' : '' }}">
                    @endif
                        @if ($e['kind'] === 'naara' && empty($e['request']))<span class="nc__selbox" x-show="on" x-cloak style="display:none" :class="has({{ $e['id'] }}) ? 'is-on' : ''" aria-hidden="true"></span>@endif
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-teal)/0.15)] text-sm font-bold text-[rgb(var(--nx-teal-ink))]" aria-hidden="true">{{ mb_strtoupper(mb_substr(ltrim($e['title'], '@+'), 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1">
                                    <span class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $e['title'] }}</span>
                                    @if (! empty($e['pinned']))<x-nx.icon name="pin" class="shrink-0 text-[rgb(var(--nx-text-2))]" style="font-size:12px" aria-label="Pinned" />@endif
                                    @if (! empty($e['muted']))<x-nx.icon name="bell-off" class="shrink-0 text-[rgb(var(--nx-text-2))]" style="font-size:12px" aria-label="Muted" />@endif
                                </span>
                                <span class="shrink-0 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $e['at'] ? (now()->timestamp - $e['at'] < 60 ? 'now' : \Illuminate\Support\Carbon::createFromTimestamp($e['at'])->diffForHumans(null, true, true)) : '' }}</span>
                            </span>
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $e['mine'] ? 'You: ' : '' }}{{ $e['preview'] }}</span>
                                <span class="flex shrink-0 items-center gap-1">
                                    <span class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold {{ $e['line'] === 'line' ? 'bg-[rgb(var(--nx-warn)/0.16)]' : 'bg-[rgb(var(--nx-teal)/0.14)]' }} text-[rgb(var(--nx-text))]">{{ $e['line'] === 'line' ? 'Line' : 'Naara' }}</span>
                                    @if ($e['request'])<span class="nc__reqchip rounded-full bg-[rgb(var(--nx-warn)/0.2)] px-1.5 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text))]">Request</span>@endif
                                    @if ($e['unread'] > 0)<span class="min-w-[1.25rem] rounded-full bg-[rgb(var(--nx-teal))] px-1.5 text-center text-[11px] font-bold text-white" aria-label="{{ $e['unread'] }} unread">{{ $e['unread'] }}</span>@endif
                                </span>
                            </span>
                        </span>
                    @if ($e['kind'] === 'line')</a>@else</button>@endif
                </li>
            @empty
                <li>@if (trim($q) !== '')<p class="px-4 py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">No messages match.</p>@else<x-n2n.empty art="nc-chats" title="No conversations yet" text="Start one with the button below, or find someone by their Naara ID."><a href="{{ route('n2n.people') }}" wire:navigate class="ns-cta ns-cta--pill mt-3 inline-flex">Find people</a></x-n2n.empty>@endif</li>
            @endforelse
        </ul>

        {{-- Floating invite: one tap to bring someone onto Naara chat (share link, copy, WhatsApp, or find by Naara ID / phone / email). --}}
        @php
            $inviteLink = url('/?ref='.auth()->user()->referral_code);
            $inviteText = 'Chat with me on Naara chat: '.$inviteLink;
        @endphp
        <div class="nc__fabwrap" x-data="{ open: false, copied: false, copy() { navigator.clipboard?.writeText(@js($inviteLink)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); }, share() { if (navigator.share) { navigator.share({ title: 'Naara chat', text: 'Chat with me on Naara chat', url: @js($inviteLink) }).catch(() => {}); } else { this.copy(); } } }"
             @keydown.escape.window="open = false" @click.outside="open = false" x-show="!on" x-cloak>
            <div class="nc__fabpop" x-show="open" x-transition.opacity x-cloak role="dialog" aria-label="Invite someone to Naara chat">
                <p class="nc__fabtitle">Invite someone to Naara chat</p>
                <p class="nc__fabhint">They join through your link and you can chat as soon as they sign up.</p>
                <button type="button" class="nc__fabrow" @click="share()"><x-nx.icon name="share" /> <span>Share my invite link</span></button>
                <button type="button" class="nc__fabrow" @click="copy()"><x-nx.icon name="copy" /> <span x-text="copied ? 'Link copied' : 'Copy my invite link'"></span></button>
                <a class="nc__fabrow" href="https://wa.me/?text={{ rawurlencode($inviteText) }}" target="_blank" rel="noopener noreferrer"><x-nx.icon name="message-circle" /> <span>Send on WhatsApp</span></a>
                <a class="nc__fabrow" href="{{ route('n2n.people', ['tab' => 'brands']) }}" wire:navigate data-chat-find-brands><x-nx.icon name="sparkles" /> <span>Find and connect with brands</span></a>
                <a class="nc__fabrow" href="{{ route('n2n.people') }}" wire:navigate><x-nx.icon name="search" /> <span>Find or invite by Naara ID, phone or email</span></a>
            </div>
            <button type="button" data-tour="chat.invite" class="ns-cta ns-cta--pill nc__fab" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-label="Invite someone to Naara chat">
                <x-nx.icon name="user-plus" /> <span>Invite</span>
            </button>
        </div>
    </aside>
    <div class="nn__grip nn__grip--list" data-nn-grip="list" wire:ignore role="separator" aria-orientation="vertical" aria-label="Resize the conversation list (double-click to reset)" aria-valuemin="260" aria-valuemax="560" aria-valuenow="360" tabindex="0"><i></i></div>

    {{-- ===== Thread / new chat ===== --}}
    <section data-nlb-scope class="nc__thread {{ ($current || $startedTo || $showNew) ? '' : 'is-hidden' }}" aria-label="Conversation" x-data="n2nMsgSel()" @n2n-msgsel-start.window="start($event.detail.id)" @keydown.escape.window="if (on) { stop() }" :class="on ? 'is-selecting' : ''" @if ($startedTo) data-n2n-new @endif>
        <div class="nc__selbar nc__selbar--thread" x-show="on" x-cloak style="display:none" role="toolbar" aria-label="Selected messages">
            <div class="nc__selrow">
                <button type="button" class="nn__iconbtn" @click="stop()" aria-label="Cancel selection"><x-nx.icon name="x" /></button>
                <b class="nc__selcount" aria-live="polite"><span x-text="count"></span> selected</b>
                <button type="button" class="nc__chip" @click="all()">Select all</button>
            </div>
            <div class="nc__selchips" role="group" aria-label="Choose messages by time or sender">
                <span>Pick</span>
                <button type="button" class="nc__chip" @click="today()">Today</button>
                <button type="button" class="nc__chip" @click="older(7)">Older than 7 days</button>
                <button type="button" class="nc__chip" @click="older(30)">30 days</button>
                <button type="button" class="nc__chip" @click="older(90)">90 days</button>
                <button type="button" class="nc__chip" @click="mine()">Mine</button>
                <button type="button" class="nc__chip" @click="theirs()">Theirs</button>
            </div>
            <div class="nc__selchips" role="group" aria-label="Choose a date range">
                <span>Dates</span>
                <label class="nc__date"><span class="sr-only">From</span><input type="date" x-model="from"></label>
                <label class="nc__date"><span class="sr-only">To</span><input type="date" x-model="to"></label>
                <button type="button" class="nc__chip" @click="range()">Select range</button>
            </div>
            <div class="nc__selchips nc__selacts" role="group" aria-label="Actions on selected messages">
                <button type="button" class="nc__chip" :disabled="!count" @click="copy()">Copy</button>
                <button type="button" class="nc__chip" :disabled="!count" @click="star()">Star</button>
                <button type="button" class="nc__chip is-danger" :disabled="!count" @click="hide()">Delete for me</button>
            </div>
        </div>
        @if ($showNew)
            <div class="flex h-full flex-col overflow-y-auto p-5">
                <button type="button" wire:click="back" class="nn__iconbtn mb-3 self-start lg:hidden" aria-label="Back to conversations"><x-nx.icon name="left" /></button>
                <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">New chat</h2>
                @if ($error)<p class="mt-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                <label class="mt-4 text-xs font-medium text-[rgb(var(--nx-text-2))]" for="n2n-new-to">To</label>
                <input id="n2n-new-to" type="text" wire:model.live.debounce.500ms="newTo" autocomplete="off" placeholder="Name, @username, Naara ID, phone or email"
                       class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @if ($newHit)
                    <p class="mt-2 text-sm text-[rgb(var(--nx-text))]" role="status">
                        @if ($newHit['name'])Chatting with <b>{{ $newHit['name'] }}</b> @if ($newHit['username'])(&#64;{{ $newHit['username'] }})@endif
                        @else Not on Naara yet. <a class="underline" href="{{ route('n2n.credits') }}" wire:navigate>Invite them with a gift</a> or share your invite link.@endif
                    </p>
                @endif

                @if ($route && $route['channel'] !== 'none')
                    @php($isN2n = $route['channel'] === 'n2n')
                    @php($texts = $route['texts'] ?? [])
                    @php($smsGate = ! $isN2n && ($route['requires_user_choice'] ?? false) && array_diff($route['blockers'], ['sms_opt_in_required']) === [])
                    <div class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-route-card role="status">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-teal)/.14)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]" data-route-pill>
                                <x-nx.icon name="message-circle" class="h-3.5 w-3.5" />{{ $isN2n ? 'Naara chat' : 'SMS from your Naara Line' }}
                            </span>
                            <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $isN2n ? 'Paid with NaaraCredit' : 'Charged to your wallet' }}</span>
                        </div>
                        @if ($route['price'] !== null)
                            <p class="mt-2 text-sm text-[rgb(var(--nx-text))]" data-route-price>
                                @if ($isN2n){{ rtrim(rtrim(number_format($route['price'], 2), '0'), '.') }} credit per message. You have {{ $available }}.
                                @else About ${{ number_format($route['price'], 4) }} per SMS, shown before anything is sent.@endif
                            </p>
                        @endif
                        @if ($route['suggestion'])<p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $route['suggestion'] }}</p>@endif
                        @foreach (collect($texts)->reject(fn ($t, $i) => ($route['blockers'][$i] ?? '') === 'sms_opt_in_required') as $t)
                            <p class="mt-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $t }}</p>
                        @endforeach

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($isN2n)
                                @if ($route['allowed'])<button type="button" wire:click="startChat" wire:loading.attr="disabled" wire:target="startChat" class="ns-cta ns-cta--pill">Continue</button>@endif
                                @if (in_array('insufficient_credit', $route['blockers'], true))
                                    <a class="ns-cta ns-cta--pill" href="{{ route('n2n.credits') }}" wire:navigate>Top up credit</a>
                                @endif
                                @if (($route['target']['e164'] ?? null) && ! $viaSms)
                                    <button type="button" wire:click="chooseSms(true)" class="nc__chip">Send as SMS instead</button>
                                @endif
                            @elseif ($smsGate)
                                <button type="button" wire:click="chooseSms(true)" wire:loading.attr="disabled" wire:target="chooseSms" class="ns-cta ns-cta--pill">Send as SMS</button>
                                <span class="text-xs text-[rgb(var(--nx-text-2))]">Not on Naara. Invite them to chat free instead.</span>
                            @else
                                @if ($route['allowed'])
                                    <a class="ns-cta ns-cta--pill" href="{{ route('numbers.messages', ['to' => $route['target']['e164']]) }}" wire:navigate>Continue to SMS</a>
                                @elseif (in_array('no_line', $route['blockers'], true))
                                    <a class="ns-cta ns-cta--pill" href="{{ route('numbers') }}" wire:navigate>Get a Line</a>
                                @elseif (in_array('insufficient_wallet', $route['blockers'], true))
                                    <a class="ns-cta ns-cta--pill" href="{{ route('wallet') }}" wire:navigate>Add funds</a>
                                @endif
                                @if ($viaSms)<button type="button" wire:click="chooseSms(false)" class="nc__chip">Back</button>@endif
                            @endif
                        </div>
                    </div>
                @elseif ($route)
                    @foreach ($route['texts'] ?? [] as $t)<p class="mt-3 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $t }}</p>@endforeach
                @endif
            </div>

        @elseif ($current || $startedTo)
            <header class="nc__thead" data-n2n-peer="{{ $peer?->naara_id ?? $startedTo }}">
                <button type="button" wire:click="back" data-n2n-back class="nn__iconbtn lg:hidden" aria-label="Back to conversations"><x-nx.icon name="left" /></button>
                @php($peerHandle = $peer?->username ?: $peer?->naara_id)
                <a data-tour="chat.profile" @if ($peerHandle) href="{{ route('n2n.profile', ['handle' => $peerHandle]) }}" wire:navigate @endif class="flex min-w-0 flex-1 items-center gap-3" aria-label="Open profile">
                    <x-nx.avatar :name="$peer?->name ?: '?'" :size="40" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $peer?->name ?: 'New chat' }}</span>
                        <span class="block truncate text-xs text-[rgb(var(--nx-text-2))]"><span data-n2n-typing hidden>typing...</span>@if ($peer?->username)<span>&#64;{{ $peer->username }}</span>@endif</span>
                    </span>
                </a>
                @if ($current && $peer?->naara_id && \App\Services\N2N\Calls\CallsAvailability::enabled())
                    <button type="button" data-tour="chat.call" class="nn__iconbtn" data-n2n-call="{{ $peer->naara_id }}" aria-label="Call {{ $peer->name }}"><x-nx.icon name="phone" /></button>
                    @if (\App\Services\N2N\Calls\CallsAvailability::videoEnabled())
                        <button type="button" class="nn__iconbtn" data-n2n-video-call="{{ $peer->naara_id }}" aria-label="Video call {{ $peer->name }}"><x-nx.icon name="video" /></button>
                    @endif
                @endif
                @if ($current)
                    <div class="relative" x-data="{ open: false, mute: false }" @keydown.escape.window="open = false; mute = false">
                        <button type="button" class="nn__iconbtn" @click="open = !open; mute = false" :aria-expanded="open" aria-label="Chat options"><x-nx.icon name="menu" /></button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="nc__menu" style="display:none" role="menu">
                            <template x-if="!mute">
                                <div>
                                    <button type="button" role="menuitem" wire:click="openPanel('search')" @click="open = false"><x-nx.icon name="search" /> Search in chat</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('starred')" @click="open = false"><x-nx.icon name="star" /> Starred messages</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('media')" @click="open = false"><x-nx.icon name="image" /> Media, voice and links</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('theme')" @click="open = false"><x-nx.icon name="palette" /> Chat theme</button>
                                    <hr>
                                    @if ($tools['muted'])
                                        <button type="button" role="menuitem" wire:click="toggleMute" @click="open = false"><x-nx.icon name="bell" /> Turn notifications on</button>
                                    @else
                                        <button type="button" role="menuitem" @click="mute = true"><x-nx.icon name="bell-off" /> Mute notifications</button>
                                    @endif
                                    <button type="button" role="menuitem" wire:click="togglePinChat" @click="open = false"><x-nx.icon name="pin" /> {{ $tools['pinnedChat'] ? 'Unpin chat' : 'Pin chat' }}</button>
                                    <button type="button" role="menuitem" wire:click="toggleArchive" @click="open = false"><x-nx.icon name="archive" /> {{ $tools['archived'] ? 'Unarchive chat' : 'Archive chat' }}</button>
                                    <hr>
 <button type="button" role="menuitem" wire:click="openPanel('event')" @click="open = false"><x-nx.icon name="calendar" /> Plan an event</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('agenda')" @click="open = false"><x-nx.icon name="calendar" /> Upcoming events</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('followup')" @click="open = false"><x-nx.icon name="bell" /> Remind me to follow up</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('scheduled')" @click="open = false"><x-nx.icon name="clock" /> Scheduled messages</button>
                                    <button type="button" role="menuitem" wire:click="rowLock({{ $current->id }})" @click="open = false"><x-nx.icon name="lock" /> {{ ($tools['part']?->locked_at ?? null) ? 'Unlock this chat' : 'Lock this chat' }}</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('disappear')" @click="open = false"><x-nx.icon name="clock" /> Disappearing messages</button>
                                    <button type="button" role="menuitem" wire:click="openPanel('storage')" @click="open = false"><x-nx.icon name="database" /> Manage storage</button>
                                    <a role="menuitem" href="{{ route('n2n.chat.export', $current->id) }}" @click="open = false"><x-nx.icon name="download" /> Export chat (text)</a>
                                    <hr>
                                    @if ($peerHandle)<a role="menuitem" href="{{ route('n2n.profile', ['handle' => $peerHandle]) }}" wire:navigate><x-nx.icon name="user" /> View profile</a>@endif
                                    <button type="button" role="menuitem" wire:click="$toggle('reporting')" @click="open = false"><x-nx.icon name="flag" /> Report</button>
                                    <button type="button" role="menuitem" wire:click="block" wire:confirm="Block this person? They will not be told." @click="open = false" class="nc__danger"><x-nx.icon name="shield-alert" /> Block</button>
                                </div>
                            </template>
                            <template x-if="mute">
                                <div>
                                    <button type="button" role="menuitem" @click="mute = false"><x-nx.icon name="chevron-left" /> Mute for&hellip;</button>
                                    <hr>
                                    <button type="button" role="menuitem" wire:click="toggleMute('8h')" @click="open = false; mute = false">8 hours</button>
                                    <button type="button" role="menuitem" wire:click="toggleMute('1w')" @click="open = false; mute = false">1 week</button>
                                    <button type="button" role="menuitem" wire:click="toggleMute('always')" @click="open = false; mute = false">Always</button>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif
            </header>

            @foreach ($chatNotices as $cn)
                <div class="flex items-start gap-2 border-b border-[rgb(var(--nx-line))] px-4 py-2 text-xs {{ $cn->tone === 'warn' ? 'bg-[rgb(var(--nx-warn)/0.12)] text-[rgb(var(--nx-text))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' }}" role="note" wire:key="cn-{{ $cn->id }}">
                    <p class="min-w-0 flex-1"><strong class="text-[rgb(var(--nx-text))]">{{ $cn->title }}</strong> {{ $cn->body }}</p>
                    @if ($cn->dismissible)<button type="button" wire:click="dismissChatNotice({{ $cn->id }})" wire:loading.attr="disabled" class="nc__chip" aria-label="Dismiss notice">Got it</button>@endif
                </div>
            @endforeach
            @if ($notice)<p class="bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2 text-xs text-[rgb(var(--nx-ok))]" role="status">{{ $notice }}</p>@endif
            @if ($error)<p class="bg-[rgb(var(--nx-bad)/0.1)] px-4 py-2 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif

            @if ($reporting)
                <form wire:submit="sendReport" class="space-y-2 border-b border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4">
                    <label class="text-xs font-medium text-[rgb(var(--nx-text-2))]" for="rp-reason">Why are you reporting them?</label>
                    <select id="rp-reason" wire:model="reportReason" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <option value="spam">Spam</option><option value="scam">Scam or fraud</option><option value="abuse">Abuse or harassment</option><option value="other">Something else</option>
                    </select>
                    <textarea wire:model="reportNote" rows="2" maxlength="1000" placeholder="Anything we should know (optional)" aria-label="Details" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></textarea>
                    <div class="flex gap-2"><button type="submit" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--sm">Report and block</button><button type="button" wire:click="$set('reporting', false)" class="text-xs text-[rgb(var(--nx-text-2))]">Cancel</button></div>
                    <p class="text-[11px] text-[rgb(var(--nx-text-2))]">We keep the last few messages from this person with your report so our team can review it.</p>
                </form>
            @endif

            @if ($isRequestForMe)
                <div class="nc__reqbar flex flex-wrap items-center gap-2 border-b border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-warn)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-text))]">
                    <span class="flex-1">{{ $peer?->name ?: 'Someone' }} is not in your contacts. Accept to chat, or decline.</span>
                    <button type="button" wire:click="accept" class="ns-cta ns-cta--pill ns-cta--sm">Accept</button>
                    <button type="button" wire:click="decline" class="rounded-lg px-3 py-1 text-xs font-medium">Decline</button>
                    <button type="button" wire:click="block" wire:confirm="Block this person?" class="rounded-lg px-3 py-1 text-xs font-medium">Block</button>
                </div>
            @elseif ($isRequestFromMe)
                <p class="border-b border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] px-4 py-2 text-xs text-[rgb(var(--nx-text-2))]">Message request sent. You can send a few more once they accept.</p>
            @endif

            @if ($tools['pinnedMessage'])
                <div class="nc__pinbar" wire:key="pinbar-{{ $tools['pinnedMessage']['id'] }}">
                    <x-nx.icon name="pin" style="font-size:14px" />
                    <button type="button" class="nc__pintext" x-on:click="window.n2nJump && window.n2nJump({{ $tools['pinnedMessage']['id'] }})">Pinned: {{ $tools['pinnedMessage']['body'] }}</button>
                    <button type="button" wire:click="togglePinMessage({{ $tools['pinnedMessage']['id'] }})" class="nn__iconbtn nc__pinx" aria-label="Unpin message"><x-nx.icon name="x" style="font-size:14px" /></button>
                </div>
            @endif
            @if ($tools['themeCss'])<style>{!! $tools['themeCss'] !!}</style>@endif
            <div class="nc__msgs" id="n2n-thread" @if ($tools['themeId']) data-nc-theme="{{ $tools['themeId'] }}" @endif x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" wire:key="thread-{{ $c }}-{{ count($messages) }}">
                <span class="nc__pill">Messages are encrypted in transit and at rest, but not end-to-end encrypted: Naara can see a message if it is reported or the law requires it. Links are not allowed.@unless ($pro) <a class="underline" href="{{ route('brand.get-listed') }}" wire:navigate>Unlock photos, videos and voice notes with Naara Pro.</a>@endunless</span>
                @if ($current && $current->disappear_after)<span class="nc__pill"><x-nx.icon name="clock" style="font-size:12px" /> Disappearing messages are on: new messages disappear after {{ \App\Services\N2N\Messaging\Disappearing::label($current->disappear_after) }}.</span>@endif
                @forelse ($messages as $m)
                    @foreach ($m['lines'] ?? [] as $l)@include('livewire.n2n.partials.channel-line', ['l' => $l])@endforeach
                    <div class="flex items-end gap-1 {{ $m['mine'] ? 'justify-end' : 'justify-start' }}" wire:key="m-{{ $m['id'] }}">
                        @if ($m['mine'] && ! $m['deleted'])@include('n2n.bubble-menu', ['m' => $m])@endif
                        <div id="m-{{ $m['id'] }}" data-at="{{ $m['at']?->timestamp }}" class="ncb {{ $m['mine'] ? 'ncb--out rounded-ee-md' : 'ncb--in rounded-es-md' }} max-w-[80%] break-words rounded-2xl px-3 py-2 text-sm" tabindex="0"
                             @unless ($m['deleted']) data-ctx="{{ json_encode(['k' => 'msg', 'id' => $m['id'], 'mine' => $m['mine'], 'starred' => $m['starred'], 'pinned' => ! empty($m['pinned']), 'text' => trim($m['body']) !== '', 'body' => mb_substr($m['body'], 0, 4000), 'canDelete' => $m['canDelete'], 'canEdit' => $m['canEdit'], 'canForward' => $m['canForward'], 'reaction' => $m['myReaction']], JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endunless>
                            @if (! empty($m['forwarded']))<span class="ncb__fwd"><x-nx.icon name="share" style="font-size:11px" /> Forwarded</span>@endif
                            @if ($m['deleted'])
                                <span class="italic opacity-80">This message was deleted.</span>
                            @else
                                @if (! empty($m['reply']))
                                    <button type="button" class="ncb__quote" @if (empty($m['reply']['gone'])) x-on:click="window.n2nJump && window.n2nJump({{ $m['reply']['id'] }})" @endif>
                                        @if (empty($m['reply']['gone']))<b>{{ $m['reply']['who'] }}</b>@endif<span>{{ $m['reply']['text'] }}</span>
                                    </button>
                                @endif
                                @if (! empty($m['quote']) && ($m['quote']['source'] ?? '') === 'brand_card')
                                    @php($q = $m['quote'])
                                    @php($qaddr = preg_match('/^[0-9A-Za-z*~$=-]{3,30}$/', (string) ($q['address'] ?? '')) === 1 ? $q['address'] : null)
                                    <span class="ncb__upd ncb__bcard" data-brand-card>
                                        <span class="ncb__upd-thumb" aria-hidden="true">@if (! empty($q['media']) && str_starts_with($q['media'], '/'))<img src="{{ $q['media'] }}" alt="" loading="lazy" onerror="this.remove()">@else<x-nx.icon name="briefcase" />@endif</span>
                                        <span class="ncb__upd-txt"><b>{{ $q['brand'] ?? 'A brand' }}</b>@if (! empty($q['caption']))<span>{{ $q['caption'] }}</span>@endif<span class="ncb__bcard__tag">Business Card</span>@if ($qaddr)<a href="{{ route('business-card.show', $qaddr) }}" class="ncb__bcard__open" target="_blank" rel="noopener">Open card</a>@endif</span>
                                    </span>
                                @elseif (! empty($m['quote']))
                                    @php($q = $m['quote'])
                                    <span class="ncb__upd" data-update-card>
                                        <span class="ncb__upd-thumb" aria-hidden="true">
                                            @if (! empty($q['media']) && ($q['type'] ?? '') === 'image')<img src="{{ $q['media'] }}" alt="" loading="lazy" onerror="this.remove()">
                                            @elseif (! empty($q['media']) && ($q['type'] ?? '') === 'video')<x-nx.icon name="play" />
                                            @elseif (! empty($q['swatch']))<span class="ncb__upd-sw" style="background: {{ \App\Services\N2N\Updates\StatusService::cleanColor($q['swatch']['bg'] ?? null) ?? 'rgb(var(--nx-surface-2))' }}">{{ $q['swatch']['text'] }}</span>
                                            @elseif (($q['type'] ?? '') === 'voice')<x-nx.icon name="mic" />
                                            @else<x-nx.icon name="image" />@endif
                                        </span>
                                        <span class="ncb__upd-txt"><b>Replying to {{ ($q['source'] ?? '') === 'status' ? 'a status' : 'an update' }} from {{ $q['brand'] ?? 'a brand' }}</b>@if (! empty($q['caption']))<span>{{ $q['caption'] }}</span>@endif</span>
                                    </span>
                                @endif
                                @if ($m['type'] === 'event' && ! $m['deleted'])
                                    @php($ev = $m['event'])
                                    @if ($ev['gone'])
                                        <p class="nev__gone"><x-nx.icon name="calendar" style="font-size:14px" /> Event removed</p>
                                    @else
                                        <div class="nev {{ $ev['cancelled'] ? 'is-cancelled' : '' }}" wire:key="ev-{{ $ev['id'] }}-{{ $ev['mine'] }}-{{ $ev['theirs'] }}-{{ $ev['cancelled'] ? 1 : 0 }}-{{ md5($ev['starts'].$ev['title'].$ev['description']) }}" role="group" aria-label="Event: {{ $ev['title'] }}">
                                            <p class="nev__k"><x-nx.icon name="calendar" style="font-size:14px" /> Event @if ($ev['cancelled'])<b class="nev__flag">Cancelled</b>@elseif ($ev['past'])<b class="nev__flag">Ended</b>@endif</p>
                                            <p class="nev__t">{{ $ev['title'] }}</p>
                                            <p class="nev__w"><time datetime="{{ $ev['starts'] }}" x-data x-init="$el.textContent = new Date($el.dateTime).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })">{{ \Illuminate\Support\Carbon::parse($ev['starts'])->setTimezone($ev['tz'])->format('D j M, H:i') }} ({{ $ev['tz'] }})</time> <small>in your local time</small></p>
                                            @if ($ev['description'])<p class="nev__d">{{ $ev['description'] }}</p>@endif
                                            @php($lab = ['going' => 'Going', 'maybe' => 'Maybe', 'declined' => 'Can\'t go', 'no_response' => 'Not responded'])
                                            <p class="nev__r">You: <b>{{ $lab[$ev['mine']] }}</b> · {{ $peer?->name ?: 'Them' }}: <b>{{ $lab[$ev['theirs']] }}</b></p>
                                            @if (! $ev['cancelled'] && ! $ev['past'])
                                                <div class="nev__a" role="group" aria-label="Your answer">
                                                    @foreach (['going' => 'Going', 'maybe' => 'Maybe', 'declined' => 'Can\'t go'] as $k => $t)
                                                        <button type="button" class="nev__btn {{ $ev['mine'] === $k ? 'is-on' : '' }}" aria-pressed="{{ $ev['mine'] === $k ? 'true' : 'false' }}" wire:click="answerEvent({{ $ev['id'] }}, '{{ $k }}')" wire:loading.attr="disabled" wire:target="answerEvent">{{ $t }}</button>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="nev__a">
                                                <a class="nev__btn" href="{{ $ev['ics'] }}" download>Add to calendar</a>
                                                <a class="nev__btn" href="{{ $ev['gcal'] }}" target="_blank" rel="noopener noreferrer">Google Calendar</a>
                                                @if ($ev['joinable'] && $peer?->naara_id && \App\Services\N2N\Calls\CallsAvailability::enabled())<button type="button" class="nev__btn nev__btn--call" data-n2n-call="{{ $peer->naara_id }}"><x-nx.icon name="phone" style="font-size:14px" /> Call</button>@endif
                                                @if ($ev['creator'] && ! $ev['cancelled'] && ! $ev['past'])
                                                    <button type="button" class="nev__btn" wire:click="editEvent({{ $ev['id'] }})">Edit</button>
                                                    <button type="button" class="nev__btn nev__btn--stop" wire:click="cancelEvent({{ $ev['id'] }})" wire:confirm="Cancel this event for both of you?">Cancel event</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                @if ($m['type'] === 'live' && ! $m['deleted'])
                                    <div class="nlive" data-live-id="{{ $m['id'] }}" data-live-mine="{{ $m['mine'] ? 1 : 0 }}" wire:ignore>
                                        <p class="nlive__t"><span class="nlive__dot" aria-hidden="true"></span><x-nx.icon name="map-pin" style="font-size:15px" /> <b>Live location</b></p>
                                        <p class="nlive__s" data-live-status aria-live="polite">Checking…</p>
                                        <div class="nlive__a">
                                            <a class="nlive__btn" data-live-open href="#" target="_blank" rel="noopener noreferrer" hidden>Open in Maps</a>
                                            @if ($m['mine'])<button type="button" class="nlive__btn nlive__btn--stop" data-live-stop hidden>Stop sharing</button>@endif
                                        </div>
                                        @if ($m['mine'])<p class="nlive__n" data-live-note hidden>Your position is shared while Naara stays open on this device. It ends when the time is up or you stop.</p>@endif
                                    </div>
                                @endif
                                @if (! empty($m['poll']))
                                    @php($pl = $m['poll'])
                                    <div class="npoll" wire:key="poll-{{ $pl['id'] }}-{{ $pl['total'] }}-{{ $pl['closed'] ? 1 : 0 }}" role="group" aria-label="Poll: {{ $pl['question'] }}">
                                        <p class="npoll__q"><x-nx.icon name="bars" style="font-size:15px" /> {{ $pl['question'] }}</p>
                                        <p class="npoll__hint">{{ $pl['closed'] ? 'Poll closed' : ($pl['multiple'] ? 'Choose one or more' : 'Choose one') }}</p>
                                        @foreach ($pl['options'] as $o)
                                            <button type="button" class="npoll__opt {{ $o['mine'] ? 'is-mine' : '' }}" @if (! $pl['closed']) wire:click="vote({{ $pl['id'] }}, {{ $o['id'] }})" wire:loading.attr="disabled" wire:target="vote({{ $pl['id'] }}, {{ $o['id'] }})" @else disabled @endif aria-pressed="{{ $o['mine'] ? 'true' : 'false' }}" style="--p: {{ $o['pct'] }}%">
                                                <i class="npoll__bar" aria-hidden="true"></i>
                                                <span class="npoll__dot" aria-hidden="true"></span>
                                                <span class="npoll__t">{{ $o['text'] }}</span>
                                                <b class="npoll__n">{{ $o['votes'] }}<small> · {{ $o['pct'] }}%</small></b>
                                            </button>
                                        @endforeach
                                        <p class="npoll__foot">{{ $pl['voters'] }} {{ $pl['voters'] === 1 ? 'person has' : 'people have' }} voted @if ($m['mine'] && ! $pl['closed']) · <button type="button" class="underline" wire:click="closePoll({{ $pl['id'] }})" wire:confirm="Close this poll? Nobody can vote after it is closed.">Close poll</button>@endif</p>
                                    </div>
                                @endif
                                @php($stills = collect($m['files'])->filter(fn ($x) => $x['kind'] === 'image' && empty($x['gif']))->values())
                                @if ($stills->count() >= 2)
                                    @php($shown = $stills->take(5))
                                    <div class="nimgs nimgs--{{ $shown->count() }}" wire:key="grid-{{ $m['id'] }}">
                                        @foreach ($shown as $gi => $f)
                                            <a href="{{ route('n2n.media', $f['id']) }}" data-nlb data-nlb-alt="Photo {{ $gi + 1 }} of {{ $stills->count() }}" data-nlb-dl="{{ route('n2n.media', ['attachment' => $f['id'], 'download' => 1]) }}" wire:key="gi-{{ $f['id'] }}">
                                                <img src="{{ route('n2n.media', ['attachment' => $f['id'], 'thumb' => 1]) }}" alt="Photo {{ $gi + 1 }} of {{ $stills->count() }}" loading="lazy">
                                                @if ($loop->last && $stills->count() > 5)<span class="nimgs__more" aria-hidden="true">+{{ $stills->count() - 4 }}</span>@endif
                                            </a>
                                        @endforeach
                                        @foreach ($stills->slice(5) as $f)<a href="{{ route('n2n.media', $f['id']) }}" data-nlb hidden wire:key="gh-{{ $f['id'] }}" data-nlb-dl="{{ route('n2n.media', ['attachment' => $f['id'], 'download' => 1]) }}"></a>@endforeach
                                    </div>
                                @endif
                                @foreach ($m['files'] as $f)
                                    @continue($stills->count() >= 2 && $f['kind'] === 'image' && empty($f['gif']))
                                    <div class="mb-1" wire:key="f-{{ $f['id'] }}">
                                        @if ($f['kind'] === 'image')
                                            <a href="{{ route('n2n.media', $f['id']) }}" target="_blank" rel="noopener" data-nlb data-nlb-dl="{{ route('n2n.media', ['attachment' => $f['id'], 'download' => 1]) }}"><img src="{{ route('n2n.media', ! empty($f['gif']) ? ['attachment' => $f['id']] : ['attachment' => $f['id'], 'thumb' => 1]) }}" alt="{{ ! empty($f['gif']) ? 'GIF' : 'Photo' }}" loading="lazy" class="max-h-64 rounded-xl"></a>
                                        @elseif ($f['kind'] === 'video')
                                            <video controls preload="metadata" class="max-h-64 rounded-xl" src="{{ route('n2n.media', $f['id']) }}"></video>
                                        @elseif ($f['kind'] === 'doc')
                                            <a class="ndoc" href="{{ route('n2n.media', ['attachment' => $f['id'], 'download' => 1]) }}" download>
                                                <span class="ndoc__ic" aria-hidden="true"><x-nx.icon name="file" /><b>{{ $f['ext'] ?: 'FILE' }}</b></span>
                                                <span class="ndoc__t"><span class="ndoc__n">{{ $f['name'] ?? 'Document' }}</span><small>{{ $f['ext'] ?: 'File' }} · {{ $f['mb'] }} MB · tap to download</small></span>
                                                <span class="ndoc__dl" aria-hidden="true"><x-nx.icon name="dl" /></span>
                                            </a>
                                        @else
                                            @if ($f['kind'] === 'audio')<p class="ndoc__n ndoc__n--audio"><x-nx.icon name="volume" style="font-size:14px" /> {{ $f['name'] ?? 'Audio file' }}</p>@endif
                                            <div class="nvp" wire:ignore data-nvp data-id="{{ $f['id'] }}" data-src="{{ route('n2n.media', $f['id']) }}" data-dur="{{ (int) round((float) ($f['seconds'] ?? 0)) }}" data-mine="{{ $m['mine'] ? 1 : 0 }}" @if (! empty($f['peaks'])) data-peaks="{{ implode(',', array_map('intval', $f['peaks'])) }}" @endif>
                                                <button type="button" class="nvp__btn" aria-label="Play voice message"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></button>
                                                <div class="nvp__body" role="slider" tabindex="0" aria-label="Voice message position" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                                    <canvas class="nvp__wave" data-no-swipe aria-hidden="true"></canvas>
                                                    <div class="nvp__row"><span class="nvp__time">0:00</span><i class="nvp__dot" hidden aria-label="Not played yet"></i><button type="button" class="nvp__speed" hidden aria-label="Playback speed">1x</button></div>
                                                </div>
                                            </div>
                                            @if ($f['transcript'])<details class="mt-1 text-xs opacity-90"><summary>Transcript</summary>{{ $f['transcript'] }}</details>@endif
                                        @endif
                                        @if ($f['kind'] !== 'doc')<a class="text-[11px] underline opacity-80" href="{{ route('n2n.media', ['attachment' => $f['id'], 'download' => 1]) }}">Download ({{ $f['mb'] }} MB)</a>@endif
                                    </div>
                                @endforeach
                                @if (trim($m['body']) !== '' && empty($m['poll']) && $m['type'] !== 'live' && $m['type'] !== 'event'){!! \App\Support\N2N\ChatFormat::render($m['body'], $tools['mentionTokens']) !!}@endif
                            @endif<span class="ncb__meta ms-2 inline-flex items-center gap-0.5 align-bottom text-[10px]">@if ($m['starred'])<x-nx.icon name="star" style="font-size:11px" aria-label="Starred" />@endif@if (! empty($m['edited']))<i class="ncb__edited">edited</i> @endif{{ $m['at']?->format('H:i') }}@if ($m['mine'])<span class="ms-0.5 inline-flex" role="img" aria-label="{{ $m['state'] }}"><x-nx.icon name="check" style="font-size:11px" />@if ($m['state'] !== 'sent')<x-nx.icon name="check" style="font-size:11px;margin-inline-start:-6px" />@endif</span>@endif</span>
                            @if (! empty($m['reactions']))
                                <span class="ncb__reacts" role="group" aria-label="Reactions">
                                    @foreach ($m['reactions'] as $code => $n)
                                        <button type="button" wire:click="react({{ $m['id'] }}, '{{ $code }}')" class="ncb__react {{ $m['myReaction'] === $code ? 'is-mine' : '' }}" aria-label="{{ $code }} {{ $n }}">{{ \App\Services\N2N\Messaging\MessageActions::REACTIONS[$code] ?? '' }}@if ($n > 1)<b>{{ $n }}</b>@endif</button>
                                    @endforeach
                                </span>
                            @endif
                        </div>
                        @if (! $m['mine'] && ! $m['deleted'])@include('n2n.bubble-menu', ['m' => $m])@endif
                    </div>
                @empty
                    @unless ($hasChannelLines ?? false)<p class="nc__empty text-sm text-[rgb(var(--nx-text-2))]">Say hello. Each message costs {{ $cost }} credit.</p>@endunless
                @endforelse
                @foreach ($tailLines ?? [] as $l)@include('livewire.n2n.partials.channel-line', ['l' => $l])@endforeach
            </div>

            <div class="nc__foot" x-data x-init="const t = $el.closest('.nc__thread'); const sync = () => t.style.setProperty('--nc-foot-h', $el.offsetHeight + 'px'); sync(); const ro = new ResizeObserver(sync); ro.observe($el)">
            @if ($phone ?? null)
                <div class="nc__phone" data-by-phone>
                    <a href="{{ $phone['sms'] }}" wire:navigate class="nc__phone__btn" data-by-phone-sms><x-nx.icon name="message-circle" style="font-size:14px" /><span>Text by SMS</span></a>
                    @if ($phone['call'])<a href="{{ $phone['call'] }}" wire:navigate class="nc__phone__btn" data-by-phone-call><x-nx.icon name="phone" style="font-size:14px" /><span>Call</span></a>@endif
                </div>
            @endif
            @if ($replyBar)
                <div class="nc__replybar" wire:key="replybar-{{ $replyBar['id'] }}">
                    <x-nx.icon name="reply" style="font-size:14px" />
                    <span class="min-w-0 flex-1 truncate">Replying to <b>{{ $replyBar['who'] }}</b>: {{ $replyBar['text'] }}</span>
                    <button type="button" wire:click="clearReply" class="nn__iconbtn" aria-label="Cancel reply"><x-nx.icon name="x" style="font-size:14px" /></button>
                </div>
            @endif
            @if ($composerOn)
                @php($peerMention = $peer && trim((string) $peer->name) !== '' ? [['label' => $peer->name, 'insert' => explode(' ', trim($peer->name))[0]]] : [])
                <div class="nc__dock" data-n2n-reply="{{ $replyBar ? $replyBar['id'] : '' }}" wire:key="composer-{{ $convoKey }}">
                    <x-composer surface="naara_chat" mode="chat" :features="['format', 'emoji', 'hints', 'snippets', 'schedule', 'gif', 'attach', 'voice', 'sendmenu', 'slash', 'colon', 'keys', 'expand', 'holdmic']" :attach-kinds="['media', 'camera', 'video', 'document', 'audio', 'contact', 'location', 'poll']"
                                :accept="['media' => 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'camera' => 'image/*', 'video' => 'video/mp4,video/webm,video/quicktime', 'document' => '.pdf,.docx,.xlsx,.pptx,.txt,.csv,application/pdf,text/plain', 'audio' => 'audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg,audio/aac,.mp3,.m4a,.wav,.ogg,.aac']"
                                :endpoint="$endpoint" :convo-id="$convoKey" :max-chars="4000" :max-files="4" :max-file-mb="25"
                                :extra="['locked' => $pro ? [] : ['voice', 'gif', 'files'], 'lockedKinds' => $pro ? [] : ['media', 'camera', 'video', 'document', 'audio'], 'lockedUrl' => route('brand.get-listed'), 'lockedText' => 'Photos, videos, documents, audio files, GIFs and voice notes are part of Naara Pro. Text, emoji, formatting, scheduling, contacts and locations are free.', 'lockedCta' => 'Get Naara Pro', 'liveLocation' => false, 'disappearing' => \App\Services\N2N\Messaging\Disappearing::enabled(), 'liveMinutes' => \App\Services\N2N\Messaging\LiveLocations::allowed(), 'mentions' => $peerMention]"
                                :placeholder="'Message (' . $cost . ' credit)'" />
                </div>
            @endif
            </div>
            @if ($current && $panel)@include('n2n.chat-panels')@endif
        @else
            <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[rgb(var(--nx-teal)/0.15)] text-[rgb(var(--nx-teal-ink))]"><x-nx.icon name="message-circle" /></span>
                <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Chat with anyone on Naara</h2>
                <p class="mt-1 max-w-xs text-sm text-[rgb(var(--nx-text-2))]">Pick a conversation, or start a new one with a Naara ID, username, phone or email.</p>
            </div>
        @endif
    </section>
</div>
