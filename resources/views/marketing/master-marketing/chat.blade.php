{{-- Naara Chat: the public landing page (MASTER-ONLY). Copy and images = Site Editor page `naara-chat`; icons are code. --}}
@php
    use App\Support\MasterMarketing\ChatContent as CC;
    use App\Support\SiteContent;
    $img = fn ($v) => SiteContent::imageUrl($v ?? '');
    $h = $sections['hero'] ?? [];
    $pl = $sections['pillars'] ?? [];
    $pw = $sections['power'] ?? [];
    $pv = $sections['privacy'] ?? [];
    $br = $sections['brands'] ?? [];
    $sk = $sections['skins'] ?? [];
    $cr = $sections['credits'] ?? [];
    $fq = $sections['faq'] ?? [];
    $ct = $sections['cta'] ?? [];
    $startUrl = auth()->check() ? (\Illuminate\Support\Facades\Route::has('n2n.chat') ? route('n2n.chat') : url('/n2n/chat')) : ($h['cta_primary_href'] ?? route('register'));
@endphp
<x-layouts.marketing :title="'Naara Chat | '.$brand" :description="$h['subtext'] ?? null">

{{-- Hero --}}
@if (! empty($h))
<section class="relative isolate overflow-hidden px-4 pb-24 pt-14 sm:pt-20 nx-ambient nx-ambient--rich">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1fr_1.05fr]">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="message-circle" class="h-4 w-4" />{{ $h['eyebrow'] }}</p>
            <h1 data-reveal class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {{ $h['headline'] }}
                <span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">{{ $h['headline_accent'] }}</span>
            </h1>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $h['subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $startUrl }}" class="nx-btn nx-btn--primary !px-7 !py-3">{{ $h['cta_primary'] }}</a>
                <a href="#essentials" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $h['cta_secondary'] }}</a>
            </div>
            <ul data-reveal class="mt-8 flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4] as $n)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-[13px] font-medium text-slate-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-200"><x-icon name="check" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $h["chip_{$n}"] }}</li>
                @endforeach
            </ul>
        </div>
        <div data-reveal class="relative">
            @if ($img($h['hero_image'] ?? ''))
                <div class="overflow-hidden rounded-3xl shadow-2xl shadow-slate-900/20 ring-1 ring-black/5 dark:shadow-black/50 dark:ring-white/10">
                    <img src="{{ $img($h['hero_image']) }}" alt="{{ $h['hero_image_alt'] }}" width="1440" height="1060" fetchpriority="high" decoding="async" class="block w-full">
                </div>
            @endif
            @if ($img($h['hero_phone_image'] ?? ''))
                <div class="absolute -bottom-10 -left-3 hidden w-36 sm:block lg:-left-10 lg:w-44">
                    @include('marketing.master-marketing._phone', ['src' => $img($h['hero_phone_image']), 'alt' => $h['hero_phone_alt'], 'priority' => true])
                </div>
            @endif
        </div>
    </div>
</section>
@endif

@if (class_exists(\App\Models\MasterMarketingVideoSection::class))
    @include('marketing.master-marketing._video-slot', ['slot' => 'chat.intro'])
@endif

{{-- The three essentials --}}
@if (! empty($pl))
<section id="essentials" class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $pl['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $pl['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $pl['subtext'] }}</p>
        </div>
        <div class="mt-16 space-y-20">
            @foreach ([1, 2, 3] as $n)
                <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16 {{ $n % 2 === 0 ? 'lg:[&>div:first-child]:order-last' : '' }}">
                    <div data-reveal>
                        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:text-accent">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-[13px] font-bold text-white dark:bg-accent dark:text-[#0D1B2A]">{{ $n }}</span>{{ $pl["p{$n}_label"] }}
                        </p>
                        <h3 class="mt-4 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $pl["p{$n}_title"] }}</h3>
                        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $pl["p{$n}_text"] }}</p>
                        <ul class="mt-6 space-y-2.5">
                            @foreach ([1, 2, 3] as $b)
                                <li class="flex gap-3 text-[15px] text-slate-700 dark:text-slate-200"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3.5 w-3.5" /></span>{{ $pl["p{$n}_b{$b}"] }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($img($pl["p{$n}_image"] ?? ''))
                        <figure data-reveal class="relative mx-auto w-full max-w-md overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 dark:shadow-black/40 dark:ring-white/10">
                            <img src="{{ $img($pl["p{$n}_image"]) }}" alt="{{ $pl["p{$n}_image_alt"] }}" loading="lazy" decoding="async" class="block h-auto w-full">
                        </figure>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Power features --}}
@if (! empty($pw))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $pw['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $pw['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $pw['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (CC::FEATURE_ICONS as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ ($n % 4) * 0.05 }}s" class="group rounded-3xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $pw["f{$n}_title"] }}</h3>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-slate-600 dark:text-slate-300">{{ $pw["f{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Privacy and control --}}
@if (! empty($pv))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $pv['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $pv['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $pv['subtext'] }}</p>
        </div>
        <div class="mt-12 grid items-center gap-10 lg:grid-cols-[.8fr_1.2fr]">
            @if ($img($pv['shot_image'] ?? ''))
                <figure data-reveal class="mx-auto w-full max-w-sm overflow-hidden rounded-[2rem] ring-1 ring-white/15 shadow-2xl shadow-black/50">
                    <img src="{{ $img($pv['shot_image']) }}" alt="{{ $pv['image_alt'] }}" loading="lazy" decoding="async" class="block h-auto w-full">
                </figure>
            @endif
            <div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (CC::PRIVACY_ICONS as $n => $icon)
                        <div data-reveal style="--reveal-delay: {{ ($n % 2) * 0.06 }}s" class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent/15 text-accent"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
                            <h3 class="mt-3 font-semibold">{{ $pv["pv{$n}_title"] }}</h3>
                            <p class="mt-1.5 text-[13.5px] leading-relaxed text-slate-300">{{ $pv["pv{$n}_text"] }}</p>
                        </div>
                    @endforeach
                </div>
                <div data-reveal class="mt-4 rounded-2xl border border-accent/30 bg-accent/10 p-5">
                    <h3 class="flex items-center gap-2 font-semibold text-accent"><x-icon name="info" class="h-5 w-5" />{{ $pv['note_title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-200">{{ $pv['note_text'] }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- For brands --}}
@if (! empty($br))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2">
        @if ($img($br['shot_image'] ?? ''))
            <figure data-reveal class="mx-auto w-full max-w-md overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 lg:order-last dark:shadow-black/40 dark:ring-white/10">
                <img src="{{ $img($br['shot_image']) }}" alt="{{ $br['image_alt'] }}" loading="lazy" decoding="async" class="block h-auto w-full">
            </figure>
        @endif
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $br['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $br['headline'] }}</h2>
            <p data-reveal class="mt-5 leading-relaxed text-slate-600 dark:text-slate-300">{{ $br['text_1'] }}</p>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $br['text_2'] }}</p>
            <a data-reveal href="{{ $br['cta_href'] }}" class="nx-btn nx-btn--primary mt-8 !px-7 !py-3">{{ $br['cta'] }}</a>
        </div>
    </div>
</section>
@endif

{{-- Skins --}}
@if (! empty($sk))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] py-20 sm:py-24 -mt-8 dark:bg-[#0F2033] nx-ambient nx-ambient--rich" data-bg="light">
    <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $sk['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $sk['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $sk['subtext'] }}</p>
        </div>
    </div>
    <div class="mm-rail mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-6 sm:gap-7 lg:justify-center" tabindex="0" aria-label="{{ $sk['headline'] }}">
        @foreach (CC::SKINS as $key => $name)
            <div class="w-[13.5rem] shrink-0 snap-center sm:w-[14.5rem]" data-reveal style="--reveal-delay: {{ $loop->index * 0.04 }}s">
                @include('marketing.master-marketing._phone', ['src' => $img($sk["sk_{$key}_image"] ?? ''), 'alt' => $sk["sk_{$key}_name"].' skin in Naara Chat'])
                <p class="mt-4 text-center font-semibold text-slate-900 dark:text-white">{{ $sk["sk_{$key}_name"] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- How it is paid --}}
@if (! empty($cr))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $cr['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $cr['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $cr['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['send', 1], ['phone', 2], ['star', 3], ['wallet', 4]] as [$icon, $n])
                <div data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $cr["k{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $cr["k{$n}_text"] }}</p>
                </div>
            @endforeach
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
            @foreach ([1, 2, 3, 4, 5, 6] as $n)
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
@if (! empty($ct))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-gradient-to-br from-primary via-[#0B5C66] to-[#0D1B2A] px-4 py-20 text-center text-white -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-2xl">
        <h2 data-reveal class="text-3xl font-bold sm:text-4xl">{{ $ct['headline'] }}</h2>
        <p data-reveal class="mt-4 text-lg text-white/80">{{ $ct['subtext'] }}</p>
        <div data-reveal class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $ct['primary_href'] }}" class="nx-btn !bg-white !px-8 !py-3 !text-primary hover:!bg-slate-100">{{ $ct['primary'] }}</a>
            <a href="{{ $ct['secondary_href'] }}" class="nx-btn !px-8 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .5)">{{ $ct['secondary'] }}</a>
        </div>
    </div>
</section>
@endif
</x-layouts.marketing>
