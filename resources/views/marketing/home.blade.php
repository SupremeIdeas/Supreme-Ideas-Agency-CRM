{{-- A referral link is the homepage itself with a ?ref= query string
     (App\Livewire\Referrals::mount()), so the referral-specific preview image
     only applies here, gated on that query param — every other visit keeps
     the site-wide default. --}}
<x-layouts.marketing :og-image="request()->filled('ref') ? \App\Support\LinkPreviewSettings::resolve('referral') : null">
    {{-- Per-theme custom landing page (owner request, 2026-09-07): a theme
         with its OWN hand-built landing layout (see LandingHeroLibrary)
         takes over the whole homepage content area — 'default' (every
         theme unless an admin deliberately assigns one) leaves the
         Section Builder / SiteContent flow below completely untouched. --}}
    @php($themeLandingStyle = \App\Support\ThemePreset::sectionStyle('landing_hero'))
    @if ($themeLandingStyle !== 'default' && \App\Support\LandingHeroLibrary::has($themeLandingStyle))
        @include(\App\Support\LandingHeroLibrary::bladeFor($themeLandingStyle), ['content' => \App\Support\ThemePreset::landingContent()])
    @else
    {{-- Section Builder: if an admin has published built sections for this page,
         render them through the shared renderer. Empty = fall back to the
         existing content below, so an untouched page is unchanged (BUILD-6 §B). --}}
    @php($builtSections = \App\Support\PageSections::live('home'))
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @foreach ($sections as $key => $s)
        {{-- "What Naara Gives You" moved to its own /products page (owner
             request, 2026-09-21: "this section in home please move it to
             another page") — content/admin-editing stays on home.products,
             it just no longer renders here. --}}
        @continue($key === 'products')

        {{-- Messaging audit (owner request, 2026-09-21: "nothing should
             confuse them... move some certain sections... blend well"):
             this "Who Naara Is For" audience-tabs section duplicated the
             hero's own persona-tabs (_who-for, added the same day) with an
             overlapping-but-different persona list right on the same page —
             confusing for a first-time visitor. 'audiences' is the
             platform's one registered PORTABLE_SECTIONS entry, so nothing is
             lost: an admin can still add it back here or to any other page
             from Admin → Pages' portable-section picker if they want it. --}}
        @continue($key === 'audiences')

        {{-- Page-specific partial wins; a portable/reused section falls back to
             the shared `marketing.sections.*` partial so it renders anywhere.
             Phase C: an admin-set top corner radius wraps the section in a
             rounded "sheet" pulled up over the previous section, so a seam
             between two different backgrounds is a curve, not a flat line. --}}
        @php($radiusTop = max(0, min(80, (int) ($s['radius_top'] ?? 30))))
        {{-- overflow:clip (not hidden) so the hero/how sticky stacks keep working. --}}
        @php($auroraKeys = ['how', 'features', 'wizard', 'blogs', 'faq', 'cta', 'pricing'])
        <div class="relative {{ in_array($key, $auroraKeys, true) ? 'nx-ambient nx-ambient--rich' : '' }}" style="border-top-left-radius:{{ $radiusTop }}px;border-top-right-radius:{{ $radiusTop }}px;overflow:clip;margin-top:-{{ $loop->first ? 0 : $radiusTop }}px">
        @includeFirst(['marketing.home.'.$key, 'marketing.sections.'.$key], ['s' => $s])
        </div>

        {{-- Decorative, non-CMS sections injected at fixed anchors so the admin's
             section ordering stays intact. --}}
        @if ($key === 'hero')
            @include('marketing.home._flags')
        @endif
        {{-- Manifesto scroll-word-reveal (owner request, 2026-09-21) is now a
             Site-Editor section ('manifesto'); the homepage video (BUILD-3 §8)
             then story (§9) follow it, both admin-editable and self-hiding. --}}
        {{-- $sections only holds VISIBLE sections, so hiding the manifesto in the
             editor re-anchors video/story to the hero instead of dropping them. --}}
        @if ($key === (isset($sections['manifesto']) ? 'manifesto' : 'hero'))
            @include('marketing.home._video')
            @if (class_exists(\App\Models\MasterMarketingVideoSection::class))
                @include('marketing.master-marketing._video-slot', ['slot' => 'home.intro'])
            @endif
            @include('marketing.home._story')
        @endif
        {{-- 'wizard' (after how) and 'blogs' (after testimonials) are real
             Site-Editor sections now — see SiteContent::defaults(). --}}
    @endforeach
    @endif
    @endif

    {{-- Always the last section on the homepage, regardless of which content
         path above is active (theme landing page, Section Builder, or the
         legacy CMS loop) — see the partial's own docblock. --}}
    @include('marketing.home._banner_carousel')
</x-layouts.marketing>
