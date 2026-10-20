{{-- The sections that follow the product carousel on /products (MASTER-ONLY). Copy and images = Site Editor page `products`; icons are code. --}}
@php
    use App\Support\MasterMarketing\ProductsContent as PC;
    use App\Support\SiteContent;
    $img = fn ($v) => SiteContent::imageUrl($v ?? '');
    $q = $extra['quote'] ?? [];
    $earn = $extra['earn'] ?? [];
    $net = $extra['network'] ?? [];
    $per = $extra['personal'] ?? [];
    $more = $extra['more'] ?? [];
    $cta = $extra['cta'] ?? [];
@endphp

{{-- 1. The infrastructure statement --}}
@if (! empty($q))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.15fr_.85fr]">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $q['eyebrow'] }}</p>
            <svg class="mt-6 h-10 w-10 text-accent/70" viewBox="0 0 40 40" fill="currentColor" aria-hidden="true"><path d="M8 24c0-7 3-12 10-14l1.5 3c-3.5 1.4-5 4-5 6.5H18V30H8V24Zm14 0c0-7 3-12 10-14l1.5 3c-3.5 1.4-5 4-5 6.5H32V30H22V24Z"/></svg>
            <blockquote data-reveal class="mt-4 text-3xl font-bold leading-[1.15] tracking-tight sm:text-4xl lg:text-[2.75rem]">
                {{ $q['quote_before'] }}<span class="bg-gradient-to-r from-accent via-[#7FE0D8] to-accent bg-clip-text text-transparent">{{ $q['quote_accent'] }}</span>{{ $q['quote_after'] }}
            </blockquote>
            <p data-reveal class="mt-5 text-sm font-semibold uppercase tracking-widest text-white/60">{{ $q['attribution'] }}</p>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-300">{{ $q['subtext'] }}</p>
            <dl class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([1, 2, 3, 4] as $n)
                    <div data-reveal style="--reveal-delay: {{ $n * 0.06 }}s" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                        <dt class="font-display text-2xl font-bold text-accent">{{ $q["s{$n}_value"] }}</dt>
                        <dd class="mt-1 text-xs leading-snug text-slate-300">{{ $q["s{$n}_label"] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        @if ($img($q['quote_image'] ?? ''))
            <figure data-reveal class="relative">
                <div class="overflow-hidden rounded-3xl shadow-2xl shadow-black/50 ring-1 ring-white/15">
                    <img src="{{ $img($q['quote_image']) }}" alt="{{ $q['quote_image_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
                </div>
            </figure>
        @endif
    </div>
</section>
@endif

{{-- 2. Three ways to earn --}}
@if (! empty($earn))
<section id="earn" class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A] nx-ambient" data-bg="light"
         x-data="{ tab: 1 }">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $earn['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $earn['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $earn['subtext'] }}</p>
        </div>

        <div data-reveal class="mx-auto mt-10 flex max-w-xl rounded-2xl border border-slate-200 bg-slate-50 p-1.5 dark:border-white/10 dark:bg-white/5" role="tablist">
            @foreach ([1, 2, 3] as $n)
                <button type="button" role="tab" :aria-selected="tab === {{ $n }}" @click="tab = {{ $n }}"
                        class="flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                        :class="tab === {{ $n }} ? 'bg-primary text-white shadow-lg shadow-primary/30 dark:bg-accent dark:text-[#0D1B2A]' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'">{{ $earn["tab{$n}_label"] }}</button>
            @endforeach
        </div>

        <div class="mt-10">
            @foreach ([1, 2, 3] as $n)
                <div x-show="tab === {{ $n }}" @if ($n > 1) x-cloak @endif x-transition.opacity.duration.300ms role="tabpanel"
                     class="grid items-center gap-10 lg:grid-cols-2 {{ $n === 2 ? 'lg:[&>figure]:order-first' : '' }}">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $earn["tab{$n}_title"] }}</h3>
                        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $earn["tab{$n}_text"] }}</p>
                        <ul class="mt-6 space-y-3">
                            @foreach ([1, 2, 3, 4] as $b)
                                <li class="flex gap-3 text-slate-700 dark:text-slate-200">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3.5 w-3.5" /></span>
                                    <span class="text-[15px] leading-snug">{{ $earn["tab{$n}_b{$b}"] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ $earn["tab{$n}_href"] }}" class="nx-btn nx-btn--primary mt-8 !px-7 !py-3">{{ $earn["tab{$n}_cta"] }}</a>
                    </div>
                    @if ($img($earn["tab{$n}_image"] ?? ''))
                        <figure class="relative mx-auto w-full max-w-md overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 dark:shadow-black/40 dark:ring-white/10">
                            <img src="{{ $img($earn["tab{$n}_image"]) }}" alt="{{ $earn["tab{$n}_title"] }}" loading="lazy" decoding="async" class="block h-auto w-full">
                        </figure>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 3. Business meets business: the journey from the directory to a long relationship --}}
@if (! empty($net))
@php
    $netItems = [];
    foreach ([1, 2, 3, 4, 5] as $n) {
        $netItems[] = [
            'title' => $net["st{$n}_label"],
            'desc' => $net["st{$n}_title"].'. '.$net["st{$n}_text"],
            'image' => $img($net["st{$n}_image"] ?? ''),
            'device' => true,
        ];
    }
@endphp
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-6xl">
        <x-timed-tab-switcher :items="$netItems" tone="dark" id="mm-net" :eyebrow="$net['eyebrow']" :heading="$net['headline']" :subtext="$net['subtext']" :duration="9000" />
        <p data-reveal class="mx-auto mt-10 max-w-2xl text-center text-lg font-medium leading-relaxed text-slate-200">{{ $net['closing'] }}</p>
    </div>
</section>
@endif

{{-- 4. Personalisation on demand --}}
@if (! empty($per))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] py-20 sm:py-24 -mt-8 dark:bg-[#0F2033] nx-ambient nx-ambient--rich" data-bg="light"
         x-data="{ rail: null, scroll(d) { this.$refs.rail.scrollBy({ left: d * this.$refs.rail.clientWidth * 0.8, behavior: 'smooth' }) } }">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 lg:grid-cols-[.9fr_1.1fr] lg:items-end">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $per['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $per['headline'] }}</h2>
            <p data-reveal class="mt-5 leading-relaxed text-slate-600 dark:text-slate-300">{{ $per['text_1'] }}</p>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $per['text_2'] }}</p>
        </div>
        <div data-reveal>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">{{ $per['dials_label'] }}</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4, 5, 6] as $n)
                    <li class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">{{ $per["d{$n}"] }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ $per['accents_label'] }}</p>
        </div>
    </div>

    {{-- The skin rail: eight real dashboard screens, each in its own skin --}}
    <div class="relative mt-12">
        <div x-ref="rail" class="mm-rail flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth px-4 pb-6 sm:gap-7 lg:px-[max(1rem,calc((100vw-72rem)/2+1rem))]" tabindex="0" aria-label="{{ $per['headline'] }}">
            @foreach (PC::SKINS as $key => $meta)
                <div class="w-[15.5rem] shrink-0 snap-center sm:w-[17rem]" data-reveal style="--reveal-delay: {{ $loop->index * 0.04 }}s">
                    @include('marketing.master-marketing._phone', ['src' => $img($per["sk_{$key}_image"] ?? ''), 'alt' => $per["sk_{$key}_name"].' skin', 'caption' => null])
                    <p class="mt-4 text-center font-semibold text-slate-900 dark:text-white">{{ $per["sk_{$key}_name"] }}</p>
                    <p class="mx-auto mt-1 max-w-[15rem] text-center text-[13px] leading-snug text-slate-600 dark:text-slate-300">{{ $per["sk_{$key}_text"] }}</p>
                </div>
            @endforeach
        </div>
        <div class="mx-auto mt-2 hidden max-w-6xl justify-end gap-2 px-4 sm:flex">
            <button type="button" @click="scroll(-1)" aria-label="Previous skins" class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 transition hover:border-primary hover:text-primary dark:border-white/15 dark:bg-white/5 dark:text-white"><x-icon name="chevron-left" class="h-5 w-5" /></button>
            <button type="button" @click="scroll(1)" aria-label="More skins" class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 transition hover:border-primary hover:text-primary dark:border-white/15 dark:bg-white/5 dark:text-white"><x-icon name="chevron-right" class="h-5 w-5" /></button>
        </div>
    </div>

    <div class="mx-auto mt-8 flex max-w-6xl flex-col items-start gap-4 px-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex max-w-2xl items-start gap-3 text-sm leading-relaxed text-slate-700 dark:text-slate-200">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent/20 text-[#6B4D00] dark:text-accent"><x-icon name="star" class="h-4 w-4" /></span>
            {{ $per['pro_note'] }}
        </p>
        <a href="{{ $per['cta_href'] }}" class="nx-btn nx-btn--primary shrink-0 !px-7 !py-3">{{ $per['cta'] }}</a>
    </div>
</section>
@endif

{{-- 5. And a lot more --}}
@if (! empty($more))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $more['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $more['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $more['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (PC::MORE_ICONS as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ ($n % 3) * 0.06 }}s" class="group rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $more["m{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $more["m{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 6. Closing call to action --}}
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
