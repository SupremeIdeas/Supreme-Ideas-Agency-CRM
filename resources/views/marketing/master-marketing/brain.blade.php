{{-- NaaraBrain: the public landing page (MASTER-ONLY, ships switched off until the product is live). Copy and images = Site Editor page `naara-brain`; icons are code. --}}
@php
    use App\Support\MasterMarketing\BrainContent as BC;
    use App\Support\SiteContent;
    $img = fn ($v) => SiteContent::imageUrl($v ?? '');
    $h = $sections['hero'] ?? [];
    $hw = $sections['how'] ?? [];
    $dp = $sections['depth'] ?? [];
    $td = $sections['today'] ?? [];
    $tr = $sections['trust'] ?? [];
    $fq = $sections['faq'] ?? [];
    $ct = $sections['cta'] ?? [];
@endphp
<x-layouts.marketing :title="'NaaraBrain | '.$brand" :description="$h['subtext'] ?? null">

{{-- Hero --}}
@if (! empty($h))
<section class="relative isolate overflow-hidden px-4 pb-24 pt-14 sm:pt-20 nx-ambient nx-ambient--rich">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1fr_1.05fr]">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="sparkles" class="h-4 w-4" />{{ $h['eyebrow'] }}</p>
            <h1 data-reveal class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {{ $h['headline'] }}
                <span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">{{ $h['headline_accent'] }}</span>
            </h1>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $h['subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ auth()->check() ? url('/dashboard') : ($h['cta_primary_href'] ?? route('register')) }}" class="nx-btn nx-btn--primary !px-7 !py-3">{{ $h['cta_primary'] }}</a>
                <a href="#how" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $h['cta_secondary'] }}</a>
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
    @include('marketing.master-marketing._video-slot', ['slot' => 'brain.intro'])
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

{{-- Depth --}}
@if (! empty($dp))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $dp['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $dp['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $dp['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 md:grid-cols-3">
            @foreach (BC::DEPTH_ICONS as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="group rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <p class="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:text-accent">{{ $dp["d{$n}_label"] }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $dp["d{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $dp["d{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
        @if (! empty($dp['note']))
            <p data-reveal class="mx-auto mt-8 max-w-2xl text-center text-sm text-slate-500 dark:text-slate-400">{{ $dp['note'] }}</p>
        @endif
    </div>
</section>
@endif

{{-- Honest about today --}}
@if (! empty($td))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $td['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $td['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $td['subtext'] }}</p>
        </div>
        @if ($img($td['shot_image'] ?? ''))
            <figure data-reveal class="mx-auto mt-12 max-w-4xl overflow-hidden rounded-3xl ring-1 ring-white/15 shadow-2xl shadow-black/50">
                <img src="{{ $img($td['shot_image']) }}" alt="{{ $td['image_alt'] }}" loading="lazy" decoding="async" class="block h-auto w-full">
            </figure>
        @endif
        <div class="mt-10 grid gap-4 md:grid-cols-2">
            @foreach ([['now', 'check', 'border-accent/30 bg-accent/10', 'bg-accent text-[#0D1B2A]', 'now_label'], ['soon', 'clock', 'border-white/10 bg-white/5', 'bg-white/15 text-white', 'soon_label']] as [$key, $icon, $card, $pill, $labelKey])
                <div data-reveal class="rounded-3xl border p-6 backdrop-blur {{ $card }}">
                    <h3 class="flex items-center gap-2 text-lg font-semibold"><x-icon :name="$icon" class="h-5 w-5 text-accent" />{{ $td["{$key}_title"] }}</h3>
                    <ul class="mt-4 space-y-3">
                        @foreach ([1, 2, 3, 4] as $i)
                            <li class="flex items-start gap-3 text-[15px] text-slate-100">
                                <span class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $pill }}">{{ $td[$labelKey] }}</span>
                                <span>{{ $td["{$key}_{$i}"] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Trust --}}
@if (! empty($tr))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $tr['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $tr['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $tr['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([1 => 'info', 2 => 'lock', 3 => 'wallet', 4 => 'palette'] as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $tr["t{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $tr["t{$n}_text"] }}</p>
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
