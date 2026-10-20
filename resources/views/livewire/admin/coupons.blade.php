{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Coupons</h1>
    <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">Discount codes for banner offers. Every discount is margin-guarded: the charged price is automatically clamped above provider cost + minimum profit, so a coupon can never sell at a loss.</p>

    {{-- Marketing nudges toggle (owner request): friendly first-purchase /
         comeback offers shown on the dashboard of not-yet-purchased accounts. --}}
    <div class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[rgb(var(--nx-gold)/0.15)] text-[rgb(var(--nx-gold-ink))]"><x-icon name="gift" class="h-5 w-5" /></span>
            <div>
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Marketing nudges</p>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Show a friendly {{ \App\Support\MarketingCoupons::WELCOME_CODE }} / {{ \App\Support\MarketingCoupons::COMEBACK_CODE }} offer to accounts that haven't purchased yet.</p>
            </div>
        </div>
        <button type="button" wire:click="toggleNudges" role="switch" aria-checked="{{ $nudgesOn ? 'true' : 'false' }}"
                @class([
                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition',
                    'bg-[rgb(var(--nx-cta-b))]' => $nudgesOn,
                    'bg-[rgb(var(--nx-line-strong))]' => ! $nudgesOn,
                ])>
            <span @class(['inline-block h-5 w-5 transform rounded-full bg-[rgb(var(--nx-surface))] transition', 'translate-x-5' => $nudgesOn, 'translate-x-0.5' => ! $nudgesOn])></span>
        </button>
    </div>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="gift" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> New coupon
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Code</label>
                <div class="flex gap-2">
                    <input wire:model="code" type="text" placeholder="WELCOME10"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase tracking-wider text-[rgb(var(--nx-text))]">
                    <button type="button" wire:click="generateCode" class="shrink-0 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Generate</button>
                </div>
                @error('code') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Discount %</label>
                <input wire:model="percent_off" type="number" min="1" max="90" step="0.5"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('percent_off') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Whatever the percent, the price never drops below cost + minimum profit.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Applies to</label>
                <select wire:model="applies_to" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="all">Everything</option>
                    <option value="esim">eSIM plans only</option>
                    <option value="number">Numbers only</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Total uses <span class="font-normal text-[rgb(var(--nx-text-2))]">(blank = ∞)</span></label>
                    <input wire:model="max_redemptions" type="number" min="1"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('max_redemptions') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Per-user limit</label>
                    <input wire:model="per_user_limit" type="number" min="1" max="100"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('per_user_limit') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Expires <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional)</span></label>
                <input wire:model="expires_at" type="datetime-local"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('expires_at') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Create coupon</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Creating…</span>
                </button>
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
        <table class="w-full text-left text-sm">
            <thead class="bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]">
                <tr>
                    <th class="px-4 py-2 font-medium">Code</th>
                    <th class="px-4 py-2 font-medium">Discount</th>
                    <th class="px-4 py-2 font-medium">Scope</th>
                    <th class="px-4 py-2 font-medium">Used</th>
                    <th class="px-4 py-2 font-medium">Given away</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                @forelse ($coupons as $coupon)
                    <tr wire:key="coupon-{{ $coupon->id }}" class="text-[rgb(var(--nx-text))]">
                        <td class="px-4 py-2.5 font-mono font-semibold uppercase">{{ $coupon->code }}</td>
                        <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format((float) $coupon->percent_off, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-2.5 capitalize">{{ $coupon->applies_to === 'all' ? 'Everything' : $coupon->applies_to }}</td>
                        <td class="px-4 py-2.5">{{ $coupon->times_redeemed }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td>
                        <td class="px-4 py-2.5">${{ number_format((float) ($coupon->total_saved ?? 0), 2) }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.tag :variant="$coupon->isRedeemable() ? 'live' : 'soon'">
                                {{ $coupon->isRedeemable() ? 'Live' : ($coupon->is_active ? 'Ended' : 'Paused') }}
                            </x-ui.tag>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="toggle({{ $coupon->id }})" wire:loading.attr="disabled"
                                    class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-teal-ink))]">
                                {{ $coupon->is_active ? 'Pause' : 'Resume' }}
                            </button>
                            <button type="button" wire:click="delete({{ $coupon->id }})" wire:loading.attr="disabled"
                                    wire:confirm="Delete coupon {{ $coupon->code }}? A coupon that has been used is paused instead, to keep the audit trail."
                                    class="ml-2 text-xs font-medium text-[rgb(var(--nx-bad))] underline hover:text-[rgb(var(--nx-bad))]">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-[rgb(var(--nx-text-2))]">No coupons yet — create one above, then attach it to a banner.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
