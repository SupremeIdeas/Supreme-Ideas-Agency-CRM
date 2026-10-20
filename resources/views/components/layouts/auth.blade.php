@props(['title' => null, 'heading' => null, 'subheading' => null, 'preloaderType' => 'auth', 'footerArt' => false])

{{-- Two-column auth shell (Module 28). Desktop: an admin-set media panel
     (WebP/JPEG image or a short muted video) on the left, the form on the
     right; the panel collapses on mobile to a compact branded header. Every
     auth page ends with the assignable footer (Supreme Ideas Agency + legal
     links). Dark mode + reduced-motion throughout. --}}
@php($panel = \App\Support\SiteChrome::authPanel())
@php($authStyle = $panel['style'] ?? 'auto')
@php($useMedia = \App\Support\SiteChrome::hasAuthMedia() && $authStyle !== 'webgl')
@php($useWebgl = $authStyle === 'webgl' || ($authStyle === 'auto' && ! \App\Support\SiteChrome::hasAuthMedia()))
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name()" :page-type="$preloaderType">
    {{-- Swappable LOGIN section (owner request, 2026-09-07): admin can point
         this at any built style family via ThemePreset::sectionStyle('login')
         — 'default' (below) is the original, unmodified markup, so every
         existing theme keeps rendering today's exact login screen. --}}
    {{-- Language shortcut for guests (owner request): a small floating chip, independent of whichever login style family renders below. --}}
    <div class="fixed right-3 top-3 z-[60]"><x-lang-switcher class="shadow-sm backdrop-blur" /></div>
    {{-- $footerArt (set by the master sign-in pages) is read by each login style: the illustration sits under the login card, before the footer links. --}}
    @includeIf('components.master-marketing.auth-art-style')
    {{-- data-glow-fields: the fields in every sign-in form glow while typed in (resources/css/field-glow.css). `contents` keeps the layout untouched. --}}
    <div data-glow-fields class="contents">
        @include('components.layouts.theme-sections.login.'.\App\Support\ThemePreset::sectionStyle('login'))
    </div>
</x-layouts.app>
