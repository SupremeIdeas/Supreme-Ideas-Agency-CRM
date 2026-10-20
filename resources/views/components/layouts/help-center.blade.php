{{-- Help Center shell (Naara Care overhaul): a deliberately SEPARATE chrome
     from the main app shell — its own sticky header (Naara wordmark, mimics
     the Numbers section's sticky-bar pattern) and its own floating bottom
     nav (Home / Chat / Help), so /help reads as one calm hub whether a
     visitor is signed in or not. Works for guests too — only the live chat
     thread itself (/support) requires signing in. --}}
@props(['title' => null])
@php
    // Same "More" destinations the main dashboard and the Numbers section
    // header both open — real parity, not a look-alike button that goes
    // nowhere. Guests have no dashboard nav to show, so the button itself
    // is auth-only below.
    $moreItems = auth()->check() ? \App\Support\BottomNav::resolveMain(auth()->user())['more'] : [];
@endphp
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name().' Help Center'" page-type="marketing" :skin="auth()->check()">
    <div x-data="{ faqOpen: false, moreOpen: false, moreLayout: localStorage.getItem('nx_more_layout') === 'list' ? 'list' : 'grid' }"
         x-effect="localStorage.setItem('nx_more_layout', moreLayout)">
    {{-- nx:allow:start (Nia Help Center keeps its own page background and glow artwork; everything on top of it follows the skin) --}}
    <div class="relative min-h-screen overflow-x-hidden bg-[#F4FAFA] pb-28 dark:bg-navy">
{{-- nx:allow:end --}}
        {{-- The rainbow-glow background element (owner-supplied asset), arcing
             behind the header exactly like the reference design. Two real
             images, not one dimmed with opacity: the dark variant is a
             genuinely re-graded (navy-tinted, tamed highlights) export of
             the same photo (scratchpad/dark-img/make_dark_rgba.py) so its
             bright/white glow melts into the dark page instead of just
             looking washed out at low opacity — same treatment as the
             dedicated FAQ page's hero, for a consistent feel switching
             between the two. --}}
        <img src="{{ asset('images/help-center/help-center-bg.webp') }}" alt=""
             class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[26rem] w-full object-cover opacity-80 dark:hidden" aria-hidden="true">
        <img src="{{ asset('images/help-center/help-center-bg-dark.webp') }}" alt=""
             class="pointer-events-none absolute inset-x-0 top-0 -z-0 hidden h-[26rem] w-full object-cover opacity-90 dark:block" aria-hidden="true">

        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <a href="{{ route('help.center') }}" wire:navigate class="flex items-center gap-2">
                <x-brand-logo variant="family" size="sm" />
            </a>
            <div class="flex items-center gap-2">
                @auth
                    <livewire:notification-center :key="'nc-help'" />
                    <x-lang-switcher />
                    <x-theme-toggle />
                    <span class="hidden items-center gap-2 rounded-full border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.85)] px-2 py-1 pr-3 text-sm font-medium text-[rgb(var(--nx-text))] sm:flex">
                        <x-avatar :src="auth()->user()->avatar" :name="auth()->user()->name" size="h-7 w-7" />
                        {{ auth()->user()->name }}
                    </span>
                    {{-- "More" — the same global sheet the Numbers section
                         header opens, so this dedicated shell still gives a
                         signed-in visitor the full app nav, not a dead end. --}}
                    <button type="button" @click="moreOpen = true" aria-label="More"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))] transition hover:bg-[rgb(var(--nx-surface-2))]">
                        <x-icon name="grid" class="h-4 w-4" />
                    </button>
                @else
                    <x-lang-switcher />
                    <x-theme-toggle />
                    <a href="{{ route('login') }}" wire:navigate class="rounded-full px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]">Sign in</a>
                    <a href="{{ route('register') }}" wire:navigate class="nx-btn nx-btn--primary !py-2 !px-4 text-sm">Get Started</a>
                @endauth
            </div>
        </header>

        <main class="relative z-10 mx-auto w-full max-w-3xl px-4 pt-2 sm:px-6">
            {{ $slot }}
        </main>
    </div>

    {{-- Help Center's own bottom nav — Home / Chat / Help. Deliberately NOT
         the global app bottom nav: this hub is reachable signed-out too, and
         "Help" here opens the FAQ in place rather than navigating away. --}}
    <nav class="nx-bottomnav ns-ring fixed inset-x-3 bottom-3 z-40 rounded-[1.75rem] border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] shadow-[0_10px_40px_rgba(13,27,42,0.16)] dark:shadow-[0_10px_40px_rgba(0,0,0,0.5)]"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="mx-auto grid max-w-md grid-cols-3 items-center px-2 py-1">
            @php($hcOn = request()->routeIs('help.center'))
            <a href="{{ route('help.center') }}" wire:navigate @if ($hcOn) aria-current="page" @endif
               @class(['nx-tab flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition', 'is-on ns-cta' => $hcOn, 'text-[rgb(var(--nx-text-2))]' => ! $hcOn])>
                <x-icon name="signal" class="h-5 w-5" :gradient="! $hcOn" />
                Home
            </a>
            @php($chOn = request()->routeIs('support'))
            <a href="{{ auth()->check() ? route('support') : route('login') }}" wire:navigate @if ($chOn) aria-current="page" @endif
               @class(['nx-tab flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition', 'is-on ns-cta' => $chOn, 'text-[rgb(var(--nx-text-2))]' => ! $chOn])>
                <x-icon name="send" class="h-5 w-5" :gradient="! $chOn" />
                Chat
            </a>
            <button type="button" @click="faqOpen = true"
                    class="nx-tab flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-[rgb(var(--nx-text-2))]">
                <x-icon name="help-circle" class="h-5 w-5" />
                Help
            </button>
        </div>
    </nav>

    {{-- FAQ modal — reuses the exact marketing FAQ content (owner requirement:
         "as-is"), no second copy of the accordion to drift out of sync. --}}
    <div x-cloak x-show="faqOpen" class="fixed inset-0 z-[79]" style="display:none;">
        <div x-show="faqOpen" x-transition.opacity @click="faqOpen = false" class="absolute inset-0 bg-navy/50"></div>
        <div x-show="faqOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="absolute inset-x-3 bottom-3 top-10 overflow-y-auto rounded-3xl bg-[rgb(var(--nx-surface))] p-5 shadow-2xl sm:inset-x-auto sm:left-1/2 sm:top-1/2 sm:h-[85vh] sm:w-full sm:max-w-2xl sm:-translate-x-1/2 sm:-translate-y-1/2">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-lg font-bold text-[rgb(var(--nx-text))]">Frequently asked questions</h2>
                <button type="button" @click="faqOpen = false" aria-label="Close" class="rounded-lg p-1 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            @include('marketing.home.faq', ['s' => \App\Support\SiteContent::page('home')['faq'] ?? []])
        </div>
    </div>

    @auth
        <x-more-sheet :more="$moreItems" :promo="false" />
    @endauth
    </div>
</x-layouts.app>
