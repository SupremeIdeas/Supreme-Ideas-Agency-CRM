{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php
    $dot = fn ($state) => match ($state) {
        'operational' => 'bg-[rgb(var(--nx-ok))]',
        'degraded' => 'bg-[rgb(var(--nx-warn))]',
        'partial_outage' => 'bg-[rgb(var(--nx-warn))]',
        'major_outage' => 'bg-[rgb(var(--nx-bad))]',
        'maintenance' => 'bg-[rgb(var(--nx-teal))]',
        default => 'bg-[rgb(var(--nx-line-strong))]',
    };
    $bannerTone = match ($overall) {
        'operational' => 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]',
        'degraded', 'maintenance' => 'bg-[rgb(var(--nx-warn)/0.12)] text-[rgb(var(--nx-warn))]',
        default => 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]',
    };
@endphp
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))] sm:text-3xl">System status</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Live health of NaaraSim's services and Developer API.</p>

    {{-- Overall banner --}}
    <div class="mt-6 flex items-center gap-3 rounded-2xl px-5 py-4 {{ $bannerTone }}">
        <span class="relative flex h-3 w-3">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $dot($overall) }} opacity-60"></span>
            <span class="relative inline-flex h-3 w-3 rounded-full {{ $dot($overall) }}"></span>
        </span>
        <span class="text-base font-semibold">{{ $overallLabel }}</span>
        <span class="ml-auto text-xs opacity-70">Updated {{ now()->format('M j, H:i') }} UTC</span>
    </div>

    {{-- Components --}}
    <div class="mt-8 divide-y divide-[rgb(var(--nx-line))] overflow-hidden rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))]">
        @foreach ($components as $c)
            <div class="flex items-center justify-between px-5 py-4">
                <span class="text-sm font-medium text-[rgb(var(--nx-text))]">{{ $c['label'] }}</span>
                <span class="flex items-center gap-2 text-xs font-semibold {{ $c['state'] === 'operational' ? 'text-[rgb(var(--nx-ok))]' : ($c['state'] === 'not_live' ? 'text-[rgb(var(--nx-text-2))]' : 'text-[rgb(var(--nx-warn))]') }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ $dot($c['state']) }}"></span>
                    {{ \App\Support\StatusPage::stateLabel($c['state']) }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- Incident timeline --}}
    <h2 class="mb-4 mt-10 text-lg font-semibold text-[rgb(var(--nx-text))]">Incident history</h2>
    @forelse ($timeline as $incident)
        <div class="mb-4 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-[rgb(var(--nx-text))]">{{ $incident->title }}</h3>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $incident->isResolved() ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }}">
                    {{ ucfirst($incident->status) }}
                </span>
            </div>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Started {{ $incident->started_at->format('M j, Y H:i') }} UTC</p>
            <div class="mt-3 space-y-2 border-l-2 border-[rgb(var(--nx-line))] pl-4">
                @foreach ($incident->updates as $u)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-teal-ink))]">{{ ucfirst($u->status) }} · {{ $u->created_at->format('M j, H:i') }}</p>
                        <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $u->body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-[rgb(var(--nx-line))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
            No incidents reported in the last 90 days. All quiet.
        </p>
    @endforelse

    {{-- Subscribe --}}
    <div class="mt-10 rounded-2xl bg-[rgb(var(--nx-surface-2))] p-6">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Get status updates</h2>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">We'll email you when an incident is opened or resolved.</p>
        @if ($subscribed)
            <p class="mt-3 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-ok))]"><x-icon name="check" class="h-4 w-4" /> You're subscribed.</p>
        @else
            <form wire:submit="subscribe" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="email" wire:model="email" placeholder="you@example.com"
                    class="w-full rounded-full border border-[rgb(var(--nx-edge))] bg-[rgb(var(--nx-surface))] px-4 py-2.5 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]">
                <button type="submit" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b))] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Subscribe</button>
            </form>
            @error('email') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        @endif
    </div>
</div>
