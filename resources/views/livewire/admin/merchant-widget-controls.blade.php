{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Naara Widgets</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
        The merchant embed and WordPress channel. Every limit below is yours to tune. Keys are checked live on every request, so a revoke or the master switch takes effect immediately.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>
    @endif

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4"><p class="text-xs text-[rgb(var(--nx-text-2))]">Checkouts started (24h)</p><p class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ number_format($orders24h) }}</p></div>
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4"><p class="text-xs text-[rgb(var(--nx-text-2))]">Completed (24h)</p><p class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ number_format($completed24h) }}</p></div>
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4"><p class="text-xs text-[rgb(var(--nx-text-2))]">Held for review</p><p class="text-xl font-bold {{ $held->count() ? 'text-[rgb(var(--nx-warn))]' : 'text-[rgb(var(--nx-text))]' }}">{{ $held->count() }}</p></div>
    </div>

    <form wire:submit="save" class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <label class="flex items-center justify-between gap-4">
            <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Enable Naara Widgets</span>
                <span class="block text-xs text-[rgb(var(--nx-text-2))]">Off by default. When off, every widget API call, hosted checkout and merchant page returns 404.</span></span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
        </label>
        <div class="mt-5 grid gap-4 border-t border-[rgb(var(--nx-line))] pt-5 sm:grid-cols-3">
            @foreach ([
                ['maxKeys', 'Active keys per merchant', 1], ['catalogueRate', 'Catalogue requests / min / key', 1], ['checkoutRate', 'Checkout starts / min / key', 1],
                ['velocity', 'Checkout sessions / hour / key', 1], ['sessionTtl', 'Checkout link lifetime (minutes)', 1], ['guestCap', 'First-time buyer cap (USD)', 1],
            ] as [$field, $label, $min])
                <div>
                    <label for="mw-{{ $field }}" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $label }}</label>
                    <input id="mw-{{ $field }}" type="number" min="{{ $min }}" step="{{ $field === 'guestCap' ? '1' : '1' }}" wire:model="{{ $field }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error($field) <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            @endforeach
        </div>
        <div class="mt-5 space-y-4 border-t border-[rgb(var(--nx-line))] pt-5">
            <label class="flex items-center justify-between gap-4">
                <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Sell verification numbers through widgets</span>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">Off by default because it spends provider money. A key also needs the "number" product on it. The shopper pays first, the number is reserved at today's live price, no code in 15 minutes means an automatic refund, and the merchant's margin is booked only when the code arrives. Test with a small order before you tell merchants.</span></span>
                <input type="checkbox" wire:model="numbersEnabled" class="h-5 w-9 cursor-pointer rounded-full">
            </label>
            <div>
                <label for="mw-numsvc" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Services on offer (comma separated provider names, in this order)</label>
                <textarea id="mw-numsvc" rows="2" wire:model="numberServices" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></textarea>
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">For example: whatsapp, telegram, google, facebook. Names that are not in the number catalogue are skipped. Leave empty for the standard list of 20.</p>
                @error('numberServices') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <label class="flex items-center justify-between gap-4">
                <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Shopper "My orders" area</span>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">Lets a returning shopper sign in with an emailed code on the merchant's site to see their eSIMs, number codes and gift cards, download their data, or ask for deletion. A shopper only ever sees their own orders placed through that merchant.</span></span>
                <input type="checkbox" wire:model="accountEnabled" class="h-5 w-9 cursor-pointer rounded-full">
            </label>
            <div class="max-w-xs">
                <label for="mw-days" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Stay signed in for (days)</label>
                <input id="mw-days" type="number" min="1" max="90" wire:model="shopperDays" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('shopperDays') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] p-4" data-mw-pricing>
            <label class="flex items-center justify-between gap-4">
                <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Charge merchants for Widgets</span>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">Off = free for every merchant. On = a merchant pays once from their wallet to create keys, copy the snippet and download the plugin. Switch it off again whenever you like: everyone gets access straight away. Merchants who already paid keep access if you turn charging back on. Keys already live keep serving shoppers either way.</span></span>
                <input type="checkbox" wire:model="paidAccess" class="h-5 w-9 cursor-pointer rounded-full">
            </label>
            <div class="mt-3 max-w-xs">
                <label for="mw-price" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">One-time price (USD)</label>
                <input id="mw-price" type="number" min="0" step="0.01" wire:model="accessPrice" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">A price of 0 is the same as free, even with charging switched on.</p>
                @error('accessPrice') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Right now: <b class="text-[rgb(var(--nx-text))]">{{ $paidAccess && $accessPrice > 0 ? 'Paid, $'.number_format($accessPrice, 2) : 'Free for everyone' }}</b> (unsaved changes included). Collected so far: <b class="text-[rgb(var(--nx-text))]">${{ number_format($purchaseRevenue, 2) }}</b>.</p>
            @if ($purchases->isNotEmpty())
                <ul class="mt-2 grid gap-1 text-xs text-[rgb(var(--nx-text-2))]">
                    @foreach ($purchases as $pu)
                        <li wire:key="pu-{{ $pu->id }}">{{ $pu->merchant?->business_name ?? 'Merchant #'.$pu->merchant_id }} · {{ $pu->source === 'grant' ? 'granted by admin' : '$'.number_format((float) $pu->amount_usd, 2) }} · {{ $pu->granted_at?->diffForHumans() }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="mt-4">
            <label for="mw-plugin" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">WordPress plugin download link (optional)</label>
            <input id="mw-plugin" type="url" wire:model="pluginUrl" placeholder="https://" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Leave empty to let merchants download the plugin straight from their Widgets page (pre-filled with their key).</p>
            @error('pluginUrl') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill mt-5">Save settings</button>
    </form>

    {{-- Shopper data requests --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Shopper deletion requests</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">A shopper asked for their data to be deleted from the widget's "My orders" area. Deletion is never self-serve: carry it out with the account erasure tools (Admin > Users), then close the request here.</p>
        @forelse ($dataRequests as $d)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="dr-{{ $d->id }}">
                <span class="text-[rgb(var(--nx-text))]"><b>{{ $d->email }}</b> · {{ $d->merchant?->business_name }}</span>
                <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $d->created_at?->diffForHumans() }}</span>
                <button type="button" wire:click="closeRequest({{ $d->id }})" wire:confirm="Have you carried out this deletion?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Mark done</button>
            </div>
        @empty
            <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">No open requests.</p>
        @endforelse
    </div>

    {{-- Hold queue --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Orders held for a manual look</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Held orders are never delivered or refunded automatically: a replayed debit or a gift card that needs review. Settle them in Reconciliation or Refunds, then close the loop with the buyer.</p>
        @forelse ($held as $o)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="held-{{ $o->id }}">
                <span class="text-[rgb(var(--nx-text))]"><b>#{{ $o->id }}</b> {{ $o->kind }} · ${{ number_format((float) $o->merchant_price_usd, 2) }} · {{ $o->merchant?->business_name }}</span>
                <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-xs text-[rgb(var(--nx-text))]">{{ str_replace('_', ' ', (string) $o->failure_reason) }}</span>
                <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $o->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">Nothing is held.</p>
        @endforelse
    </div>

    {{-- Keys --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Widget keys</h2>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search merchant, label or key" aria-label="Search keys"
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
        </div>
        @forelse ($keys as $k)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="k-{{ $k->id }}">
                <span class="min-w-0 text-[rgb(var(--nx-text))]"><b>{{ $k->merchant?->business_name }}</b> · {{ $k->label }}
                    <span class="block truncate text-xs text-[rgb(var(--nx-text-2))]">{{ implode(', ', $k->allowedDomains()) }} · {{ implode('/', (array) $k->product_scopes) }} · {{ $sessions[$k->id] ?? 0 }} checkouts / 24h · seen {{ $k->last_seen_at?->diffForHumans() ?? 'never' }}</span></span>
                @if ($k->status === 'active')
                    @if ($paidAccess && $accessPrice > 0)<button type="button" wire:click="grantAccess({{ $k->merchant_id }})" wire:loading.attr="disabled" class="mr-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Grant access</button>@endif
                    <button type="button" wire:click="revoke({{ $k->id }})" wire:confirm="Revoke this key now?" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Revoke</button>
                @else
                    <span class="rounded-full bg-[rgb(var(--nx-line))] px-2 py-0.5 text-xs text-[rgb(var(--nx-text-2))]">Revoked</span>
                @endif
            </div>
        @empty
            <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">No keys yet.</p>
        @endforelse
    </div>
</div>
