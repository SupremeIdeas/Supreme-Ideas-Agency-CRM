<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="{{ $theme === 'auto' ? 'light dark' : $theme }}">
    <title>Checkout · {{ $merchant->business_name }}</title>
    @include('merchant-widget.partials.clay')
</head>
<body>
@include('merchant-widget.partials.icons')
@php
    $money = fn (string $cur, float $v) => ($cur === 'USD' ? '$' : $cur.' ').number_format($v, in_array($cur, ['NGN', 'KES', 'INR'], true) ? 0 : 2);
    $usd = (float) $order->merchant_price_usd;
    $deliver = $kind === 'giftcard' ? 'gift card code' : ($kind === 'number' ? 'number and its SMS code (shown on the shop page)' : 'eSIM QR code and install steps');
@endphp
<main>
    <div class="top">
        <div class="brand">
            @if ($merchant->logo_url)<img src="{{ $merchant->logo_url }}" alt="">@endif
            <b>{{ $merchant->business_name }}</b>
        </div>
        <span class="lock"><svg class="i"><use href="#i-lock"/></svg> Secure checkout</span>
    </div>

    <ol class="steps" aria-label="Checkout steps">
        <li class="done"><span class="n"><svg class="i"><use href="#i-check"/></svg></span><span class="t">Choose</span></li>
        <li class="on" aria-current="step"><span class="n">2</span><span class="t">Pay</span></li>
        <li><span class="n">3</span><span class="t">Delivery</span></li>
    </ol>

    <section class="card" aria-label="Your order">
        <div class="sum">
            <div>
                <div class="name">{{ $productName }}</div>
                <div class="chips">
                    @foreach ($details as $d)<span class="chip"><svg class="i"><use href="#i-{{ $d['icon'] }}"/></svg>{{ $d['text'] }}</span>@endforeach
                </div>
            </div>
            <div class="price">${{ number_format($usd, 2) }}<small>USD, one-time</small></div>
        </div>
    </section>

    @if ($gateways === [])
        <section class="card center" role="status">
            <div class="badge"><svg class="i"><use href="#i-alert"/></svg></div>
            <h1>Payments are paused</h1>
            <p class="sub">We can't take payments right now. Nothing has been charged. Please try again in a little while.</p>
        </section>
    @else
    <form id="pay" class="card" autocomplete="on" novalidate>
        <div class="sec">
            <label class="l" for="email">1. Where should we send it?</label>
            <input id="email" name="email" type="email" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="you@example.com" aria-describedby="email-hint">
            <p class="hint" id="email-hint"><span id="typo"></span><span id="typo-base">We'll email your {{ $deliver }} here, so check it's right.</span></p>
        </div>

        <fieldset class="sec opts" id="gw" @if (count($gateways) === 1) hidden @endif>
            <legend class="l">2. How would you like to pay?</legend>
            @foreach ($gateways as $slug => $g)
                <label class="opt"><input type="radio" name="gateway" value="{{ $slug }}" @checked($slug === $defaultGateway)><span class="b"><span><b>{{ $g['label'] }}</b><small>{{ $g['hint'] }}</small></span><span class="tick"><svg class="i"><use href="#i-check"/></svg></span></span></label>
            @endforeach
        </fieldset>
        @if (count($gateways) === 1)<input type="hidden" name="gateway" value="{{ $defaultGateway }}">@endif

        <fieldset class="sec opts" id="cur-wrap">
            <legend class="l">{{ count($gateways) === 1 ? '2.' : '3.' }} Pay in</legend>
            <div class="pills" id="cur"></div>
            <p class="hint" id="cur-note" aria-live="polite"></p>
        </fieldset>

        <button type="submit" class="pay" id="go"><span class="spin" aria-hidden="true"></span><svg class="i" aria-hidden="true"><use href="#i-lock"/></svg><span id="go-t">Pay ${{ number_format($usd, 2) }}</span></button>
        <div class="err" id="err" role="alert"></div>

        <ul class="trust">
            <li><svg class="i"><use href="#i-shield"/></svg><span>You pay on your bank's or wallet's own secure page. We never see your card details.</span></li>
            <li><svg class="i"><use href="#i-mail"/></svg><span>Delivered by email as soon as payment is confirmed.</span></li>
            <li><svg class="i"><use href="#i-check"/></svg><span>If we can't deliver, you're refunded automatically.</span></li>
        </ul>
    </form>

    <section class="card" aria-label="What happens next">
        <h2 style="margin-bottom:.7rem">What happens next</h2>
        <ol class="next">
            <li><span>Pick how to pay, then you'll go to a secure payment page.</span></li>
            <li><span>Come back here automatically and watch your order finish.</span></li>
            <li><span>It arrives in your inbox. Check spam if it's slow.</span></li>
        </ol>
        <p class="ref">Order reference {{ $ref }}</p>
    </section>
    @endif

    <p class="by">Powered by <b>Naara</b> · Naara handles payment and support for this order.</p>
</main>
@if ($gateways !== [])
<script>
(function () {
    // postMessage contract (blueprint §8): events go ONLY to origins this key is allowed on, never '*'.
    var parents = @json(array_map(fn ($h) => 'https://'.$h, $parentOrigins));
    function emit(type, data) { if (window.parent === window) return; parents.forEach(function (o) { try { window.parent.postMessage(Object.assign({ source: 'naara', type: type }, data || {}), o); } catch (e) {} }); }
    emit('naara:checkout:ready');

    var GATEWAYS = @json(array_map(fn ($g) => $g['currencies'], $gateways));
    var QUOTES = @json($quotes);
    var USD = {{ json_encode($usd) }};
    var f = document.getElementById('pay'), btn = document.getElementById('go'), label = document.getElementById('go-t'), err = document.getElementById('err');
    var cur = document.getElementById('cur'), note = document.getElementById('cur-note'), email = document.getElementById('email');
    var state = { currency: @json($defaultCurrency) };

    function money(c, v) {
        var zero = ['NGN', 'KES', 'INR'].indexOf(c) >= 0;
        var n = v.toLocaleString('en-US', { minimumFractionDigits: zero ? 0 : 2, maximumFractionDigits: zero ? 0 : 2 });
        return (c === 'USD' ? '$' : c + ' ') + n;
    }
    function gateway() { var r = f.querySelector('input[name=gateway]:checked') || f.querySelector('input[name=gateway]'); return r ? r.value : ''; }
    function show() {
        var c = state.currency, q = QUOTES[c];
        label.textContent = 'Pay ' + (c === 'USD' ? money('USD', USD) : (q ? '≈ ' + money(c, q) : money('USD', USD)));
        note.textContent = c === 'USD' ? '' : (q ? 'About ' + money(c, q) + ' at today\'s rate. Your payment page shows the exact amount before you confirm.' : 'The exact amount is shown on the payment page.');
    }
    function pills() {
        var list = GATEWAYS[gateway()] || [];
        if (list.indexOf(state.currency) < 0) { state.currency = list.indexOf('USD') >= 0 ? 'USD' : list[0]; }
        cur.textContent = '';
        list.forEach(function (c) {
            var l = document.createElement('label'); l.className = 'opt';
            var i = document.createElement('input'); i.type = 'radio'; i.name = 'currency'; i.value = c; i.checked = c === state.currency;
            i.addEventListener('change', function () { state.currency = c; show(); });
            var s = document.createElement('span'); s.className = 'b'; s.textContent = c;
            l.appendChild(i); l.appendChild(s); cur.appendChild(l);
        });
        document.getElementById('cur-wrap').hidden = list.length < 2 && list[0] === 'USD';
        show();
    }
    f.addEventListener('change', function (e) { if (e.target.name === 'gateway') { pills(); } });
    pills();

    // Gentle typo catch: the one mistake that costs a shopper their delivery.
    var FIX = { 'gmial.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gamil.com': 'gmail.com', 'gmail.con': 'gmail.com', 'gmail.co': 'gmail.com', 'yahooo.com': 'yahoo.com', 'yaho.com': 'yahoo.com', 'hotmial.com': 'hotmail.com', 'hotmai.com': 'hotmail.com', 'outlok.com': 'outlook.com' };
    var typo = document.getElementById('typo'), base = document.getElementById('typo-base');
    function checkTypo() {
        typo.textContent = ''; base.hidden = false;
        var v = email.value.trim(), at = v.lastIndexOf('@'), fix = at > 0 ? FIX[v.slice(at + 1).toLowerCase()] : null;
        if (!fix) { return; }
        base.hidden = true; typo.appendChild(document.createTextNode('Did you mean '));
        var b = document.createElement('button'); b.type = 'button'; b.textContent = v.slice(0, at + 1) + fix; b.onclick = function () { email.value = v.slice(0, at + 1) + fix; checkTypo(); email.focus(); };
        typo.appendChild(b); typo.appendChild(document.createTextNode('?'));
    }
    email.addEventListener('blur', checkTypo);

    function fail(msg) { err.textContent = msg; btn.disabled = false; show(); emit('naara:checkout:error'); }
    f.addEventListener('submit', function (e) {
        e.preventDefault(); err.textContent = '';
        var v = email.value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { err.textContent = 'Enter the email where we should send your order.'; email.focus(); email.setAttribute('aria-invalid', 'true'); return; }
        email.removeAttribute('aria-invalid');
        btn.disabled = true; label.textContent = 'Taking you to the payment page…';
        fetch(@json(route('merchant-widget.checkout.pay', ['token' => $token])), {
            method: 'POST', credentials: 'omit',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            body: JSON.stringify({ email: v, gateway: gateway(), currency: state.currency })
        }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (x) {
            if (x.ok && x.j.ok) {
                // Payment providers refuse to be framed, so a framed page asks its parent to navigate; unframed, we go ourselves.
                if (window.parent !== window) { emit('naara:checkout:redirect', { url: x.j.redirect_url }); } else { window.location.assign(x.j.redirect_url); }
            } else { fail((x.j && (x.j.message || (x.j.errors && Object.values(x.j.errors)[0][0]))) || 'Something went wrong. You were not charged. Please try again.'); }
        }).catch(function () { fail('Network problem. You were not charged. Check your connection and try again.'); });
    });
    window.addEventListener('pagehide', function () { emit('naara:checkout:closed'); });
})();
</script>
@endif
</body>
</html>
