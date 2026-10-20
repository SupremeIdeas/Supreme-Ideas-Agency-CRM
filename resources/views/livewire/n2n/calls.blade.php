{{-- nx:converted --}}
{{-- Calls (N2N Batch 10, MASTER-ONLY): recents + missed + call back. Skin tokens only. --}}
<div class="mx-auto w-full max-w-2xl px-3 py-3" data-n2n-calls>
    @unless ($status['enabled'])
        <p class="mb-3 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))]" role="status">Calls are not available right now.</p>
    @endunless
    @if ($group)
        <section class="mb-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]" data-n2n-group-start
            x-data="{ open: false, title: '', cap: {{ min(20, $groupCapacity) }}, approve: false, busy: false, err: '',
                async go() { this.busy = true; this.err = '';
                    try { const r = await fetch('{{ route('n2n.api.group.create') }}', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }, body: JSON.stringify({ title: this.title, capacity: this.cap, requires_approval: this.approve }) });
                        const j = await r.json(); if (j.ok) { window.location = j.room_url; return; } this.err = j.message || 'Could not start the call.'; } catch (e) { this.err = 'Could not start the call.'; } this.busy = false; } }">
            <div class="flex items-center justify-between gap-2">
                <div><b>Group call</b><p class="text-xs text-[rgb(var(--nx-text-2))]">Host a room: you choose up to {{ \App\Support\N2N\N2NSettings::groupMaxSpeakers() }} speakers, everyone else listens free.</p></div>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-on:click="open = !open" x-bind:aria-expanded="open">New group call</button>
            </div>
            <form x-show="open" x-cloak x-on:submit.prevent="go()" class="mt-3 grid gap-2">
                <label class="grid gap-1"><span class="text-xs text-[rgb(var(--nx-text-2))]">Title</span><input type="text" required maxlength="80" x-model="title" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></label>
                <label class="grid gap-1"><span class="text-xs text-[rgb(var(--nx-text-2))]">Room size (max {{ $groupCapacity }})</span><input type="number" min="2" max="{{ $groupCapacity }}" x-model.number="cap" class="w-28 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></label>
                <label class="flex gap-2"><input type="checkbox" x-model="approve"> <span>I approve each person before they join</span></label>
                <p x-show="err" x-text="err" role="alert" class="text-xs text-[rgb(var(--nx-bad))]"></p>
                <button type="submit" x-bind:disabled="busy" class="ns-cta ns-cta--pill ns-cta--sm justify-self-start"><span x-show="!busy">Start call</span><span x-show="busy" x-cloak>Starting...</span></button>
            </form>
            @foreach ($openRooms as $r)
                <a href="{{ route('n2n.group.room', $r->id) }}" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2"><span class="truncate">{{ $r->title }}</span><span class="text-xs text-[rgb(var(--nx-text-2))]">Rejoin</span></a>
            @endforeach
        </section>
    @endif
    <div class="nc__chips" role="tablist" aria-label="Call filter">
        <button type="button" role="tab" wire:click="$set('tab','all')" aria-selected="{{ $tab === 'all' ? 'true' : 'false' }}" class="nc__chip {{ $tab === 'all' ? 'is-on' : '' }}">All</button>
        <button type="button" role="tab" wire:click="$set('tab','missed')" aria-selected="{{ $tab === 'missed' ? 'true' : 'false' }}" class="nc__chip {{ $tab === 'missed' ? 'is-on' : '' }}">Missed</button>
    </div>
    <ul class="mt-2 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        @forelse ($list as $c)
            <li wire:key="call-{{ $c['id'] }}" class="flex items-center gap-3 border-b border-[rgb(var(--nx-line)/0.6)] px-3 py-2 last:border-0">
                <x-nx.avatar :name="$c['name']" :size="40" />
                <div class="min-w-0 flex-1">
                    <b class="block truncate text-sm {{ $c['missed'] ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-text))]' }}">{{ $c['name'] }}</b>
                    <small class="text-[rgb(var(--nx-text-2))]">{{ $c['out'] ? 'Outgoing' : ($c['missed'] ? 'Missed' : 'Incoming') }}@if ($c['seconds'] > 0) · {{ gmdate($c['seconds'] >= 3600 ? 'G:i:s' : 'i:s', $c['seconds']) }}@endif · {{ $c['at']->diffForHumans(null, true, true) }}</small>
                </div>
                @if ($c['handle'] && $status['enabled'])
                    <button type="button" class="nn__iconbtn" data-n2n-call="{{ $c['handle'] }}" aria-label="Call {{ $c['name'] }}"><x-nx.icon name="phone" /></button>
                    @if (\App\Services\N2N\Calls\CallsAvailability::videoEnabled())<button type="button" class="nn__iconbtn" data-n2n-video-call="{{ $c['handle'] }}" aria-label="Video call {{ $c['name'] }}"><x-nx.icon name="video" /></button>@endif
                @endif
            </li>
        @empty
            <li><x-n2n.empty art="nc-calls" :title="$tab === 'missed' ? 'No missed calls' : 'No calls yet'" :text="$tab === 'missed' ? 'Calls you miss will wait for you here.' : 'Open a profile or a chat and tap the phone.'" /></li>
        @endforelse
    </ul>
</div>
