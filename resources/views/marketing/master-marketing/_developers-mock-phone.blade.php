{{-- A phone showing what a shopper sees (MASTER-ONLY, developer landing mock-up). screen = shop | delivered. Built in HTML so it is crisp, themed and never a missing image. --}}
@php $screen = $screen ?? 'shop'; @endphp
<div class="relative mx-auto w-[236px] rounded-[2.1rem] border-[6px] border-[#0D1B2A] bg-[#0D1B2A] shadow-2xl shadow-[#0D1B2A]/40 ring-1 ring-white/10 {{ $class ?? '' }}" role="img"
     aria-label="{{ $screen === 'shop' ? 'A phone showing a merchant shop with eSIM, number and gift card tabs' : 'A phone showing an eSIM delivered with a QR code' }}">
    <span class="absolute left-1/2 top-1.5 z-10 h-2.5 w-14 -translate-x-1/2 rounded-full bg-black" aria-hidden="true"></span>
    <div class="min-h-[430px] overflow-hidden rounded-[1.6rem] bg-white px-4 pb-5 pt-8 text-[#0D1B2A]" aria-hidden="true">
        @if ($screen === 'shop')
            <p class="text-[11px] font-bold uppercase tracking-wider text-[#0A6E6E]">Sahara Connect</p>
            <p class="mt-0.5 text-[15px] font-bold">Stay connected abroad</p>
            <div class="mt-3 flex gap-1 rounded-full bg-slate-100 p-1 text-[10.5px] font-bold">
                <span class="flex-1 rounded-full bg-[#0A6E6E] py-1.5 text-center text-white">eSIM data</span><span class="flex-1 py-1.5 text-center text-slate-600">Numbers</span><span class="flex-1 py-1.5 text-center text-slate-600">Gifts</span>
            </div>
            @foreach ([['Nigeria', '5 GB · 30 days', '$11.00'], ['Ghana', '3 GB · 15 days', '$7.50'], ['Kenya', '10 GB · 30 days', '$18.00'], ['South Africa', '5 GB · 30 days', '$12.50']] as [$c, $d, $p])
                <div class="mt-2.5 flex items-center justify-between rounded-2xl border border-slate-200 p-3">
                    <div><p class="text-[13px] font-bold">{{ $c }}</p><p class="text-[11px] text-slate-600">{{ $d }}</p></div>
                    <span class="rounded-full bg-[#D4A017] px-3 py-1.5 text-[11px] font-bold text-[#0D1B2A]">{{ $p }}</span>
                </div>
            @endforeach
            <p class="mt-3 text-center text-[10px] font-semibold text-slate-600">Pay securely · Delivered by email</p>
        @else
            <p class="text-center text-[11px] font-bold uppercase tracking-wider text-[#0A6E6E]">Your eSIM is ready</p>
            <p class="mt-0.5 text-center text-[15px] font-bold">Nigeria · 5 GB</p>
            @php
                $cells = [];
                mt_srand(11);
                for ($y = 0; $y < 15; $y++) {
                    for ($x = 0; $x < 15; $x++) {
                        $corner = ($x < 5 && $y < 5) || ($x > 9 && $y < 5) || ($x < 5 && $y > 9);
                        $cells[] = $corner ? 0 : (mt_rand(0, 9) > 4 ? 1 : 0);
                    }
                }
                $finders = [[0, 0], [10, 0], [0, 10]];
            @endphp
            <svg viewBox="0 0 15 15" class="mx-auto mt-4 h-32 w-32 rounded-xl border border-slate-200 bg-white p-1.5" shape-rendering="crispEdges">
                @foreach ($cells as $i => $on)@if ($on)<rect x="{{ $i % 15 }}" y="{{ intdiv($i, 15) }}" width="1" height="1" fill="#0D1B2A" />@endif @endforeach
                @foreach ($finders as [$fx, $fy])<rect x="{{ $fx + .5 }}" y="{{ $fy + .5 }}" width="4" height="4" fill="none" stroke="#0D1B2A" stroke-width="1" /><rect x="{{ $fx + 1.75 }}" y="{{ $fy + 1.75 }}" width="1.5" height="1.5" fill="#0A6E6E" />@endforeach
            </svg>
            <div class="mt-4 rounded-full bg-[#0A6E6E] py-2.5 text-center text-[12px] font-bold text-white">Install eSIM</div>
            <p class="mt-3 break-all rounded-xl bg-slate-100 p-2.5 font-mono text-[9.5px] text-slate-700">LPA:1$rsp.example.com$ACTIVATION</p>
            <p class="mt-3 text-center text-[10.5px] text-slate-600">Scan on the new phone, or paste the code under Settings.</p>
        @endif
    </div>
</div>
