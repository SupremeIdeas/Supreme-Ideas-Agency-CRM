{{-- Admin overview on the skin system (S3 Batch 5): every colour is a --nx-* token, so each of the 35 skins restyles it. Business logic and
     data are unchanged from the legacy view; only presentation moved. --}}
<div>
<x-nx.page :bleed="false" class="ns-adm">
    <h1 class="ns-adm__title">Overview</h1>

    {{-- Production misconfiguration banner (BUILD-1 §2.2 / §3.8): loud, admin-visible warnings for a sync queue or debug-on in production. --}}
    @foreach (\App\Support\EnvironmentGuard::warnings() as $warn)
        <div wire:key="envwarn-{{ $warn['key'] }}" class="ns-adm__alert ns-adm__alert--warn">
            <x-nx.icon name="shield" />
            <div><b>{{ $warn['title'] }}</b><small>{{ $warn['detail'] }}</small></div>
        </div>
    @endforeach

    {{-- Payment sandbox indicator (BUILD-2 §8): unmissable if any gateway is still pointed at its test environment. --}}
    @if (count($sandboxGateways) > 0)
        <div class="ns-adm__alert ns-adm__alert--warn">
            <x-nx.icon name="shield" />
            <div>
                <b>Payment gateway in TEST / sandbox mode</b>
                <small>{{ implode(', ', $sandboxGateways) }} {{ count($sandboxGateways) === 1 ? 'is' : 'are' }} using test keys. Real money will not move until you switch to live keys.</small>
            </div>
        </div>
    @endif

    @unless ($privileged)
        {{-- Staff view: scopes only, no business figures. --}}
        <section class="ns-card ns-adm__panel">
            <h2 class="ns-adm__h"><x-nx.icon name="shield" /> Your access</h2>
            <p class="ns-sub">You can work within these scopes. Ask a super admin if you need more.</p>
            <div class="ns-adm__chips">
                @forelse ($myScopes as $scope)
                    <span class="ns-pill ns-pill--rec"><x-nx.icon name="check" /> {{ $scopeLabels[$scope] ?? $scope }}</span>
                @empty
                    <span class="ns-sub">No scopes assigned yet.</span>
                @endforelse
            </div>
        </section>
    @else

    {{-- Revenue hero: real 30-day revenue, trend vs the previous window, and real last-7-days revenue bars. --}}
    <div class="ns-adm__grid ns-adm__grid--hero">
        <section class="ns-adm__rev">
            <div class="ns-adm__rev-top">
                <div>
                    <p class="ns-adm__rev-kicker"><x-nx.icon name="credit-card" /> Revenue — last 30 days</p>
                    <p class="ns-adm__rev-value">${{ number_format($revenue, 2) }}</p>
                    <p class="ns-adm__rev-note">eSIM data + numbers + verification + Naara Gift, before provider cost.</p>
                    @if ($handbookSales !== null)
                        <p class="ns-adm__rev-note" data-handbook-sales><a href="{{ route('admin.handbooks') }}" wire:navigate>Handbook sales (30d): <b>${{ number_format($handbookSales['net'], 2) }}</b> from {{ $handbookSales['sales'] }} sale(s)@if ($handbookSales['refunded'] > 0), ${{ number_format($handbookSales['refunded'], 2) }} refunded @endif, counted separately</a></p>
                    @endif
                </div>
                @if ($revenueDelta !== null)
                    <span @class(['ns-adm__delta', 'is-up' => $revenueDelta >= 0, 'is-down' => $revenueDelta < 0])>
                        <x-nx.icon name="chevron-right" class="{{ $revenueDelta >= 0 ? '-rotate-90' : 'rotate-90' }}" />
                        {{ $revenueDelta >= 0 ? '+' : '' }}{{ number_format($revenueDelta, 1) }}% vs previous 30 days
                    </span>
                @else
                    <span class="ns-adm__delta">First 30-day window</span>
                @endif
            </div>

            <div class="ns-adm__bars" role="img"
                 aria-label="Daily revenue, last 7 days: {{ collect($revenueBars)->map(fn ($b) => $b['label'].' $'.number_format($b['value'], 2))->implode(', ') }}">
                @foreach ($revenueBars as $k => $bar)
                    <div wire:key="revbar-{{ $k }}" class="ns-adm__bar">
                        <span class="ns-adm__bar-val">${{ number_format($bar['value'], 0) }}</span>
                        <i style="--bar-h: {{ max(4, round($bar['value'] / $barPeak * 100)) }}%; --bar-delay: {{ $k * 90 }}ms"></i>
                        <span class="ns-adm__bar-lbl">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Revenue split donut: 30-day revenue share per product line. --}}
        <section class="ns-card ns-adm__panel">
            <h2 class="ns-adm__h"><x-nx.icon name="signal" /> Revenue split (30d)</h2>
            <div class="ns-adm__split">
                <div class="ns-adm__donut" @if ($splitTotal > 0) style="--donut: {{ $splitGradient }}" @endif role="img" aria-label="Revenue split by product">
                    <div class="ns-adm__donut-hole">
                        <small>Total</small>
                        <b>${{ number_format($splitTotal, 0) }}</b>
                    </div>
                </div>
                <ul class="ns-adm__legend">
                    @foreach ($split as $k => $seg)
                        <li wire:key="split-{{ $k }}">
                            <span><i style="background: {{ $seg['color'] }}"></i><em>{{ $seg['label'] }}</em></span>
                            <b>{{ $splitTotal > 0 ? number_format($seg['value'] / $splitTotal * 100, 0).'%' : '—' }}</b>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($splitTotal <= 0)
                <p class="ns-sub">No sales in the last 30 days yet — the split fills in with your first orders.</p>
            @endif
        </section>
    </div>

    {{-- 30-day cost/profit tiles (admin-only figures) --}}
    <div class="ns-adm__grid ns-adm__grid--3">
        <x-nx.stat label="Cost (30d)" :value="'$'.number_format($cost, 2)" />
        <x-nx.stat label="Gross profit" :value="'$'.number_format($profit, 2)" tone="ok" />
        <x-nx.stat label="Gross margin" :value="number_format($margin, 1).'%'" tone="ok" />
    </div>
    <p class="ns-sub ns-adm__foot">Cost / profit / margin cover eSIM + numbers + verification only — Naara Gift's provider cost isn't tracked per order (see revenue split above for its revenue contribution).</p>

    {{-- Oversight metrics: users, weekly/monthly profit, API. --}}
    <div class="ns-adm__grid ns-adm__grid--4">
        <x-nx.stat label="Registered users" :value="number_format($totalUsers)"><small>+{{ number_format($newUsersWeek) }} this week</small></x-nx.stat>
        <x-nx.stat label="Profit — this week" :value="'$'.number_format($profitWeek, 2)" tone="ok"><small>revenue − provider cost</small></x-nx.stat>
        <x-nx.stat label="Profit — this month" :value="'$'.number_format($profitMonth, 2)" tone="ok"><small>month to date</small></x-nx.stat>
        <x-nx.stat label="API orders — week" :value="number_format($apiOrdersWeek)"><small>${{ number_format($apiRevenueWeek, 2) }} billed</small></x-nx.stat>
    </div>

    {{-- Most-bought by country + most-used models. --}}
    <div class="ns-adm__grid ns-adm__grid--2">
        <section class="ns-card ns-adm__panel">
            <h3 class="ns-adm__h"><x-nx.icon name="globe" /> Most-bought numbers by country <span>· 30d</span></h3>
            @php
                $__max = max(array_merge([0], array_column($topCountries, 'count'))) ?: 1;
            @endphp
            @forelse ($topCountries as $row)
                <div class="ns-adm__meter" wire:key="tc-{{ $row['country'] }}">
                    <em>{{ str_replace('_', ' ', $row['country']) }}</em>
                    <span><i style="width: {{ round($row['count'] / $__max * 100) }}%"></i></span>
                    <b>{{ $row['count'] }}</b>
                </div>
            @empty
                <p class="ns-sub">No number orders yet.</p>
            @endforelse
        </section>

        <section class="ns-card ns-adm__panel">
            <h3 class="ns-adm__h"><x-nx.icon name="grid" /> Most-used NaaraSim models <span>· 30d</span></h3>
            @php
                $__mmax = max(array_merge([0], array_column($topModels, 'count'))) ?: 1;
            @endphp
            @forelse ($topModels as $row)
                <div class="ns-adm__meter" wire:key="tm-{{ $row['label'] }}">
                    <em>{{ $row['label'] }}</em>
                    <span><i style="width: {{ round($row['count'] / $__mmax * 100) }}%"></i></span>
                    <b>{{ $row['count'] }}</b>
                </div>
            @empty
                <p class="ns-sub">No orders yet.</p>
            @endforelse
        </section>
    </div>

    <div class="ns-adm__grid ns-adm__grid--2">
        {{-- Provider health — every eSIM + number provider (BUILD-5 §2). `down` = API unreachable/erroring. --}}
        <section>
            <h2 class="ns-adm__h ns-adm__h--cap"><x-nx.icon name="signal" /> Provider health</h2>
            <div class="ns-adm__stack">
                @forelse ($health as $provider => $info)
                    @php
                        $st = $info['status'] ?? '';
                    @endphp
                    <div wire:key="health-{{ $provider }}" class="ns-card ns-adm__row">
                        <span class="ns-adm__row-name">
                            <b>{{ $provider }}</b>
                            @if (($info['stack'] ?? '') !== '')<span class="ns-pill">{{ $info['stack'] }}</span>@endif
                        </span>
                        <span class="ns-adm__row-end">
                            @if (! is_null($info['balance'] ?? null))<span class="ns-sub">${{ number_format((float) $info['balance'], 2) }}</span>@endif
                            <span @class(['ns-adm__status', 'is-ok' => $st === 'ok', 'is-warn' => $st === 'low', 'is-bad' => in_array($st, ['error', 'down'], true), 'is-info' => $st === 'configured'])>{{ ucfirst(str_replace('_', ' ', $st ?: 'unknown')) }}</span>
                        </span>
                    </div>
                @empty
                    <x-nx.empty text="No health data yet. Run providers:health-check." />
                @endforelse
            </div>
        </section>

        {{-- Active / Coming Soon per product --}}
        <section>
            <h2 class="ns-adm__h ns-adm__h--cap"><x-nx.icon name="badge-check" /> Products</h2>
            <div class="ns-adm__grid ns-adm__grid--2 ns-adm__grid--tight">
                @foreach ($statuses as $provider => $label)
                    <div wire:key="status-{{ $provider }}" class="ns-card ns-adm__row">
                        <b class="ns-adm__row-name">{{ $provider }}</b>
                        <x-ui.tag :variant="$label === 'Active' ? 'live' : 'soon'">{{ $label }}</x-ui.tag>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Tier 5 #15 — appended below every existing section above. Nothing above this divider moves, restyles, or reorders. --}}
    <div class="ns-adm__tier">
        {{-- Attention tile — the Hot Menu's live badge counts rolled up into one glance; only renders when something is pending. --}}
        @if ($attentionTotal > 0)
            <div class="ns-adm__alert ns-adm__alert--warn">
                <x-nx.icon name="bell" />
                <div>
                    <b>{{ $attentionTotal }} {{ \Illuminate\Support\Str::plural('item', $attentionTotal) }} need your attention today</b>
                    <span class="ns-adm__chips">
                        @foreach ($hotMenuSections as $section)
                            <a href="{{ route($section['route']) }}" wire:navigate class="ns-pill">{{ $section['total'] }} {{ $section['label'] }}</a>
                        @endforeach
                    </span>
                </div>
            </div>
        @endif

        <div class="ns-adm__grid ns-adm__grid--2">
            <x-nx.stat label="Background jobs (1h)" :value="$jobsThisHourTotal">
                @if ($jobsFailedThisHour > 0)
                    <small class="ns-adm__bad">{{ $jobsFailedThisHour }} failed</small>
                @else
                    <small class="ns-adm__ok"><x-nx.icon name="check" /> all healthy</small>
                @endif
            </x-nx.stat>
            <x-nx.stat label="KYC auto-resolved (30d)" :value="$kycAutomation['automated_pct'] !== null ? $kycAutomation['automated_pct'].'%' : '—'">
                <small>{{ $kycAutomation['automated_pct'] !== null ? $kycAutomation['automated'].'/'.$kycAutomation['total'].' automated' : 'No decisions yet this window.' }}</small>
            </x-nx.stat>
        </div>

        {{-- Per-provider live analytics — one card per currently CONFIGURED provider only. --}}
        <h2 class="ns-adm__h">Provider network</h2>
        @if (empty($providerCards))
            <x-nx.empty text="No providers configured yet." />
        @else
            <div class="ns-adm__grid ns-adm__grid--cards">
                @foreach ($providerCards as $card)
                    @php
                        $status = $card['health']['status'] ?? null;
                        $tone = match ($status) { 'ok', 'configured' => 'ok', 'low' => 'warn', 'down', 'error' => 'bad', default => 'off' };
                        $rates = collect($card['trend'])->pluck('rate_pct')->filter(fn ($r) => $r !== null)->values();
                        $latestRate = $rates->last();
                        $points = collect($card['trend'])->values()->map(function ($d, $i) use ($card) {
                            $x = count($card['trend']) > 1 ? $i / (count($card['trend']) - 1) * 100 : 0;
                            $y = $d['rate_pct'] === null ? 26 : round(26 - ($d['rate_pct'] / 100 * 26), 1);
                            return "{$x},{$y}";
                        })->implode(' ');
                    @endphp
                    <div wire:key="provider-card-{{ $card['provider'] }}" class="ns-card ns-adm__pcard">
                        <div class="ns-adm__row-name">
                            <i class="ns-adm__dot ns-adm__dot--{{ $tone }}"></i>
                            <b>{{ $card['label'] }}</b>
                            <span class="ns-sub ns-adm__stack-tag">{{ $card['stack'] }}</span>
                        </div>
                        <div class="ns-adm__grid ns-adm__grid--2 ns-adm__grid--tight">
                            <div>
                                <small class="ns-sub">Success rate (7d)</small>
                                <svg viewBox="0 0 100 26" class="ns-adm__spark" preserveAspectRatio="none" aria-hidden="true">
                                    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <b class="ns-adm__big">{{ $latestRate !== null ? $latestRate.'%' : '—' }}</b>
                            </div>
                            <div>
                                <small class="ns-sub">Orders (30d)</small>
                                <b class="ns-adm__big ns-adm__big--pad">{{ $card['order_volume'] }}</b>
                            </div>
                        </div>
                        <p class="ns-sub ns-adm__reach">Reachability: <b>{{ str_replace('_', ' ', $status ?? 'unknown') }}</b></p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endunless
</x-nx.page>
</div>
