<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="{{ $theme === 'auto' ? 'light dark' : $theme }}">
    @if ($pending)<meta http-equiv="refresh" content="5">@endif
    <title>Order status · {{ $merchant->business_name }}</title>
    @include('merchant-widget.partials.clay')
</head>
<body>
@include('merchant-widget.partials.icons')
@php
    $done = $order->status === 'completed';
    $bad = in_array($order->status, ['failed', 'refunded'], true);
    $held = $order->status === 'held';
@endphp
<main>
    <div class="top">
        <div class="brand">
            @if ($merchant->logo_url)<img src="{{ $merchant->logo_url }}" alt="">@endif
            <b>{{ $merchant->business_name }}</b>
        </div>
        <span class="lock"><svg class="i"><use href="#i-lock"/></svg> Secure</span>
    </div>

    <ol class="steps" aria-label="Order progress">
        <li class="done"><span class="n"><svg class="i"><use href="#i-check"/></svg></span><span class="t">Choose</span></li>
        <li class="{{ $bad ? '' : 'done' }}"><span class="n">
            @if ($bad) 2 @else <svg class="i"><use href="#i-check"/></svg> @endif
        </span><span class="t">Pay</span></li>
        <li class="{{ $done ? 'done' : ($bad ? '' : 'on') }}" @if (! $done && ! $bad) aria-current="step" @endif><span class="n">@if ($done)<svg class="i"><use href="#i-check"/></svg>@else 3 @endif</span><span class="t">Delivery</span></li>
    </ol>

    <section class="card center" role="status" aria-live="polite">
        @if ($done)
            <div class="badge ok"><svg class="i"><use href="#i-check"/></svg></div>
            @if ($kind === 'number')
                <h1>Your number is ready.</h1>
                <p class="sub">Go back to {{ $merchant->business_name }}: your number and its SMS code appear there as soon as the code arrives. If no code comes within {{ \App\Support\MerchantWidgetNumbers::refundWindowMinutes() }} minutes you are refunded automatically.</p>
            @else
            <h1>All done. Check your email.</h1>
            <p class="sub">We've sent your {{ $kind === 'giftcard' ? 'gift card code' : 'eSIM QR code and install steps' }}. It can take a minute to arrive, and it may land in spam.</p>
            @endif
        @elseif ($bad)
            <div class="badge bad"><svg class="i"><use href="#i-alert"/></svg></div>
            <h1>We couldn't complete this order</h1>
            <p class="sub">If you were charged, it will be returned to you automatically. You don't need to do anything.</p>
        @elseif ($held)
            <div class="badge"><svg class="i"><use href="#i-shield"/></svg></div>
            <h1>Your order needs a quick check</h1>
            <p class="sub">Your payment went through. We're doing a short manual check, and you'll get an email as soon as it's done. There's nothing else to do.</p>
        @else
            <div class="badge"><svg class="i"><use href="#i-hourglass"/></svg></div>
            <h1>Finishing your order…</h1>
            <p class="sub">Please keep this page open. It updates by itself, and your details will also be emailed to you.</p>
            <p class="sub busy" style="margin-top:.8rem;display:flex;gap:.5rem;align-items:center;justify-content:center"><span class="spin" aria-hidden="true"></span> Confirming your payment</p>
        @endif
        @if ($shopUrl)<a class="btn" href="{{ $shopUrl }}" rel="noopener"><svg class="i"><use href="#i-back"/></svg> Back to {{ $merchant->business_name }}</a>@endif
        <p class="ref">Order reference {{ $ref }}</p>
    </section>

    @if ($done && $kind === 'esim')
    <section class="card" aria-label="How to install your eSIM">
        <h2 style="margin-bottom:.7rem">Installing your eSIM</h2>
        <ol class="next">
            <li><span>Open the email from Naara and keep it on a second screen or print it.</span></li>
            <li><span>Connect your phone to Wi-Fi, then go to Settings, Mobile data, Add eSIM and scan the QR code.</span></li>
            <li><span>When you arrive, switch the eSIM on and turn data roaming on for it.</span></li>
        </ol>
    </section>
    @endif

    <p class="by">Powered by <b>Naara</b> · Naara handles payment and support for this order.</p>
</main>
</body>
</html>
