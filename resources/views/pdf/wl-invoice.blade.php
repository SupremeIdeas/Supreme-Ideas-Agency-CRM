<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #0D1B2A; }
    .head { width: 100%; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 14px; }
    .head td { vertical-align: middle; }
    .logo { max-height: 44px; max-width: 160px; }
    h1 { font-size: 20px; margin: 0 0 2px; color: #0A6E6E; }
    .muted { color: #666; }
    table.t { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.t th { text-align: left; border-bottom: 2px solid #D4A017; padding: 6px 4px; font-size: 10px; text-transform: uppercase; }
    table.t td { border-bottom: 1px solid #eee; padding: 6px 4px; vertical-align: top; }
    .r { text-align: right; }
    .box { border: 1px solid #ddd; padding: 8px; }
    .stamp { display: inline-block; border: 2px solid #0A6E6E; color: #0A6E6E; padding: 2px 10px; font-weight: bold; letter-spacing: 1px; }
    .stamp.due { border-color: #B45309; color: #B45309; }
    .stamp.void { border-color: #B91C1C; color: #B91C1C; }
</style>
</head>
<body>
@php
    $s = $inv->seller_snapshot; $b = $inv->buyer_snapshot;
    $titles = ['invoice' => 'Invoice', 'receipt' => 'Receipt', 'proforma' => 'Proforma', 'credit_note' => 'Credit note'];
    $stamp = $inv->doc_type === 'proforma' ? 'PROFORMA' : ($inv->status === 'paid' ? 'PAID' : ($inv->status === 'void' ? 'VOID' : 'DUE'));
@endphp
<table class="head"><tr>
    <td>@foreach (array_slice((array) ($s['logos'] ?? []), 0, 1) as $l)<img class="logo" src="{{ $l }}" alt="">@endforeach</td>
    <td class="r">@foreach (array_slice((array) ($s['logos'] ?? []), 1, 1) as $l)<img class="logo" src="{{ $l }}" alt="">@endforeach</td>
</tr></table>
@if (empty($s['logos']))<p style="margin:0 0 8px"><b>{{ $s['name'] }}</b></p>@endif
<h1>{{ $titles[$inv->doc_type] ?? 'Document' }} {{ $inv->number }}</h1>
<p class="muted">Issued {{ $inv->issued_at->format('Y-m-d') }}@if ($inv->due_at) · Due {{ $inv->due_at->format('Y-m-d') }}@endif · <span class="stamp {{ $stamp === 'PAID' ? '' : strtolower($stamp) }}">{{ $stamp }}</span></p>
@if ($inv->doc_type === 'proforma')<p><b>Proforma: not a tax invoice.</b></p>@endif

<table style="width:100%"><tr>
    <td style="width:50%;vertical-align:top"><div class="box"><b>From</b><br>{{ $s['name'] }}@if (! empty($s['address']))<br>{!! nl2br(e($s['address'])) !!}@endif @if (! empty($s['registration']))<br>Reg: {{ $s['registration'] }}@endif @if (! empty($s['tax_id']))<br>Tax ID: {{ $s['tax_id'] }}@endif @if (! empty($s['email']))<br>{{ $s['email'] }}@endif</div></td>
    <td style="width:50%;vertical-align:top"><div class="box"><b>Bill to</b><br>{{ $b['name'] }}@if (! empty($b['address']))<br>{!! nl2br(e($b['address'])) !!}@endif @if (! empty($b['country']))<br>{{ $b['country'] }}@endif @if (! empty($b['tax_id']))<br>Tax ID: {{ $b['tax_id'] }}@endif<br>{{ $b['email'] }}<br><span class="muted">Brand: {{ $b['brand'] }} · Reference {{ $b['funnel_code'] }}</span></div></td>
</tr></table>

<table class="t">
    <tr><th>Description</th><th class="r">Qty</th><th class="r">Unit (USD)</th><th class="r">Amount (USD)</th></tr>
    @foreach ($inv->lines as $l)
        <tr><td>{{ $l['description'] }}</td><td class="r">{{ $l['qty'] }}</td><td class="r">{{ number_format((float) $l['unit_price'], 2) }}</td><td class="r">{{ number_format((float) $l['amount'], 2) }}</td></tr>
    @endforeach
    <tr><td colspan="3" class="r">Subtotal</td><td class="r">{{ number_format((float) $inv->subtotal, 2) }}</td></tr>
    @if ((float) $inv->tax_total > 0)<tr><td colspan="3" class="r">Tax</td><td class="r">{{ number_format((float) $inv->tax_total, 2) }}</td></tr>@endif
    <tr><td colspan="3" class="r"><b>Total</b></td><td class="r"><b>{{ number_format((float) $inv->total, 2) }}</b></td></tr>
    <tr><td colspan="3" class="r">Paid</td><td class="r">{{ number_format((float) $inv->amount_paid, 2) }}</td></tr>
    <tr><td colspan="3" class="r"><b>Balance due</b></td><td class="r"><b>{{ number_format((float) $inv->balance_due, 2) }}</b></td></tr>
</table>

@if (! empty($inv->payments))
    <p><b>Payments applied</b></p>
    @foreach ($inv->payments as $p)
        @php
            $rateText = $p['paid_currency'] !== 'USD' ? ' at 1 '.$p['paid_currency'].' = '.rtrim(rtrim(number_format((float) $p['rate_used'], 6, '.', ''), '0'), '.').' USD' : '';
            $txText = ! empty($p['tx_hash']) ? ' · tx '.$p['tx_hash'] : '';
        @endphp
        <p class="muted" style="margin:2px 0">{{ \Illuminate\Support\Carbon::parse($p['applied_at'])->format('Y-m-d') }} · {{ number_format((float) $p['paid_amount'], 2) }} {{ $p['paid_currency'] }}{{ $rateText }} = {{ number_format((float) $p['usd_value'], 2) }} USD · ref {{ $p['reference'] }}{{ $txText }}</p>
    @endforeach
@endif
@if ($inv->terms)<p style="margin-top:14px"><b>Terms</b><br>{!! nl2br(e($inv->terms)) !!}</p>@endif
@if ($inv->notes)<p class="muted">{!! nl2br(e($inv->notes)) !!}</p>@endif
</body>
</html>
