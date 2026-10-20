{{-- NaaraBusiness Suite: the public marketing page (MASTER-ONLY). Copy = Site Editor page `business-suite`; plans are live. Reuses the homepage problem/solution bento, the About page's Matter.js pills and the homepage FAQ. --}}
@php
    $h = $sections['hero'] ?? [];
    $ch = $sections['channels'] ?? [];
    $ls = $sections['listing'] ?? [];
    $az = $sections['atoz'] ?? [];
    $hw = $sections['how'] ?? [];
    $pk = $sections['perks'] ?? [];
    $pl = $sections['plans'] ?? [];
    $pi = $sections['pills'] ?? [];
    $plansUrl = route('brand.get-listed');
    $startUrl = auth()->check() ? $plansUrl : route('register');
@endphp
<x-layouts.marketing :title="'NaaraBusiness Suite | '.\App\Support\BrandSettings::name()" :description="$h['subtext'] ?? null">
@if (! empty($h))
{{-- Hero --}}
<section class="relative overflow-hidden px-4 pb-16 pt-14 sm:pt-20">
    <div class="mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-2">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $h['eyebrow'] }}</p>
            <h1 data-reveal class="mt-4 text-4xl font-bold leading-tight text-slate-900 sm:text-5xl dark:text-white">{{ $h['headline'] }}</h1>
            <p data-reveal class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $h['subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="#plans" class="nx-btn nx-btn--primary !px-7 !py-3">{{ $h['cta_primary'] }}</a>
                <a href="#how" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $h['cta_secondary'] }}</a>
            </div>
            <dl data-reveal class="mt-10 grid grid-cols-3 gap-3 text-center sm:max-w-md">
                @foreach ([1, 2, 3] as $n)
                    @php
                        [$big, $small] = array_pad(explode('|', (string) ($h["stat_$n"] ?? ''), 2), 2, '');
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 dark:border-white/10 dark:bg-white/5">
                        <dt class="text-lg font-bold text-primary dark:text-accent">{{ $big }}</dt>
                        <dd class="text-xs text-slate-600 dark:text-slate-300">{{ $small }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div data-reveal class="relative">
            <div class="overflow-hidden rounded-3xl shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
                <img src="{{ asset('images/themes/shared/listed-hero-light.webp') }}" alt="A shop owner serving a customer with a tablet at the counter" class="h-full w-full object-cover dark:hidden" width="1600" height="500" fetchpriority="high">
                <img src="{{ asset('images/themes/shared/listed-hero-dark.webp') }}" alt="" class="hidden h-full w-full object-cover dark:block" width="1600" height="500" aria-hidden="true">
            </div>
            <div class="absolute -bottom-5 left-5 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-xl ring-1 ring-black/5 dark:bg-[#16233d] dark:text-white dark:ring-white/10">
                <x-naara-pro-badge size="md" /> Verified on Naara
            </div>
        </div>
    </div>
    <p data-reveal class="mx-auto mt-14 max-w-6xl text-center text-xs text-slate-500 dark:text-slate-400">{{ $h['trust'] }}</p>
</section>

{{-- Channels strip --}}
@if (! empty($ch))
<section class="px-4 pb-12">
    <div class="mx-auto max-w-4xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500 dark:text-slate-400">{{ $ch['eyebrow'] }}</p>
        <div data-reveal class="mt-5 flex flex-wrap items-center justify-center gap-3">
            @foreach ([['whatsapp.svg', 1], ['telegram.svg', 2], ['instagram.svg', 3]] as [$file, $n])
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"><img src="{{ asset('images/channels/'.$file) }}" alt="" class="h-5 w-5" width="20" height="20"> {{ $ch["label_$n"] }}</span>
            @endforeach
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"><x-icon name="message-circle" class="h-5 w-5 text-primary dark:text-accent" /> {{ $ch['label_4'] }}</span>
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"><x-icon name="phone" class="h-5 w-5 text-primary dark:text-accent" /> {{ $ch['label_5'] }}</span>
        </div>
    </div>
</section>
@endif

{{-- Problem and solution (reuses the homepage bento) --}}
@if (! empty($sections['problem']))
    @include('marketing.business._bento', ['s' => $sections['problem']])
@endif

{{-- Get listed (the old Get Listed page's story) --}}
@if (! empty($ls))
<section class="mx-auto max-w-6xl px-4 py-20">
    <div class="mx-auto max-w-3xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $ls['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $ls['headline'] }}</h2>
        <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $ls['subtext'] }}</p>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        @foreach ([['users', 1], ['pin', 2], ['trending-up', 3]] as [$ic, $n])
            <div data-reveal style="--reveal-delay: {{ $n * 0.07 }}s" class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$ic" class="h-5 w-5" /></span>
                <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $ls["c{$n}_title"] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $ls["c{$n}_text"] }}</p>
            </div>
        @endforeach
    </div>
    <div data-reveal class="mx-auto mt-8 max-w-3xl rounded-3xl border border-slate-200 bg-slate-50 p-6 dark:border-white/10 dark:bg-white/5">
        <h3 class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white"><x-icon name="shield-check" class="h-5 w-5 text-primary dark:text-accent" /> {{ $ls['guarantee_title'] }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $ls['guarantee_text'] }}</p>
    </div>
</section>
@endif

{{-- Showcases: team and calls, assistant on ads, conversation insights, channels and payments (owner-supplied artwork re-made in Naara's own branding; items still to ship carry a badge) --}}
@foreach (['team', 'assistant', 'insights', 'connect'] as $si => $sk)
@php $tm = $sections[$sk] ?? []; @endphp
@if (! empty($tm['headline']))
<section class="mx-auto max-w-6xl px-4 py-16" data-showcase="{{ $sk }}" @if ($sk === 'team') data-team-showcase @endif>
    <div class="grid items-center gap-10 md:grid-cols-2">
        <div @class(['md:order-2' => $si % 2 === 1])>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $tm['eyebrow'] ?? '' }}
                @if (! empty($tm['badge']))<span class="ml-2 rounded-full bg-accent/20 px-2.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wider text-accent-dark dark:text-accent">{{ $tm['badge'] }}</span>@endif</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $tm['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $tm['text'] ?? '' }}</p>
            <ul class="mt-6 space-y-3">
                @foreach (['b1', 'b2', 'b3'] as $b)
                    @continue(empty($tm[$b]))
                    <li data-reveal class="flex gap-3 text-sm text-slate-700 dark:text-slate-200"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3.5 w-3.5" /></span>{{ $tm[$b] }}</li>
                @endforeach
            </ul>
        </div>
        <div data-reveal class="flex justify-center">
            <img src="{{ str_starts_with((string) ($tm['main_image'] ?? ''), 'http') || str_starts_with((string) ($tm['main_image'] ?? ''), '/') ? $tm['main_image'] : asset($tm['main_image'] ?? 'images/marketing/business-suite/team-call-routing.webp') }}" alt="{{ $tm['image_alt'] ?? '' }}" class="w-full max-w-md rounded-3xl shadow-xl ring-1 ring-white/10" width="{{ $tm['w'] ?? 714 }}" height="{{ $tm['h'] ?? 568 }}" loading="lazy" decoding="async">
        </div>
    </div>
</section>
@endif
@endforeach

{{-- A to Z --}}
@if (! empty($az))
<section class="bg-[#F4FAFA] px-4 py-20 dark:bg-[#0F2033]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $az['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $az['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $az['subtext'] }}</p>
        </div>
        <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (range('a', 'z') as $i => $l)
                @continue(empty($az[$l.'_title']))
                <li data-reveal style="--reveal-delay: {{ ($i % 4) * 0.05 }}s" class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-extrabold text-white dark:bg-accent dark:text-[#0D1B2A]" aria-hidden="true">{{ strtoupper($l) }}</span>
                    <span>
                        <b class="block text-sm text-slate-900 dark:text-white"><span class="sr-only">{{ strtoupper($l) }}: </span>{{ $az[$l.'_title'] }}</b>
                        <span class="mt-1 block text-[13px] leading-snug text-slate-600 dark:text-slate-300">{{ $az[$l.'_text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

{{-- How it works --}}
@if (! empty($hw))
<section id="how" class="mx-auto max-w-5xl px-4 py-20">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $hw['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $hw['headline'] }}</h2>
    </div>
    <ol class="mt-12 grid gap-5 md:grid-cols-2">
        @foreach ([1, 2, 3, 4] as $n)
            <li data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="flex gap-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-lg font-bold text-white dark:bg-accent dark:text-[#0D1B2A]">{{ $n }}</span>
                <span><b class="block text-slate-900 dark:text-white">{{ $hw["s{$n}_title"] }}</b><span class="mt-1 block text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $hw["s{$n}_text"] }}</span></span>
            </li>
        @endforeach
    </ol>
</section>
@endif

{{-- Naara Pro perks --}}
@if (! empty($pk))
<section class="bg-[#0F2033] px-4 py-20">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $pk['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-white sm:text-4xl">{{ $pk['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $pk['subtext'] }}</p>
        </div>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['star', 1], ['sparkles', 2], ['palette', 3], ['trending-up', 4]] as [$ic, $n])
                <div data-reveal style="--reveal-delay: {{ $n * 0.06 }}s" class="rounded-3xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-accent/15 text-accent"><x-icon :name="$ic" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-white">{{ $pk["p{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ $pk["p{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
        @if ($skins->isNotEmpty())
            <div data-reveal class="mt-10">
                <p class="text-center text-sm font-semibold text-slate-200">{{ $pk['skins_title'] ?? '' }}</p>
                <ul class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach ($skins as $sk)
                        <li class="rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-sm text-slate-100" title="{{ $sk['blurb'] }}">{{ $sk['label'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
@endif

{{-- Plans: live from the plans table --}}
@if (! empty($pl))
<section id="plans" class="mx-auto max-w-6xl px-4 py-20">
    <div class="mx-auto max-w-3xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $pl['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $pl['headline'] }}</h2>
        <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $pl['subtext'] }}</p>
    </div>
    @if ($plans->isEmpty())
        <p class="mt-10 text-center text-slate-500 dark:text-slate-400">{{ $pl['empty'] }}</p>
    @else
        <div class="mt-12 grid gap-6 md:grid-cols-{{ min(3, $plans->count()) }}">
            @foreach ($plans as $i => $row)
                @php $p = $row['plan'] @endphp
                @php $popular = $plans->count() > 2 && $i === 1 @endphp
                <div data-reveal style="--reveal-delay: {{ $i * 0.07 }}s" class="relative flex flex-col rounded-3xl border bg-white p-7 dark:bg-[#16233d] {{ $popular ? 'border-primary shadow-2xl ring-2 ring-primary/20 dark:border-accent dark:ring-accent/20' : 'border-slate-200 dark:border-white/10' }}">
                    @if ($popular)<span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-accent px-3 py-1 text-xs font-bold text-[#0D1B2A]">{{ $pl['popular'] }}</span>@endif
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $p->name }}</h3>
                    <p class="mt-3 text-4xl font-extrabold text-slate-900 dark:text-white">${{ number_format($p->price_usd_per_month, 0) }}<small class="text-base font-medium text-slate-500 dark:text-slate-400">/mo</small></p>
                    <ul class="mt-6 flex-1 space-y-2.5 text-sm text-slate-700 dark:text-slate-200" data-pro-bullets>
                        @foreach (\App\Support\Business\NaaraProCatalogue::bullets($p) as $line)
                            <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-primary dark:text-accent" /> {{ $line }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ $plansUrl }}" class="nx-btn {{ $popular ? 'nx-btn--primary' : 'text-slate-800 dark:text-white' }} mt-7 !justify-center !py-3" style="{{ $popular ? '' : 'box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)' }}">{{ $pl['cta'] }} {{ $p->name }}</a>
                </div>
            @endforeach
        </div>
        <details data-reveal class="mx-auto mt-10 max-w-5xl rounded-2xl border border-slate-200 bg-white/60 p-4 dark:border-white/10 dark:bg-white/5" data-pro-compare-wrap>
            <summary class="cursor-pointer text-sm font-bold text-slate-900 dark:text-white">Compare everything Naara Pro includes</summary>
            <div class="mt-4"><x-naara-pro.compare :plans="$plans->pluck('plan')" compact /></div>
        </details>
        <p data-reveal class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ $pl['foot'] }}</p>
    @endif
</section>
@endif

{{-- Matter.js pills (same playground as the About page) --}}
@if (! empty($pi))
@php
    $colors = [['#0A6E6E', '#fff'], ['#5eead4', '#0D1B2A'], ['#E8412A', '#fff'], ['#fff', '#0D1B2A'], ['#1A2840', '#fff'], ['#D4A017', '#0D1B2A'], ['#0A6E6E', '#fff'], ['rgba(255,255,255,.1)', '#fff'], ['#2dd4bf', '#0D1B2A'], ['#fff', '#0A6E6E'], ['#1A2840', '#5eead4'], ['#E8412A', '#fff']];
    $labels = collect(range(1, 40))->map(fn ($n) => trim((string) ($pi["pill_$n"] ?? '')))->filter()->values();
@endphp
<section class="bg-[#0F2033] px-4 py-20">
    <div class="mx-auto max-w-4xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $pi['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-2xl font-bold text-white sm:text-3xl">{{ $pi['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-3 max-w-xl text-sm text-slate-300">{{ $pi['subtext'] }}</p>
        <div data-pill-physics data-reveal style="--reveal-delay:.15s" class="nx-pills mt-10 flex flex-wrap items-center justify-center gap-3">
            @foreach ($labels as $i => $label)
                @php
                    [$bg, $fg] = $colors[$i % count($colors)];
                @endphp
                <span data-pill class="rounded-full px-5 py-2.5 text-sm font-medium shadow-lg transition hover:shadow-xl" style="background:{{ $bg }}; color:{{ $fg }};">{{ $label }}</span>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- FAQ: the homepage FAQ partial, with Suite questions --}}
@if (! empty($sections['faq']))
    @include('marketing.home.faq', ['s' => $sections['faq']])
@endif

{{-- Closing call to action --}}
@if (! empty($sections['cta']))
@php $c = $sections['cta'] @endphp
<section class="px-4 pb-32 pt-8" data-bg="light">
    <div data-reveal class="relative mx-auto max-w-4xl overflow-hidden rounded-3xl bg-primary px-6 py-16 text-center text-white shadow-2xl">
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-accent/25 blur-3xl"></div>
        <h2 class="relative text-3xl font-bold sm:text-4xl">{{ $c['headline'] }}</h2>
        <p class="relative mx-auto mt-4 max-w-xl leading-relaxed text-teal-50">{{ $c['subtext'] }}</p>
        <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $startUrl }}" class="nx-btn nx-btn--gold !px-8 !py-3">{{ $c['cta_primary'] }}</a>
            <a href="{{ \App\Support\FeatureEntitlements::isMaster() ? route('faq') : route('contact') }}" wire:navigate class="nx-btn !px-8 !py-3 !text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .6)">{{ $c['cta_secondary'] }}</a>
        </div>
        <p class="relative mt-6 text-xs text-teal-100">{{ $c['trust'] }}</p>
    </div>
</section>
@endif
@endif
</x-layouts.marketing>
