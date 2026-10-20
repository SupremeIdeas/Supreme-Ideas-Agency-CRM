{{-- White Label: the public sales page (MASTER-ONLY, admin can switch it off). Copy and images = Site Editor page `white-label`; plan names and prices come live
     from the license catalogue; icons are code. --}}
@php
    use App\Models\WhiteLabelLicensePlan;
    use App\Support\MasterMarketing\WhiteLabelContent as WC;
    use App\Support\SiteContent;
    $img = fn ($v) => SiteContent::imageUrl($v ?? '');
    $h = $sections['hero'] ?? [];
    $rf = $sections['reflection'] ?? [];
    $kit = $sections['kit'] ?? [];
    $vd = $sections['video'] ?? [];
    $en = $sections['engine'] ?? [];
    $sc = $sections['showcase'] ?? [];
    $cp = $sections['compare'] ?? [];
    $ho = $sections['handover'] ?? [];
    $ag = $sections['agency'] ?? [];
    $dc = $sections['disclaimer'] ?? [];
    $fq = $sections['faq'] ?? [];
    $ct = $sections['cta'] ?? [];
    $funnelOn = \Illuminate\Support\Facades\Route::has('wl-funnel.start') && \App\Services\WhiteLabel\Funnel\WlFunnel::enabled();   // master only: the plan buttons start the guided purchase
    $buyUrl = \Illuminate\Support\Facades\Route::has('merchant.white-label') ? route('merchant.white-label') : route('register');
    // The table's columns: the live catalogue when it is seeded, otherwise the four standard licenses by key (names are editable fallbacks).
    $columns = $plans->isNotEmpty()
        ? $plans->map(fn ($p) => ['key' => $p->key, 'name' => $p->name, 'tagline' => $p->tagline, 'price' => '$'.number_format((float) $p->price_usd), 'balance' => WhiteLabelLicensePlan::balanceToExtended($p), 'live' => true])->all()
        : array_map(fn ($k) => ['key' => $k, 'name' => $cp["plan_{$k}_name"] ?? $k, 'tagline' => '', 'price' => null, 'balance' => null, 'live' => false], WC::PLAN_KEYS);
@endphp
<x-layouts.marketing :title="'White Label | '.$brand" :description="$h['subtext'] ?? null">

{{-- Hero: the pitch on the left, a living wall of real screens on the right --}}
@if (! empty($h))
<section class="relative isolate overflow-hidden px-4 pb-24 pt-12 sm:pt-16 nx-ambient nx-ambient--rich">
    <div class="mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-[1fr_1fr]">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="layers" class="h-4 w-4" />{{ $h['eyebrow'] }}</p>
            <h1 data-reveal class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {{ $h['headline'] }}
                <span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">{{ $h['headline_accent'] }}</span>
            </h1>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $h['subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $h['cta_primary_href'] }}" class="nx-btn nx-btn--primary !px-7 !py-3">{{ $h['cta_primary'] }}</a>
                <a href="{{ $h['cta_secondary_href'] }}" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $h['cta_secondary'] }}</a>
            </div>
            <ul data-reveal class="mt-8 flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4] as $n)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-[13px] font-medium text-slate-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-200"><x-icon name="check" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $h["chip_{$n}"] }}</li>
                @endforeach
            </ul>
            <dl data-reveal class="mt-10 grid max-w-md grid-cols-3 divide-x divide-slate-200 dark:divide-white/10">
                @foreach ([1, 2, 3] as $n)
                    <div class="px-4 first:pl-0">
                        <dt class="font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $h["stat_{$n}_value"] }}</dt>
                        <dd class="mt-1 text-[13px] leading-snug text-slate-600 dark:text-slate-300">{{ $h["stat_{$n}_label"] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- The wall: three slow columns of real dashboard screens, each in a different skin --}}
        <div data-reveal class="mm-wall relative mx-auto h-[420px] w-full max-w-xl sm:h-[560px] lg:h-[600px]" role="img" aria-label="{{ $h['wall_alt'] }}">
            @foreach ([[1, 2, 3], [4, 5, 6], [7, 8, 9]] as $col => $cells)
                <div class="mm-wall__col {{ $col === 1 ? 'mm-wall__col--down' : '' }}" aria-hidden="true">
                    <div class="mm-wall__track">
                        @foreach ([0, 1] as $copy)
                            @foreach ($cells as $n)
                                @if ($img($h["wall_{$n}_image"] ?? ''))
                                    <div class="mm-wall__cell">@include('marketing.master-marketing._phone', ['src' => $img($h["wall_{$n}_image"]), 'alt' => ''])</div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (class_exists(\App\Models\MasterMarketingVideoSection::class))
    @include('marketing.master-marketing._video-slot', ['slot' => 'white-label.intro'])
@endif

{{-- The video (appears once the admin has added one) --}}
@if (! empty($vd))
    @include('marketing.master-marketing._video', ['vd' => $vd])
@endif

{{-- A reflection of the official platform --}}
@if (! empty($rf))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.1fr_.9fr]">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $rf['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ $rf['headline'] }}</h2>
            <p data-reveal class="mt-5 leading-relaxed text-slate-300">{{ $rf['text_1'] }}</p>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $rf['text_2'] }}</p>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $rf['text_3'] }}</p>
            <ul data-reveal class="mt-7 flex flex-wrap gap-2">
                @foreach ([1, 2, 3] as $n)
                    <li class="rounded-full border border-accent/40 bg-accent/10 px-4 py-1.5 text-sm font-semibold text-accent">{{ $rf["pill_{$n}"] }}</li>
                @endforeach
            </ul>
        </div>
        @if ($img($rf['shot_image'] ?? ''))
            <figure data-reveal class="mx-auto w-full max-w-md overflow-hidden rounded-[2rem] shadow-2xl shadow-black/50 ring-1 ring-white/15">
                <img src="{{ $img($rf['shot_image']) }}" alt="{{ $rf['image_alt'] }}" loading="lazy" decoding="async" class="block h-auto w-full">
            </figure>
        @endif
    </div>
</section>
@endif

{{-- One engine, your brand on top (the illustration) --}}
@if (! empty($en))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $en['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $en['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $en['subtext'] }}</p>
        </div>
        <div data-reveal class="mt-12 overflow-hidden rounded-[2rem] shadow-2xl shadow-slate-900/10 ring-1 ring-slate-200 dark:shadow-black/40 dark:ring-white/10">
            @include('marketing.master-marketing._engine-art', ['l' => $en])
        </div>
        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ([['layers', 1], ['shield-check', 2], ['refresh', 3]] as [$icon, $n])
                <div data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $en["p{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $en["p{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- What you get --}}
@if (! empty($kit))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $kit['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $kit['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $kit['subtext'] }}</p>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (WC::KIT_ICONS as $n => $icon)
                <div data-reveal style="--reveal-delay: {{ ($n % 3) * 0.06 }}s" class="group rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $kit["k{$n}_title"] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $kit["k{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- The portfolio: eight skins, each a case study --}}
@if (! empty($sc))
@php($skinKeys = array_keys(WC::SKINS))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark"
         x-data="{ open: null, keys: @js($skinKeys), step(d) { const i = this.keys.indexOf(this.open); this.open = this.keys[(i + d + this.keys.length) % this.keys.length]; } }"
         @keydown.escape.window="open = null">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $sc['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $sc['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $sc['subtext'] }}</p>
        </div>

        {{-- Featured piece --}}
        <div data-reveal class="mt-14 grid items-center gap-8 overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.04] p-6 backdrop-blur sm:p-8 lg:grid-cols-[1.15fr_.85fr] lg:gap-12">
            @if ($img($sc['feat_image'] ?? ''))
                <figure class="overflow-hidden rounded-2xl shadow-2xl shadow-black/50 ring-1 ring-white/15">
                    <img src="{{ $img($sc['feat_image']) }}" alt="{{ $sc['feat_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
                </figure>
            @endif
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $sc['feat_eyebrow'] }}</p>
                <h3 class="mt-3 text-2xl font-bold leading-tight sm:text-3xl">{{ $sc['feat_title'] }}</h3>
                <p class="mt-4 leading-relaxed text-slate-300">{{ $sc['feat_text'] }}</p>
                <ul class="mt-6 space-y-2.5">
                    @foreach ([1, 2, 3] as $n)
                        <li class="flex gap-3 text-[15px] text-slate-100"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent/20 text-accent"><x-icon name="check" class="h-3.5 w-3.5" /></span>{{ $sc["feat_b{$n}"] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- The skin cards --}}
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (WC::SKINS as $key => [$skinName, $skinLine, $tint])
                <button type="button" @click="open = '{{ $key }}'" data-reveal style="--reveal-delay: {{ ($loop->index % 4) * 0.06 }}s; --tint: {{ $tint }}"
                        class="group relative isolate flex flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#101f33] text-left transition duration-300 hover:-translate-y-1 hover:border-white/30 hover:shadow-2xl hover:shadow-black/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        aria-haspopup="dialog" aria-label="{{ $sc["sk_{$key}_name"] }}: {{ $sc['view_label'] }}">
                    <span class="pointer-events-none absolute inset-0 -z-10 opacity-90" style="background: radial-gradient(120% 80% at 50% 0%, color-mix(in srgb, var(--tint) 38%, transparent), transparent 70%)" aria-hidden="true"></span>
                    <span class="relative block h-64 overflow-hidden" aria-hidden="true">
                        <span class="absolute left-[7%] top-10 w-[40%] -rotate-[8deg] transition duration-500 group-hover:-translate-x-2 group-hover:-rotate-[12deg]">@include('marketing.master-marketing._phone', ['src' => $img($sc["sk_{$key}_esim_image"] ?? ''), 'alt' => ''])</span>
                        <span class="absolute right-[7%] top-10 w-[40%] rotate-[8deg] transition duration-500 group-hover:translate-x-2 group-hover:rotate-[12deg]">@include('marketing.master-marketing._phone', ['src' => $img($sc["sk_{$key}_wallet_image"] ?? ''), 'alt' => ''])</span>
                        <span class="absolute left-1/2 top-5 z-10 w-[44%] -translate-x-1/2 transition duration-500 group-hover:-translate-y-2">@include('marketing.master-marketing._phone', ['src' => $img($sc["sk_{$key}_home_image"] ?? ''), 'alt' => ''])</span>
                    </span>
                    <span class="flex flex-1 flex-col border-t border-white/10 bg-black/20 p-5">
                        <span class="flex items-center gap-2 font-display text-lg font-bold"><span class="h-3 w-3 rounded-full ring-2 ring-white/30" style="background: var(--tint)"></span>{{ $sc["sk_{$key}_name"] }}</span>
                        <span class="mt-1.5 flex-1 text-[13.5px] leading-relaxed text-slate-300">{{ $sc["sk_{$key}_text"] }}</span>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-[13px] font-semibold text-accent">{{ $sc['view_label'] }}<x-icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" /></span>
                    </span>
                </button>
            @endforeach
        </div>
        <p class="mx-auto mt-10 max-w-2xl text-center text-sm text-slate-400">{{ $sc['footnote'] }}</p>
    </div>

    {{-- Case-study view: the four screens of one skin (teleported to the body so the sticky header can never sit on top of it) --}}
    <template x-teleport="body">
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center bg-[#050d16]/90 p-3 text-white backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" @click.self="open = null"
         x-effect="if (open) $nextTick(() => $refs.close && $refs.close.focus())">
        @foreach (WC::SKINS as $key => [$skinName, $skinLine, $tint])
            <div x-show="open === '{{ $key }}'" x-cloak class="relative max-h-full w-full max-w-5xl overflow-y-auto rounded-[2rem] border border-white/15 bg-[#0D1B2A] p-5 shadow-2xl shadow-black/60 sm:p-8" style="--tint: {{ $tint }}">
                <span class="pointer-events-none absolute inset-0 rounded-[2rem]" style="background: radial-gradient(90% 60% at 50% 0%, color-mix(in srgb, var(--tint) 30%, transparent), transparent 70%)" aria-hidden="true"></span>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <p class="flex items-center gap-2 font-display text-2xl font-bold"><span class="h-3.5 w-3.5 rounded-full ring-2 ring-white/30" style="background: var(--tint)"></span>{{ $sc["sk_{$key}_name"] }}</p>
                        <p class="mt-1.5 max-w-xl text-[15px] text-slate-300">{{ $sc["sk_{$key}_text"] }}</p>
                    </div>
                    <button type="button" x-ref="close" @click="open = null" class="shrink-0 rounded-full border border-white/20 p-2 text-white transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent" aria-label="{{ $sc['close_label'] }}"><x-icon name="x" class="h-5 w-5" /></button>
                </div>
                <div class="relative mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    @foreach (WC::SCREENS as $screen => $screenLabel)
                        @php($label = $sc["screen_{$screen}"] ?? $screenLabel)
                        <div class="{{ $loop->index % 2 === 1 ? 'lg:mt-8' : '' }}">
                            @include('marketing.master-marketing._phone', ['src' => $img($sc["sk_{$key}_{$screen}_image"] ?? ''), 'alt' => $sc["sk_{$key}_name"].': '.$label])
                            <p class="mt-3 text-center text-sm font-semibold text-slate-200">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="relative mt-8 flex items-center justify-between gap-3">
                    <button type="button" @click="step(-1)" class="nx-btn !px-5 !py-2.5 text-sm text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .35)"><x-icon name="arrow-left" class="h-4 w-4" />{{ $sc['prev_label'] }}</button>
                    <button type="button" @click="step(1)" class="nx-btn !px-5 !py-2.5 text-sm text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .35)">{{ $sc['next_label'] }}<x-icon name="arrow-right" class="h-4 w-4" /></button>
                </div>
            </div>
        @endforeach
    </div>
    </template>
</section>
@endif

{{-- Licenses: the full comparison --}}
@if (! empty($cp))
<section id="licenses" class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $cp['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $cp['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $cp['subtext'] }}</p>
        </div>

        <div data-reveal class="mt-12 overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5 dark:border-white/10 dark:bg-[#16233d] dark:shadow-black/30">
            <table class="w-full min-w-[46rem] border-collapse text-left text-sm">
                <thead>
                    <tr class="align-bottom">
                        <th scope="col" class="sticky left-0 z-10 w-[26%] bg-white p-5 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500 dark:bg-[#16233d] dark:text-slate-400">{{ $cp['feature_label'] }}</th>
                        @foreach ($columns as $col)
                            @php($star = $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED)
                            <th scope="col" class="p-5 align-bottom {{ $star ? 'bg-primary/[0.06] dark:bg-accent/[0.08]' : '' }}">
                                @if ($star)<span class="mb-2 inline-block rounded-full bg-accent px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#0D1B2A]">{{ $cp['recommended_label'] }}</span>@endif
                                <span class="block text-lg font-bold text-slate-900 dark:text-white">{{ $col['name'] }}</span>
                                @if ($col['tagline'])<span class="mt-1 block text-xs font-normal leading-snug text-slate-500 dark:text-slate-400">{{ $col['tagline'] }}</span>@endif
                            </th>
                        @endforeach
                    </tr>
                    <tr class="border-y border-slate-200 dark:border-white/10">
                        <th scope="row" class="sticky left-0 z-10 bg-slate-50 p-5 text-sm font-semibold text-slate-700 dark:bg-[#1b2a47] dark:text-slate-200">{{ $cp['price_label'] }}</th>
                        @foreach ($columns as $col)
                            <td class="p-5 {{ $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED ? 'bg-primary/[0.06] dark:bg-accent/[0.08]' : 'bg-slate-50 dark:bg-[#1b2a47]' }}">
                                @if ($col['live'])
                                    <span class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $col['price'] }}</span>
                                @else
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $cp['no_plans'] }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @if ($plans->isNotEmpty())
                        <tr class="border-b border-slate-200 dark:border-white/10">
                            <th scope="row" class="sticky left-0 z-10 bg-white p-5 text-sm font-semibold text-slate-700 dark:bg-[#16233d] dark:text-slate-200">{{ $cp['balance_label'] }}</th>
                            @foreach ($columns as $col)
                                <td class="p-5 text-slate-700 dark:text-slate-200 {{ $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED ? 'bg-primary/[0.06] dark:bg-accent/[0.08]' : '' }}">{{ $col['balance'] === null ? $cp['balance_none'] : '$'.number_format($col['balance']) }}</td>
                            @endforeach
                        </tr>
                    @else
                        <tr class="border-b border-slate-200 dark:border-white/10">
                            <th scope="row" class="sticky left-0 z-10 bg-white p-5 text-sm font-semibold text-slate-700 dark:bg-[#16233d] dark:text-slate-200">{{ $cp['balance_label'] }}</th>
                            <td colspan="{{ count($columns) }}" class="p-5 text-slate-600 dark:text-slate-300">{{ $cp['balance_none'] }}</td>
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @foreach (range(1, 13) as $n)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-white/5">
                            <th scope="row" class="sticky left-0 z-10 bg-white p-4 pl-5 text-[13.5px] font-medium leading-snug text-slate-800 dark:bg-[#16233d] dark:text-slate-100">{{ $cp["r{$n}_label"] }}</th>
                            @foreach ($columns as $col)
                                @php($v = trim((string) ($cp["r{$n}_{$col['key']}"] ?? '')))
                                <td class="p-4 {{ $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED ? 'bg-primary/[0.06] dark:bg-accent/[0.08]' : '' }}">
                                    @if ($v === 'Yes')
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3.5 w-3.5" /><span class="sr-only">{{ $cp['yes_label'] }}</span></span>
                                    @elseif ($v === 'No')
                                        <span class="inline-block h-0.5 w-4 rounded bg-slate-300 align-middle dark:bg-white/20"><span class="sr-only">{{ $cp['no_label'] }}</span></span>
                                    @else
                                        <span class="text-[13px] font-medium text-slate-700 dark:text-slate-200">{{ $v }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 dark:border-white/10">
                        <td class="sticky left-0 bg-white p-5 dark:bg-[#16233d]"></td>
                        @foreach ($columns as $col)
                            <td class="p-5 {{ $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED ? 'bg-primary/[0.06] dark:bg-accent/[0.08]' : '' }}">
                                <a href="{{ $funnelOn ? route('wl-funnel.start', ['plan' => $col['key']]) : $buyUrl }}" class="nx-btn {{ $col['key'] === WhiteLabelLicensePlan::KEY_EXTENDED ? 'nx-btn--primary' : '' }} !px-4 !py-2 text-[13px]" @if ($col['key'] !== WhiteLabelLicensePlan::KEY_EXTENDED) style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)" @endif>{{ $cp['cta_label'] }}</a>
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-5 flex items-center justify-center gap-5 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3 w-3" /></span>{{ $cp['yes_label'] }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 rounded bg-slate-300 dark:bg-white/20"></span>{{ $cp['no_label'] }}</span>
        </p>
        <p data-reveal class="mx-auto mt-6 max-w-3xl text-center text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $cp['note'] }}</p>
    </div>
</section>
@endif

{{-- Handing you the keys --}}
@if (! empty($ho))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $ho['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $ho['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $ho['text_1'] }}</p>
            <p data-reveal class="mt-3 leading-relaxed text-slate-600 dark:text-slate-300">{{ $ho['text_2'] }}</p>
        </div>
        @if ($img($ho['shot_image'] ?? ''))
            <figure data-reveal class="mx-auto mt-12 max-w-4xl overflow-hidden rounded-3xl shadow-2xl shadow-slate-900/15 ring-1 ring-black/5 dark:shadow-black/50 dark:ring-white/10">
                <img src="{{ $img($ho['shot_image']) }}" alt="{{ $ho['image_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
            </figure>
        @endif
        <div class="mx-auto mt-10 grid max-w-4xl gap-5 md:grid-cols-2">
            <div data-reveal class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                <h3 class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white"><x-icon name="key" class="h-5 w-5 text-primary dark:text-accent" />{{ $ho['a_title'] }}</h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ([1, 2, 3, 4] as $n)
                        <li class="flex gap-3 text-[14.5px] text-slate-700 dark:text-slate-200"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="check" class="h-3 w-3" /></span>{{ $ho["a{$n}"] }}</li>
                    @endforeach
                </ul>
            </div>
            <div data-reveal class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
                <h3 class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white"><x-icon name="headset" class="h-5 w-5 text-primary dark:text-accent" />{{ $ho['b_title'] }}</h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ([1, 2, 3, 4] as $n)
                        <li class="flex gap-3 text-[14.5px] text-slate-700 dark:text-slate-200"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#F6E4A8] text-[#5A4100] dark:bg-accent/20 dark:text-accent"><x-icon name="arrow-up-right" class="h-3 w-3" /></span>{{ $ho["b{$n}"] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <p data-reveal class="mx-auto mt-6 max-w-3xl text-center text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $ho['help_note'] }}</p>
    </div>
</section>
@endif

{{-- Agency customisation --}}
@if (! empty($ag))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $ag['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $ag['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-300">{{ $ag['subtext'] }}</p>
        </div>
        <div data-reveal class="mx-auto mt-10 max-w-3xl rounded-3xl border border-accent/30 bg-gradient-to-br from-accent/15 to-white/5 p-8 text-center backdrop-blur">
            <div class="flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1">
                <span class="text-sm font-semibold uppercase tracking-widest text-slate-300">{{ $ag['from_label'] }}</span>
                <span class="font-display text-4xl font-bold text-accent sm:text-5xl">{{ $ag['from_value'] }}</span>
                <span class="text-sm font-semibold uppercase tracking-widest text-slate-300">{{ $ag['to_label'] }}</span>
                <span class="font-display text-4xl font-bold text-accent sm:text-5xl">{{ $ag['to_value'] }}</span>
            </div>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-slate-300">{{ $ag['range_note'] }}</p>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['sparkles', 1], ['arrow-right-left', 2], ['palette', 3], ['layers', 4]] as [$icon, $n])
                <div data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/15 text-accent"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-3 font-semibold">{{ $ag["a{$n}_title"] }}</h3>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-slate-300">{{ $ag["a{$n}_text"] }}</p>
                </div>
            @endforeach
        </div>
        <div data-reveal class="mt-8 text-center">
            <a href="{{ $ag['cta_href'] }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $ag['cta'] }}</a>
        </div>
    </div>
</section>
@endif

{{-- The honest disclaimer --}}
@if (! empty($dc))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 -mt-8 dark:bg-[#0D1B2A]" data-bg="light">
    <div data-reveal class="mx-auto max-w-3xl rounded-3xl border border-[#E3C25A] bg-[#FFF6DB] p-8 sm:p-10 dark:border-accent/40 dark:bg-accent/10">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $dc['eyebrow'] }}</p>
        <h2 class="mt-3 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $dc['headline'] }}</h2>
        <p class="mt-4 leading-relaxed text-slate-700 dark:text-slate-200">{{ $dc['text_1'] }}</p>
        <p class="mt-3 leading-relaxed text-slate-700 dark:text-slate-200">{{ $dc['text_2'] }}</p>
        <p class="mt-3 leading-relaxed text-slate-700 dark:text-slate-200">{{ $dc['text_3'] }}</p>
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
            <a href="{{ $buyUrl }}" class="nx-btn !bg-white !px-8 !py-3 !text-primary hover:!bg-slate-100">{{ $ct['primary'] }}</a>
            <a href="{{ $ct['secondary_href'] }}" class="nx-btn !px-8 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .5)">{{ $ct['secondary'] }}</a>
        </div>
    </div>
</section>
@endif
</x-layouts.marketing>
