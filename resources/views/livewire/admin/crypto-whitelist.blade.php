{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm';
    $btn = 'rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50';
    $btn2 = 'rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50';
@endphp
<div class="mx-auto max-w-5xl space-y-6 pb-12">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Crypto wallet whitelist</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">NOWPayments only pays wallets on its dashboard whitelist. Download the new addresses, add them in NOWPayments (Settings, Whitelist), then mark them live here. Payouts to an address wait until it is marked live.</p>
        </div>
        <a href="{{ route('admin.payout-health', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Payout health</a>
    </div>

    @if ($message)<div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $message }}</div>@endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="{{ $card }}"><p class="text-xs text-[rgb(var(--nx-text-2))]">New, not downloaded</p><p class="text-2xl font-bold text-[rgb(var(--nx-text))]">{{ $counts['pending'] }}</p></div>
        <div class="{{ $card }}"><p class="text-xs text-[rgb(var(--nx-text-2))]">Downloaded, awaiting activation</p><p class="text-2xl font-bold text-[rgb(var(--nx-text))]">{{ $counts['exported'] }}</p></div>
        <div class="{{ $card }}"><p class="text-xs text-[rgb(var(--nx-text-2))]">Live (payable)</p><p class="text-2xl font-bold text-[rgb(var(--nx-text))]">{{ $counts['whitelisted'] }}</p></div>
    </div>

    <section class="{{ $card }}" aria-labelledby="h-new">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="h-new" class="text-base font-bold text-[rgb(var(--nx-text))]">New addresses to whitelist</h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $btn }}" wire:click="exportNew" wire:loading.attr="disabled" wire:target="exportNew" @disabled($counts['pending'] === 0)>
                    <span wire:loading.remove wire:target="exportNew">Download new as CSV</span><span wire:loading wire:target="exportNew">Preparing…</span>
                </button>
                <button type="button" class="{{ $btn2 }}" wire:click="exportAll" wire:loading.attr="disabled" wire:target="exportAll">Download everything awaiting</button>
            </div>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-[rgb(var(--nx-text-2))]"><tr><th class="py-2 pr-3">Select</th><th class="pr-3">Network</th><th class="pr-3">Address</th><th>Added</th></tr></thead>
                <tbody>
                @forelse ($pending->concat($exported) as $a)
                    <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="wl-{{ $a->id }}">
                        <td class="py-2 pr-3"><input type="checkbox" wire:model="selected" value="{{ $a->id }}" aria-label="Select address {{ $a->id }}"></td>
                        <td class="pr-3 text-[rgb(var(--nx-text))]">{{ $a->bank_name }}<span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $a->whitelist_status === 'exported' ? 'downloaded' : 'new' }}</span></td>
                        <td class="break-all pr-3 font-mono text-xs text-[rgb(var(--nx-text))]">{{ $a->account_number }}</td>
                        <td class="text-xs text-[rgb(var(--nx-text-2))]">{{ optional($a->created_at)->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No addresses waiting. New wallets users save will appear here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="{{ $btn }}" wire:click="markSelected" wire:loading.attr="disabled" wire:target="markSelected" @disabled(empty($selected))>Mark selected as whitelisted</button>
            <button type="button" class="{{ $btn2 }}" wire:click="markAllExported" wire:confirm="Only do this after you have added every downloaded address in the NOWPayments dashboard. Continue?" wire:loading.attr="disabled" wire:target="markAllExported" @disabled($counts['exported'] === 0)>Mark all downloaded as whitelisted</button>
        </div>
    </section>
</div>
