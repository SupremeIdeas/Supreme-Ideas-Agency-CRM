{{-- nx:converted (skin tokens only) --}}
<div class="mb-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
    <p class="text-xs font-semibold uppercase tracking-wider text-[rgb(var(--nx-text-2))]">Naara ID lookup</p>
    <form wire:submit="find" class="mt-2 flex gap-2">
        <input type="text" wire:model="q" placeholder="Naara ID, @username or phone" aria-label="Naara ID, username or phone"
               class="min-w-0 flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
        <button type="submit" wire:loading.attr="disabled" wire:target="find" class="ns-cta ns-cta--pill ns-cta--sm">Look up</button>
    </form>
    @if ($message)<p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]" role="status">{{ $message }}</p>@endif
    @if ($user)
        <div class="mt-3 space-y-2 text-sm text-[rgb(var(--nx-text))]">
            <p><b>{{ $user->name }}</b> · {{ $user->naara_id ?? 'no Naara ID yet' }} · @{{ $user->username ?? 'no username' }}</p>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Phone: {{ $maskedPhone ?? 'none linked' }}@if ($user->n2n_hide_me) · hidden from search @endif</p>
            <form wire:submit="rename" class="flex gap-2">
                <input type="text" wire:model="newUsername" placeholder="New username" aria-label="New username" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                <button type="submit" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Force rename</button>
                @if ($maskedPhone)<button type="button" wire:click="releasePhone" wire:confirm="Release this phone number? The person must verify it again." class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Release phone</button>@endif
            </form>
        </div>
    @endif
</div>
