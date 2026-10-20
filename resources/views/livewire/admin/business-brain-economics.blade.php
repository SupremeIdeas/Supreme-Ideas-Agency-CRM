{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-5xl space-y-4 p-4 text-[rgb(var(--nx-text))]">
    @php($usd = fn ($v) => '$'.number_format((float) $v, 2))
    <div>
        <a href="{{ route('admin.business-suite') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">Business Suite</a>
        <h1 class="text-xl font-bold">Brain economics</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">What NaaraBrain costs against what Naara Pro earns. Revenue is each paying plan's monthly price, prorated to the window; cost is the real provider cost of NaaraBrain chat and the inbox assistant. Margin here is AI-only: telephony, SMS and hosting are tracked elsewhere.</p>
    </div>
    <div class="flex gap-2" role="group" aria-label="Window">
        @foreach ([7, 30, 90] as $d)
            <button type="button" wire:click="setDays({{ $d }})" class="nc__chip {{ $days === $d ? 'is-on' : '' }}" aria-pressed="{{ $days === $d ? 'true' : 'false' }}">{{ $d }} days</button>
        @endforeach
    </div>

    <div class="nx-tablewrap rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[rgb(var(--nx-line))] text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <tr><th class="px-3 py-3">Plan</th><th class="px-3 py-3 text-right">Subs</th><th class="px-3 py-3 text-right">Revenue</th><th class="px-3 py-3 text-right">Chat cost</th><th class="px-3 py-3 text-right">Assistant cost</th><th class="px-3 py-3 text-right">Actions</th><th class="px-3 py-3 text-right">Margin</th><th class="px-3 py-3 text-right">Margin %</th></tr>
            </thead>
            <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($report['rows'] as $r)
                    <tr wire:key="pl-{{ $loop->index }}">
                        <td class="px-3 py-3 font-semibold">{{ $r['plan'] }}@if ($r['complimentary'])<span class="ml-1 text-xs font-normal text-[rgb(var(--nx-text-2))]">(no revenue)</span>@endif</td>
                        <td class="px-3 py-3 text-right">{{ $r['subscribers'] }}</td>
                        <td class="px-3 py-3 text-right">{{ $usd($r['revenue']) }}</td>
                        <td class="px-3 py-3 text-right">{{ $usd($r['chat_cost']) }}</td>
                        <td class="px-3 py-3 text-right">{{ $usd($r['assist_cost']) }}</td>
                        <td class="px-3 py-3 text-right">{{ $r['actions'] }}</td>
                        <td class="px-3 py-3 text-right font-semibold {{ $r['margin'] < 0 ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-ok))]' }}">{{ $usd($r['margin']) }}</td>
                        <td class="px-3 py-3 text-right">{{ $r['margin_pct'] === null ? 'n/a' : $r['margin_pct'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-[rgb(var(--nx-text-2))]">No Naara Pro plans or AI usage in this window yet.</td></tr>
                @endforelse
            </tbody>
            @if ($report['rows'])
                <tfoot class="border-t border-[rgb(var(--nx-line-strong))] font-semibold"><tr><td class="px-3 py-3">All</td><td class="px-3 py-3 text-right">{{ $report['totals']['subscribers'] }}</td><td class="px-3 py-3 text-right">{{ $usd($report['totals']['revenue']) }}</td><td class="px-3 py-3 text-right" colspan="2">{{ $usd($report['totals']['cost']) }} total AI cost</td><td class="px-3 py-3 text-right">{{ $report['totals']['actions'] }}</td><td class="px-3 py-3 text-right {{ $report['totals']['margin'] < 0 ? 'text-[rgb(var(--nx-bad))]' : '' }}">{{ $usd($report['totals']['margin']) }}</td><td></td></tr></tfoot>
            @endif
        </table>
    </div>

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm">
        <h2 class="font-semibold">Heaviest users</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Highest AI cost first. "Covered" means that user's plan revenue for the window is at least their AI cost.</p>
        @forelse ($report['top'] as $t)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-2" wire:key="tp-{{ $t['user_id'] }}">
                <span><b>{{ $t['name'] }}</b> <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $t['plan'] }}</span></span>
                <span>{{ $usd($t['cost']) }} cost · {{ $usd($t['revenue']) }} plan <b class="{{ $t['covered'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}">{{ $t['covered'] ? 'Covered' : 'Loss' }}</b></span>
            </div>
        @empty
            <p class="mt-2 text-[rgb(var(--nx-text-2))]">No AI usage by Naara Pro owners in this window.</p>
        @endforelse
        <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">To change what a plan allows, use Brand directory > plan > capabilities (inbox assistant actions per month, NaaraBrain questions per day). The default allowance and depth are in Business Suite settings > NaaraBrain assistant.</p>
    </section>
</div>
