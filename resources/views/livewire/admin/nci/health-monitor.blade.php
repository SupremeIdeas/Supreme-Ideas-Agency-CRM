{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Health Monitor</h1>
    <p class="mb-5 text-sm text-[rgb(var(--nx-text-2))]">Live provider health at a glance — the same data the System Health panel reports.</p>

    @if ($task)
        <div @class([
            'mb-5 flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm',
            'border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' => ! $task['overdue'],
            'border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' => $task['overdue'],
        ])>
            <x-icon name="{{ $task['overdue'] ? 'info' : 'check' }}" class="h-4 w-4" />
            <span>Provider health-check {{ $task['overdue'] ? 'is OVERDUE — the cron may not be firing' : 'last ran '.($task['ago'] ?? 'recently') }}. <a href="{{ route('admin.system-health') }}" wire:navigate class="underline">System Health</a></span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($rows as $r)
            <a href="{{ route('admin.nci.provider', ['provider' => $r->provider_key]) }}" wire:navigate wire:key="hm-{{ $r->provider_key }}"
               class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 transition hover:border-[rgb(var(--nx-teal)/0.4)] hover:shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-semibold capitalize text-[rgb(var(--nx-text))]">{{ $r->provider_key }}</span>
                    <span class="h-3 w-3 rounded-full {{ ['ok' => 'bg-[rgb(var(--nx-ok))]', 'low' => 'bg-[rgb(var(--nx-warn))]', 'down' => 'bg-[rgb(var(--nx-bad))]'][$r->status] ?? 'bg-[rgb(var(--nx-line-strong))]' }}"></span>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <x-nci.pill kind="status" :value="$r->status" />
                    <x-nci.pill kind="circuit" :value="$r->circuit_breaker_state" />
                </div>
                <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $r->last_checked_at?->diffForHumans() ?? 'never checked' }}</p>
            </a>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No providers yet — fills after the first health-check cycle.</p>
        @endforelse
    </div>
</div>
