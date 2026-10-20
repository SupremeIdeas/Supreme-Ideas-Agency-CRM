{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl space-y-4 p-4">
    <div>
        <a href="{{ route('admin.n2n') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">N2N</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Voice calls</h1>
    </div>
    @if ($saved)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $saved }}</div>@endif

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <h2 class="font-semibold">Status: {{ $status['enabled'] ? 'On' : 'Off' }}</h2>
        @foreach ($status['reasons'] as $r)<p class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $r }}</p>@endforeach
        <p class="mt-2 text-[rgb(var(--nx-text-2))]">TURN relay: {{ $turn['urls'] }} URL(s) configured, secret {{ $turn['secret'] ? 'set' : 'not set' }}. Calls need a VPS (Reverb + coturn); cPanel installs keep this off. Setup steps: docs/N2N-CALLS.md.</p>
    </section>

    <form wire:submit="save" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <label class="flex gap-2"><input type="checkbox" wire:model="enabled"> <span>Enable voice calls</span></label>
        <label class="flex gap-2"><input type="checkbox" wire:model="videoEnabled"> <span>Allow video on calls (shows a video button; camera is the person's choice on every call)</span></label>
        <label class="flex gap-2"><input type="checkbox" wire:model="reviewEnabled"> <span>Ask for a rating after a call, then invite the person to review us on Trustpilot</span></label>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $reviewUrl ? 'Trustpilot review form: '.$reviewUrl : 'Needs the Trustpilot profile address (Admin > More > Trustpilot reviews). Until it is set the sheet never shows.' }} Everyone who answers is invited equally, whatever stars they give (Trustpilot does not allow inviting only happy customers). Nothing is offered or paid for a review.</p>
        <div class="flex flex-wrap gap-3 text-sm">
            <label>Only after calls longer than (seconds)<input type="number" min="0" max="600" wire:model="reviewMinS" class="ns-input mt-1 w-28"></label>
            <label>Ask the same person at most once every (days)<input type="number" min="0" max="90" wire:model="reviewCooldownDays" class="ns-input mt-1 w-28"></label>
        </div>
        <label class="block text-sm">Video costs this many times the voice rate per minute<input type="number" step="0.1" min="1" max="10" wire:model="videoMultiplier" class="ns-input mt-1 w-32"></label>
        <label class="mt-2 flex gap-2"><input type="checkbox" wire:model="publicStun"> <span>Also offer a public STUN server (not needed once your own coturn answers STUN)</span></label>
        <label class="mt-2 flex gap-2"><input type="checkbox" wire:model="devMode"> <span><b>Local testing only:</b> skip the Reverb and TURN requirements (polling signalling, STUN only). Never use in production.</span></label>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ([['holdMinutes', 'Credit held per slice (minutes)'], ['incrementS', 'Billing increment (seconds)'], ['ringS', 'Ring time (seconds)'], ['heartbeatS', 'Heartbeat (seconds)'], ['staleS', 'Lost-peer threshold (seconds)'], ['attemptsPerMin', 'Call attempts per minute']] as [$f, $l])
                <label class="flex items-center justify-between gap-2"><span>{{ $l }}</span><input type="number" wire:model="{{ $f }}" class="w-24 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]"></label>
                @error($f)<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            @endforeach
        </div>
        <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">The per-minute call price is set in Admin > N2N (Call, USD per minute). The caller pays, the callee is free. Billing runs on the server clock from answer to end, rounded up to the increment.</p>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill ns-cta--sm mt-3">Save</button>
    </form>

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <h2 class="font-semibold">Measured</h2>
        <p class="mt-1">Completed calls: {{ $stats['calls'] }} · {{ $stats['minutes'] }} billed minutes · {{ number_format($stats['cents'] / 100, 2) }} credits charged · {{ $stats['missed'] }} missed.</p>
        <p class="mt-1">Relay share: @if ($stats['relay_share'] === null) not measured yet (no call has reported it) @else {{ $stats['relay_share'] }}% of {{ $stats['reported'] }} reporting calls used the TURN relay @endif.</p>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Relay bandwidth is not measured by the app: read it from coturn's own logs and use that figure for the price floor.</p>
    </section>
</div>
