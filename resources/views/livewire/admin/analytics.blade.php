{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Analytics</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">The platform deep-dive — wallet/FX, merchants, operational health, provider reliability. The Overview page stays the fast, glanceable summary.</p>
        </div>
        {{-- Period toggle (same convention as Reconciliation) --}}
        <div class="flex items-center gap-0.5 rounded-lg bg-[rgb(var(--nx-surface-3))] p-0.5">
            @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $d => $label)
                <button type="button" wire:click="setDays({{ $d }})"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-semibold transition',
                            'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm' => $days === $d,
                            'text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' => $days !== $d,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ============================== WALLET & FX ============================== --}}
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
            <x-icon name="wallet" class="h-4 w-4" /> Wallet & FX
        </h2>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Platform USD liability</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">${{ number_format($usdLiability, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Every wallet's usd_balance, right now</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Deposits — {{ $days }}d</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">${{ number_format(collect($topUpByGateway)->sum('volume'), 2) }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Across every gateway</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <a href="{{ route('admin.reconciliation') }}" wire:navigate class="group flex h-full flex-col justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Full reconciliation</p>
                    <span class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-[rgb(var(--nx-teal-ink))] group-hover:underline">
                        Open report <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                    </span>
                </a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h3 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Deposit volume by gateway</h3>
                @forelse ($topUpByGateway as $row)
                    <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                        <span class="capitalize text-[rgb(var(--nx-text-2))]">{{ $row['gateway'] }}</span>
                        <span class="font-semibold text-[rgb(var(--nx-text))]">${{ number_format($row['volume'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">No gateway charges in this period.</p>
                @endforelse
            </div>

            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h3 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Deposit volume by original currency</h3>
                <p class="mb-3 text-[11px] text-[rgb(var(--nx-text-2))]">What users actually typed in — before any FX conversion to USD.</p>
                @forelse ($topUpByCurrency as $row)
                    <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                        <span class="text-[rgb(var(--nx-text-2))]">{{ $row['currency'] }}</span>
                        <span class="font-semibold text-[rgb(var(--nx-text))]">{{ number_format($row['volume'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">No deposits in this period.</p>
                @endforelse
            </div>
        </div>

        <div x-data="{ open: false }" class="mt-4">
            <button type="button" @click="open = ! open" class="flex items-center gap-1.5 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" /> Live FX rates (USD → currency)
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm sm:grid-cols-3">
                @foreach ($fxRates as $row)
                    <div class="flex items-center justify-between">
                        <span class="text-[rgb(var(--nx-text-2))]">{{ $row['currency'] }}</span>
                        <span class="font-medium text-[rgb(var(--nx-text))]">{{ number_format($row['usd_rate'], 4) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== MERCHANTS ============================== --}}
    <section class="mb-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <x-icon name="id-card" class="h-4 w-4" /> Merchants
            </h2>
            <a href="{{ route('admin.merchants') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                Manage merchants <x-icon name="chevron-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Merchant earnings — {{ $days }}d</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">${{ number_format($merchantEarnings, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Accrued commission</p>
            </div>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h3 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Top merchants by client-order volume</h3>
            @forelse ($merchantLeaderboard as $row)
                <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                    <span class="min-w-0 truncate text-[rgb(var(--nx-text-2))]">{{ $row['business_name'] }} <span class="text-xs text-[rgb(var(--nx-text-2))]">· {{ $row['clients'] }} {{ Str::plural('client', $row['clients']) }}</span></span>
                    <span class="shrink-0 font-semibold text-[rgb(var(--nx-text))]">${{ number_format($row['volume'], 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No merchant client orders in this period.</p>
            @endforelse
        </div>
    </section>

    {{-- ============================== OPERATIONAL HEALTH ============================== --}}
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
            <x-icon name="shield-check" class="h-4 w-4" /> Operational health
        </h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">KYC approval rate</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $kyc['rate_pct'] !== null ? number_format($kyc['rate_pct'], 1).'%' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $kyc['approved'] }} of {{ $kyc['total'] }} decisions</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Refund rate</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $refunds['rate_pct'] !== null ? number_format($refunds['rate_pct'], 1).'%' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">${{ number_format($refunds['refunds'], 2) }} of ${{ number_format($refunds['revenue'], 2) }} revenue</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Support — open</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $support['open'] }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $support['resolved'] }} resolved this period</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Avg. resolution time</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $support['avg_resolution_hours'] !== null ? number_format($support['avg_resolution_hours'], 1).'h' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Created → last update, resolved tickets</p>
            </div>
        </div>
    </section>

    {{-- ============================== PROVIDER RELIABILITY + eSIM USAGE ============================== --}}
    <section>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <x-icon name="signal" class="h-4 w-4" /> Provider reliability & eSIM usage
            </h2>
            @can('nci.view')
                <a href="{{ route('admin.nci.health') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                    Full health monitor <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                </a>
            @endcan
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Providers tracked</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $providerReliability['tracked'] }}</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Degraded (circuit open)</p>
                <p @class(['mt-1 text-xl font-bold', 'text-[rgb(var(--nx-bad))]' => $providerReliability['degraded'] > 0, 'text-[rgb(var(--nx-text))]' => $providerReliability['degraded'] === 0])>{{ $providerReliability['degraded'] }}</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Avg. success rate (24h)</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $providerReliability['avg_success_rate_pct'] !== null ? number_format($providerReliability['avg_success_rate_pct'], 1).'%' : '—' }}</p>
            </div>
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Data used this week</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">
                    @if ($esimUsage['total_mb_this_week'] >= 1024)
                        {{ number_format($esimUsage['total_mb_this_week'] / 1024, 1) }} GB
                    @else
                        {{ number_format($esimUsage['total_mb_this_week'], 0) }} MB
                    @endif
                </p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $esimUsage['active_esims'] }} active · {{ $esimUsage['expiring_soon'] }} expiring soon</p>
            </div>
        </div>

        @if (count($providerReliability['worst']))
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h3 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Lowest success rate (24h)</h3>
                @foreach ($providerReliability['worst'] as $row)
                    <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                        <span class="flex items-center gap-2">
                            <span class="capitalize text-[rgb(var(--nx-text-2))]">{{ $row['provider_key'] }}</span>
                            @if ($row['circuit_breaker_state'] !== 'closed')
                                <span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-1.5 py-0.5 text-[10px] font-semibold uppercase text-[rgb(var(--nx-bad))]">{{ str_replace('_', ' ', $row['circuit_breaker_state']) }}</span>
                            @endif
                        </span>
                        <span class="font-semibold text-[rgb(var(--nx-text))]">{{ number_format($row['success_rate_pct'], 1) }}%</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
