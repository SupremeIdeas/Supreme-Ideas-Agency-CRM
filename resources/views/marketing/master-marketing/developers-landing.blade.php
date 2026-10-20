{{-- Developers: the public marketing page for the API, the embeddable widgets and the WordPress plugin (MASTER-ONLY). Mock-ups are built in HTML, so there is never an empty
     image slot, and every section seam is a rounded sheet rather than a straight line. --}}
@php
    use App\Support\MasterMarketing\DeveloperSamples;
    $order = ['type' => 'esim', 'plan_id' => 12, 'reference' => 'order-1042'];
    $orderSample = ['title' => 'Place an order', 'tabs' => DeveloperSamples::request('POST', '/orders', $order)];
    $respSample = ['title' => '201 Created', 'lang' => 'json', 'code' => json_encode(['reference' => 'order-1042', 'kind' => 'esim', 'status' => 'processing', 'price_usd' => 11.0, 'currency' => 'USD', 'result' => ['iccid' => '8944...', 'qr_code' => 'https://.../qr.png', 'lpa' => 'LPA:1$rsp.example.com$ACTIVATION-CODE']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)];
@endphp
<x-layouts.marketing :title="'Developers | '.$brand" description="Sell eSIM data and verification numbers from your own app with a REST API, or drop in a widget or WordPress plugin. Prepaid, idempotent, never charged without delivery.">
    @include('marketing.master-marketing._developers-style')

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden px-4 pb-16 pt-8 sm:pb-24 sm:pt-16 nx-ambient nx-ambient--rich" data-dev-hero>
        <div class="mx-auto grid max-w-6xl items-center gap-8 sm:gap-12 lg:grid-cols-[1.05fr_1fr] [&>*]:min-w-0">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="code" class="h-4 w-4" /> Naara for developers</p>
                <h1 class="mt-4 text-[2.05rem] font-bold leading-[1.1] tracking-tight sm:mt-5 sm:text-5xl lg:text-[3.4rem] text-slate-900 dark:text-white">
                    Sell connectivity from your own app
                    <span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">in three API calls.</span>
                </h1>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-700 sm:mt-6 sm:text-lg dark:text-slate-300">eSIM data for 190+ countries and verification numbers, on one prepaid balance. Use the REST API, paste a widget on any page, or install the WordPress plugin. You keep your margin; we handle delivery.</p>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:flex sm:flex-wrap">
                    <a href="{{ route('developers.docs', 'quick-start') }}" wire:navigate class="nx-btn nx-btn--primary !px-4 !py-3 text-center sm:!px-7">Read the quick start</a>
                    @auth
                        <a href="{{ Route::has('developer') ? route('developer') : route('dashboard') }}" class="nx-btn !px-4 !py-3 text-center text-slate-800 sm:!px-7 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">Open my developer area</a>
                    @else
                        <a href="{{ route('register') }}" class="nx-btn !px-4 !py-3 text-center text-slate-800 sm:!px-7 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">Create an account</a>
                    @endauth
                </div>
                <ul class="-mx-4 mt-6 flex snap-x gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:mt-8 sm:flex-wrap sm:overflow-visible sm:px-0 [&::-webkit-scrollbar]:hidden">
                    @foreach (['REST and JSON', 'Prepaid wholesale prices', 'Never charged without delivery', 'Safe to retry'] as $chip)
                        <li class="inline-flex shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-[13px] font-medium text-slate-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-200"><x-icon name="check" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $chip }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="relative min-w-0 pb-4 sm:pb-16 sm:pr-10 lg:pb-24">
                @include('marketing.master-marketing._developers-mock-terminal')
                <div class="dv-float relative mx-auto mt-6 w-[168px] sm:absolute sm:-bottom-2 sm:right-0 sm:mt-0 sm:w-[220px]">@include('marketing.master-marketing._developers-mock-phone', ['screen' => 'delivered', 'class' => 'w-full'])</div>
            </div>
        </div>
    </section>

    {{-- Three ways to build --}}
    <section class="relative px-4 pb-24 pt-4" aria-labelledby="dv-ways">
        <div class="mx-auto max-w-6xl">
            <h2 id="dv-ways" class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">Pick how you want to build</h2>
            <p class="mt-3 max-w-2xl text-lg text-slate-700 dark:text-slate-300">Start with code, or start with a snippet. Every route uses the same safe checkout and the same prepaid money rules.</p>
            <div class="mt-10 grid gap-5 md:grid-cols-3 [&>*]:min-w-0">
                @foreach ([
                    ['dev-api', 'REST API', 'Full control. Catalogue, quotes, orders and order status as simple JSON calls, with scoped keys.', ['Bearer-token keys with four scopes', 'Idempotent orders on your own reference', 'Poll for delivery: QR code or number and code'], 'introduction', 'Read the API guide'],
                    ['dev-widget', 'Embeddable widget', 'No server code. Paste two lines and your site sells eSIMs, numbers and gift cards on a Naara-hosted checkout.', ['One public key, locked to your domains', 'Pop-up or full-page checkout', 'A My orders area for returning customers'], 'widgets-overview', 'Embed the widget'],
                    ['dev-plugin', 'WordPress plugin', 'Upload, activate, place a block. Comes pre-filled with your key and tests its own connection.', ['Block and shortcodes', 'One-click connection test', 'Updates flagged in wp-admin'], 'wordpress', 'Set up WordPress'],
                ] as [$ic, $t, $d, $items, $slug, $cta])
                    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <img src="{{ asset('images/illustrations/'.$ic.'.svg') }}" alt="" width="480" height="180" loading="lazy" decoding="async" class="-mx-2 -mt-2 mb-1 h-auto w-[calc(100%+1rem)] max-w-none rounded-2xl bg-gradient-to-b from-primary/5 to-transparent">
                        <h3 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">{{ $t }}</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">{{ $d }}</p>
                        <ul class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">@foreach ($items as $it)<li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary dark:text-accent" />{{ $it }}</li>@endforeach</ul>
                        <a href="{{ route('developers.docs', $slug) }}" wire:navigate class="mt-auto inline-flex items-center gap-1.5 pt-6 text-sm font-bold text-primary dark:text-accent">{{ $cta }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Code: from zero to an eSIM --}}
    <section class="relative -mt-8 rounded-t-[30px] bg-[#0D1B2A] px-4 pb-28 pt-16 text-white" aria-labelledby="dv-calls">
        <div class="mx-auto grid max-w-6xl items-start gap-10 lg:grid-cols-[1fr_1.1fr] [&>*]:min-w-0">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#F2CB5C]">Quick start</p>
                <h2 id="dv-calls" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">From an empty balance to a delivered eSIM</h2>
                <ol class="mt-8 space-y-5">
                    @foreach ([['List plans', 'GET /catalogue returns what you can sell, with your price on every plan.'], ['Quote the price', 'POST /quote confirms the exact amount before you spend anything.'], ['Place the order', 'POST /orders with a unique reference. Hand the QR code to your customer.'], ['Confirm delivery', 'GET /orders/{reference} until the status is completed.']] as $i => [$t, $d])
                        <li class="flex gap-4"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D4A017] text-sm font-bold text-[#0D1B2A]">{{ $i + 1 }}</span><div><p class="font-semibold text-white">{{ $t }}</p><p class="mt-0.5 text-[15px] leading-relaxed text-slate-300">{{ $d }}</p></div></li>
                    @endforeach
                </ol>
                <a href="{{ route('developers.docs', 'quick-start') }}" wire:navigate class="nx-btn nx-btn--primary mt-8 !px-7 !py-3">Follow the full guide</a>
            </div>
            <div class="space-y-4">
                <x-master-marketing.developer-code :sample="$orderSample" :host="$host" />
                <x-master-marketing.developer-code :sample="$respSample" :host="$host" />
            </div>
        </div>
    </section>

    {{-- Showcase: what you get --}}
    <section class="relative -mt-8 rounded-t-[30px] bg-white px-4 pb-24 pt-16 dark:bg-[#0a1522]" aria-labelledby="dv-see">
        <div class="mx-auto max-w-6xl">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary dark:text-accent">What you get</p>
            <h2 id="dv-see" class="mt-3 max-w-2xl text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">A developer area, a shop your customers can use, and numbers you can watch</h2>
            <div class="mt-12 grid items-center gap-8 lg:grid-cols-[1.25fr_1fr] [&>*]:min-w-0">
                <div class="space-y-8">
                    @include('marketing.master-marketing._developers-mock-portal')
                    <p class="max-w-xl text-[15px] leading-relaxed text-slate-700 dark:text-slate-300"><strong class="text-slate-900 dark:text-white">Keys, balance and orders in one place.</strong> Create a key, choose its scopes, top up your prepaid balance and watch each order move from processing to completed.</p>
                </div>
                <div class="space-y-4">
                    @include('marketing.master-marketing._developers-mock-phone', ['screen' => 'shop'])
                    <p class="mx-auto max-w-xs text-center text-[15px] leading-relaxed text-slate-700 dark:text-slate-300"><strong class="text-slate-900 dark:text-white">A shop in two lines.</strong> The widget shows tabs for eSIMs, numbers and gift cards on your own site.</p>
                </div>
            </div>
            <div class="mt-14 grid items-center gap-8 lg:grid-cols-[1fr_1.25fr] [&>*]:min-w-0">
                <div>
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">See which pages sell</h3>
                    <p class="mt-3 text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">Every widget key has its own analytics: views, checkouts opened, orders and conversion, a daily chart, a funnel that shows where shoppers drop off, what sold, and the margin you earned. Compare any week, month or quarter with the one before it.</p>
                    <a href="{{ route('developers.docs', 'widgets-overview') }}" wire:navigate class="mt-5 inline-flex items-center gap-1.5 text-sm font-bold text-primary dark:text-accent">How widgets work <x-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
                @include('marketing.master-marketing._developers-mock-analytics')
            </div>
        </div>
    </section>

    {{-- Guarantees --}}
    <section class="relative px-4 pb-24" aria-labelledby="dv-safe">
        <div class="mx-auto max-w-6xl">
            <h2 id="dv-safe" class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">Built so money mistakes cannot happen quietly</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4 [&>*]:min-w-0">
                @foreach ([
                    ['dev-shield', 'Never charged without delivery', 'If a provider or a save fails, your balance is refunded automatically.'],
                    ['dev-retry', 'Safe to retry', 'Orders are idempotent on your reference: the same request returns the same order.'],
                    ['dev-lock', 'Your cost stays private', 'You see your developer price. Provider cost is never in a response.'],
                    ['dev-key', 'Scoped, revocable keys', 'Give each key only the scopes it needs, and suspend it in one click.'],
                ] as [$ic, $t, $d])
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                        <img src="{{ asset('images/illustrations/'.$ic.'.svg') }}" alt="" width="160" height="120" loading="lazy" decoding="async" class="h-24 w-auto">
                        <h3 class="mt-3 font-bold text-slate-900 dark:text-white">{{ $t }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $d }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Docs explorer --}}
    <section class="relative px-4 pb-24" aria-labelledby="dv-docs">
        <div class="mx-auto max-w-6xl">
            <h2 id="dv-docs" class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">Explore the documentation</h2>
            <p class="mt-3 max-w-2xl text-lg text-slate-700 dark:text-slate-300">Grouped the way you look for things: get started, learn the concepts, then go to the exact endpoint.</p>
            <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3 [&>*]:min-w-0">
                @foreach ($groups as $g)
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" data-dv-group>
                        @php($dvArt = ['get-started' => 'dev-start', 'core-concepts' => 'dev-concepts', 'api-reference' => 'dev-reference', 'widgets' => 'dev-widget', 'resources' => 'dev-resources'][$g['slug']] ?? null)
                        @if ($dvArt)<img src="{{ asset('images/illustrations/'.$dvArt.'.svg') }}" alt="" width="480" height="180" loading="lazy" decoding="async" class="-mx-5 -mt-5 mb-3 h-auto w-[calc(100%+2.5rem)] max-w-none bg-gradient-to-b from-primary/5 to-transparent">@endif
                        <p class="flex items-center gap-2 font-bold text-slate-900 dark:text-white"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-white"><x-icon :name="$g['icon']" class="h-4 w-4" /></span>{{ $g['title'] }}</p>
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($g['pages'] as $p)
                                <li><a href="{{ route('developers.docs', $p['slug']) }}" wire:navigate class="group flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-accent">
                                    <span class="flex items-center gap-2">@if (! empty($p['method']))<span class="rounded px-1.5 py-px text-[10px] font-bold {{ $p['method'] === 'GET' ? 'bg-primary/15 text-primary-dark dark:text-[#7FE0D8]' : 'bg-accent/25 text-[#6B4D00] dark:text-accent' }}">{{ $p['method'] }}</span>@endif{{ $p['title'] }}</span>
                                    <x-icon name="chevron-right" class="h-3.5 w-3.5 opacity-60 transition group-hover:translate-x-0.5" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing call to action --}}
    <section class="px-4 pb-24">
        <div class="mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0A6E6E] to-[#0D1B2A] p-8 text-white sm:p-12">
            <h2 class="max-w-xl text-3xl font-bold tracking-tight sm:text-4xl">Place your first test order today</h2>
            <p class="mt-3 max-w-xl text-lg text-slate-100">Create an account, make a key, top up a small balance and send three calls. Questions go to our support team.</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('developers.docs', 'quick-start') }}" wire:navigate class="rounded-full bg-[#D4A017] px-7 py-3 text-sm font-bold text-[#0D1B2A] transition hover:brightness-110">Open the quick start</a>
                @guest<a href="{{ route('register') }}" class="rounded-full px-7 py-3 text-sm font-bold text-white ring-1 ring-white/50 transition hover:bg-white/10">Create an account</a>@endguest
            </div>
        </div>
    </section>
</x-layouts.marketing>
