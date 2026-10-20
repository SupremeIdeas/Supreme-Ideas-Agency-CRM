{{-- nx:converted --}}
{{-- White Label hub bar (MASTER-ONLY): one set of tabs on every White Label admin page, so the whole job (buyers, payments, licences, invoices, payment setup) is one place with live counts. Scrolls sideways on a phone. --}}
@props(['active' => 'funnel'])
@php
    $review = $overdue = $custom = 0;
    try {
        $review = \App\Models\WlFunnelManualPayment::query()->where('status', 'under_review')->count();
        $custom = class_exists(\App\Models\WlCustomOrder::class) && \App\Services\WhiteLabel\Funnel\WlCustomOrders::enabled() ? \App\Models\WlCustomOrder::query()->where('status', 'requested')->count() : 0;
        $overdue = \App\Models\WlInvoice::query()->where('doc_type', 'invoice')->where('status', 'issued')->where('balance_due', '>', 0)->whereNotNull('due_at')->where('due_at', '<', now())->count();
    } catch (\Throwable) {
    }
    $tabs = [
        ['funnel', 'admin.white-label.funnel', 'Purchases', $overdue, 'overdue'],
        ['review', 'admin.white-label.payment-review', 'Payments', $review, 'to review'],
        ['custom', 'admin.white-label.custom-orders', 'Custom orders', $custom, 'to quote'],
        ['licences', 'admin.white-label', 'Licences', 0, ''],
        ['invoices', 'admin.white-label.invoice-settings', 'Invoices', 0, ''],
        ['setup', 'admin.white-label.payments', 'Payment setup', 0, ''],
        ['check', 'admin.white-label.funnel.check', 'Funnel check', 0, ''],
    ];
@endphp
<nav class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="White Label sections" data-tour="wl-hub">
    @foreach ($tabs as [$key, $route, $label, $n, $what])
        @continue(! \Illuminate\Support\Facades\Route::has($route) || ($key === 'custom' && ! \App\Services\WhiteLabel\Funnel\WlCustomOrders::enabled()))
        <a href="{{ route($route) }}" wire:navigate class="ns-nt__chip whitespace-nowrap {{ $active === $key ? 'is-on' : '' }}" @if ($active === $key) aria-current="page" @endif>
            {{ $label }}@if ($n > 0)<span class="ml-1.5 rounded-full bg-[rgb(var(--nx-gold)/0.2)] px-1.5 text-[11px] font-semibold text-[rgb(var(--nx-text))]" title="{{ $n }} {{ $what }}">{{ $n }}</span>@endif
        </a>
    @endforeach
</nav>
