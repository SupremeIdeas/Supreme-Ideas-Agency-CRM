{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl space-y-4 p-4">
    <div>
        <a href="{{ route('admin.n2n') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">N2N</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Reports and restrictions</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Reports never restrict anyone by themselves. You see only the short excerpt captured when the report was made. A restriction stops sending and calling only: the person can still read, react, export and erase their data.</p>
    </div>
    @if ($message)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $message }}</div>@endif

    <div class="flex flex-wrap gap-2" role="group" aria-label="Report filters">
        @foreach (['open' => 'Open ('.$counts['open'].')', 'closed' => 'Closed', 'restricted' => 'Restricted ('.$counts['restricted'].')'] + ($isAdmin ? ['moderators' => 'Moderators'] : []) as $k => $l)
            <button type="button" wire:click="setTab('{{ $k }}')" aria-pressed="{{ $tab === $k ? 'true' : 'false' }}" class="nc__chip {{ $tab === $k ? 'is-on' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    @if ($tab === 'moderators')
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Staff you switch on can work the report queue and pause chat access for up to 7 days. They cannot make a restriction permanent, restrict another staff member or an admin, or lift a restriction an admin set. You can switch them off at any time.</p>
        @forelse ($moderators as $m)
            @php($on = $m->hasPermissionTo('n2n.moderate'))
            <section class="flex items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]" wire:key="mod-{{ $m->id }}">
                <div><b>{{ $m->name }}</b><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $m->email }} · {{ $on ? 'Can moderate' : 'No chat moderation' }}</p></div>
                <button type="button" wire:click="setModerator({{ $m->id }}, {{ $on ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setModerator({{ $m->id }}, {{ $on ? 'false' : 'true' }})" class="nc__chip {{ $on ? 'is-danger' : 'is-on' }}">{{ $on ? 'Switch off' : 'Switch on' }}</button>
            </section>
        @empty
            <p class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text-2))]">No staff accounts yet. Add staff from Admin > Staff.</p>
        @endforelse
    @elseif ($tab === 'restricted')
        @forelse ($restricted as $m)
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
                <div class="flex items-center justify-between gap-3">
                    <div><b>{{ $m->name ?: ($m->username ? '@'.$m->username : 'Member') }}</b> <span class="text-[rgb(var(--nx-text-2))]">{{ $m->naara_id }}</span>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $m->n2n_restricted_reason }} · until {{ $m->n2n_restricted_until->year > now()->year + 5 ? 'lifted by staff' : $m->n2n_restricted_until->format('Y-m-d H:i') }}</p></div>
                    <button type="button" wire:click="lift({{ $m->id }})" wire:loading.attr="disabled" wire:target="lift({{ $m->id }})" class="nc__chip">Lift</button>
                </div>
            </section>
        @empty
            <p class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text-2))]">Nobody is restricted.</p>
        @endforelse
    @else
        @forelse ($reports as $r)
            @php($by = $people[$r->reporter_id] ?? null)
            @php($on = $people[$r->reported_id] ?? null)
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]" wire:key="r-{{ $r->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold">#{{ $r->id }} · {{ ucfirst($r->reason) }} <span class="font-normal text-[rgb(var(--nx-text-2))]">{{ $r->created_at?->diffForHumans() }}</span></h2>
                    @if ($attention[$r->reported_id] ?? false)<span class="rounded-full bg-[rgb(var(--nx-bad)/.16)] px-2 py-0.5 text-xs font-bold text-[rgb(var(--nx-bad))]">Needs attention: {{ \App\Services\N2N\Messaging\Moderation::ATTENTION_REPORTERS }}+ people reported them today</span>@endif
                </div>
                <p class="mt-1 text-[rgb(var(--nx-text-2))]">Reported: <b class="text-[rgb(var(--nx-text))]">{{ $on?->name ?: ($on?->username ? '@'.$on->username : 'Member') }}</b> {{ $on?->naara_id }}@if (\App\Services\N2N\Messaging\Moderation::restricted($on)) · <b>currently restricted</b>@endif · by {{ $by?->name ?: ($by?->username ? '@'.$by->username : 'a member') }}</p>
                @if ($r->note)<p class="mt-2 rounded-xl bg-[rgb(var(--nx-surface-3))] p-2">“{{ $r->note }}”</p>@endif
                @if (! empty($r->excerpt))
                    <div class="mt-2 space-y-1" aria-label="Excerpt of the reported person's recent messages">
                        @foreach ($r->excerpt as $e)<p class="rounded-lg bg-[rgb(var(--nx-surface-2))] px-2 py-1 text-[rgb(var(--nx-text))]">{{ $e['body'] ?? '' }}</p>@endforeach
                    </div>
                @endif
                @if ($r->status === 'open')
                    <input type="text" wire:model="notes.{{ $r->id }}" maxlength="500" placeholder="Note for the record (optional)" class="mt-3 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]">
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($actions as $key => $label)
                            @continue($key === 'restricted_permanent' && ! $isAdmin)
                            <button type="button" wire:click="resolve({{ $r->id }}, '{{ $key }}')" wire:loading.attr="disabled" wire:target="resolve({{ $r->id }}, '{{ $key }}')" class="nc__chip {{ str_starts_with($key, 'restricted') ? 'is-danger' : '' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Closed: {{ str_replace('_', ' ', (string) $r->action) }}@if ($r->staff_note) · {{ $r->staff_note }}@endif</p>
                @endif
            </section>
        @empty
            <p class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text-2))]">Nothing here.</p>
        @endforelse
    @endif
</div>
