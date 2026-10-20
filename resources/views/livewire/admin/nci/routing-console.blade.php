{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Routing Console</h1>
    <p class="mb-5 text-sm text-[rgb(var(--nx-text-2))]">A read-only simulation of the order the router would try per product family right now — the real ordering logic, no purchase executed.</p>

    {{-- BUILD-19 §1 — NCI kill switch. Prominent because it changes how every
         order routes: OFF makes ordering ignore learned scores and fall back to
         plain latency / success-rate. Circuit breakers stay active either way. --}}
    <div @class([
        'mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4',
        'border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)]' => $nciEnabled,
        'border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)]' => ! $nciEnabled,
    ])>
        <div class="flex items-start gap-3">
            <span @class([
                'mt-0.5 flex h-8 w-8 items-center justify-center rounded-full',
                'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' => $nciEnabled,
                'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' => ! $nciEnabled,
            ])>
                <x-icon name="{{ $nciEnabled ? 'zap' : 'pause' }}" class="h-4 w-4" />
            </span>
            <div>
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">
                    NCI Intelligence: <span class="uppercase">{{ $nciEnabled ? 'On' : 'Off' }}</span>
                </p>
                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                    @if ($nciEnabled)
                        Learned reliability scores are influencing routing order. Circuit breakers active.
                    @else
                        Learned scores are ignored — routing falls back to latency &amp; success-rate. Circuit breakers still active.
                    @endif
                </p>
            </div>
        </div>
        @if (auth()->user()?->hasAnyRole(['super_admin', 'admin']) || auth()->user()?->can('nci.override'))
            <button type="button" wire:click="toggleNci" wire:loading.attr="disabled" wire:target="toggleNci"
                    wire:confirm="{{ $nciEnabled ? 'Turn NCI OFF? Routing will ignore learned scores until re-enabled.' : 'Turn NCI ON? Routing will resume using learned scores.' }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white transition disabled:opacity-60',
                        'bg-[color-mix(in_srgb,rgb(var(--nx-warn))_58%,#000)] hover:bg-[color-mix(in_srgb,rgb(var(--nx-warn))_48%,#000)]' => $nciEnabled,
                        'bg-[color-mix(in_srgb,rgb(var(--nx-ok))_58%,#000)] hover:bg-[color-mix(in_srgb,rgb(var(--nx-ok))_48%,#000)]' => ! $nciEnabled,
                    ])>
                <x-icon name="{{ $nciEnabled ? 'pause' : 'zap' }}" class="h-4 w-4" wire:loading.remove wire:target="toggleNci" />
                <svg wire:loading wire:target="toggleNci" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                {{ $nciEnabled ? 'Turn NCI off' : 'Turn NCI on' }}
            </button>
        @endif
    </div>

    <div class="space-y-4">
        @foreach ($families as $f)
            <div wire:key="rc-{{ $f['key'] }}" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $f['name'] }}</h2>
                    <span class="text-[11px] uppercase text-[rgb(var(--nx-text-2))]">{{ $f['stack'] }}</span>
                </div>
                @if (count($f['order']))
                    <ol class="flex flex-wrap items-center gap-2">
                        @foreach ($f['order'] as $i => $provider)
                            <li class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-text))]">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[10px] font-bold text-[rgb(var(--nx-teal-ink))]">{{ $i + 1 }}</span>
                                    <span class="capitalize">{{ $provider }}</span>
                                </span>
                                @if (! $loop->last)<x-icon name="chevron-right" class="h-3.5 w-3.5 text-[rgb(var(--nx-text-3))]" />@endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">No eligible providers (all excluded or none configured).</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Manual preference (nci.override) --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Manual preference</h2>
        <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Pin a provider to the front of a stack's order for a few hours. It never resurrects an open circuit or skips the live call.</p>

        <div class="mt-3 space-y-2">
            @foreach ($prefs as $stack => $pref)
                @if ($pref)
                    <div class="flex items-center justify-between rounded-xl bg-[rgb(var(--nx-cta-b)/0.05)] px-3 py-2 text-sm">
                        <span><span class="uppercase text-[rgb(var(--nx-text-2))]">{{ $stack }}</span> → <span class="font-semibold capitalize text-[rgb(var(--nx-teal-ink))]">{{ $pref['provider'] }}</span> <span class="text-xs text-[rgb(var(--nx-text-2))]">until {{ \Illuminate\Support\Carbon::parse($pref['until'])->diffForHumans() }}</span></span>
                        <button type="button" wire:click="clearPreference('{{ $stack }}')" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-bad))]">Clear</button>
                    </div>
                @endif
            @endforeach
        </div>

        <form wire:submit="setPreference" class="mt-4 flex flex-wrap items-end gap-2">
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Stack</label>
                <select wire:model="prefStack" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-2 text-sm">
                    <option value="esim">eSIM</option><option value="sms">SMS</option><option value="permanent">Permanent</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Provider key</label>
                <input type="text" wire:model="prefProvider" placeholder="esimgo" class="w-36 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('prefProvider') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Hours</label>
                <input type="number" wire:model="prefHours" min="1" max="168" class="w-20 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Set preference</button>
        </form>
    </div>
</div>
