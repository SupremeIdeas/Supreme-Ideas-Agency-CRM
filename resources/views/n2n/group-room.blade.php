{{-- nx:converted --}}
{{-- Group-call room (N2N Batch 11, MASTER-ONLY). State comes from the server; the page only renders it and connects to the SFU with the token the server signed. --}}
<x-layouts.n2n title="Group call">
@vite('resources/js/n2n/group.js')
<div class="gr" data-group-room data-call-id="{{ $callId }}" data-state-url="{{ route('n2n.api.group.state', $callId) }}" data-status-url="{{ route('n2n.api.group.status', $callId) }}" data-base="{{ url('/n2n/api/group/'.$callId) }}"
     x-data="groupRoom({{ $callId }})" x-init="start()">
    <header class="gr__head">
        <div class="min-w-0">
            <h1 class="gr__title" x-text="s.title || 'Group call'">Group call</h1>
            <p class="gr__sub"><span x-text="s.count || 0"></span> in the room<span x-show="s.is_host"> &middot; you are the host</span></p>
        </div>
        <button type="button" class="gr__leave" @click="leave()" :disabled="busy"><x-nx.icon name="phone" /> <span x-text="s.is_host ? 'End call' : 'Leave'">Leave</span></button>
    </header>

    <p class="gr__banner gr__banner--bad" x-show="msg" x-text="msg" role="alert"></p>
    <p class="gr__banner" x-show="s.open === false" role="status">This call has ended. <a class="underline" href="{{ route('n2n.calls') }}" wire:navigate>Back to calls</a></p>
    <p class="gr__banner" x-show="s.pending" role="status">Waiting for the host to let you in...</p>
    <p class="gr__banner" x-show="demoted" role="status" x-text="demoted"></p>

    <section class="gr__stage" aria-label="Speakers">
        <h2 class="gr__h">On stage <small x-text="'(' + (s.speakers ? s.speakers.length : 0) + ' of ' + (s.max_speakers || 5) + ')'"></small></h2>
        <ul class="gr__people gr__people--stage">
            <template x-for="p in (s.speakers || [])" :key="p.id">
                <li class="gr__p" :class="{ 'is-talking': talking.has(p.id) }">
                    <span class="gr__av" x-text="(p.name || '?').slice(0, 1).toUpperCase()"></span>
                    <span class="gr__name" x-text="p.name + (p.host ? ' (host)' : '')"></span>
                    <span class="gr__ctl" x-show="s.is_host && !p.host">
                        <button type="button" @click="act('mute', p.id)" aria-label="Mute"><x-nx.icon name="mic-off" /></button>
                        <button type="button" @click="act('demote', p.id)" aria-label="Back to audience"><x-nx.icon name="arrow-left" /></button>
                        <button type="button" @click="act('remove', p.id)" aria-label="Remove and ban"><x-nx.icon name="x" /></button>
                    </span>
                </li>
            </template>
        </ul>
        <div class="gr__me" x-show="s.speaking">
            <button type="button" class="ns-cta ns-cta--pill" @click="toggleMic()"><x-nx.icon name="mic" /> <span x-text="micOn ? 'Mute me' : 'Unmute me'">Mute me</span></button>
            <button type="button" class="gr__link" @click="act('demote', {{ auth()->id() }})" x-show="!s.is_host">Step down</button>
        </div>
        <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-show="s.is_host && !s.speaking" @click="act('appoint', {{ auth()->id() }})">Speak</button>
        <p class="gr__hint" x-show="s.speaking">Speaking is billed from your NaaraCredit for the time you are on stage, even while muted. Listening is free.</p>
    </section>

    <section class="gr__aud" aria-label="Listeners">
        <h2 class="gr__h">Listening <small x-text="'(' + (s.listeners ? s.listeners.length : 0) + ')'"></small></h2>
        <ul class="gr__people">
            <template x-for="p in (s.listeners || [])" :key="p.id">
                <li class="gr__p">
                    <span class="gr__av" x-text="(p.name || '?').slice(0, 1).toUpperCase()"></span>
                    <span class="gr__name" x-text="p.name"></span>
                    <span class="gr__ctl" x-show="s.is_host && p.id !== {{ auth()->id() }}">
                        <button type="button" @click="act('appoint', p.id)" aria-label="Let them speak"><x-nx.icon name="mic" /></button>
                        <button type="button" @click="act('remove', p.id)" aria-label="Remove and ban"><x-nx.icon name="x" /></button>
                    </span>
                </li>
            </template>
        </ul>
    </section>

    <section class="gr__host" x-show="s.is_host && s.open" aria-label="Host tools">
        <h2 class="gr__h">Host tools</h2>
        <div x-show="(s.waiting || []).length">
            <p class="gr__hint">Waiting to join</p>
            <ul class="gr__people">
                <template x-for="p in s.waiting" :key="p.id">
                    <li class="gr__p"><span class="gr__av" x-text="(p.name || '?').slice(0, 1).toUpperCase()"></span><span class="gr__name" x-text="p.name"></span>
                        <span class="gr__ctl"><button type="button" @click="act('approve', p.id, { allow: true })" aria-label="Let in"><x-nx.icon name="check" /></button><button type="button" @click="act('approve', p.id, { allow: false })" aria-label="Decline"><x-nx.icon name="x" /></button></span></li>
                </template>
            </ul>
        </div>
        <label class="gr__linkbox"><span class="sr-only">Call link</span><input type="text" readonly :value="s.link || ''" @focus="$event.target.select()"></label>
        <div class="gr__row">
            <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" @click="copy()"><x-nx.icon name="copy" /> <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span></button>
            <button type="button" class="gr__link" @click="newLink()">New link</button>
            <button type="button" class="gr__link" @click="revoke()">Turn link off</button>
        </div>
        <label class="gr__check"><input type="checkbox" :checked="s.requires_approval" @change="approval($event.target.checked)"> Approve each person before they join</label>
    </section>
    <div data-audio-bay hidden></div>
</div>
</x-layouts.n2n>
