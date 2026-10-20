{{-- The order lifecycle as a flow of steps (MASTER-ONLY, developer docs). Plain HTML so it follows light/dark and stays crisp at any size. --}}
@php
    $steps = [
        ['POST /orders', 'Your request arrives with a unique reference.', 'bg-primary text-white'],
        ['Charge balance', 'Your prepaid API balance is debited. Too low returns 402.', 'bg-slate-100 text-slate-900 dark:bg-white/10 dark:text-white'],
        ['Fulfil at provider', 'No provider available returns 502 and refunds you.', 'bg-slate-100 text-slate-900 dark:bg-white/10 dark:text-white'],
        ['Save the order', 'A failed save returns 500 and refunds you.', 'bg-slate-100 text-slate-900 dark:bg-white/10 dark:text-white'],
        ['201 processing', 'Poll GET /orders/{reference} until completed.', 'bg-accent text-[#0D1B2A]'],
    ];
@endphp
<ol class="grid gap-3 sm:grid-cols-5" aria-label="Order lifecycle" data-dv-lifecycle>
    @foreach ($steps as $i => [$t, $d, $cls])
        <li class="relative rounded-2xl border border-slate-200 p-3 dark:border-white/10">
            <span class="inline-flex rounded-lg px-2 py-1 text-[11px] font-bold {{ $cls }}">{{ $i + 1 }}. {{ $t }}</span>
            <p class="mt-2 text-[13px] leading-snug text-slate-700 dark:text-slate-300">{{ $d }}</p>
            @unless ($loop->last)<x-icon name="arrow-right" class="absolute -right-3 top-1/2 z-10 hidden h-4 w-4 -translate-y-1/2 text-primary sm:block dark:text-accent" />@endunless
        </li>
    @endforeach
</ol>
