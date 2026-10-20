{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-6xl" @if (in_array($tab, ['overview', 'live'], true)) wire:poll.15s @endif>
    @php
        $inp = 'rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
        $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 shadow-sm';
        $usd = fn ($v) => '$'.number_format((float) $v, 2);
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Global payout rail</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Who chose the global rail, what they could withdraw, and how much to fund next. Admin only.</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-[rgb(var(--nx-ok))]"></span> Live</span>
            <span>{{ $lastSnapshot ? 'snapshot '.\Illuminate\Support\Carbon::parse($lastSnapshot)->diffForHumans() : 'no snapshot yet' }}</span>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="provider" class="{{ $inp }} capitalize" aria-label="Provider">
            @foreach ($providers as $p) <option value="{{ $p }}">{{ str_replace('_', ' ', $p) }}</option> @endforeach
        </select>
        <input type="text" wire:model.live.debounce.400ms="country" maxlength="2" placeholder="Country" class="{{ $inp }} w-24 uppercase" aria-label="Country">
        <input type="text" wire:model.live.debounce.400ms="currency" maxlength="3" placeholder="Currency" class="{{ $inp }} w-24 uppercase" aria-label="Currency">
    </div>

    <div class="mt-4 flex flex-wrap gap-1 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-1 text-sm" role="tablist">
        @foreach (['overview' => 'Overview', 'users' => 'Users', 'live' => 'Live analytics', 'planner' => 'Funding planner', 'unserved' => 'Unserved demand'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="rounded-lg px-3 py-1.5 font-medium transition {{ $tab === $key ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ───────── Overview ───────── --}}
    @if ($tab === 'overview')
        @if ($overview['will_stall'])
            <div class="mt-4 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm font-medium text-[rgb(var(--nx-bad))]">Payouts will stall: float ({{ $usd($overview['float_available']) }}) is below what is already committed plus due at the next sweep.</div>
        @endif
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Enrolled users <span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px]">Exact</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ array_sum($overview['users']) }}</div>
                <div class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $overview['users']['selected'] }} selected · {{ $overview['users']['onboarding'] }} onboarding · {{ $overview['users']['active'] }} active</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Max exposure <span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px]">Exact</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['max_exposure']) }}</div><div class="text-[11px] text-[rgb(var(--nx-text-2))]">if everyone withdrew now</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Committed unsent <span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px]">Exact</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['committed_unsent']) }}</div><div class="text-[11px] text-[rgb(var(--nx-text-2))]">pending · approved · awaiting funds</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">In flight <span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px]">Exact</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['in_flight']) }}</div><div class="text-[11px] text-[rgb(var(--nx-text-2))]">sent, awaiting provider confirmation</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Due at next sweep <span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px]">Exact</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['due_next_sweep']) }}</div><div class="text-[11px] text-[rgb(var(--nx-text-2))]">0 in manual mode</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Forecast 7 days <span class="ml-1 rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[10px] text-[rgb(var(--nx-warn))]">Estimate</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['forecast_7d_p50']) }} <span class="text-sm font-normal text-[rgb(var(--nx-text-2))]">p50</span></div>
                <div class="text-[11px] text-[rgb(var(--nx-text-2))]">p90 {{ $usd($overview['forecast_7d_p90']) }} · {{ $overview['low_confidence'] ? 'low confidence ('.$overview['history_weeks'].' wk data)' : $overview['history_weeks'].' weeks of data' }}</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Float available</div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $overview['float_unknown'] ? 'Unknown' : $usd($overview['float_available']) }}</div>
                <div class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $overview['float_unknown'] ? 'record it under Funding planner' : 'tracked float, in USD' }}</div></div>
            <div class="{{ $card }} border-[rgb(var(--nx-teal)/0.4)]"><div class="text-xs text-[rgb(var(--nx-text-2))]">Recommended top-up now <span class="ml-1 rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[10px] text-[rgb(var(--nx-warn))]">Estimate</span></div>
                <div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($overview['recommended_topup_p50']) }} <span class="text-sm font-normal text-[rgb(var(--nx-text-2))]">p50</span></div>
                <div class="text-[11px] text-[rgb(var(--nx-text-2))]">p90 {{ $usd($overview['recommended_topup_p90']) }}{{ $overview['float_unknown'] ? ' · before your recorded float' : '' }}</div></div>
        </div>
    @endif

    {{-- ───────── Users ───────── --}}
    @if ($tab === 'users')
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search name or email" class="{{ $inp }}" aria-label="Search">
            <select wire:model.live="status" class="{{ $inp }}" aria-label="Status">
                <option value="">Any status</option>@foreach (['selected', 'onboarding', 'active', 'paused', 'declined'] as $s) <option value="{{ $s }}">{{ ucfirst($s) }}</option> @endforeach
            </select>
            <input type="number" step="any" wire:model.live.debounce.500ms="minBalance" placeholder="Min $" class="{{ $inp }} w-24" aria-label="Minimum balance">
            <input type="number" step="any" wire:model.live.debounce.500ms="maxBalance" placeholder="Max $" class="{{ $inp }} w-24" aria-label="Maximum balance">
            <label class="flex items-center gap-1 text-xs text-[rgb(var(--nx-text-2))]"><input type="checkbox" wire:model.live="sweepOnly"> Swept next run</label>
            <label class="flex items-center gap-1 text-xs text-[rgb(var(--nx-text-2))]"><input type="checkbox" wire:model.live="kycBlockedOnly"> KYC blocked</label>
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="ml-auto rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Export CSV</button>
        </div>

        <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    @foreach (['name' => 'User', 'country' => 'Country', 'status' => 'Status'] as $col => $label)
                        <th class="px-3 py-3"><button type="button" wire:click="sortBy('{{ $col }}')" class="uppercase">{{ $label }}{{ $sort === $col ? ($desc ? ' ↓' : ' ↑') : '' }}</button></th>
                    @endforeach
                    <th class="px-3 py-3">Stripe</th>
                    <th class="px-3 py-3 text-right">Credits</th><th class="px-3 py-3 text-right">Referral</th><th class="px-3 py-3 text-right">Merchant</th><th class="px-3 py-3 text-right">Partner</th><th class="px-3 py-3 text-right">Staff</th>
                    @foreach (['total' => 'Total', 'committed' => 'Committed', 'lifetime_paid' => 'Paid', 'kyc' => 'KYC'] as $col => $label)
                        <th class="px-3 py-3 text-right"><button type="button" wire:click="sortBy('{{ $col }}')" class="uppercase">{{ $label }}{{ $sort === $col ? ($desc ? ' ↓' : ' ↑') : '' }}</button></th>
                    @endforeach
                    <th class="px-3 py-3">Flags</th><th class="px-3 py-3"></th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="rail-user-{{ $r['enrollment_id'] }}">
                            <td class="px-3 py-2"><div class="font-medium text-[rgb(var(--nx-text))]">{{ $r['name'] }}</div><div class="text-[11px] text-[rgb(var(--nx-text-2))]">#{{ $r['user_id'] }} · {{ $r['currency'] }} · {{ $r['masked'] }}</div></td>
                            <td class="px-3 py-2 text-[rgb(var(--nx-text-2))]">{{ $r['country'] }}</td>
                            <td class="px-3 py-2 capitalize text-[rgb(var(--nx-text-2))]">{{ $r['status'] }}</td>
                            <td class="px-3 py-2 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $r['reason'] ? str_replace('_', ' ', $r['reason']) : '—' }}</td>
                            @foreach (['credits', 'referral', 'merchant', 'partner', 'staff'] as $k) <td class="px-3 py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $r['b'][$k], 2) }}</td> @endforeach
                            <td class="px-3 py-2 text-right font-semibold tabular-nums text-[rgb(var(--nx-text))]">{{ number_format((float) $r['total'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $r['committed'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $r['lifetime_paid'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-[rgb(var(--nx-text-2))]">L{{ $r['kyc'] }}</td>
                            <td class="px-3 py-2 text-[11px]">
                                @if ($r['will_sweep']) <span class="rounded bg-[rgb(var(--nx-ok)/0.16)] px-1 text-[rgb(var(--nx-ok))]">sweep</span> @endif
                                @if ($r['kyc_blocked']) <span class="rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[rgb(var(--nx-warn))]">KYC</span> @endif
                                @if ($r['cooling']) <span class="rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[rgb(var(--nx-text-2))]">cooling-off</span> @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if ($r['status'] === 'paused')
                                    <button type="button" wire:click="resume({{ $r['enrollment_id'] }})" wire:loading.attr="disabled" class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Resume</button>
                                @else
                                    <button type="button" wire:click="pause({{ $r['enrollment_id'] }})" wire:loading.attr="disabled" wire:confirm="Pause this enrollment?" class="text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]">Pause</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="16" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No enrolled users match.</td></tr>
                    @endforelse
                </tbody>
                @if ($totals['users'] > 0)
                    <tfoot><tr class="border-t-2 border-[rgb(var(--nx-line))] text-sm font-semibold text-[rgb(var(--nx-text))]">
                        <td class="px-3 py-3" colspan="4">Filtered total · {{ $totals['users'] }} users</td>
                        @foreach (['credits', 'referral', 'merchant', 'partner', 'staff', 'total', 'committed'] as $k) <td class="px-3 py-3 text-right tabular-nums" data-total="{{ $k }}">{{ number_format((float) $totals[$k], 2) }}</td> @endforeach
                        <td colspan="4"></td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
        @if ($pages > 1)
            <div class="mt-3 flex items-center justify-center gap-2 text-sm">
                <button type="button" wire:click="$set('page', {{ max(1, $page - 1) }})" @disabled($page <= 1) class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1 disabled:opacity-40">Prev</button>
                <span class="text-[rgb(var(--nx-text-2))]">Page {{ $page }} of {{ $pages }}</span>
                <button type="button" wire:click="$set('page', {{ min($pages, $page + 1) }})" @disabled($page >= $pages) class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1 disabled:opacity-40">Next</button>
            </div>
        @endif
    @endif

    {{-- ───────── Live analytics ───────── --}}
    @if ($tab === 'live')
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Success rate (24h)</div><div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $successRate === null ? '—' : $successRate.'%' }}</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Avg time to paid</div><div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $avgTimeToPaid ? gmdate('H:i:s', $avgTimeToPaid) : '—' }}</div></div>
            <div class="{{ $card }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Avg / median withdrawal (30d)</div><div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $usd($avgSize) }} / {{ $usd($medianSize) }}</div></div>
            <div class="{{ $card }} {{ $velocity['flag'] ? 'border-[rgb(var(--nx-bad))]' : '' }}"><div class="text-xs text-[rgb(var(--nx-text-2))]">Velocity: 1h / 24h / 7-day avg per day</div><div class="mt-1 text-xl font-bold text-[rgb(var(--nx-text))]">{{ $velocity['h1'] }} / {{ $velocity['d1'] }} / {{ $velocity['avg7'] }}</div>@if ($velocity['flag'])<div class="text-[11px] text-[rgb(var(--nx-bad))]">above normal</div>@endif</div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Requested vs paid, last 24h (USD)</h2>
                <div class="mt-3 space-y-1">
                    @php($maxBar = max(1, $byHour->max(fn ($h) => max($h['requested'], $h['paid'])) ?? 1))
                    @forelse ($byHour as $hour => $h)
                        <div class="flex items-center gap-2 text-[11px]"><span class="w-10 text-[rgb(var(--nx-text-2))]">{{ $hour }}</span>
                            <div class="flex-1"><div class="h-1.5 rounded bg-[rgb(var(--nx-cta-b)/0.7)]" style="width: {{ round($h['requested'] / $maxBar * 100) }}%"></div><div class="mt-0.5 h-1.5 rounded bg-[rgb(var(--nx-ok)/0.7)]" style="width: {{ round($h['paid'] / $maxBar * 100) }}%"></div></div>
                            <span class="w-24 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format($h['requested'], 0) }} / {{ number_format($h['paid'], 0) }}</span></div>
                    @empty <p class="text-sm text-[rgb(var(--nx-text-2))]">No withdrawals in the last 24 hours.</p> @endforelse
                </div>
                <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]"><span class="text-[rgb(var(--nx-teal-ink))]">■</span> requested · <span class="text-[rgb(var(--nx-ok))]">■</span> paid</p>
            </div>
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Top countries (24h, USD requested)</h2>
                <ul class="mt-3 space-y-1 text-sm text-[rgb(var(--nx-text-2))]">
                    @forelse ($byCountry as $c => $v) <li class="flex justify-between"><span>{{ $c }}</span><span class="tabular-nums">{{ $usd($v) }}</span></li> @empty <li class="text-[rgb(var(--nx-text-2))]">No data yet.</li> @endforelse
                </ul>
                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Failure reasons (30d)</h3>
                <ul class="mt-1 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                    @forelse ($failureReasons as $reason => $n) <li class="flex justify-between gap-3"><span class="truncate">{{ $reason }}</span><span>{{ $n }}</span></li> @empty <li class="text-[rgb(var(--nx-text-2))]">None.</li> @endforelse
                </ul>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Concentration: top users by balance</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($top as $t) <li class="flex justify-between {{ $t['share'] > 20 ? 'font-semibold text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-text-2))]' }}"><span>{{ $t['name'] }}</span><span class="tabular-nums">{{ $usd($t['usd']) }} · {{ $t['share'] }}%</span></li> @empty <li class="text-[rgb(var(--nx-text-2))]">No balances.</li> @endforelse
                </ul>
            </div>
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">New enrollments (14 days)</h2>
                <ul class="mt-3 space-y-1 text-sm text-[rgb(var(--nx-text-2))]">
                    @forelse ($newEnrollments as $day => $n) <li class="flex justify-between"><span>{{ $day }}</span><span>{{ $n }}</span></li> @empty <li class="text-[rgb(var(--nx-text-2))]">None.</li> @endforelse
                </ul>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
            <h2 class="px-4 pt-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Latest withdrawals</h2>
            <table class="mt-2 w-full text-sm"><tbody>
                @forelse ($feed as $f)
                    <tr class="border-t border-[rgb(var(--nx-line))]"><td class="px-4 py-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $f['at']->diffForHumans() }}</td><td class="px-4 py-2 text-[rgb(var(--nx-text-2))]">{{ $f['user'] }}</td><td class="px-4 py-2 text-[rgb(var(--nx-text-2))]">{{ $f['country'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-[rgb(var(--nx-text))]">{{ $usd($f['usd']) }} <span class="text-xs text-[rgb(var(--nx-text-2))]">({{ $f['local'] }})</span></td><td class="px-4 py-2 text-xs capitalize text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $f['status']) }}</td></tr>
                @empty <tr><td class="px-4 py-6 text-center text-[rgb(var(--nx-text-2))]">No withdrawals yet.</td></tr> @endforelse
            </tbody></table>
        </div>
    @endif

    {{-- ───────── Funding planner ───────── --}}
    @if ($tab === 'planner')
        <div class="mt-4 {{ $card }}">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Recommended funding by currency <span class="ml-1 rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[10px] font-normal text-[rgb(var(--nx-warn))]">Estimate</span></h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]"><th class="py-2">Currency</th><th class="py-2 text-right">Exposure (USD)</th><th class="py-2 text-right">Top-up p50 (USD)</th><th class="py-2 text-right">Top-up p90 (USD)</th><th class="py-2 text-right">p50 in currency</th></tr></thead>
                <tbody>
                    @forelse ($planner['by_currency'] as $ccy => $row)
                        <tr class="border-t border-[rgb(var(--nx-line))]"><td class="py-2 font-medium text-[rgb(var(--nx-text))]">{{ $ccy }}</td><td class="py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $row['max_exposure_usd'], 2) }}</td>
                            <td class="py-2 text-right tabular-nums text-[rgb(var(--nx-text))]">{{ number_format((float) $row['topup_p50_usd'], 2) }}</td><td class="py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $row['topup_p90_usd'], 2) }}</td>
                            <td class="py-2 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ isset($row['topup_p50_local']) ? number_format((float) $row['topup_p50_local'], 2) : 'no live rate' }}</td></tr>
                    @empty <tr><td colspan="5" class="py-6 text-center text-[rgb(var(--nx-text-2))]">No exposure on this rail yet.</td></tr> @endforelse
                </tbody>
            </table>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Coverage: {{ $planner['coverage_days'] === null ? 'unknown (needs recorded float and paid history)' : $planner['coverage_days'].' days of average payouts' }}</p>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Float and last top-up</h2>
                <ul class="mt-2 space-y-1 text-sm text-[rgb(var(--nx-text-2))]">
                    @forelse ($planner['float_rows'] as $f) <li class="flex justify-between"><span>{{ $f['currency'] }}: {{ number_format((float) $f['balance'], 2) }}</span><span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $f['last_topup'] ? 'last top-up '.$f['last_topup']->created_at->diffForHumans() : 'no top-up recorded' }}</span></li>
                    @empty <li class="text-[rgb(var(--nx-text-2))]">No float tracked for this rail.</li> @endforelse
                </ul>
                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <input type="text" wire:model="topupCurrency" maxlength="3" class="{{ $inp }} uppercase" aria-label="Currency">
                    <input type="number" step="any" wire:model="topupAmount" placeholder="Amount" class="{{ $inp }}" aria-label="Amount">
                    <input type="text" wire:model="topupNote" placeholder="Note / bank reference" class="{{ $inp }}" aria-label="Note">
                </div>
                @error('topupAmount') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror @error('topupNote') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                <button type="button" wire:click="recordTopUp" wire:loading.attr="disabled" wire:target="recordTopUp" class="mt-3 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Record top-up</button>
            </div>
            <div class="{{ $card }}">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">What if…</h2>
                <label class="mt-2 block text-xs text-[rgb(var(--nx-text-2))]">% of enrolled balance withdrawn today</label>
                <input type="number" min="0" max="100" wire:model.live="whatIfPct" class="{{ $inp }} w-28" aria-label="Percent">
                <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">Funding needed: <strong class="text-[rgb(var(--nx-text))]">{{ $usd($planner['what_if']) }}</strong></p>
                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Recommended vs float, last 14 days</h3>
                <ul class="mt-1 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                    @forelse ($planner['history'] as $day => $h) <li class="flex justify-between"><span>{{ $day }}</span><span class="tabular-nums">rec. {{ number_format($h['recommended'], 2) }} · float {{ $h['float'] === null ? '—' : number_format((float) $h['float'], 2) }}</span></li> @empty <li class="text-[rgb(var(--nx-text-2))]">No snapshots yet.</li> @endforelse
                </ul>
            </div>
        </div>
    @endif

    {{-- ───────── Unserved demand ───────── --}}
    @if ($tab === 'unserved')
        <div class="mt-4 {{ $card }}">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Unserved demand</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Users holding withdrawable money in countries with NO enabled payout corridor. Not enrolled and not part of funding — it tells you which corridor to open next.</p>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]"><th class="py-2">Country</th><th class="py-2 text-right">Users</th><th class="py-2 text-right">Withdrawable (USD)</th><th class="py-2 text-right">Asked to be notified</th></tr></thead>
                <tbody>
                    @forelse ($unserved as $u)
                        <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="unserved-{{ $u['country'] }}"><td class="py-2 font-medium text-[rgb(var(--nx-text))]">{{ $u['country'] }}</td><td class="py-2 text-right">{{ $u['users'] }}</td><td class="py-2 text-right tabular-nums">{{ number_format((float) $u['usd'], 2) }}</td><td class="py-2 text-right">{{ $u['notify_requests'] }}</td></tr>
                    @empty <tr><td colspan="4" class="py-6 text-center text-[rgb(var(--nx-text-2))]">No unserved demand found.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
