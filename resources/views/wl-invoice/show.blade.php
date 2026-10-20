{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php $titles = ['invoice' => 'Invoice', 'receipt' => 'Receipt', 'proforma' => 'Proforma', 'credit_note' => 'Credit note']; $s = $inv->seller_snapshot; @endphp
<x-layouts.marketing :title="($titles[$inv->doc_type] ?? 'Document').' '.$inv->number" :bare="true">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <main class="mx-auto max-w-2xl px-4 py-10" data-wl-invoice>
        <article class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $titles[$inv->doc_type] ?? 'Document' }} {{ $inv->number }}</h1>
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $s['name'] }} · issued {{ $inv->issued_at->format('Y-m-d') }}@if ($inv->due_at) · due {{ $inv->due_at->format('Y-m-d') }}@endif</p>
                </div>
                <span class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">{{ strtoupper($inv->status) }}</span>
            </div>
            <table class="mt-5 w-full text-sm text-[rgb(var(--nx-text))]">
                @foreach ($inv->lines as $l)<tr class="border-b border-[rgb(var(--nx-line))]"><td class="py-2">{{ $l['description'] }}</td><td class="py-2 text-right">{{ number_format((float) $l['amount'], 2) }}</td></tr>@endforeach
                <tr><td class="pt-3 font-semibold">Total (USD)</td><td class="pt-3 text-right font-semibold">{{ number_format((float) $inv->total, 2) }}</td></tr>
                <tr><td class="text-[rgb(var(--nx-text-2))]">Paid</td><td class="text-right">{{ number_format((float) $inv->amount_paid, 2) }}</td></tr>
                <tr><td class="font-semibold">Balance due</td><td class="text-right font-semibold">{{ number_format((float) $inv->balance_due, 2) }}</td></tr>
            </table>
            <a href="{{ route('wl-invoice.pdf', $token) }}" class="ns-cta ns-cta--pill mt-5 inline-flex">Download PDF</a>
        </article>
    </main>
</x-layouts.marketing>
