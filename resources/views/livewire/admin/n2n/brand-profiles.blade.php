{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-4xl space-y-4 p-4">
    <div>
        <a href="{{ route('admin.n2n') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">N2N</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Brand profiles</h1>
    </div>
    @if ($message)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $message }}</div>@endif

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <h2 class="font-semibold text-[rgb(var(--nx-text))]">Music profile requests</h2>
        @forelse ($requests as $b)
            <div wire:key="rq-{{ $b->id }}" class="mt-2 flex items-center justify-between gap-2 text-sm text-[rgb(var(--nx-text))]">
                <span>{{ $b->brand_name }} <small class="text-[rgb(var(--nx-text-2))]">requested {{ \Illuminate\Support\Carbon::parse($b->music_requested_at)->diffForHumans() }}</small></span>
                <button type="button" wire:click="confirmMusic({{ $b->id }})" wire:loading.attr="disabled" wire:target="confirmMusic" class="ns-cta ns-cta--pill ns-cta--sm">Confirm</button>
            </div>
        @empty
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">No pending requests.</p>
        @endforelse
        @if ($music->isNotEmpty())
            <h3 class="mt-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Confirmed music profiles</h3>
            @foreach ($music as $b)
                <div wire:key="mp-{{ $b->id }}" class="mt-1 flex items-center justify-between gap-2 text-sm text-[rgb(var(--nx-text))]">
                    <span>{{ $b->brand_name }}</span>
                    <button type="button" wire:click="revokeMusic({{ $b->id }})" wire:confirm="Revoke this music profile? Their limits drop back to the general ones." class="text-xs text-[rgb(var(--nx-text-2))] underline">Revoke</button>
                </div>
            @endforeach
        @endif
    </section>

    <form wire:submit="saveLimits" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <h2 class="font-semibold text-[rgb(var(--nx-text))]">Limits</h2>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($limits as $key => $v)
                <label class="flex items-center justify-between gap-2 text-sm text-[rgb(var(--nx-text))]">
                    <span>{{ str_replace(['.', '_'], [' / ', ' '], $key) }}</span>
                    <input type="number" min="0" max="200" wire:model="limits.{{ $key }}" class="w-20 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]">
                </label>
            @endforeach
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="saveLimits" class="ns-cta ns-cta--pill ns-cta--sm mt-3">Save limits</button>
    </form>

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <h2 class="font-semibold text-[rgb(var(--nx-text))]">Recent links, players and albums</h2>
        @foreach ($items as $i)
            <div wire:key="it-{{ $i['type'] }}-{{ $i['row']->id }}" class="mt-2 flex items-center justify-between gap-2 text-sm text-[rgb(var(--nx-text))]">
                <span class="min-w-0 truncate">{{ $i['text'] }}@if ($i['row']->hidden_at) <b>(hidden)</b>@endif</span>
                <button type="button" wire:click="toggleHidden('{{ $i['type'] }}', {{ $i['row']->id }}, {{ $i['row']->hidden_at ? 'false' : 'true' }})" class="shrink-0 text-xs text-[rgb(var(--nx-text-2))] underline">{{ $i['row']->hidden_at ? 'Restore' : 'Hide' }}</button>
            </div>
        @endforeach
    </section>
</div>
