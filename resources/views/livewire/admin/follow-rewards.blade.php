{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Follow rewards</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Follows on other platforms cannot be verified, so rewards are honour-based. These settings add friction against farming without getting in the way of real people. Rewards are funded by the platform (the amount comes from the brand's plan).</p>
    </div>

    <form wire:submit="save" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">The gate</h2>
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="enabled"> Require pressing Follow first and waiting before claiming</label>
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="verifiedEmail"> Require a verified email address</label>
        <div class="grid gap-3 sm:grid-cols-4">
            <div><label class="{{ $lb }}">Return delay (seconds)</label><input type="number" wire:model="seconds" min="0" max="120" class="{{ $in }}">@error('seconds')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Minimum account age (hours)</label><input type="number" wire:model="accountHours" min="0" max="720" class="{{ $in }}">@error('accountHours')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Claims per connection per hour</label><input type="number" wire:model="perIp" min="1" max="500" class="{{ $in }}">@error('perIp')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Claims per person per hour</label><input type="number" wire:model="perUser" min="1" max="500" class="{{ $in }}">@error('perUser')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Recent claims</h2>
        <div class="mt-3 space-y-3">
            @forelse ($claims as $c)
                <div wire:key="c-{{ $c->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-sm">
                    <p class="font-medium text-[rgb(var(--nx-text))]">{{ $c->user?->name }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $c->user?->email }}</span></p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $c->brandPartnerHandle?->handle_label ?? $c->handle?->handle_label ?? 'handle' }} · {{ $c->claimed_at?->format('d M Y H:i') }}@if ($c->ip_hash) · connection {{ substr($c->ip_hash, 0, 8) }}@endif</p>
                    @if ($c->clawed_back_at)
                        <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Taken back {{ $c->clawed_back_at->format('d M Y') }}: {{ $c->clawed_back_credits }} credits ({{ $c->clawback_reason }})</p>
                    @else
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <input type="text" wire:model="reason.{{ $c->id }}" placeholder="Reason (audit log)" class="{{ $in }} !w-56 !py-1.5 text-xs">
                            <button type="button" wire:click="clawback({{ $c->id }})" wire:loading.attr="disabled" wire:target="clawback({{ $c->id }})" wire:confirm="Take this reward back?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Take back</button>
                        </div>
                        @error('reason.'.$c->id)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    @endif
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No claims yet.</p>
            @endforelse
        </div>
    </div>
</div>
