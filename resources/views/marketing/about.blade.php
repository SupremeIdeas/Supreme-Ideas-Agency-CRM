<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — About Us'">
    {{-- Per-theme custom About page (owner request, 2026-09-07): a theme
         with its own hand-built About layout takes over completely,
         before the Section Builder / SiteContent flow below ever runs. --}}
    @php
        $themeAboutStyle = \App\Support\ThemePreset::sectionStyle('about_page');
    @endphp
    @if ($themeAboutStyle !== 'default' && \App\Support\ThemePageLibrary::has('about_page', $themeAboutStyle))
        @include(\App\Support\ThemePageLibrary::bladeFor('about_page', $themeAboutStyle), ['content' => \App\Support\ThemePreset::pageContent('about_page')])
    @else
    {{-- Section Builder output wins when published; else the existing content (BUILD-6 §B). --}}
    @php
        $builtSections = \App\Support\PageSections::live('about');
    @endphp
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @php
        $hero = $sections['hero'] ?? null;
    @endphp
    @if ($hero)
        @if (! empty($hero['image']))
            {{-- Admin override: a single supplied image replaces the whole
                 hero treatment below with a simple centred banner. --}}
            <section class="relative overflow-hidden">
                <div class="relative z-10 mx-auto max-w-4xl px-4 pb-16 pt-20 text-center sm:pt-24">
                    <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $hero['eyebrow'] }}</p>
                    <h1 data-reveal class="mt-4 text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
                    <p data-reveal class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
                    <img data-reveal src="{{ $hero['image'] }}" alt="" class="mx-auto mt-10 w-full max-w-3xl rounded-3xl shadow-2xl">
                </div>
            </section>
        @else
            {{-- About-hero expansion (owner request, 2026-09-21): the
                 owner-supplied AgriTech-reference layout — a premium, rounded
                 light card, left copy + right image with floating badges —
                 adapted with real Naara "Our Story" hero copy. Always renders
                 in its own light surface regardless of the site's dark/light
                 mode, per the owner's "premium feel" instruction. --}}
            @php
                $heroImg = \App\Support\SiteContent::imageUrl($hero['hero_image'] ?? '');
            @endphp
            <section class="bg-[#EDF3F2] px-3 pb-10 pt-6 dark:bg-[#0a1420] sm:px-4">
                <div class="mx-auto grid max-w-6xl gap-0 overflow-hidden rounded-[32px] bg-white shadow-[0_30px_80px_-20px_rgba(13,27,42,0.18)] lg:grid-cols-2">
                    <div class="flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-14 lg:py-16">
                        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark">{{ $hero['eyebrow'] }}</p>
                        <h1 data-reveal style="--reveal-delay:.08s" class="mt-4 text-4xl font-bold leading-tight text-slate-900 sm:text-5xl">{{ $hero['headline'] }}</h1>
                        <p data-reveal style="--reveal-delay:.16s" class="mt-5 max-w-md text-base leading-relaxed text-slate-600">{{ $hero['subtext'] }}</p>

                        <div data-reveal style="--reveal-delay:.24s" class="mt-8 flex flex-wrap items-center gap-3">
                            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="nx-btn nx-btn--primary">{{ $hero['cta_primary'] }}</a>
                            <a href="{{ route('faq') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary">
                                {{ $hero['cta_secondary'] }} <x-icon name="arrow-up-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>

                        <dl data-reveal style="--reveal-delay:.32s" class="mt-10 grid grid-cols-2 gap-4 border-t border-slate-100 pt-8 sm:grid-cols-4">
                            @foreach (['stat_1', 'stat_2', 'stat_3', 'stat_4'] as $stat)
                                @php
                                    $statParts = array_pad(explode(' ', $hero[$stat], 2), 2, '');
                                    $value = $statParts[0];
                                    $label = $statParts[1];
                                @endphp
                                <div>
                                    <dd class="font-display text-xl font-bold text-primary">{{ $value }}</dd>
                                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="relative min-h-[320px] overflow-hidden lg:min-h-[560px]">
                        @if ($heroImg)
                            <img src="{{ $heroImg }}" alt="{{ $hero['headline'] }}" loading="eager" fetchpriority="high"
                                 class="absolute inset-0 h-full w-full object-cover">
                        @else
                            <img src="{{ asset('images/about/about-hero.webp') }}" alt="{{ $hero['headline'] }}" loading="eager" fetchpriority="high"
                                 class="absolute inset-0 h-full w-full object-cover">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-navy/40 via-transparent to-transparent"></div>

                        {{-- Floating "join community" pill — reuses the same
                             owner-supplied avatar cluster as the homepage hero. --}}
                        <div data-reveal style="--reveal-delay:.2s" class="absolute left-4 top-4 flex items-center gap-2 rounded-full bg-white/90 py-1.5 pl-1.5 pr-4 shadow-lg backdrop-blur sm:left-6 sm:top-6">
                            <img src="{{ asset('images/home/hero-avatar-cluster.webp') }}" alt="" class="h-7 w-auto object-contain">
                            <span class="text-xs font-semibold text-slate-800">{{ $hero['badge_label'] }}</span>
                        </div>

                        {{-- Floating stat card. --}}
                        <div data-reveal style="--reveal-delay:.28s" class="absolute bottom-4 left-4 right-4 flex items-center justify-between rounded-2xl bg-white/95 p-4 shadow-xl backdrop-blur sm:bottom-6 sm:left-6 sm:right-auto sm:w-64">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">{{ $hero['stat_card_label'] ?? '' }}</p>
                                <p class="font-display text-2xl font-bold text-primary">{{ $hero['stat_card_value'] ?? '' }}</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <x-icon name="globe" class="h-5 w-5" />
                            </span>
                        </div>

                        {{-- A real screen from the app, floating over the photo. --}}
                        @if (! empty($hero['phone_image']))
                            <div data-reveal style="--reveal-delay:.32s" class="absolute bottom-4 right-4 hidden w-28 rotate-[5deg] overflow-hidden rounded-[1.6rem] border-[5px] border-slate-900 bg-slate-900 shadow-2xl shadow-slate-900/40 sm:bottom-6 sm:right-6 sm:block sm:w-32 lg:w-36">
                                <img src="{{ \App\Support\SiteContent::imageUrl($hero['phone_image']) }}" alt="{{ $hero['phone_alt'] ?? '' }}" width="480" height="1039" loading="lazy" decoding="async" class="block aspect-[360/780] w-full object-cover object-top">
                            </div>
                        @endif

                        {{-- Vertical accent badge (gold, "No Roaming" — the
                             reference's own "Water Savings" pattern). --}}
                        <div data-reveal style="--reveal-delay:.36s" class="absolute right-4 top-4 hidden flex-col items-center gap-2 rounded-full bg-accent px-2 py-4 text-navy shadow-lg sm:right-6 sm:top-6 sm:flex">
                            <x-icon name="shield-check" class="h-4 w-4" />
                            <span class="text-[10px] font-bold uppercase tracking-wider" style="writing-mode: vertical-rl;">No Roaming</span>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif

    @php
        $story = $sections['story'] ?? null;
    @endphp
    @if ($story)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-3xl px-4 py-14">
            <h2 data-reveal class="text-2xl font-bold text-slate-900 dark:text-white">{{ $story['headline'] }}</h2>
            <div class="mt-6 space-y-5 leading-relaxed text-slate-600 dark:text-slate-300">
                @foreach (['p1', 'p2', 'p3', 'p4'] as $p)
                    <p data-reveal>{{ $story[$p] }}</p>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $infra = $sections['infrastructure'] ?? null;
        $tour = $sections['tour'] ?? null;
    @endphp
    @includeIf('marketing.master-marketing._about-infrastructure', ['q' => $infra])
    @if ($tour)
        <section class="nx-ambient nx-ambient--rich relative overflow-hidden bg-[#F4FAFA] px-4 py-20 dark:bg-[#0F2033] {{ $infra ? 'rounded-t-[30px] -mt-8' : '' }}" data-bg="light">
            <div class="mx-auto max-w-6xl">
                <div class="mx-auto max-w-3xl text-center">
                    <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $tour['eyebrow'] }}</p>
                    <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $tour['headline'] }}</h2>
                    <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $tour['subtext'] }}</p>
                </div>
                <div class="mt-14 grid items-center gap-12 lg:grid-cols-[.9fr_1.1fr]">
                    <ul class="space-y-5">
                        @foreach ([1 => 'zap', 2 => 'wallet', 3 => 'hash', 4 => 'palette'] as $n => $icon)
                            <li data-reveal style="--reveal-delay: {{ $n * 0.05 }}s" class="flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$icon" class="h-5 w-5" /></span>
                                <div>
                                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ $tour["t{$n}_title"] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $tour["t{$n}_text"] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="relative pb-10">
                        @if (\App\Support\SiteContent::imageUrl($tour['desk_image'] ?? ''))
                            <figure data-reveal class="overflow-hidden rounded-3xl shadow-2xl shadow-slate-900/15 ring-1 ring-black/5 dark:shadow-black/50 dark:ring-white/10">
                                <img src="{{ \App\Support\SiteContent::imageUrl($tour['desk_image']) }}" alt="{{ $tour['desk_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
                            </figure>
                        @endif
                        <div class="absolute -bottom-2 left-2 right-2 flex items-end justify-center gap-3 sm:left-6 sm:right-6 sm:gap-5" role="img" aria-label="{{ $tour['phones_alt'] }}">
                            @foreach ([1 => '-rotate-[5deg] translate-y-3', 2 => 'z-10 scale-110', 3 => 'rotate-[5deg] translate-y-3'] as $n => $cls)
                                @if (\App\Support\SiteContent::imageUrl($tour["p{$n}_image"] ?? ''))
                                    <div data-reveal style="--reveal-delay: {{ $n * 0.08 }}s" class="w-[27%] max-w-[9.5rem] {{ $cls }} overflow-hidden rounded-[1.4rem] border-[4px] border-slate-900 bg-slate-900 shadow-2xl shadow-slate-900/40 dark:border-slate-600">
                                        <img src="{{ \App\Support\SiteContent::imageUrl($tour["p{$n}_image"]) }}" alt="" width="480" height="1039" loading="lazy" decoding="async" class="block aspect-[360/780] w-full object-cover object-top">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @php
        $mission = $sections['mission'] ?? null;
    @endphp
    @if ($mission)
        {{-- Vision / Mission / Essence via the reusable storytelling carousel
             (BLUEPRINT-batch1-sections §5). Same component as the product lines —
             proof it serves a very different content domain unchanged. Copy is
             the approved SiteContent text verbatim; Essence pairs the "dawn"
             meaning (the story section's own line) with the belief statement.
             Images arrive from the owner later and slot into the same slides
             prop with no markup change. --}}
        @php
            $story = $sections['story'] ?? [];
            $essenceBody = trim(($story['p3'] ?? '').' '.($mission['belief'] ?? ''));
            $aboutSlides = [
                ['eyebrow' => $mission['mission_title'] ?? 'Our Mission', 'title' => $mission['mission_slide_title'] ?? '', 'body' => $mission['mission'] ?? '', 'image' => \App\Support\SiteContent::imageUrl($mission['mission_image'] ?? '')],
                ['eyebrow' => $mission['vision_title'] ?? 'Our Vision', 'title' => $mission['vision_slide_title'] ?? '', 'body' => $mission['vision'] ?? '', 'image' => \App\Support\SiteContent::imageUrl($mission['vision_image'] ?? '')],
                ['eyebrow' => $mission['essence_eyebrow'] ?? '', 'title' => $mission['essence_title'] ?? '', 'body' => $essenceBody, 'image' => \App\Support\SiteContent::imageUrl($mission['essence_image'] ?? '')],
            ];
        @endphp
        <section class="bg-navy px-4 py-20 text-slate-100">
            <div class="mx-auto max-w-5xl">
                <h2 data-reveal class="text-center text-3xl font-bold">{{ $mission['headline'] }}</h2>
                <div data-reveal class="mt-10">
                    <x-storytelling-carousel :slides="$aboutSlides" section-key="about-essence" tone="on-dark" />
                </div>
            </div>
        </section>
    @endif

    @php
        $values = $sections['values'] ?? null;
    @endphp
    @if ($values)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-5xl px-4 py-20">
            <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 dark:text-white">{{ $values['headline'] }}</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach (range(1, 4) as $n)
                    <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.07 }}s" class="nx-card">
                        <h3 class="font-bold text-primary dark:text-teal-300">{{ $values["v{$n}_title"] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $values["v{$n}_text"] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $who = $sections['who'] ?? null;
    @endphp
    @if ($who)
        {{-- "Who We Work With" (owner-supplied About Us expansion). Fixed
             light surface regardless of site theme, per the owner's
             "premium feel" instruction. --}}
        <section class="nx-ambient nx-ambient--rich bg-[#F4FAFA] px-4 py-20">
            <div class="mx-auto max-w-5xl text-center">
                <h2 data-reveal class="text-3xl font-bold text-slate-900 sm:text-4xl">{{ $who['headline'] }}</h2>
                <p data-reveal class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-600">{{ $who['subtext'] }}</p>
            </div>
            <div class="mx-auto mt-12 grid max-w-5xl gap-6 sm:grid-cols-3">
                @foreach ([1, 2, 3] as $n)
                    @php
                        $whoImg = \App\Support\SiteContent::imageUrl($who["w{$n}_image"] ?? '');
                    @endphp
                    <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.08 }}s" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        @if ($whoImg)
                            <img src="{{ $whoImg }}" alt="{{ $who["w{$n}_title"] }}" loading="lazy" decoding="async" class="h-44 w-full object-cover">
                        @endif
                        <div class="p-6 text-left">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-accent-dark">{{ $who["w{$n}_tag"] }}</span>
                            <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $who["w{$n}_title"] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $who["w{$n}_text"] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $industries = $sections['industries'] ?? null;
    @endphp
    @if ($industries)
        {{-- "Wherever a border shows up in your life" — fixed dark surface
             regardless of site theme, matching the reference's own design. --}}
        <section class="bg-navy px-4 py-20 text-slate-100">
            <div class="mx-auto max-w-5xl">
                <h2 data-reveal class="text-center text-3xl font-bold text-white sm:text-4xl">{{ $industries['headline'] }}</h2>
                <div class="mt-12 grid gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (range(1, 8) as $n)
                        <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.05 }}s" class="bg-[#0F2033] p-6">
                            <span class="font-display text-2xl font-bold text-teal-300">{{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="mt-3 font-bold text-white">{{ $industries["i{$n}_name"] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $industries["i{$n}_text"] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @php
        $numbers = $sections['numbers'] ?? null;
    @endphp
    @if ($numbers)
        {{-- "The Naara Promise" tag cloud — the brand's own value-words,
             a lighter alternative to a full physics playground: fixed dark
             surface, real Naara copy, still genuinely playful (hover lift). --}}
        @php
            // Labels are the `numbers` section's tag_1..tag_12 fields (Site Editor);
            // the colour pairs are brand art and stay here.
            $naaraTagColors = [['#0A6E6E', '#fff'], ['#5eead4', '#0D1B2A'], ['#E8412A', '#fff'], ['#fff', '#0D1B2A'], ['#1A2840', '#fff'], ['#D4A017', '#0D1B2A'], ['#0A6E6E', '#fff'], ['rgba(255,255,255,.1)', '#fff'], ['#2dd4bf', '#0D1B2A'], ['#fff', '#0A6E6E'], ['#1A2840', '#5eead4'], ['#E8412A', '#fff']];
            $naaraTags = [];
            foreach ($naaraTagColors as $i => [$bg, $fg]) {
                $label = trim((string) ($numbers['tag_'.($i + 1)] ?? ''));
                if ($label !== '') {
                    $naaraTags[] = [$label, $bg, $fg];
                }
            }
        @endphp
        <section class="bg-[#0F2033] px-4 py-20">
            <div class="mx-auto max-w-4xl text-center">
                <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $numbers['eyebrow'] }}</p>
                <h2 data-reveal class="mt-3 text-2xl font-bold text-white sm:text-3xl">{{ $numbers['headline'] }}</h2>
                <div data-pill-physics data-reveal style="--reveal-delay:.15s" class="nx-pills mt-10 flex flex-wrap items-center justify-center gap-3">
                    @foreach ($naaraTags as [$label, $bg, $fg])
                        <span data-pill class="rounded-full px-5 py-2.5 text-sm font-medium shadow-lg transition hover:shadow-xl" style="background:{{ $bg }}; color:{{ $fg }};">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @php
        $process = $sections['process'] ?? null;
    @endphp
    @if ($process)
        {{-- "Six steps. Zero surprises." — reuses the platform's own
             established sticky-stack pattern (how.blade.php's "Connected in
             Three Steps") rather than a new pinned-scroll mechanic, so the
             site keeps one consistent step-journey treatment. Icon tiles,
             not photos — an abstract mechanic step reads better as an icon
             in a gradient card than as stock photography. --}}
        @php
            $stepIcons = ['user-plus', 'wallet', 'sim', 'qr-code', 'signal', 'headset'];
        @endphp
        <section class="nx-ambient nx-ambient--rich bg-[#F4FAFA] px-4 py-20">
            <div class="mx-auto max-w-3xl text-center">
                <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark">{{ $process['eyebrow'] }}</p>
                <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl">{{ $process['headline'] }}</h2>
                <p data-reveal class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-600">{{ $process['subtext'] }}</p>
            </div>
            @if (! empty($process['journey_image']))
                {{-- The journey as it really looks in the app: wallet, plan, install (scripts/mockups). --}}
                <figure data-reveal class="mx-auto mt-10 max-w-md overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200">
                    <img src="{{ \App\Support\SiteContent::imageUrl($process['journey_image']) }}" alt="Three phones showing the Naara wallet top-up, a data plan ready to buy, and the eSIM install QR code"
                         loading="lazy" decoding="async" width="1080" height="1372" class="h-auto w-full">
                </figure>
            @endif
            <div class="mkt-stack mx-auto mt-14 max-w-2xl">
                @foreach (range(1, 6) as $n)
                    @php
                        $stepImg = \App\Support\SiteContent::imageUrl($process["step_{$n}_image"] ?? '');
                    @endphp
                    <div data-reveal class="mkt-stack__card !p-0 overflow-hidden border border-slate-200 bg-white" style="top: calc(5.5rem + {{ ($n - 1) * 1.25 }}rem)">
                        <div class="flex items-stretch">
                            <div class="flex flex-1 items-start gap-4 p-5 sm:p-7">
                                <span class="{{ $stepImg ? 'hidden sm:flex' : 'flex' }} h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                    <x-icon :name="$stepIcons[$n - 1] ?? 'check'" class="h-5 w-5" />
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-accent-dark">Step {{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h3 class="mt-1 text-lg font-bold text-slate-900">{{ $process["step_{$n}_title"] }}</h3>
                                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $process["step_{$n}_text"] }}</p>
                                </div>
                            </div>
                            @if ($stepImg)
                                {{-- The real screen for this step, in a phone mockup (scripts/mockups). --}}
                                <div class="min-h-[15rem] w-28 shrink-0 overflow-hidden bg-slate-100 sm:w-48">
                                    <img src="{{ $stepImg }}" alt="{{ $process["step_{$n}_alt"] ?? '' }}" loading="lazy" decoding="async" width="720" height="900"
                                         class="h-full w-full object-cover object-center">
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $culture = $sections['culture'] ?? null;
    @endphp
    @if ($culture)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-5xl px-4 py-20">
            <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 dark:text-white">{{ $culture['headline'] }}</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-3">
                @foreach ([1, 2, 3] as $n)
                    <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.08 }}s" class="nx-card">
                        <h3 class="font-bold text-primary dark:text-teal-300">{{ $culture["c{$n}_name"] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $culture["c{$n}_text"] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $team = $sections['team'] ?? null;
    @endphp
    @if ($team)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-3xl px-4 pb-24">
            <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 dark:text-white">{{ $team['headline'] }}</h2>
            <div data-reveal class="nx-card mt-10">
                @if (! empty($team['image']))
                    <img src="{{ $team['image'] }}" alt="{{ $team['founder_name'] }}" class="h-20 w-20 rounded-2xl object-cover">
                @endif
                <h3 class="mt-3 text-xl font-bold text-slate-900 dark:text-white">{{ $team['founder_name'] }}</h3>
                <p class="text-sm font-medium text-accent-dark dark:text-accent">{{ $team['founder_title'] }}</p>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $team['founder_bio'] }}</p>
                <p class="mt-4 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-400 dark:border-[var(--brand-card-border-dark)]">{{ $team['company'] }}</p>
            </div>
        </section>
    @endif

    @php
        $global = $sections['global'] ?? null;
    @endphp
    @if ($global)
        <section class="relative flex min-h-[30rem] items-center overflow-hidden bg-navy px-4 py-24 text-center text-slate-100">
            {{-- min-height gives the orbit's periphery-tuned % coordinates room
                 to clear this section's centered copy — this band is shorter
                 than the hero/login contexts the component was tuned for. --}}
            <x-flag-orbit tone="dark" class="opacity-40" />
            <div class="relative mx-auto max-w-2xl">
                <h2 data-reveal class="text-3xl font-bold text-white sm:text-4xl">{{ $global['headline'] }}</h2>
                <p data-reveal class="mt-4 text-base leading-relaxed text-slate-300">{{ $global['body'] }}</p>
                <a data-reveal href="{{ route('support') }}" wire:navigate class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:underline">
                    {{ $global['cta'] }} <x-icon name="arrow-up-right" class="h-3.5 w-3.5" />
                </a>
            </div>
        </section>
    @endif

    @php
        $closingCta = $sections['closing_cta'] ?? null;
    @endphp
    @if ($closingCta)
        <section class="nx-ambient nx-ambient--rich px-4 pb-32 pt-8" data-bg="light">
            <div data-reveal class="relative mx-auto max-w-4xl overflow-hidden rounded-3xl bg-primary px-6 py-16 text-center text-white shadow-2xl">
                <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-accent/25 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-16 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
                <p class="relative text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $closingCta['label'] }}</p>
                <h2 class="relative mt-3 text-3xl font-bold sm:text-4xl">{{ $closingCta['headline'] }}</h2>
                <p class="relative mx-auto mt-4 max-w-xl leading-relaxed text-teal-50">{{ $closingCta['subtext'] }}</p>
                <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="nx-btn nx-btn--gold !px-8 !py-3">{{ $closingCta['cta_primary'] }}</a>
                    <a href="{{ route('support') }}" wire:navigate class="nx-btn !px-8 !py-3 !text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .6)">{{ $closingCta['cta_secondary'] }}</a>
                </div>
            </div>
        </section>
    @endif
    @include('marketing._reused-sections')
    @endif
    @endif
</x-layouts.marketing>
