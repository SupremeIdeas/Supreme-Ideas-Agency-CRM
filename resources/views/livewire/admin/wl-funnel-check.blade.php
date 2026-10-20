{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- White Label funnel check and quote simulator (MASTER-ONLY): pure reads, nothing is created or numbered. --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $ready = collect($checks)->every(fn ($c) => $c['ok'] || str_contains($c['label'], 'payee note'));
@endphp
<div class="mx-auto max-w-4xl">
    <x-admin.wl-hub active="check" />
    <div class="mb-4"><h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Funnel check</h1><p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Is the White Label funnel ready to take a real buyer, and what would a buyer be asked to pay right now? Nothing here creates or saves anything.</p></div>

    <section class="{{ $card }}" data-funnel-readiness>
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $ready ? 'Ready for buyers' : 'Not ready yet' }}</h2>
        <ul class="mt-3 grid gap-2">
            @foreach ($checks as $c)
                <li class="flex items-start gap-3" wire:key="chk-{{ $loop->index }}">
                    <span class="mt-0.5 grid h-5 w-5 flex-none place-items-center rounded-full {{ $c['ok'] ? 'bg-[rgb(var(--nx-ok)/0.18)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.2)] text-[rgb(var(--nx-warn))]' }}"><x-nx.icon :name="$c['ok'] ? 'check' : 'alert'" class="h-3 w-3" /></span>
                    <span><b class="text-sm text-[rgb(var(--nx-text))]">{{ $c['label'] }}</b><span class="block text-sm text-[rgb(var(--nx-text-2))]">{{ $c['detail'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="{{ $card }} mt-4" data-quote-simulator>
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Quote simulator</h2>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Pick a plan and a currency to see the amount, rate and lock a buyer would get.</p>
        <form wire:submit="simulate" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
            <select wire:model="plan" aria-label="Plan" class="{{ $in }}">@foreach ($plans as $p)<option value="{{ $p->key }}">{{ $p->name }} (${{ number_format((float) $p->price_usd, 2) }})</option>@endforeach</select>
            <select wire:model="currency" aria-label="Currency" class="{{ $in }}">@foreach ($currencies as $c)<option>{{ $c }}</option>@endforeach</select>
            <button type="submit" wire:loading.attr="disabled" wire:target="simulate" class="ns-cta ns-cta--pill ns-cta--sm">Run quote</button>
        </form>
        @if ($quoteError)<p class="mt-3 text-sm text-[rgb(var(--nx-bad))]" role="alert" data-quote-error>{{ $quoteError }}</p>@endif
        @if ($quote)
            <dl class="mt-3 grid gap-1 text-sm text-[rgb(var(--nx-text-2))]" data-quote-result>
                <div><dt class="inline">Plan:</dt> <dd class="inline text-[rgb(var(--nx-text))]">{{ $quote['plan'] }}, ${{ number_format($quote['price_usd'], 2) }} @if ($quote['tax_rate'] > 0)+ ${{ number_format($quote['tax'], 2) }} tax ({{ $quote['tax_rate'] }}%) = ${{ number_format($quote['total_usd'], 2) }}@endif</dd></div>
                <div><dt class="inline">Buyer sends:</dt> <dd class="inline text-base font-bold text-[rgb(var(--nx-text))]">{{ number_format($quote['amount'], 2) }} {{ $quote['currency'] }}</dd></div>
                <div><dt class="inline">Rate:</dt> <dd class="inline text-[rgb(var(--nx-text))]">1 {{ $quote['currency'] }} = {{ rtrim(rtrim(number_format($quote['rate'], 6, '.', ''), '0'), '.') }} USD ({{ $quote['source'] === 'none' ? 'no conversion' : $quote['source'] }}, as of {{ $quote['as_of'] }})</dd></div>
                <div><dt class="inline">Locked until:</dt> <dd class="inline text-[rgb(var(--nx-text))]">{{ $quote['locked_until'] }}</dd></div>
            </dl>
        @endif
    </section>
</div>
