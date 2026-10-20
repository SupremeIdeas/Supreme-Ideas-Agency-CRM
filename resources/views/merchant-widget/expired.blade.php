<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Checkout link expired</title>
    @include('merchant-widget.partials.clay')
</head>
<body>
@include('merchant-widget.partials.icons')
<main>
    <section class="card center" role="status" style="margin-top:2rem">
        <div class="badge"><svg class="i"><use href="#i-hourglass"/></svg></div>
        <h1>This checkout has ended</h1>
        <p class="sub">Checkout links work once and only for a short time, to keep your order safe. If you already paid, your order is on its way to your email.</p>
        <p class="sub" style="margin-top:.6rem"><b>Not paid yet?</b> Close this window and choose your item again on the shop's page. You haven't been charged.</p>
    </section>
    <p class="by">Powered by <b>Naara</b></p>
</main>
</body>
</html>
