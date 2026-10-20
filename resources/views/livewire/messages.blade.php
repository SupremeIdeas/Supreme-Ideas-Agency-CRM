{{-- nx:converted --}}
{{-- Messages: the omni-channel inbox, in the Naara chat look (same .nc / .ncb parts, so every skin's chat signature and contrast file apply).
     Three resizable panels on desktop (conversations + contacts | thread | contact info), one pane at a time on a phone. SMS, WhatsApp, Telegram and
     Instagram share one list with a coloured channel marker; a channel reply uses the Naara composer, an SMS reply keeps the send modal (billing and
     provider routing unchanged). In the Business shell the header/bar step aside while a thread is open (data-bz-thread). Tokens only; RTL-safe. --}}
@php
    $chMono = ['sms' => 'SMS', 'whatsapp' => 'WA', 'telegram' => 'TG', 'instagram' => 'IG'];
    $chName = fn ($c) => $c === 'sms' ? 'SMS' : \App\Models\ChannelConnection::label($c);
    $isBiz = $shell === 'business';
@endphp
<div class="bz-root">{{-- single Livewire root: x-nx.page opens with a conditional, which would otherwise take the wire attributes --}}
<x-nx.page :bare="$isBiz" class="ns-msg-page" wire:ignore.self
    x-data="{
        l: Number(localStorage.getItem('nx_msg_l')) || 340, r: Number(localStorage.getItem('nx_msg_r')) || 300, listOpen: localStorage.getItem('nx_msg_list') !== '0', tx: 0, ty: 0, tt: 0, ok: false, drag: false,
        info: localStorage.getItem('nx_msg_info') === null ? window.innerWidth >= 1500 : localStorage.getItem('nx_msg_info') === '1', pane: '{{ $active ? 'thread' : 'list' }}',
        clamp(v, lo, hi) { return Math.min(Math.max(lo, hi), Math.max(lo, v)); },
        room(side) { const w = this.$el.querySelector('.bz-msg').clientWidth; return w - 24 - 340 - (side === 'l' ? (this.info ? this.r : 0) : this.l); },
        phone() { return window.innerWidth < 1024; },
        toggleList() { this.listOpen = !this.listOpen; this.save(); },
        toggleInfo() { if (this.phone()) { this.info = true; this.pane = this.pane === 'info' ? 'thread' : 'info'; } else { this.info = !this.info; } this.save(); },
        swipeStart(e) {
            const t = e.touches[0];
            this.ok = this.phone() && ! e.target.closest('[data-noswipe], textarea, input, audio, naara-composer, .cc-box, .cc-panel');
            this.tx = t.clientX; this.ty = t.clientY; this.tt = Date.now(); this.drag = false;
        },
        swipeMove(e) {
            if (! this.ok || this.pane !== 'thread') return;
            const t = e.touches[0], rtl = document.dir === 'rtl', dx = (t.clientX - this.tx) * (rtl ? -1 : 1), dy = t.clientY - this.ty;
            if (! this.drag && (Math.abs(dx) < 12 || Math.abs(dy) > Math.abs(dx))) return;
            if (dx <= 0) return;
            this.drag = true; this.$el.classList.add('is-dragging');
            this.$el.querySelector('.bz-msg__thread').style.setProperty('--nc-x', (rtl ? -1 : 1) * Math.min(dx, this.$el.clientWidth) + 'px');
        },
        swipeEnd(e) {
            const th = this.$el.querySelector('.bz-msg__thread');
            this.$el.classList.remove('is-dragging'); th.style.removeProperty('--nc-x');
            if (! this.ok) return; this.ok = false;
            const t = e.changedTouches[0], rtl = document.dir === 'rtl', dx = (t.clientX - this.tx) * (rtl ? -1 : 1), dy = t.clientY - this.ty, fast = Date.now() - this.tt < 450;
            if (Math.abs(dy) > Math.abs(dx) * .7) return;
            if (dx > 0 && (dx > 90 || (fast && dx > 50))) { if (this.pane === 'info') this.pane = 'thread'; else if (this.pane === 'thread') { this.pane = 'list'; this.$wire.closeThread(); } }
            else if (dx < -60) {
                if (this.pane === 'list' && this.$wire.active) this.pane = 'thread';
                else if (this.pane === 'thread' && this.$wire.active) { this.info = true; this.pane = 'info'; this.save(); }
            }
        },
        save() { try { localStorage.setItem('nx_msg_list', this.listOpen ? '1' : '0'); localStorage.setItem('nx_msg_l', this.l); localStorage.setItem('nx_msg_r', this.r); localStorage.setItem('nx_msg_info', this.info ? '1' : '0'); } catch (e) {} },
        drag(e, side) {
            const sign = document.dir === 'rtl' ? -1 : 1, x0 = e.clientX, w0 = side === 'l' ? this.l : this.r;
            const move = (ev) => { const dx = (ev.clientX - x0) * sign; if (side === 'l') this.l = this.clamp(w0 + dx, 260, Math.min(520, this.room('l'))); else this.r = this.clamp(w0 - dx, 240, Math.min(460, this.room('r'))); };
            const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); document.body.classList.remove('ns-msg-dragging'); this.save(); };
            document.body.classList.add('ns-msg-dragging'); window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
        },
        nudge(side, d) { if (side === 'l') this.l = this.clamp(this.l + d, 260, Math.min(520, this.room('l'))); else this.r = this.clamp(this.r - d, 240, Math.min(460, this.room('r'))); this.save(); },
    }"
    x-init="$wire.$watch('active', v => { pane = v ? 'thread' : 'list' })">

    <div class="bz-msg {{ $isBiz ? '' : 'bz-msg--page' }}" data-bz-chat wire:ignore.self
         :class="[info ? '' : 'bz-msg--noinfo', listOpen ? '' : 'bz-msg--nolist', 'bz-msg--' + pane]" :style="{ '--msg-l': (listOpen ? l : 0) + 'px', '--msg-r': r + 'px' }"
         @touchstart.passive="swipeStart($event)" @touchmove.passive="swipeMove($event)" @touchend.passive="swipeEnd($event)" @touchcancel.passive="swipeEnd($event)">
        @if ($active)<i hidden data-bz-thread></i>@endif

        {{-- ============ LEFT: conversations / contacts ============ --}}
        <section class="nc__list bz-msg__panel bz-msg__list" :class="pane !== 'list' ? 'is-hidden' : ''" aria-label="{{ __('messages.conversations') }}">
            <header class="bz-head">
                <div>
                    <h1 class="bz-title">{{ __('messages.title') }}</h1>
                    @if ($workspaceName)<p class="bz-sub">{{ $workspaceName }}</p>@endif
                </div>
                <button type="button" class="bz-iconbtn bz-iconbtn--cta" wire:click="$dispatch('open-send-message', { to: '', name: '' })" aria-label="{{ __('messages.new') }}" title="{{ __('messages.new') }}"><x-nx.icon name="edit" /></button>
            </header>

            @if ($inboxLive && $tab === 'chats')
                {{-- Channel strip as a slider: arrows (mouse), drag (mouse), swipe (touch), fades at the edges that still have more. --}}
                <div class="bz-slide" data-noswipe :class="{ 'has-l': l, 'has-r': r }"
                     x-data="{ l: false, r: false, dragging: false, moved: false, sx: 0, sl: 0,
                        upd() { const e = this.$refs.t; if (! e) return; const x = Math.abs(e.scrollLeft), max = e.scrollWidth - e.clientWidth; this.l = x > 2; this.r = x < max - 2; },
                        go(d) { const e = this.$refs.t, s = document.dir === 'rtl' ? -1 : 1; e.scrollBy({ left: s * d * Math.max(160, e.clientWidth * .7), behavior: 'smooth' }); },
                        down(ev) { if (ev.pointerType !== 'mouse' || ev.button !== 0) return; this.sx = ev.clientX; this.sl = this.$refs.t.scrollLeft; this.dragging = true; this.moved = false; },
                        move(ev) { if (! this.dragging) return; const dx = ev.clientX - this.sx; if (Math.abs(dx) > 4) this.moved = true; if (this.moved) this.$refs.t.scrollLeft = this.sl - dx; },
                        up() { this.dragging = false; setTimeout(() => { this.moved = false; }, 0); },
                     }"
                     x-init="$nextTick(() => upd()); new ResizeObserver(() => upd()).observe($refs.t)">
                    <button type="button" class="bz-slide__btn bz-slide__btn--prev" x-show="l" x-cloak style="display:none" @click="go(-1)" aria-label="Scroll channels back"><x-nx.icon name="chevron-left" /></button>
                    <div class="bz-chans" x-ref="t" role="group" aria-label="{{ __('messages.channels') }}" :class="dragging && moved ? 'is-dragging' : ''"
                         @scroll.passive="upd()" @pointerdown="down($event)" @pointermove="move($event)" @pointerup.window="up()" @click.capture="if (moved) { $event.stopPropagation(); $event.preventDefault() }">
                        @foreach ($channelTabs as $key => $label)
                            @php($n = $key === 'all' ? array_sum($unreadByChannel) : ($unreadByChannel[$key] ?? 0))
                            <button type="button" wire:click="$set('channel','{{ $key }}')" aria-pressed="{{ $channel === $key ? 'true' : 'false' }}" @if ($key !== 'all') data-ch="{{ $key }}" @endif @class(['bz-chan', 'is-on' => $channel === $key])>
                                <span class="bz-chan__dot" aria-hidden="true">@if ($key === 'all')<x-nx.icon name="inbox" style="font-size:13px" />@else<x-business.channel-logo :channel="$key" />@endif</span>
                                <span>{{ $label }}</span>
                                @if ($n > 0)<b class="bz-chan__n">{{ $n > 99 ? '99+' : $n }}</b>@endif
                            </button>
                        @endforeach
                    </div>
                    <button type="button" class="bz-slide__btn bz-slide__btn--next" x-show="r" x-cloak style="display:none" @click="go(1)" aria-label="Scroll channels forward"><x-nx.icon name="chevron-right" /></button>
                </div>
            @endif

            <div class="nc__chips" role="tablist">
                <button type="button" role="tab" wire:click="$set('tab','chats')" aria-selected="{{ $tab === 'chats' ? 'true' : 'false' }}" @class(['nc__chip', 'is-on' => $tab === 'chats'])>{{ __('messages.chats') }}</button>
                <button type="button" role="tab" wire:click="$set('tab','contacts')" aria-selected="{{ $tab === 'contacts' ? 'true' : 'false' }}" @class(['nc__chip', 'is-on' => $tab === 'contacts'])>{{ __('messages.contacts') }}</button>
            </div>
            <div class="nc__search">
                <label class="relative block">
                    <span class="sr-only">{{ __('messages.search') }}</span>
                    <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('messages.search') }}" class="w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] py-2 ps-4 pe-4 text-sm text-[rgb(var(--nx-text))]">
                </label>
            </div>

            <ul class="nc__rows" wire:loading.class="opacity-60" wire:target="search,channel,tab">
                @if ($tab === 'chats')
                    @forelse ($threads as $thread)
                        @php($nm = $names[$thread->counterpart_number] ?? null)
                        <li wire:key="thread-{{ $thread->id }}">
                            <button type="button" wire:click="openThread('{{ $thread->counterpart_number }}', '{{ $thread->channel }}')" @click="pane = 'thread'" data-ch="{{ $thread->channel }}"
                                    @class(['bz-row', 'is-on' => $active === $thread->counterpart_number && $activeChannel === $thread->channel, 'is-unread' => $thread->unread_count > 0])>
                                <span class="bz-av" aria-hidden="true">{{ $nm ? mb_strtoupper(mb_substr($nm, 0, 1)) : '#' }}@if ($inboxLive)<i class="bz-av__ch"><x-business.channel-logo :channel="$thread->channel" /></i>@endif</span>
                                <span class="bz-row__main">
                                    <span class="bz-row__top"><b>{{ $nm ?: $thread->counterpart_number }}</b><time>{{ optional($thread->last_at)->diffForHumans(null, true, true) }}</time></span>
                                    <span class="bz-row__bot">
                                        <span class="bz-row__prev">@if ($thread->last_direction === 'out')<i>{{ __('messages.you') }}:</i> @endif{{ $thread->last_body }}</span>
                                        @if ($thread->unread_count > 0)<b class="bz-badge" aria-label="{{ $thread->unread_count }}">{{ $thread->unread_count }}</b>@endif
                                    </span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="bz-empty">
                            <span class="bz-empty__ic"><x-nx.icon name="inbox" /></span>
                            <h2>{{ __('messages.empty_title') }}</h2>
                            <p>{{ \App\Support\BrandSettings::rebrand(__('messages.empty_text')) }}</p>
                        </li>
                    @endforelse
                @else
                    @forelse ($contacts as $c)
                        <li wire:key="contact-{{ $c->id }}">
                            <button type="button" wire:click="startWith('{{ $c->phone_number }}')" @click="pane = 'thread'" class="bz-row" data-ch="sms">
                                <span class="bz-av" aria-hidden="true">{{ $c->initials() }}</span>
                                <span class="bz-row__main">
                                    <span class="bz-row__top"><b>{{ $c->name }}</b>@if (class_exists(\App\Services\N2N\Identity\ContactLinker::class) && $c->e164 !== null)<span class="bz-tag {{ $c->linked_user_id ? 'is-on' : '' }}" data-contact-label="{{ $c->linked_user_id ? 'naara' : 'not-naara' }}">{{ $c->linked_user_id ? 'On Naara' : 'Not on Naara' }}</span>@endif @if ($c->is_favorite)<x-nx.icon name="star" style="font-size:14px;color:rgb(var(--nx-gold-ink))" />@endif</span>
                                    <span class="bz-row__bot"><span class="bz-row__prev">{{ $c->phone_number }}</span></span>
                                </span>
                                <span class="bz-go" aria-hidden="true"><x-nx.icon name="send" /></span>
                            </button>
                        </li>
                    @empty
                        <li class="bz-empty">
                            <span class="bz-empty__ic"><x-nx.icon name="users" /></span>
                            <h2>{{ __('messages.no_contacts') }}</h2>
                            <p>{{ __('messages.no_contacts_text') }}</p>
                            <a href="{{ route('numbers.contacts') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm">{{ __('messages.open_contacts') }}</a>
                        </li>
                    @endforelse
                @endif
            </ul>
        </section>

        <div class="bz-msg__grip" role="separator" aria-orientation="vertical" aria-label="{{ __('messages.resize_list') }}" tabindex="0" title="Drag to resize, double-click to hide or show the conversations"
             @dblclick="toggleList()" @pointerdown.prevent="drag($event, 'l')" @keydown.arrow-left.prevent="nudge('l', -16)" @keydown.arrow-right.prevent="nudge('l', 16)" :aria-valuenow="l"><i></i></div>

        {{-- ============ CENTRE: thread ============ --}}
        <section class="nc__thread bz-msg__panel bz-msg__thread" :class="pane === 'list' ? 'is-hidden' : ''" aria-label="{{ __('messages.thread') }}" @if ($active) data-ch="{{ $activeChannel }}" @endif>
            @if ($active)
                @php($activeName = $names[$active] ?? null)
                <header class="nc__thead">
                    <button type="button" class="bz-iconbtn bz-back" wire:click="closeThread" @click="pane = 'list'" aria-label="{{ __('messages.back') }}"><x-nx.icon name="chevron-left" /></button>
                    <button type="button" class="bz-iconbtn bz-listbtn" @click="toggleList()" :aria-expanded="listOpen" :title="listOpen ? 'Hide conversations' : 'Show conversations'" :aria-label="listOpen ? 'Hide conversations' : 'Show conversations'"><x-nx.icon name="list" /></button>
                    <button type="button" class="bz-who" @click="toggleInfo()" :aria-expanded="info">
                        <span class="bz-av" aria-hidden="true">{{ $activeName ? mb_strtoupper(mb_substr($activeName, 0, 1)) : '#' }}</span>
                        <span style="min-width:0"><b>{{ $activeName ?: $active }}</b><small>@if ($activeName){{ $active }} · @endif{{ $chName($activeChannel) }}</small></span>
                    </button>
                    @if ($activeChannel === 'sms')
                        <a href="{{ route('numbers.dialer', ['to' => $active]) }}" wire:navigate class="bz-iconbtn" aria-label="{{ __('messages.call') }}"><x-nx.icon name="phone" /></a>
                    @else
                        <span class="bz-chtag" data-ch="{{ $activeChannel }}"><x-business.channel-logo :channel="$activeChannel" />{{ $chName($activeChannel) }}</span>
                    @endif
                    <button type="button" class="bz-iconbtn bz-infobtn" @click="toggleInfo()" :class="info ? 'is-on' : ''" aria-label="{{ __('messages.info') }}"><x-nx.icon name="info" /></button>
                </header>

                @if (! empty($brain))
                    <div class="bz-brain" @if ($brain['pending']) wire:poll.2s @endif>
                        <div class="bz-brain__bar">
                            <span class="bz-brain__t"><x-nx.icon name="sparkles" /> NaaraBrain</span>
                            <div class="bz-brain__acts" role="group" aria-label="NaaraBrain assistant">
                                @foreach (['draft' => 'Draft reply', 'summary' => 'Summarise', 'tasks' => 'Follow-ups'] as $ak => $al)
                                    <button type="button" wire:click="brainAsk('{{ $ak }}')" wire:loading.attr="disabled" wire:target="brainAsk" @disabled($brain['pending'] || $brain['status']['remaining'] < 1) class="nc__chip">{{ $al }}</button>
                                @endforeach
                            </div>
                            <small class="bz-brain__n">{{ $brain['status']['remaining'] }} of {{ $brain['status']['allowance'] }} left this month</small>
                        </div>
                        @if ($brainNotice)<p class="bz-brain__warn" role="alert">{{ $brainNotice }}</p>@endif
                        @foreach ($brain['runs'] as $run)
                            <div class="bz-brain__run" wire:key="br-{{ $run->id }}">
                                <b>{{ ['draft' => 'Suggested reply', 'summary' => 'Summary', 'tasks' => 'Suggested follow-ups'][$run->action] ?? 'Suggestion' }}</b>
                                @if ($run->status === 'pending')<p class="bz-brain__wait">Thinking...</p>
                                @else<p class="bz-brain__out">{{ $run->output }}</p>
                                    @if ($run->status === 'done' && $run->action === 'draft' && $activeChannel !== 'sms')<button type="button" wire:click="useDraft({{ $run->id }})" class="nc__chip">Use as reply</button>@endif
                                @endif
                            </div>
                        @endforeach
                        <small class="bz-brain__fine">AI-written suggestions for you to review. The recent messages of this conversation are sent to our AI provider to write them. Nothing is sent to your customer unless you send it.</small>
                    </div>
                @endif

                <div class="nc__msgs" data-ch="{{ $activeChannel }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @php($prevDay = null)
                    @forelse ($timeline as $m)
                        @php($day = optional($m->at)->format('Y-m-d'))
                        @if ($day !== $prevDay)
                            <span class="nc__pill">{{ optional($m->at)->isToday() ? __('messages.today') : optional($m->at)->format('M j, Y') }}</span>
                            @php($prevDay = $day)
                        @endif
                        <div class="bz-brow {{ $m->direction === 'out' ? 'is-out' : '' }}" wire:key="m-{{ $loop->index }}">
                            <div class="bz-bubble ncb {{ $m->direction === 'out' ? 'ncb--out' : 'ncb--in' }}">
                                @if ($m->attachment_url && ($m->is_voicemail || in_array($m->kind, ['audio', 'voice'], true)))
                                    <audio controls preload="none" src="{{ $m->attachment_url }}"></audio>
                                @elseif ($m->attachment_url && $m->kind === 'video')
                                    <video controls preload="metadata" playsinline src="{{ $m->attachment_url }}"></video>
                                @elseif ($m->attachment_url && $m->kind === 'document')
                                    <a class="bz-file" href="{{ $m->attachment_url }}" target="_blank" rel="noopener"><x-nx.icon name="file-text" /><span>{{ $m->name ?: __('messages.attachment') }}</span></a>
                                @elseif ($m->attachment_url)
                                    <img src="{{ $m->attachment_url }}" alt="{{ __('messages.attachment') }}" loading="lazy">
                                @endif
                                @if ($m->body !== '' && ! ($m->kind && $m->body === \App\Services\Inbox\ChannelSender::label($m->kind)))<p>{{ $m->body }}</p>@endif
                                <span class="ncb__meta">{{ optional($m->at)->format('g:i a') }}@if ($m->failed) · {{ __('messages.not_sent') }}@endif</span>
                            </div>
                        </div>
                    @empty
                        <div class="bz-empty">
                            <span class="bz-empty__ic"><x-nx.icon name="message-circle" /></span>
                            <h2>{{ __('messages.thread_empty_title') }}</h2>
                            <p>{{ __('messages.thread_empty_text') }}</p>
                        </div>
                    @endforelse
                </div>

                {{-- Docked like Naara chat: a floating foot over the wallpaper (nc__foot) that reports its height so the messages never hide behind it.
                     SMS replies open the existing send modal (billing and provider routing unchanged); channel replies use the Naara composer. --}}
                <div class="nc__foot" x-data x-init="const t = $el.closest('.nc__thread'); const sync = () => t.style.setProperty('--nc-foot-h', $el.offsetHeight + 'px'); sync(); new ResizeObserver(sync).observe($el)">
                    <div class="nc__dock bz-dock">
                        @if ($activeChannel === 'sms')
                            <button type="button" class="bz-replybar" wire:click="$dispatch('open-send-message', { to: '{{ $active }}', name: '{{ $activeName ? e($activeName) : '' }}' })">
                                <span>{{ __('messages.type_message') }}</span><x-nx.icon name="send" />
                            </button>
                        @elseif ($activeThread?->opted_out_at)
                            <p class="bz-note"><x-nx.icon name="info" /> {{ __('messages.opted_out') }}</p>
                        @elseif (config('composer.surfaces.inbox_reply'))
                            @php($cc = \App\Services\Inbox\InboxComposerConfig::for($activeChannel))
                            @if ($scheduled->isNotEmpty())
                                <div class="bz-sched" role="status">
                                    @foreach ($scheduled as $sc)
                                        <div class="bz-sched__row" wire:key="sc-{{ $sc->id }}">
                                            <x-nx.icon name="clock" />
                                            <span class="bz-sched__txt"><b>{{ $sc->summary() }}</b>
                                                <small>@if ($sc->status === 'failed'){{ __('messages.sched_failed') }}: {{ $sc->error }}@elseif ($sc->status === 'sending'){{ __('messages.sched_sending') }}@else{{ __('messages.sched_at', ['time' => $sc->send_at->timezone(config('app.timezone'))->format('M j, g:i a')]) }}@endif</small></span>
                                            @if ($sc->status !== 'sending')
                                                <button type="button" class="bz-iconbtn" wire:click="cancelScheduled({{ $sc->id }})" wire:loading.attr="disabled" aria-label="{{ $sc->status === 'failed' ? __('messages.sched_dismiss') : __('messages.sched_cancel') }}"><x-nx.icon name="x" /></button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            {{-- The same composer as Naara chat. It posts to the inbox endpoint itself (files, voice, GIFs, location/contact/poll, scheduled sends, offline outbox with retry);
                                 the page only refreshes the thread when a send lands. --}}
                            <div wire:key="reply-composer-{{ $activeChannel }}-{{ $active }}" x-data x-on:cc:sent.window="$wire.$refresh()">
                                <x-composer surface="inbox" convo-id="inbox-{{ $activeChannel }}-{{ $active }}" :features="$cc['features']" :attach-kinds="$cc['attachKinds']" :max-files="$cc['maxFiles']" :max-file-mb="$cc['maxFileMb']" :max-chars="$cc['maxChars']" :endpoint="$cc['endpoint']" :placeholder="__('messages.type_message')" />
                            </div>
                            @if ($replyError)<p role="alert" class="bz-err">{{ $replyError }}</p>@endif
                        @else
                            <form wire:submit="sendChannelReply" style="display:flex;gap:8px;align-items:flex-end;width:100%">
                                <label class="ns-search" style="flex:1;height:auto;min-height:44px;padding:6px 12px">
                                    <span class="sr-only">{{ __('messages.type_message') }}</span>
                                    <textarea wire:model="reply" rows="1" maxlength="4000" placeholder="{{ __('messages.type_message') }}" style="width:100%;background:transparent;border:0;outline:0;resize:none;color:inherit;font:inherit"></textarea>
                                </label>
                                <button type="submit" class="bz-iconbtn bz-iconbtn--cta" wire:loading.attr="disabled" wire:target="sendChannelReply" aria-label="{{ __('messages.send') }}"><x-nx.icon name="send" /></button>
                            </form>
                            @if ($replyError)<p role="alert" class="bz-err">{{ $replyError }}</p>@endif
                        @endif
                    </div>
                </div>
            @else
                <div class="bz-empty">
                    <span class="bz-empty__ic"><x-nx.icon name="inbox" /></span>
                    <h2>{{ __('messages.select_title') }}</h2>
                    <p>{{ __('messages.select_text') }}</p>
                </div>
            @endif
        </section>

        <div class="bz-msg__grip" role="separator" aria-orientation="vertical" aria-label="{{ __('messages.resize_info') }}" tabindex="0" x-show="info"
             @pointerdown.prevent="drag($event, 'r')" @keydown.arrow-left.prevent="nudge('r', -16)" @keydown.arrow-right.prevent="nudge('r', 16)" :aria-valuenow="r"><i></i></div>

        {{-- ============ RIGHT: contact info ============ --}}
        <aside class="bz-msg__panel bz-msg__info bz-info" x-show="info" aria-label="{{ __('messages.info') }}">
            @if ($active && $info)
                @php($c = $info['contact'])
                <header class="bz-info__head"><b>{{ __('messages.info') }}</b>
                    <button type="button" class="bz-iconbtn" @click="info = false; pane = 'thread'; save()" aria-label="{{ __('messages.close_info') }}"><x-nx.icon name="x" /></button>
                </header>
                <div class="bz-info__body">
                    <div class="bz-hero" data-ch="{{ $activeChannel }}">
                        <span class="bz-av bz-av--xl" aria-hidden="true">{{ $c ? $c->initials() : '#' }}</span>
                        <b>{{ $c?->name ?: $active }}</b>
                        @if ($c)<small>{{ $active }}</small>@endif
                        <span class="bz-chtag" data-ch="{{ $activeChannel }}"><x-business.channel-logo :channel="$activeChannel" />{{ $chName($activeChannel) }}</span>
                    </div>
                    @if ($activeChannel === 'sms')
                        <div class="bz-actions">
                            <a href="{{ route('numbers.dialer', ['to' => $active]) }}" wire:navigate class="ns-btn" style="justify-content:center;text-decoration:none"><x-nx.icon name="phone" /> {{ __('messages.call') }}</a>
                            <button type="button" class="ns-btn" style="justify-content:center" wire:click="$dispatch('open-send-message', { to: '{{ $active }}', name: '{{ $c ? e($c->name) : '' }}' })"><x-nx.icon name="send" /> {{ __('messages.message') }}</button>
                        </div>
                    @endif
                    @if (! $c && $activeChannel === 'sms')
                        <x-nx.note variant="link" icon="plus" tag="a" :href="route('numbers.contacts')" wire:navigate style="margin-top:14px"><b>{{ __('messages.save_contact') }}</b>{{ __('messages.save_contact_text') }}</x-nx.note>
                    @endif
                    <dl class="bz-facts">
                        <div><dt>{{ __('messages.number') }}</dt><dd>{{ $active }}</dd></div>
                        <div><dt>{{ __('messages.received') }}</dt><dd>{{ $info['incoming'] }}</dd></div>
                        <div><dt>{{ __('messages.sent') }}</dt><dd>{{ $info['outgoing'] }}</dd></div>
                        @if ($info['first'])<div><dt>{{ __('messages.first') }}</dt><dd>{{ $info['first']->format('M j, Y') }}</dd></div>@endif
                        @if ($info['last'])<div><dt>{{ __('messages.last') }}</dt><dd>{{ $info['last']->format('M j, Y') }}</dd></div>@endif
                    </dl>
                </div>
            @else
                <div class="bz-empty">
                    <span class="bz-empty__ic"><x-nx.icon name="info" /></span>
                    <h2>{{ __('messages.info') }}</h2>
                    <p>{{ __('messages.info_empty') }}</p>
                </div>
            @endif
        </aside>
    </div>

    {{-- Reply composer for SMS (reuses the existing send modal). --}}
    @livewire('send-message')
</x-nx.page>
</div>
