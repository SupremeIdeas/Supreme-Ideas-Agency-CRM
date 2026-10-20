{{-- A browser window showing the developer area: balance, API key and recent orders (MASTER-ONLY, developer landing mock-up). --}}
<div class="overflow-hidden rounded-2xl border border-white/10 bg-[#0D1B2A] shadow-2xl shadow-black/40 {{ $class ?? '' }}" role="img" aria-label="Browser window showing the developer area with an API balance, a key with its scopes and recent orders">
    <div class="flex items-center gap-2 border-b border-white/10 bg-[#12263A] px-4 py-2.5">
        <span class="h-2.5 w-2.5 rounded-full bg-[#D4A017]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#0A6E6E]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#4A6178]"></span>
        <span class="ml-3 flex-1 truncate rounded-md bg-[#0D1B2A] px-3 py-1 font-mono text-[11px] text-[#9FB3C6]">{{ $host }}/developer</span>
    </div>
    <div class="grid gap-3 p-4 text-[#E4ECF3] sm:grid-cols-[1fr_1.4fr]" aria-hidden="true">
        <div class="rounded-xl border border-[#0A6E6E]/60 bg-[#12263A] p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#9FB3C6]">API balance</p>
            <p class="mt-1 text-2xl font-bold text-white">$248.60</p>
            <p class="mt-1 text-[11px] text-[#7FE0D8]">Prepaid. Refunded on any failure.</p>
            <span class="mt-3 inline-block rounded-full bg-[#D4A017] px-3.5 py-1.5 text-[11px] font-bold text-[#0D1B2A]">Top up</span>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#12263A] p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#9FB3C6]">API key</p>
            <p class="mt-1 font-mono text-[13px] text-white">Production <span class="text-[#6F879D]">7|••••••••4f2a</span></p>
            <div class="mt-2 flex flex-wrap gap-1.5">@foreach (['catalogue', 'quote', 'order', 'status'] as $s)<span class="rounded-full border border-[#0A6E6E] bg-[#0A6E6E]/20 px-2.5 py-0.5 font-mono text-[10.5px] text-[#A8EDE6]">{{ $s }}</span>@endforeach</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#12263A] p-4 sm:col-span-2">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#9FB3C6]">Recent orders</p>
            @foreach ([['order-1042', 'eSIM · Nigeria 5 GB', '$11.00', 'Completed'], ['order-1041', 'Number · WhatsApp', '$0.23', 'Completed'], ['order-1040', 'eSIM · Ghana 3 GB', '$7.50', 'Processing']] as [$r, $w, $p, $st])
                <div class="mt-2 flex items-center justify-between gap-3 border-t border-white/10 pt-2 text-[12px]">
                    <span class="font-mono text-[#9FB3C6]">{{ $r }}</span><span class="hidden flex-1 text-white sm:block">{{ $w }}</span><span class="font-semibold text-white">{{ $p }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-[10.5px] font-bold {{ $st === 'Completed' ? 'bg-[#0A6E6E] text-white' : 'bg-[#D4A017] text-[#0D1B2A]' }}">{{ $st }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
