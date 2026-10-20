{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Financial reconciliation</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Money in vs. wallet credits vs. paid out vs. provider costs — built on the wallet ledger, so an unaccounted-for gap surfaces early.</p>
        </div>
        {{-- Period toggle --}}
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

    <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">{{ $report['from'] }} → {{ $report['to'] }} · all figures USD unless labelled</p>

    {{-- Reconciliation signal — the headline: money the gateways report in vs.
         money the wallet ledger credited. A large gap = investigate. --}}
    @php($gap = $report['reconciliation_gap'])
    <div @class([
        'mb-6 rounded-2xl border p-5',
        'border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)]' => abs($gap) < 0.01,
        'border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)]' => abs($gap) >= 0.01,
    ])>
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-[rgb(var(--nx-text-2))]">Reconciliation gap</p>
                <p @class([
                    'mt-1 text-3xl font-bold',
                    'text-[rgb(var(--nx-ok))]' => abs($gap) < 0.01,
                    'text-[rgb(var(--nx-warn))]' => abs($gap) >= 0.01,
                ])>${{ number_format($gap, 2) }}</p>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Gateway money in (${{ number_format($report['total_in'], 2) }}) − wallet credits (${{ number_format($report['wallet_credits'], 2) }})</p>
            </div>
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold',
                'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' => abs($gap) < 0.01,
                'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' => abs($gap) >= 0.01,
            ])>
                <x-icon name="{{ abs($gap) < 0.01 ? 'check' : 'info' }}" class="h-4 w-4" />
                {{ abs($gap) < 0.01 ? 'Balanced' : 'Investigate' }}
            </span>
        </div>
        @if (abs($gap) >= 0.01)
            <p class="mt-3 text-xs text-[rgb(var(--nx-warn))]">A non-zero gap can mean a top-up webhook charged the customer but never credited the wallet (or a double credit). Check recent charges against wallet transactions.</p>
        @endif
    </div>

    {{-- KPI row --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Money in', $report['total_in'], 'Gateway charges'],
            ['Paid out', $report['paid_out'], 'Settled payouts'],
            ['Provider cost', $report['provider_cost'], 'Fulfilled orders'],
            ['Gross profit', $report['gross_profit'], 'Retail − cost'],
        ] as [$label, $value, $sub])
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">${{ number_format($value, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Money in by gateway --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Money in by gateway</h2>
            @forelse ($report['in_by_gateway'] as $gateway => $amount)
                <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                    <span class="capitalize text-[rgb(var(--nx-text-2))]">{{ $gateway }}</span>
                    <span class="font-semibold text-[rgb(var(--nx-text))]">${{ number_format($amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No gateway charges in this period.</p>
            @endforelse
        </div>

        {{-- Wallet ledger by type --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Wallet ledger by type</h2>
            @forelse ($report['wallet_by_type'] as $type => $amount)
                <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] py-2 text-sm last:border-0">
                    <span class="capitalize text-[rgb(var(--nx-text-2))]">{{ $type }}</span>
                    <span class="font-semibold text-[rgb(var(--nx-text))]">${{ number_format($amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No wallet movements in this period.</p>
            @endforelse
        </div>
    </div>

    {{-- Outstanding liabilities (point-in-time) --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Outstanding liabilities <span class="font-normal text-[rgb(var(--nx-text-2))]">(right now, not the period)</span></h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">What the platform still owes — user wallet balances + payouts not yet settled.</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Wallet balances (USD)</p>
                <p class="text-lg font-bold text-[rgb(var(--nx-text))]">${{ number_format($report['outstanding_usd'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Wallet balances (NGN, legacy)</p>
                <p class="text-lg font-bold text-[rgb(var(--nx-text))]">₦{{ number_format($report['outstanding_ngn'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Payouts owed</p>
                <p class="text-lg font-bold text-[rgb(var(--nx-text))]">${{ number_format($report['pending_out'], 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Payout rails (money OUT, per provider + currency) --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Payout rails <span class="font-normal text-[rgb(var(--nx-text-2))]">(each in its own currency)</span></h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Sent, confirmed, pending and failed per provider, with the float ledger check. A flagged rail needs a human look.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="py-2 pr-3">Rail</th><th class="py-2 pr-3 text-right">Sent</th><th class="py-2 pr-3 text-right">Confirmed</th><th class="py-2 pr-3 text-right">Pending</th><th class="py-2 pr-3 text-right">Failed</th><th class="py-2 pr-3 text-right">Float</th><th class="py-2">Check</th>
                </tr></thead>
                <tbody>
                    @forelse ($report['payout_rails'] as $rail)
                        <tr class="border-b border-[rgb(var(--nx-line))]">
                            <td class="py-2 pr-3 font-medium capitalize text-[rgb(var(--nx-text))]">{{ $rail['provider'] }} <span class="text-[rgb(var(--nx-text-2))]">{{ $rail['currency'] }}</span></td>
                            <td class="py-2 pr-3 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format($rail['sent'], 2) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format($rail['confirmed'], 2) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format($rail['pending'], 2) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format($rail['failed'], 2) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ $rail['float_balance'] === null ? '—' : number_format($rail['float_balance'], 2) }}</td>
                            <td class="py-2">
                                @if ($rail['mismatches'])
                                    <span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-bad))]">{{ str_replace('_', ' ', implode(', ', $rail['mismatches'])) }}</span>
                                @else
                                    <span class="text-xs text-[rgb(var(--nx-text-2))]">ok</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No payouts in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
