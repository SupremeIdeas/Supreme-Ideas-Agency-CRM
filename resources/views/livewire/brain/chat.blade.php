{{-- nx:converted --}}
{{-- NaaraBrain chat (MASTER-ONLY). Skin tokens only (--nx-*), no hex, no palette classes. Structure: a history sidebar UNDER one sheet. The sheet (header, empty state or
     messages, suggestions, the exact Naara chat composer) follows a sideways drag, or the menu button, to reveal it; tapping the dimmed sheet or dragging back closes it.
     On a desktop the sidebar is docked and the sheet sits beside it. A single plain Livewire root. --}}
@php
    $mark = \App\Support\Brain\BrainSettings::logo('mark');
    $draft = \App\Support\Brain\BrainSettings::int('max_chars');
@endphp
<div class="brn" data-brain x-data="brainShell()" x-ref="root" :class="{ 'is-open': open, 'is-dragging': dragging, 'is-settling': settling }" @keydown.escape.window="open = false"
     @brain-close.window="open = false" @brain-open.window="open = true">

    {{-- ===== History sidebar (underneath the sheet) ===== --}}
    <aside class="brn__side" aria-label="{{ __('brain.history') }}" :inert="!open && narrow" :aria-hidden="(!open && narrow) ? 'true' : 'false'">
        <div class="brn__brand">
            <img src="{{ $mark }}" alt="" width="34" height="34" class="brn__mark" draggable="false">
            <span>{{ __('brain.name') }}</span>
        </div>

        <button type="button" class="brn__new" wire:click="newChat" @click="open = false">
            <x-nx.icon name="edit" /> <span>{{ __('brain.new_chat') }}</span>
        </button>

        <div class="brn__scroll">
            @forelse ($groups as $label => $list)
                <section class="brn__group" aria-label="{{ $label }}">
                    <h2 class="brn__glabel">{{ $label }}</h2>
                    @foreach ($list as $t)
                        <div class="brn__row {{ $thread && $thread->id === $t->id ? 'is-on' : '' }}" wire:key="th-{{ $t->id }}" x-data="{ more: false }">
                            <button type="button" class="brn__open" wire:click="open({{ $t->id }})" @click="open = false">
                                @if ($t->pinned)<x-nx.icon name="pin" class="brn__pinic" />@endif
                                <span class="brn__ttl">{{ $t->title ?: __('brain.untitled') }}</span>
                            </button>
                            <button type="button" class="brn__dots" @click="more = !more" :aria-expanded="more" aria-label="{{ __('brain.controls') }}"><i></i><i></i><i></i></button>
                            <div class="brn__acts" x-show="more" x-cloak style="display:none" @click.outside="more = false">
                                <button type="button" wire:click="togglePin({{ $t->id }})" @click="more = false"><x-nx.icon name="pin" /> {{ $t->pinned ? __('brain.unpin') : __('brain.pin') }}</button>
                                <button type="button" wire:click="archive({{ $t->id }})" @click="more = false"><x-nx.icon name="archive" /> {{ __('brain.archive') }}</button>
                                <button type="button" class="is-danger" wire:click="delete({{ $t->id }})" wire:confirm="{{ __('brain.delete_confirm') }}" @click="more = false"><x-nx.icon name="trash" /> {{ __('brain.delete') }}</button>
                            </div>
                        </div>
                    @endforeach
                </section>
            @empty
                <p class="brn__none">{{ trim($q) !== '' ? __('brain.no_results') : __('brain.empty_history') }}</p>
            @endforelse

            {{-- Your plan: Naara Pro decides how many questions a day and which depths are open. --}}
            <section class="brn__group brn__plan" aria-label="{{ __('brain.plan') }}">
                <h2 class="brn__glabel">{{ __('brain.plan') }}</h2>
                <div class="brn__planrow">
                    <b>{{ $access['label'] }}</b>
                    @if ($access['limit'] !== null)
                        <span>{{ __('brain.used_today', ['used' => min($access['used'], $access['limit']), 'limit' => $access['limit']]) }}</span>
                    @else
                        <span>{{ __('brain.unlimited') }}</span>
                    @endif
                </div>
                @if ($access['limit'] !== null)
                    <div class="brn__meter" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $access['limit'] }}" aria-valuenow="{{ min($access['used'], $access['limit']) }}"><i style="width: {{ min(100, (int) round(100 * $access['used'] / max(1, $access['limit']))) }}%"></i></div>
                @endif
                @if ($access['upgrade'])
                    <a href="{{ $access['upgrade'] }}" wire:navigate class="brn__upgrade">{{ __('brain.upgrade') }}</a>
                @endif
            </section>

            {{-- Controls: depth and answer length belong to the open conversation. --}}
            <section class="brn__group brn__ctl" aria-label="{{ __('brain.controls') }}">
                <h2 class="brn__glabel">{{ __('brain.controls') }}</h2>
                @if ($thread)
                    <div class="brn__seg-l">{{ __('brain.depth') }}</div>
                    <div class="brn__seg" role="radiogroup" aria-label="{{ __('brain.depth') }}">
                        @foreach ($depths as $key => $d)
                            @php($open = in_array($key, $access['depths'], true))
                            <button type="button" role="radio" aria-checked="{{ $thread->depth === $key ? 'true' : 'false' }}" class="{{ $thread->depth === $key ? 'is-on' : '' }} {{ $open ? '' : 'is-locked' }}" wire:click="setDepth('{{ $key }}')" title="{{ $open ? $d['hint'] : __('brain.depth_locked') }}">
                                {{ $d['label'] }}@unless ($open)<x-nx.icon name="lock" class="brn__lockic" />@endunless
                            </button>
                        @endforeach
                    </div>
                    <div class="brn__seg-l">{{ __('brain.length') }}</div>
                    <div class="brn__seg" role="radiogroup" aria-label="{{ __('brain.length') }}">
                        @foreach (['concise' => 'len_concise', 'normal' => 'len_normal', 'detailed' => 'len_detailed'] as $key => $k)
                            <button type="button" role="radio" aria-checked="{{ $thread->length === $key ? 'true' : 'false' }}" class="{{ $thread->length === $key ? 'is-on' : '' }}" wire:click="setLength('{{ $key }}')">{{ __('brain.'.$k) }}</button>
                        @endforeach
                    </div>
                @else
                    <p class="brn__hint">{{ __('brain.controls_need_chat') }}</p>
                @endif
            </section>

            <section class="brn__group" aria-label="{{ __('brain.abilities') }}">
                <h2 class="brn__glabel">{{ __('brain.abilities') }}</h2>
                <ul class="brn__abil">
                    @foreach ($capabilities as $c)
                        <li class="{{ $c['live'] ? 'is-live' : '' }}" title="{{ $c['detail'] }}">
                            <x-nx.icon :name="$c['live'] ? 'check' : 'clock'" />
                            <span><strong>{{ $c['title'] }}</strong>@if ($c['detail'] !== '')<small>{{ $c['detail'] }}</small>@endif</span>
                            <b>{{ $c['live'] ? __('brain.on') : __('brain.soon') }}</b>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- Search lives at the bottom of the sidebar, thumb-reachable. --}}
        <div class="brn__searchbar">
            <label class="brn__search">
                <x-nx.icon name="search" />
                <span class="sr-only">{{ __('brain.search') }}</span>
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="{{ __('brain.search') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (trim($q) !== '')
                <button type="button" class="brn__clear" wire:click="$set('q', '')" aria-label="{{ __('brain.search_clear') }}"><x-nx.icon name="x" /></button>
            @endif
        </div>
    </aside>

    {{-- ===== The sheet: everything the person works in ===== --}}
    <div class="brn__sheet" @if ($busy) wire:poll.1500ms @endif>
        <button type="button" class="brn__scrim" @click="open = false" aria-label="{{ __('brain.close_menu') }}" :tabindex="open ? 0 : -1" :aria-hidden="open ? 'false' : 'true'"></button>

        <header class="brn__head">
            <button type="button" class="brn__burger" @click="open = !open" :aria-expanded="open" aria-label="{{ __('brain.menu') }}"><x-nx.icon name="menu" /></button>
            @if ($thread)
                <div class="brn__headttl"><img src="{{ $mark }}" alt="" width="28" height="28" class="brn__mark" draggable="false"><span>{{ $thread->title ?: __('brain.name') }}</span></div>
            @else
                <div class="brn__headttl"><img src="{{ $mark }}" alt="" width="28" height="28" class="brn__mark" draggable="false"><span>{{ __('brain.name') }}</span></div>
            @endif
            <div class="brn__headact">
                <a href="{{ route('dashboard') }}" wire:navigate class="brn__iconbtn" aria-label="Back to the dashboard"><x-nx.icon name="home" /></a>
                <button type="button" class="brn__iconbtn" wire:click="newChat" aria-label="{{ __('brain.new_chat') }}"><x-nx.icon name="edit" /></button>
            </div>
        </header>

        <main class="brn__main" id="brn-main">
            @if (! $thread)
                {{-- Empty chat: the animated chat logo, dead centre, with the name beside it. --}}
                <div class="brn__empty">
                    <div class="brn__lockup">
                        <picture>
                            <source media="(prefers-reduced-motion: reduce)" srcset="{{ \App\Support\Brain\BrainSettings::logo('chat', true) }}">
                            <img src="{{ \App\Support\Brain\BrainSettings::logo('chat') }}" alt="" width="120" height="120" class="brn__lockimg" draggable="false">
                        </picture>
                        <h1 class="brn__lockname">{{ __('brain.name') }}</h1>
                    </div>
                    <p class="brn__tag" wire:key="tag-{{ md5($tagline) }}">{{ $tagline !== '' ? $tagline : __('brain.tagline') }}</p>
                </div>
            @else
                <div class="brn__msgs" x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" wire:key="msgs-{{ $thread->id }}-{{ $messages->count() }}-{{ $busy ? 1 : 0 }}">
                    @foreach ($messages as $m)
                        @if ($m->role === 'user')
                            <div class="brn__msg brn__msg--out" wire:key="m-{{ $m->id }}"><div class="brn__bubble">{!! nl2br(e($m->body)) !!}</div></div>
                        @elseif ($m->status === 'pending')
                            <div class="brn__msg brn__msg--in" wire:key="m-{{ $m->id }}" role="status" aria-live="polite">
                                <span class="brn__think">
                                    <picture>
                                        <source media="(prefers-reduced-motion: reduce)" srcset="{{ \App\Support\Brain\BrainSettings::logo('thinking', true) }}">
                                        <img src="{{ \App\Support\Brain\BrainSettings::logo('thinking') }}" alt="" width="30" height="30" draggable="false">
                                    </picture>
                                    <span>{{ __('brain.thinking') }}</span>
                                </span>
                            </div>
                        @else
                            <div class="brn__msg brn__msg--in {{ $m->status === 'error' ? 'is-error' : '' }}" wire:key="m-{{ $m->id }}">
                                <img src="{{ $mark }}" alt="" width="26" height="26" class="brn__avatar" draggable="false">
                                <div class="brn__bubble brn__prose">{!! \Illuminate\Support\Str::markdown($m->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </main>

        <footer class="brn__foot" x-data="{
                async transmit(p) {
                    if ((p.files && p.files.length) || p.voice || (p.items && p.items.some((i) => i.type !== 'file'))) {
                        throw Object.assign(new Error(@js(__('brain.locked_text'))), { fatal: true });
                    }
                    const ok = await $wire.send(p.text || '');
                    if (!ok) { throw Object.assign(new Error(@js(__('brain.not_sent'))), { fatal: true }); }
                },
            }" x-on:cc:transmit="$event.detail.wait(transmit($event.detail.payload))">
            @if (! $thread)
                <div class="brn__chips" role="group" aria-label="{{ __('brain.suggestions') }}">
                    @foreach ($chips as $c)
                        <button type="button" class="brn__chip" wire:key="chip-{{ md5($c) }}" wire:click="ask(@js($c))">{{ $c }}</button>
                    @endforeach
                    <button type="button" class="brn__chip brn__chip--more" wire:click="toggleIdeas"><x-nx.icon name="sparkles" /> {{ __('brain.more_ideas') }}</button>
                </div>
            @endif
            @if ($notice)<p class="brn__notice" role="alert">{{ $notice }} @if ($noticeUrl)<a href="{{ $noticeUrl }}" wire:navigate>{{ __('brain.upgrade') }}</a>@endif</p>@endif
            <div class="brn__dock" wire:key="composer">
                {{-- The exact Naara chat composer: same element, same features. Photos, files, GIFs and voice notes show their lock because NaaraBrain cannot take them yet. --}}
                <x-composer surface="naara_chat" mode="chat" :features="['format', 'emoji', 'hints', 'snippets', 'schedule', 'gif', 'attach', 'voice']" :attach-kinds="['media', 'camera', 'video', 'document', 'audio', 'contact', 'location', 'poll']"
                            convo-id="brain-{{ auth()->id() }}" :max-chars="$draft" :max-files="4" :max-file-mb="25"
                            :extra="['locked' => ['voice', 'gif', 'files', 'schedule'], 'lockedKinds' => ['media', 'camera', 'video', 'document', 'audio', 'contact', 'location', 'poll'], 'lockedUrl' => '', 'lockedText' => __('brain.locked_text'), 'liveLocation' => false]"
                            :placeholder="__('brain.placeholder')" />
            </div>
            <p class="brn__fine">{{ __('brain.disclosure') }}</p>
        </footer>

        {{-- Idea browser: every area with questions people can ask. Working-today ones send on tap; the rest are marked Soon. --}}
        @if ($ideasOpen)
            <div class="brn__ideas" role="dialog" aria-modal="true" aria-label="{{ __('brain.ideas_title') }}">
                <header class="brn__ideas-head">
                    <h2>{{ __('brain.ideas_title') }}</h2>
                    <button type="button" class="brn__iconbtn" wire:click="toggleIdeas" aria-label="{{ __('brain.close_ideas') }}"><x-nx.icon name="x" /></button>
                </header>
                <div class="brn__ideas-body">
                    @foreach ($ideas as $pack)
                        <section class="brn__ideas-pack">
                            <h3>{{ $pack['name'] }}</h3>
                            <ul>
                                @foreach ($pack['items'] as $it)
                                    <li>
                                        @if ($it['live'])
                                            <button type="button" wire:click="ask(@js($it['text']))">{{ $it['text'] }}</button>
                                        @else
                                            <span class="is-soon">{{ $it['text'] }} <b>{{ __('brain.soon') }}</b></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
