{{-- Naara Gift: the public landing page (MASTER-ONLY, admin can switch it off). Copy and images = Site Editor page `naara-gift`; icons are code. --}}
@php
    use App\Support\MasterMarketing\GiftContent as GC;
    use App\Support\SiteContent;
    $img = fn ($v) => SiteContent::imageUrl($v ?? '');
    $h = $sections['hero'] ?? [];
    $hw = $sections['how'] ?? [];
    $wy = $sections['why'] ?? [];
    $ct = $sections['categories'] ?? [];
    $dk = $sections['desktop'] ?? [];
    $fq = $sections['faq'] ?? [];
    $cta = $sections['cta'] ?? [];
    $cards = collect(array_keys(GC::CARDS))->map(fn ($n) => $img($h["card_{$n}_image"] ?? ''))->filter()->values();
    $storeUrl = auth()->check() ? url('/gift-cards') : ($h['cta_primary_href'] ?? url('/gift-cards'));
@endphp
<x-layouts.marketing :title="'Naara Gift | '.$brand" :description="$h['subtext'] ?? null">

{{-- Hero: the pitch, a fan of real gift cards and the store on a phone --}}
@if (! empty($h))
<section class="relative isolate overflow-hidden px-4 pb-28 pt-12 sm:pt-16 nx-ambient nx-ambient--rich">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1fr_1.05fr]">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="gift" class="h-4 w-4" />{{ $h['eyebrow'] }}</p>
            <h1 data-reveal class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {{ $h['headline'] }}
                <span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">{{ $h['headline_accent'] }}</span>
            </h1>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $h['subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $storeUrl }}" class="nx-btn nx-btn--primary !px-7 !py-3">{{ $h['cta_primary'] }}</a>
                <a href="#how" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $h['cta_secondary'] }}</a>
            </div>
            <ul data-reveal class="mt-8 flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4] as $n)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-[13px] font-medium text-slate-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-200"><x-icon name="check" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $h["chip_{$n}"] }}</li>
                @endforeach
            </ul>
        </div>

        <div data-reveal class="relative mx-auto h-[26rem] w-full max-w-xl sm:h-[31rem]" role="img" aria-label="{{ $h['alt'] }}">
            @foreach ([[0, 'left-0 top-2 w-[58%] -rotate-[9deg]', 1], [1, 'right-0 top-0 w-[58%] rotate-[7deg]', 2], [2, 'left-[6%] top-[34%] w-[56%] -rotate-[3deg]', 3]] as [$i, $pos, $z])
                @if (! empty($cards[$i]))
                    <img src="{{ $cards[$i] }}" alt="" width="600" height="400" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async"
                         class="mm-card absolute {{ $pos }} rounded-[1.4rem] shadow-2xl shadow-slate-900/25 ring-1 ring-black/5 dark:shadow-black/60 dark:ring-white/10" style="z-index: {{ $z }}; --i: {{ $i }}">
                @endif
            @endforeach
            @if ($img($h['hero_phone_image'] ?? ''))
                <div class="absolute bottom-0 right-[6%] z-10 w-[40%] sm:w-[38%]">
                    @include('marketing.master-marketing._phone', ['src' => $img($h['hero_phone_image']), 'alt' => $h['hero_phone_alt'], 'priority' => true])
                </div>
            @endif
        </div>
    </div>
</section>

{{-- The brand wall: two rows of real gift cards drifting in opposite directions --}}
@if ($cards->count() >= 6)
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-[#0D1B2A] py-14 nx-ambient nx-ambient--rich" data-bg="dark" aria-hidden="true">
    @foreach ([[0, 6, ''], [6, 12, 'mm-marquee--rev']] as [$from, $to, $cls])
        @php($row = $cards->slice($from, $to - $from)->values())
        @if ($row->isNotEmpty())
            <div class="mm-marquee {{ $cls }} {{ $from > 0 ? 'mt-5' : '' }}">
                <div class="mm-marquee__track">
                    @foreach ([0, 1] as $copy)
                        @foreach ($row as $src)
                            <img src="{{ $src }}" alt="" width="600" height="400" loading="lazy" decoding="async" class="h-36 w-auto shrink-0 rounded-2xl shadow-xl shadow-black/40 ring-1 ring-white/10 sm:h-44">
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</section>
@endif

@if (class_exists(\App\Models\MasterMarketingVideoSection::class))
    @include('marketing.master-marketing._video-slot', ['slot' => 'gift.intro'])
@endif
@endif

{{-- How it works --}}
@if (! empty($hw))
<section id="how" class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $hw['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $hw['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $hw['subtext'] }}</p>
        </div>
        <div class="mt-16 space-y-20">
            @foreach ([1, 2, 3] as $n)
                <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16 {{ $n % 2 === 0 ? 'lg:[&>div:first-child]:order-last' : '' }}">
                    <div data-reveal>
                        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:text-accent">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-[13px] font-bold text-white dark:bg-accent dark:text-[#0D1B2A]">{{ $n }}</span>{{ $hw["s{$n}_label"] }}
                        </p>
                        <h3 class="mt-4 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $hw["s{$n}_title"] }}</h3>
                        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $hw["s{$n}_text"] }}</p>
                    </div>
                    @if ($img($hw["s{$n}_image"] ?? ''))
                        <figure data-reveal class="relative mx-auto w-full max-w-md overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 dark:shadow-black/40 dark:ring-white/10">
                            <img src="{{ $img($hw["s{$n}_image"]) }}" alt="{{ $hw["s{$n}_image_alt"] }}" loading="lazy" decoding="async" class="block h-auto w-full">
                        </figure>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Why --}}
@if (! empty($wy))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $wy['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $wy['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $wy['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (GC::WHY_ICONS as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ ($n % 3) * 0.06 }}s" class="group rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $wy["w{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $wy["w{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Categories --}}
@if (! empty($ct))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-4xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $ct['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $ct['headline'] }}</h2>
        <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $ct['subtext'] }}</p>
        <ul data-reveal class="mt-10 flex flex-wrap justify-center gap-3">
            @foreach (range(1, 8) as $n)
                <li class="rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-[15px] font-semibold text-slate-100 backdrop-blur">{{ $ct["c{$n}"] }}</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- On every screen --}}
@if (! empty($dk))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.15fr_.85fr]">
        <div class="relative">
            @if ($img($dk['shot_image'] ?? ''))
                <figure data-reveal class="overflow-hidden rounded-3xl shadow-2xl shadow-slate-900/15 ring-1 ring-black/5 dark:shadow-black/50 dark:ring-white/10">
                    <img src="{{ $img($dk['shot_image']) }}" alt="{{ $dk['image_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
                </figure>
            @endif
            @if ($img($dk['dark_image'] ?? ''))
                <div class="absolute -bottom-10 right-3 hidden w-32 sm:block lg:-right-6 lg:w-40">
                    @include('marketing.master-marketing._phone', ['src' => $img($dk['dark_image']), 'alt' => $dk['dark_alt']])
                </div>
            @endif
        </div>
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $dk['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold leading-tight text-slate-900 sm:text-4xl dark:text-white">{{ $dk['headline'] }}</h2>
            <p data-reveal class="mt-5 leading-relaxed text-slate-600 dark:text-slate-300">{{ $dk['text_1'] }}</p>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $dk['text_2'] }}</p>
            <p data-reveal class="mt-6 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $dk['footnote'] }}</p>
        </div>
    </div>
</section>
@endif

{{-- FAQ --}}
@if (! empty($fq))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033]" data-bg="light" x-data="{ open: 1 }">
    <div class="mx-auto max-w-3xl">
        <div class="text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $fq['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $fq['headline'] }}</h2>
        </div>
        <div class="mt-10 space-y-3">
            @foreach ([1, 2, 3, 4, 5] as $n)
                <div data-reveal class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-[#16233d]">
                    <button type="button" @click="open = open === {{ $n }} ? 0 : {{ $n }}" :aria-expanded="open === {{ $n }}" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left font-semibold text-slate-900 dark:text-white">
                        <span>{{ $fq["q{$n}"] }}</span>
                        <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-slate-500 transition" x-bind:class="open === {{ $n }} && 'rotate-90'" />
                    </button>
                    <div x-show="open === {{ $n }}" x-collapse @if ($n > 1) x-cloak @endif>
                        <p class="px-5 pb-5 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">{{ $fq["a{$n}"] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Call to action --}}
@if (! empty($cta))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-gradient-to-br from-primary via-[#0B5C66] to-[#0D1B2A] px-4 py-20 text-center text-white -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-2xl">
        <h2 data-reveal class="text-3xl font-bold sm:text-4xl">{{ $cta['headline'] }}</h2>
        <p data-reveal class="mt-4 text-lg text-white/80">{{ $cta['subtext'] }}</p>
        <div data-reveal class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $cta['primary_href'] }}" class="nx-btn !bg-white !px-8 !py-3 !text-primary hover:!bg-slate-100">{{ $cta['primary'] }}</a>
            <a href="{{ $cta['secondary_href'] }}" class="nx-btn !px-8 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .5)">{{ $cta['secondary'] }}</a>
        </div>
    </div>
</section>
@endif
</x-layouts.marketing>
