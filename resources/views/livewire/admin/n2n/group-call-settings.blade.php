{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl space-y-4 p-4">
    <div>
        <a href="{{ route('admin.n2n') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">N2N</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Group calls</h1>
    </div>
    @if ($saved)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $saved }}</div>@endif

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold">Status: {{ $status['enabled'] ? 'On' : 'Off' }}</h2>
            <button type="button" wire:click="recheck" wire:loading.attr="disabled" wire:target="recheck" class="nc__chip">Re-check SFU</button>
        </div>
        @foreach ($status['reasons'] as $r)<p class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $r }}</p>@endforeach
        <p class="mt-2 text-[rgb(var(--nx-text-2))]">SFU: {{ $sfu['configured'] ? 'configured in the environment' : 'not configured' }}. Group calls need a VPS running a self-hosted LiveKit server; cPanel installs keep this off. Setup, bandwidth maths and the measured launch cap: docs/N2N-GROUP-CALLS.md. Calls are encrypted in transit but decrypted at the SFU: they are not end-to-end encrypted.</p>
    </section>

    <form wire:submit="save" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <label class="flex gap-2"><input type="checkbox" wire:model="enabled"> <span>Enable group calls</span></label>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ([['maxSpeakers', 'Speakers per room (1 to 5, host included)'], ['maxCapacity', 'People per room (cap)'], ['maxRooms', 'Open rooms at once, whole platform'], ['roomsPerDay', 'Rooms one host can start per day'], ['linkHours', 'Invite link lifetime (hours)'], ['idleMinutes', 'Close an empty room after (minutes)']] as [$f, $l])
                <label class="flex items-center justify-between gap-2"><span>{{ $l }}</span><input type="number" wire:model="{{ $f }}" class="w-24 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]"></label>
                @error($f)<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            @endforeach
        </div>
        <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Speakers pay the per-minute call price for the time they are appointed to speak (muting does not stop the clock); listeners are free. Start with a low room cap and raise it only against the measured numbers in the docs.</p>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill ns-cta--sm mt-3">Save</button>
    </form>

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <h2 class="font-semibold">Measured</h2>
        <p class="mt-1">Open now: {{ $stats['open'] }} · all rooms ever: {{ $stats['total'] }} · finished: {{ $stats['ended'] }} · {{ number_format($stats['cents'] / 100, 2) }} credits charged to speakers.</p>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">SFU bandwidth is not measured by the app: read it from your server's network graph and compare it with the budget in docs/N2N-GROUP-CALLS.md.</p>
    </section>
</div>
