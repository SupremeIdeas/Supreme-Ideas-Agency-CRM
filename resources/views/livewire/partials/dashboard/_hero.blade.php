{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php($heroLight = \App\Support\HeroBackground::light())
@php($heroDark = \App\Support\HeroBackground::dark())
@php($hasHero = \App\Support\HeroBackground::showsOnDashboard())
@php($heroDesc = \App\Support\HeroBackground::description())
{{-- Per-theme home hero (owner request): each theme carries its own hero image.
     An admin-uploaded HeroBackground still wins; otherwise the active theme's
     hero shows. --}}
@php($themeHero = \App\Support\ThemePreset::heroFor('dashboard'))
@php($heroImg = ($hasHero ? ($heroLight ?: $heroDark) : null) ?: $themeHero)
@php($heroImgDark = ($hasHero && $heroDark) ? $heroDark : $themeHero)
{{-- Admin-overridable + resizable headline (owner request). Defaults to the
     shipped "My Connectivity" at the shipped size, so an untouched install
     renders byte-identical to before. --}}
@php($heroTitleFirst = \App\Support\HeroBackground::titleFirstWord())
@php($heroTitleRest = \App\Support\HeroBackground::titleRestWords())
{{-- Literal classes here (not composed in the PHP support class) so Tailwind's
     content scanner — which only reads resources/**/*.blade.php — actually sees
     them; a string built only inside app/**/*.php would be silently purged. --}}
@php($heroTitleSizeClasses = match (\App\Support\HeroBackground::titleSize()) {
    'sm' => 'text-[2rem] sm:text-[2.5rem]',
    'lg' => 'text-[2.75rem] sm:text-[3.75rem]',
    'xl' => 'text-[3rem] sm:text-[4.25rem]',
    default => 'text-[2.5rem] sm:text-[3.25rem]', // 'md' — the shipped default
})
{{-- Admin-overridable CTA button size (owner request). 'md' matches the
     shipped size exactly, so an untouched install renders byte-identical. --}}
@php($heroCtaPillClasses = match (\App\Support\HeroBackground::ctaSize()) {
    'sm' => 'ns-cta--sm',
    'lg' => 'ns-cta--lg',
    default => '', // 'md': the shipped size
})

{{--
    Dashboard home hero (reference-matched). Text sits LEFT; the photo bleeds into
    the top-right corner with NO card and NO border, dissolving into the page
    background on its left + bottom edges via a mask (faint over the light bg, a
    deeper fade over dark). Two pill CTAs below. When no image is set it degrades
    cleanly to the headline + pills with no empty gap.
--}}
<section class="nx-home-hero mb-6">
    @if ($heroImg)
        <div class="nx-home-hero__media" aria-hidden="true">
            <img src="{{ $heroImg }}" alt="" loading="eager" decoding="async"
                 @if ($themeHero && $themeHero !== $heroImg) data-fallback="{{ $themeHero }}" onerror="if(this.dataset.fallback){this.src=this.dataset.fallback;this.removeAttribute('data-fallback');}" @endif
{{-- nx:allow:start (light/dark artwork swap) --}}
                 class="nx-home-hero__img {{ ($heroImgDark && $heroImgDark !== $heroImg) ? 'dark:hidden' : '' }}">
{{-- nx:allow:end --}}
            @if ($heroImgDark && $heroImgDark !== $heroImg)
                <img src="{{ $heroImgDark }}" alt="" loading="eager" decoding="async"
                     @if ($themeHero && $themeHero !== $heroImgDark) data-fallback="{{ $themeHero }}" onerror="if(this.dataset.fallback){this.src=this.dataset.fallback;this.removeAttribute('data-fallback');}" @endif
{{-- nx:allow:start (light/dark artwork swap) --}}
                     class="nx-home-hero__img hidden dark:block">
{{-- nx:allow:end --}}
            @endif
        </div>
    @endif

    <div class="relative z-10 max-w-[60%] pt-0.5 sm:max-w-[56%]">
        <h1 class="font-display {{ $heroTitleSizeClasses }} font-extrabold leading-[1.07] tracking-[-0.025em]" style="color:rgb(var(--nx-text))">
            {{-- from-primary to-accent (not a hardcoded teal) so the gradient
                 repaints with the active theme — same --brand-* vars every
                 other themed gradient on the platform already uses. --}}
            @if ($heroTitleRest !== '')
                {{ $heroTitleFirst }}<br>
                <span class="ns-gradtext bg-gradient-to-r from-[rgb(var(--nx-cta-b))] to-[rgb(var(--nx-gold))] bg-clip-text text-transparent">{{ $heroTitleRest }}</span>
            @else
                <span class="ns-gradtext bg-gradient-to-r from-[rgb(var(--nx-cta-b))] to-[rgb(var(--nx-gold))] bg-clip-text text-transparent">{{ $heroTitleFirst }}</span>
            @endif
        </h1>
        <p class="mt-3.5 max-w-[16rem] text-[15px] leading-relaxed sm:text-base" style="color:rgb(var(--nx-text-2))">{{ $heroDesc }}</p>
    </div>

    {{-- CTA row sits OUTSIDE the headline's narrow max-w column (owner
         report: the two pills were stacking on mid-width viewports because
         they were squeezed into the same 56-60% column reserved for the
         bleeding photo). The photo's own mask already fades out well above
         this row ("clearing the CTA row" — see .nx-home-hero__img above), so
         giving the buttons the FULL hero width here doesn't collide with it.
         shrink-0 + whitespace-nowrap on each pill still stops the label text
         itself wrapping mid-word. --}}
    <div class="relative z-10 mt-7 flex flex-wrap items-center gap-2 sm:gap-2.5">
        <a href="{{ route('catalogue') }}" wire:navigate class="ns-cta ns-cta--pill {{ $heroCtaPillClasses }}">
            <x-nx.icon name="cards" /> Buy eSIM <x-nx.icon name="chevron-right" class="hidden sm:inline-block" />
        </a>
        <a href="{{ route('numbers') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--ghost {{ $heroCtaPillClasses }}">
            <x-nx.icon name="hash" style="color:rgb(var(--nx-teal-ink))" /> Get Number <x-nx.icon name="chevron-right" class="hidden sm:inline-block" />
        </a>
    </div>
</section>
