{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div>
    <h1 class="mb-6 text-2xl font-bold text-[rgb(var(--nx-text))]">Pricing</h1>

    {{-- AI Pricing Architect entry point (Plan Price with Claude). --}}
    <a href="{{ route('admin.pricing-architect') }}"
       class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] p-4 transition hover:bg-[rgb(var(--nx-cta-b)/0.1)]">
        <span class="flex items-center gap-3">
            <x-icon name="zap" class="h-6 w-6 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
            <span>
                <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Let Claude price everything for you</span>
                <span class="block text-xs text-[rgb(var(--nx-text-2))]">Analyse provider costs + competitors, propose the most profitable retail prices, and apply on your approval — always above your profit floor.</span>
            </span>
        </span>
        <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
    </a>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Public pricing page (Module 29) --}}
    <div class="mb-6 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="globe" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Public pricing page
        </h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">What visitors see at <a href="{{ route('pricing') }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline" target="_blank">/pricing</a>. Real plans need a live eSIM provider key; until then the estimate tiers keep the page honest.</p>

        <form wire:submit="savePublicPricing" class="space-y-4">
            <div class="max-w-sm">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Display mode</label>
                <select wire:model="public_mode" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="auto">Auto — live plans when a provider is connected, else estimates</option>
                    <option value="live">Always live plans (falls back to estimates if none)</option>
                    <option value="estimate">Always estimates (“from” pricing)</option>
                </select>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Estimate tiers</label>
                    <button type="button" wire:click="addTier" @disabled(count($estimate_tiers) >= 6)
                            class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">+ Add tier</button>
                </div>
                <div class="space-y-3">
                    @foreach ($estimate_tiers as $i => $tier)
                        <div wire:key="tier-{{ $i }}" class="grid gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 sm:grid-cols-12">
                            <input wire:model="estimate_tiers.{{ $i }}.name" placeholder="Name" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm sm:col-span-2">
                            <input wire:model="estimate_tiers.{{ $i }}.from_usd" type="number" step="0.01" placeholder="From $" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm sm:col-span-1">
                            <input wire:model="estimate_tiers.{{ $i }}.data" placeholder="3 GB" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm sm:col-span-1">
                            <input wire:model="estimate_tiers.{{ $i }}.validity" placeholder="30 days" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm sm:col-span-2">
                            <input wire:model="estimate_tiers.{{ $i }}.blurb" placeholder="Short blurb" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm sm:col-span-5">
                            <button type="button" wire:click="removeTier({{ $i }})" class="rounded-lg p-1.5 text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)] sm:col-span-1" aria-label="Remove tier"><x-icon name="trash" class="h-4 w-4" /></button>
                        </div>
                    @endforeach
                </div>
                @error('estimate_tiers.*.name') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                @error('estimate_tiers.*.from_usd') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="savePublicPricing"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="savePublicPricing" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save public pricing</span>
                <span wire:loading wire:target="savePublicPricing" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Global settings --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="settings" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Global
                </h2>
                <form wire:submit="saveGlobal" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Default markup %</label>
                        <input type="number" step="0.1" wire:model="default_markup_pct"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                        @error('default_markup_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Minimum profit floor (USD)</label>
                        <input type="number" step="0.01" wire:model="minimum_profit_usd"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                        @error('minimum_profit_usd') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </div>

                    {{-- Naara Gift: gift-card retail = provider cost + markup, never below cost + the minimum profit. --}}
                    <div class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2)/0.6)] p-3 sm:col-span-2">
                        <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text))]">Gift cards</p>
                        <p class="mb-3 text-[11px] text-[rgb(var(--nx-text-2))]">Applied to every gift card at checkout. Gift cards carry thin margins, so the default is lower than eSIMs.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Gift card markup %</label>
                                <input type="number" step="0.1" min="0" max="100" wire:model="giftcard_markup_pct"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                @error('giftcard_markup_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Gift card minimum profit (USD)</label>
                                <input type="number" step="0.01" min="0" max="50" wire:model="giftcard_min_profit"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                @error('giftcard_min_profit') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Margin-safe discount floor (discount-floor blueprint §2). --}}
                    <div class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2)/0.6)] p-3 sm:col-span-2">
                        <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text))]">Discount margin caps</p>
                        <p class="mb-3 text-[11px] text-[rgb(var(--nx-text-2))]">The max % of <em>margin</em> (not price) a single order's combined coupon + NaaraCredit discount may consume. The absolute floor above always wins if it's higher.</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Non-merchant cap (% of admin margin)</label>
                                <input type="number" step="1" min="0" max="100" wire:model="discount_margin_cap_pct"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                @error('discount_margin_cap_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Merchant lane — admin margin cap (%)</label>
                                <input type="number" step="1" min="0" max="100" wire:model="merchant_discount_admin_pct"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                @error('merchant_discount_admin_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Merchant lane — merchant margin cap (%)</label>
                                <input type="number" step="1" min="0" max="100" wire:model="merchant_discount_merchant_pct"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                @error('merchant_discount_merchant_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Outbound-SMS wholesale cost (a user texting from their Naara
                         Line). Retail is layered on by the pricing engine + margin
                         floor; this is the cost basis and is never shown to users. --}}
                    <div class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2)/0.6)] p-3">
                        <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Outbound SMS cost (per segment, USD)</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Twilio</label>
                                <input type="number" step="0.0001" min="0" wire:model="sms_send_cost_twilio"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                                @error('sms_send_cost_twilio') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Telnyx</label>
                                <input type="number" step="0.0001" min="0" wire:model="sms_send_cost_telnyx"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                                @error('sms_send_cost_telnyx') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <p class="mb-2 mt-3 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Outbound MMS cost (per photo, USD)</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Twilio</label>
                                <input type="number" step="0.0001" min="0" wire:model="mms_send_cost_twilio"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                                @error('mms_send_cost_twilio') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Telnyx</label>
                                <input type="number" step="0.0001" min="0" wire:model="mms_send_cost_telnyx"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                                @error('mms_send_cost_telnyx') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="saveGlobal"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveGlobal">Save &amp; reprice all</span>
                        <span wire:loading wire:target="saveGlobal" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </form>

                <div class="mt-6 border-t border-[rgb(var(--nx-line))] pt-4">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Provider keys</h3>
                    <ul class="space-y-1.5 text-sm text-[rgb(var(--nx-text-2))]">
                        <li class="flex items-center">eSIM Go API key <x-admin-help-icon provider="esimgo" field="api_key" /></li>
                        <li class="flex items-center">Airalo client secret <x-admin-help-icon provider="airalo" field="client_secret" /></li>
                        <li class="flex items-center">Getatext API key <x-admin-help-icon provider="getatext" field="api_key" /></li>
                        <li class="flex items-center">5sim API key <x-admin-help-icon provider="fivesim" field="api_key" /></li>
                        <li class="flex items-center">Paystack secret key <x-admin-help-icon provider="paystack" field="secret_key" /></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Plans + live profit --}}
        <div class="lg:col-span-2">
            @if ($summary && $editingPlanId)
                <div class="mb-4 rounded-xl border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
                        <x-icon name="zap" class="h-5 w-5 text-[rgb(var(--nx-gold-ink))]" /> Live profit
                    </h2>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Cost</div><div class="font-bold text-[rgb(var(--nx-text))]">${{ number_format($summary['cost_price'], 2) }}</div></div>
                        <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Retail</div><div class="font-bold text-[rgb(var(--nx-text))]">${{ number_format($summary['retail_price'], 2) }}</div></div>
                        <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Profit</div><div class="font-bold text-[rgb(var(--nx-ok))]">${{ number_format($summary['profit_usd'], 2) }}</div></div>
                        <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Margin</div><div class="font-bold text-[rgb(var(--nx-ok))]">{{ number_format($summary['profit_pct'], 1) }}%</div></div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Override markup %</label>
                            <input type="number" step="0.1" wire:model.live.debounce.300ms="override_markup_pct" placeholder="use global"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Fixed retail (USD)</label>
                            <input type="number" step="0.01" wire:model.live.debounce.300ms="manual_retail_usd" placeholder="use formula"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]"><input type="checkbox" wire:model="is_active" class="rounded text-[rgb(var(--nx-teal-ink))]"> Active</label>
                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]"><input type="checkbox" wire:model="is_featured" class="rounded text-[rgb(var(--nx-teal-ink))]"> Featured</label>
                        <div class="ml-auto flex gap-2">
                            <button type="button" wire:click="cancelEdit" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-sm">Cancel</button>
                            <button type="button" wire:click="savePlan" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save plan</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="px-4 py-2 font-medium">Plan</th>
                            <th class="px-4 py-2 font-medium">Retail</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                        @foreach ($plans as $plan)
                            <tr wire:key="pp-{{ $plan->id }}" class="text-[rgb(var(--nx-text))]">
                                <td class="px-4 py-2">{{ $plan->name }}</td>
                                <td class="px-4 py-2 font-medium">${{ number_format((float) $plan->final_retail_usd, 2) }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' => $plan->is_active,
                                        'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' => ! $plan->is_active,
                                    ])>{{ $plan->is_active ? 'Active' : 'Off' }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" wire:click="editPlan({{ $plan->id }})" class="text-[rgb(var(--nx-teal-ink))] hover:underline">Edit</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $plans->links() }}</div>
        </div>
    </div>
</div>
