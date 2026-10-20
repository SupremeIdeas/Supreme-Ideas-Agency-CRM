{{-- A browser window showing widget analytics: KPIs, a daily chart and the funnel (MASTER-ONLY, developer landing mock-up). --}}
@php
    $views = [32, 41, 38, 52, 47, 63, 58, 71, 66, 80, 74, 92]; $orders = [2, 3, 2, 4, 3, 5, 4, 6, 5, 7, 6, 8];
    $n = count($views); $W = 360; $H = 110; $step = $W / $n; $max = max($views);
    $line = implode(' ', array_map(fn ($v, $i) => round($i * $step + $step / 2, 1).','.round(8 + ($H - 22) - $v / $max * ($H - 22), 1), $views, array_keys($views)));
@endphp
<div class="overflow-hidden rounded-2xl border border-white/10 bg-[#0D1B2A] shadow-2xl shadow-black/40 {{ $class ?? '' }}" role="img" aria-label="Browser window showing widget analytics with views, orders, conversion, a daily chart and a funnel">
    <div class="flex items-center gap-2 border-b border-white/10 bg-[#12263A] px-4 py-2.5">
        <span class="h-2.5 w-2.5 rounded-full bg-[#D4A017]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#0A6E6E]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#4A6178]"></span>
        <span class="ml-3 flex-1 truncate rounded-md bg-[#0D1B2A] px-3 py-1 font-mono text-[11px] text-[#9FB3C6]">{{ $host }}/merchant/widgets</span>
    </div>
    <div class="p-4 text-[#E4ECF3]" aria-hidden="true">
        <div class="grid grid-cols-3 gap-2">
            @foreach ([['Views', '1,672', '+18%'], ['Orders', '97', '+24%'], ['Conversion', '35.7%', 'per checkout']] as [$k, $v, $d])
                <div class="rounded-xl bg-[#12263A] p-3"><p class="text-[9.5px] font-bold uppercase tracking-wider text-[#9FB3C6]">{{ $k }}</p><p class="mt-0.5 text-lg font-bold text-white">{{ $v }}</p><p class="text-[10.5px] font-semibold text-[#7FE0D8]">{{ $d }}</p></div>
            @endforeach
        </div>
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-3 h-28 w-full" preserveAspectRatio="none">
            @foreach ($orders as $i => $o)<rect x="{{ round($i * $step + $step * .22, 1) }}" y="{{ round($H - 14 - $o / 8 * 60, 1) }}" width="{{ round($step * .56, 1) }}" height="{{ round($o / 8 * 60, 1) }}" rx="2" fill="#0A6E6E" />@endforeach
            <polyline points="{{ $line }}" fill="none" stroke="#D4A017" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
        </svg>
        @foreach ([['Widget views', 100], ['Checkout opened', 16], ['Order completed', 6]] as [$l, $p])
            <div class="mt-1.5 flex items-center gap-2 text-[11px]"><span class="w-28 shrink-0 text-[#9FB3C6]">{{ $l }}</span><span class="h-2.5 flex-1 overflow-hidden rounded-full bg-[#12263A]"><span class="block h-full rounded-full bg-gradient-to-r from-[#0A6E6E] to-[#D4A017]" style="width: {{ $p }}%"></span></span><span class="w-9 text-right font-semibold text-white">{{ $p }}%</span></div>
        @endforeach
    </div>
</div>
