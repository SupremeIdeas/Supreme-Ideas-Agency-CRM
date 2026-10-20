<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — How It Works'">
    {{-- Per-theme custom How It Works page (owner request, 2026-09-07): a theme
         with its own hand-built layout takes over completely, before the
         Section Builder / SiteContent flow below ever runs. --}}
    @php
        $themeHowStyle = \App\Support\ThemePreset::sectionStyle('how_it_works_page');
    @endphp
    @if ($themeHowStyle !== 'default' && \App\Support\ThemePageLibrary::has('how_it_works_page', $themeHowStyle))
        @include(\App\Support\ThemePageLibrary::bladeFor('how_it_works_page', $themeHowStyle), ['content' => \App\Support\ThemePreset::pageContent('how_it_works_page')])
    @else
    {{-- Section Builder output wins when published; else the existing content (BUILD-6 §B). --}}
    @php
        $builtSections = \App\Support\PageSections::live('how-it-works');
    @endphp
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @php
        $hero = $sections['hero'] ?? null;
    @endphp
    @if ($hero)
        <section class="relative overflow-hidden">
            @if (empty($hero['image']))
                {{-- Branded WebGL "connected city" backdrop (lazy, bundled,
                     CSP-safe, self-pausing off-screen). Glow orbs are the
                     reduced-motion / no-WebGL fallback. --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <span class="absolute bottom-0 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl dark:bg-primary/25"></span>
                    <span class="absolute right-1/4 top-1/3 h-40 w-40 rounded-full bg-accent/20 blur-3xl"></span>
                    <canvas data-webgl-hero="city"
                            class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
                    <div class="absolute inset-0 bg-gradient-to-b from-[#F8F9FA] via-[#F8F9FA]/45 to-[#F8F9FA] dark:from-[#0D1B2A] dark:via-[#0D1B2A]/20 dark:to-[#0D1B2A]"></div>
                </div>
            @endif
            <div class="relative z-10 mx-auto max-w-4xl px-4 pb-16 pt-20 text-center sm:pt-24">
                <h1 data-reveal class="text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
                <p data-reveal class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
                <p data-reveal class="mt-6 text-xs font-medium text-slate-400 dark:text-slate-400">{{ $hero['proof'] }}</p>
            </div>
        </section>
    @endif

    @php
        $steps = $sections['steps'] ?? null;
        $stepIcons = ['smartphone', 'list', 'credit-card', 'qr-code', 'settings', 'wifi', 'wallet'];
        $stepItems = $steps ? collect(range(1, 7))->map(fn ($n) => [
            'title' => $steps["s{$n}_title"],
            'desc' => $steps["s{$n}_text"],
            'icon' => $stepIcons[$n - 1],
            // A real dashboard screen inside a device mockup (resources/mockups); portrait, so it gets the device card.
            'image' => \App\Support\SiteContent::imageUrl($steps["s{$n}_image"] ?? '') ?: null,
            'device' => true,
        ])->all() : [];
    @endphp
    @if ($steps)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-5xl px-4 py-12" data-bg="light">
            {{-- Owner request (2026-09-21): "reuse that our time based tabs
                 switcher container for how it works section" — replaces the
                 old scroll-timeline rail with the same browse-with-auto-advance
                 mechanic "Who Naara Is For" already uses on the homepage. --}}
            <x-timed-tab-switcher
                :items="$stepItems"
                tone="light"
                id="how-steps"
                heading="{{ $steps['headline'] }}"
            />
        </section>
    @endif

    @php
        $compat = $sections['compatibility'] ?? null;
    @endphp
    @if ($compat)
        <section id="compatibility" class="bg-navy px-4 py-20 text-slate-100">
            <div class="mx-auto max-w-3xl text-center">
                <h2 data-reveal class="text-3xl font-bold">{{ $compat['headline'] }}</h2>
                <p data-reveal class="mt-4 leading-relaxed opacity-90">{{ $compat['intro'] }}</p>
            </div>
            {{-- Owner request (2026-09-21): "any place that has compatibility
                 checker let it work in real time in marketing page so users
                 can check their devices even outside" — this was static copy
                 promising a "compatibility checker" that didn't actually exist
                 here; now the real, DB-backed, searchable checker (already
                 used inside the app's own catalogue) is public too. --}}
            <div class="mx-auto mt-8 max-w-md text-center">
                <button type="button" @click="$dispatch('open-compatibility')" class="nx-btn nx-btn--gold !px-8 !py-3">{{ $compat['cta'] }}</button>
                <p data-reveal class="mt-6 text-sm leading-relaxed opacity-80">{{ $compat['note'] }}</p>
            </div>
            <livewire:esim-compatibility />
        </section>
    @endif

    @php
        $support = $sections['support'] ?? null;
    @endphp
    @if ($support)
        <section class="nx-ambient nx-ambient--rich mx-auto max-w-3xl px-4 pb-32 pt-20 text-center">
            <h2 data-reveal class="text-3xl font-bold text-slate-900 dark:text-white">{{ $support['headline'] }}</h2>
            <p data-reveal class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $support['text'] }}</p>
            <blockquote data-reveal class="mx-auto mt-8 max-w-xl rounded-2xl bg-primary/10 p-5 text-sm font-medium italic text-primary dark:bg-primary/20 dark:text-teal-300">
                {{ $support['promise'] }}
            </blockquote>
            <a data-reveal href="{{ route('contact') }}" class="nx-btn nx-btn--primary mt-8 !px-8 !py-3">{{ $support['cta'] ?? '' }}</a>
        </section>
    @endif
    @include('marketing._reused-sections')
    @endif
    @endif
</x-layouts.marketing>
