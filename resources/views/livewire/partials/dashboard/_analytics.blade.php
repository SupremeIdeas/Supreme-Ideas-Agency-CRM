{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Connectivity Analytics blueprint Part A §2.5 — Home hero. Dependency-free SVG sparklines only (Chart.js is reserved for My Line's
     richer charts); real data-usage + wallet-flow figures, never invented. Only shown once an account has at least one line. Line colours
     are tokens (teal ink / money gold), so every skin and mode recolours them. --}}
@if ($showAnalyticsHero)
    <a href="{{ route('numbers.lines') }}" wire:navigate class="ns-grid" style="text-decoration:none;margin-top:20px">
        <div class="ns-card ns-ring" style="padding:14px">
            <small class="ns-small" style="display:flex;align-items:center;gap:6px"><x-nx.icon name="bars" style="color:rgb(var(--nx-teal-ink))" /> Data used this week</small>
            <b class="ns-spark-figure">
                @if ($weeklyUsageMb >= 1024)
                    {{ number_format($weeklyUsageMb / 1024, 1) }} GB
                @else
                    {{ number_format($weeklyUsageMb, 0) }} MB
                @endif
            </b>
            @if ($hasUsageData)
                <svg viewBox="0 0 200 48" class="ns-spark" role="img" aria-label="Daily data usage, last 7 days" preserveAspectRatio="none">
                    <polygon points="0,44 {{ $usageSparkline }} 200,44" class="ns-spark__fill" />
                    <polyline points="{{ $usageSparkline }}" class="ns-spark__line ns-spark__line--teal" />
                </svg>
            @else
                <div class="ns-text-muted" style="height:48px;display:flex;align-items:center;font-size:12px">No usage yet this week.</div>
            @endif
        </div>

        <div class="ns-card ns-ring" style="padding:14px">
            <span style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                <small class="ns-small" style="display:flex;align-items:center;gap:6px"><x-nx.icon name="wallet" style="color:rgb(var(--nx-teal-ink))" /> Cash flow (30d)</small>
                <span  class="ns-linkink" style="font-size:12px;display:inline-flex;align-items:center;gap:2px">See more <x-nx.icon name="chevron-right" /></span>
            </span>
            <span style="display:flex;gap:12px;font-size:11px;margin-top:8px;color:rgb(var(--nx-text-2))">
                <span style="display:inline-flex;align-items:center;gap:4px"><span class="ns-dot ns-dot--gold"></span> Deposits</span>
                <span style="display:inline-flex;align-items:center;gap:4px"><span class="ns-dot ns-dot--teal"></span> Spend</span>
            </span>
            @if ($hasFlowData)
                <svg viewBox="0 0 200 48" class="ns-spark" role="img" aria-label="Deposits versus spend, last 30 days" preserveAspectRatio="none">
                    <polyline points="{{ $depositsSparkline }}" class="ns-spark__line ns-spark__line--gold" />
                    <polyline points="{{ $spendSparkline }}" class="ns-spark__line ns-spark__line--teal" />
                </svg>
            @else
                <div class="ns-text-muted" style="height:48px;display:flex;align-items:center;font-size:12px">No wallet activity yet this month.</div>
            @endif
        </div>
    </a>
@endif
