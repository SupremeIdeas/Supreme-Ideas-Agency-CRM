{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-[rgb(var(--nx-text))]">
                <x-icon name="zap" class="h-6 w-6 text-[rgb(var(--nx-teal-ink))]" /> Plan Price with Claude
            </h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
                Claude analyses your live provider costs and proposes the most profitable, competitive retail prices.
                You approve — and every price stays above its profit floor, guaranteed.
            </p>
        </div>
        @if ($enabled)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-ok))]">
                <span class="h-1.5 w-1.5 rounded-full bg-[rgb(var(--nx-ok))]"></span> Claude active
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-surface-3))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))]">
                <span class="h-1.5 w-1.5 rounded-full bg-[rgb(var(--nx-line-strong))]"></span> Claude off
            </span>
        @endif
    </div>

    @if ($notice)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $notice }}</span>
        </div>
    @endif
    @if ($error)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
        </div>
    @endif

    {{-- Always-on margin monitor (no API call). --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="signal" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Live margin monitor
        </h2>
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-[rgb(var(--nx-ok)/0.12)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-ok))]">{{ $monitor['healthy'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">Healthy</div>
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-warn)/0.12)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-warn))]">{{ $monitor['thin'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">Thin margin</div>
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-bad)/0.1)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-bad))]">{{ $monitor['at_floor'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">At the floor</div>
            </div>
        </div>
        @if (! empty($monitor['flags']))
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">
                {{ count($monitor['flags']) }} plan(s) need attention — let Claude propose a fix below.
            </p>
        @endif
    </div>

    {{-- Market competitiveness (always-on, admin-tunable model — owner request). --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="globe" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Market competitiveness
        </h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $market['verdict'] }}</p>
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-[rgb(var(--nx-ok)/0.12)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-ok))]">{{ $market['competitive'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">Competitive</div>
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-cta-b)/0.05)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-teal-ink))]">{{ $market['keen'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">Keen (below market)</div>
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-warn)/0.12)] p-3 text-center">
                <div class="text-2xl font-bold text-[rgb(var(--nx-warn))]">{{ $market['premium'] }}</div>
                <div class="text-xs text-[rgb(var(--nx-text-2))]">Above market</div>
            </div>
        </div>
        @if (! empty($market['rows']))
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                        <tr><th class="py-2">Plan</th><th class="py-2 text-right">Our price</th><th class="py-2 text-right">Est. market</th><th class="py-2 text-right">vs market</th><th class="py-2 text-right">Position</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach (collect($market['rows'])->take(8) as $r)
                            <tr wire:key="mkt-{{ $r['plan_id'] }}">
                                <td class="py-2 text-[rgb(var(--nx-text))]">{{ $r['name'] }}</td>
                                <td class="py-2 text-right font-semibold text-[rgb(var(--nx-text))]">${{ number_format($r['current_retail_usd'], 2) }}</td>
                                <td class="py-2 text-right text-[rgb(var(--nx-text-2))]">${{ number_format($r['market_low'], 2) }}–${{ number_format($r['market_high'], 2) }}</td>
                                <td class="py-2 text-right {{ $r['delta_pct'] > 0 ? 'text-[rgb(var(--nx-warn))]' : 'text-[rgb(var(--nx-teal-ink))]' }}">{{ $r['delta_pct'] > 0 ? '+' : '' }}{{ $r['delta_pct'] }}%</td>
                                <td class="py-2 text-right">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                        'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' => $r['position'] === 'competitive',
                                        'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' => $r['position'] === 'keen',
                                        'bg-[rgb(var(--nx-warn)/0.12)] text-[rgb(var(--nx-warn))]' => $r['position'] === 'premium',
                                    ])>{{ $r['position'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-3 text-[11px] text-[rgb(var(--nx-text-2))]">Market estimate is an illustrative, admin-tunable model (per-GB + per-day + base) — not scraped competitor data. When your Anthropic key is on, Claude enriches this with live research in its proposal below.</p>
    </div>

    {{-- Generate / analyse --}}
    @unless ($proposal)
        <div class="mb-6 rounded-2xl border border-dashed border-[rgb(var(--nx-teal)/0.4)] bg-[rgb(var(--nx-cta-b)/0.05)] p-6 text-center">
            @if ($enabled)
                <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">
                    Ask Claude to analyse every active plan against provider costs and competitor pricing, then
                    propose the best retail prices for your approval.
                </p>
                <button type="button" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="analyze" class="inline-flex items-center gap-2">
                        <x-icon name="zap" class="h-4 w-4" /> Analyse &amp; propose prices
                    </span>
                    <span wire:loading wire:target="analyze" class="inline-flex items-center gap-2">
                        <x-ui.spinner class="h-4 w-4" /> Asking Claude…
                    </span>
                </button>
                @if ($analyzing)
                    <p wire:poll.4s class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Analysing… this can take a few seconds.</p>
                @endif
            @else
                <p class="mb-3 text-sm text-[rgb(var(--nx-text-2))]">
                    This feature runs on your Anthropic API key. Add it once and Claude will price everything for you.
                </p>
                <a href="{{ route('admin.api-keys') }}" class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-teal)/0.4)] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]">
                    <x-icon name="key" class="h-4 w-4" /> Add Anthropic key
                </a>
            @endif
        </div>
    @else
        {{-- Pending proposal review --}}
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="zap" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Claude’s proposal
                </h2>
                <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $proposal->model_used }}</span>
            </div>

            @if ($proposal->summary)
                <p class="mb-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3 text-sm text-[rgb(var(--nx-text-2))]">{{ $proposal->summary }}</p>
            @endif

            @php($meta = $proposal->meta ?? [])
            @if (! empty($meta['recommended_max_discount_pct']) || ! empty($meta['recommended_credit_redeem_pct']))
                <div class="mb-4 flex flex-wrap gap-2 text-xs">
                    @if (! empty($meta['recommended_max_discount_pct']))
                        <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 font-medium text-[rgb(var(--nx-teal-ink))]">Safe max coupon: {{ $meta['recommended_max_discount_pct'] }}%</span>
                    @endif
                    @if (! empty($meta['recommended_credit_redeem_pct']))
                        <span class="rounded-full bg-[rgb(var(--nx-gold)/0.15)] px-3 py-1 font-medium text-[rgb(var(--nx-warn))]">Max NaaraCredit redeem: {{ $meta['recommended_credit_redeem_pct'] }}%</span>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-[rgb(var(--nx-line))] text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                            <th class="py-2 pr-2">Apply</th>
                            <th class="py-2 pr-2">Plan</th>
                            <th class="py-2 pr-2 text-right">Cost</th>
                            <th class="py-2 pr-2 text-right">Now</th>
                            <th class="py-2 pr-2 text-right">Proposed</th>
                            <th class="py-2 pr-2 text-right">Profit</th>
                            <th class="py-2 pr-2 text-right">Floor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proposal->lines as $line)
                            <tr class="border-b border-[rgb(var(--nx-line))] {{ $line->accepted ? '' : 'opacity-40' }}">
                                <td class="py-2 pr-2">
                                    <input type="checkbox" @checked($line->accepted) wire:click="toggleLine({{ $line->id }})"
                                           class="rounded text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal)/0.4)]">
                                </td>
                                <td class="py-2 pr-2">
                                    <div class="font-medium text-[rgb(var(--nx-text))]">{{ $line->name }}</div>
                                    @if ($line->rationale)
                                        <div class="text-xs text-[rgb(var(--nx-text-2))]">{{ $line->rationale }}</div>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs text-[rgb(var(--nx-text-2))]">${{ number_format($line->cost_usd, 2) }}</td>
                                <td class="py-2 pr-2 text-right font-mono text-[rgb(var(--nx-text-2))]">${{ number_format($line->current_retail_usd, 2) }}</td>
                                <td class="py-2 pr-2 text-right font-mono font-bold text-[rgb(var(--nx-teal-ink))]">
                                    ${{ number_format($line->proposed_retail_usd, 2) }}
                                    @if ($line->guard_applied)
                                        <span title="Raised to the profit floor by MarginGuard" class="ml-1 inline-flex align-middle text-[rgb(var(--nx-warn))]"><x-icon name="shield-check" class="h-3.5 w-3.5" /></span>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs {{ $line->projected_profit_usd > 0 ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}">
                                    +${{ number_format($line->projected_profit_usd, 2) }}
                                    <span class="text-[rgb(var(--nx-text-2))]">({{ number_format($line->projected_margin_pct, 0) }}%)</span>
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs text-[rgb(var(--nx-text-2))]">${{ number_format($line->floor_usd, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-3 flex items-center gap-1.5 text-xs text-[rgb(var(--nx-text-2))]">
                <x-icon name="shield-check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" />
                Every proposed price is clamped to at least cost + your minimum profit — approving can never sell below the floor.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <button type="button" wire:click="approve" wire:loading.attr="disabled" wire:target="approve"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="approve" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Approve &amp; update prices</span>
                    <span wire:loading wire:target="approve" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Applying…</span>
                </button>
                <button type="button" wire:click="reject"
                        class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                    <x-icon name="x" class="h-4 w-4" /> Dismiss
                </button>
            </div>
        </div>
    @endunless
</div>
