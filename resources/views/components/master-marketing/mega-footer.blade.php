{{-- The Naara MEGA footer (MASTER ONLY; a fork keeps the standard footer). Navy, with the water-particle backdrop of the standard footer.
     Layout: call-to-action band -> brand + app links + link groups (accordions on a phone) -> popular eSIM destinations -> legal bar. --}}
@props(['variant' => 'full'])
@php
    $groups = \App\Support\MasterMarketing\MegaFooter::groups();
    $dest = \App\Support\MasterMarketing\MegaFooter::destinations();
    $brand = \App\Support\BrandSettings::name();
    $legal = \App\Support\SiteChrome::footerLegal();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
    $apps = [['android', 'Android'], ['apple', 'iPhone and iPad'], ['windows', 'Windows'], ['apple', 'Mac'], ['linux', 'Linux'], ['chrome', 'Chrome']];
@endphp
<footer {{ $attributes->merge(['class' => 'relative overflow-hidden border-t border-white/10 bg-navy text-slate-300']) }} data-mega-footer>
    @if ($variant === 'full')
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span class="absolute bottom-0 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl"></span>
            <canvas data-webgl-hero="water" class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
            <div class="absolute inset-0 bg-gradient-to-b from-navy via-navy/40 to-navy/80"></div>
        </div>

        {{-- 1. Call to action --}}
        <div class="relative z-10 mx-auto max-w-7xl px-4 pt-14 sm:px-6">
            <div class="flex flex-col gap-6 rounded-[28px] border border-white/10 bg-white/[0.04] p-6 backdrop-blur sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $brand }}</p>
                    <h2 class="mt-2 text-2xl font-bold leading-tight text-white sm:text-3xl">Stay connected. No borders. No swaps.</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">eSIM data for 190+ countries, numbers that keep working, and one wallet for all of it.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if (Route::has('catalogue'))<a href="{{ route('catalogue') }}" class="nx-btn nx-btn--primary !px-6 !py-3">Get an eSIM</a>@endif
                    @if (Route::has('numbers'))<a href="{{ route('numbers') }}" class="nx-btn !px-6 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .35)">Get a number</a>@endif
                    @if (Route::has('download'))<a href="{{ route('download') }}" class="nx-btn !px-6 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .35)"><x-icon name="download" class="h-4 w-4" /> Get the app</a>@endif
                </div>
            </div>
        </div>

        {{-- 2. Brand + link groups --}}
        <div class="relative z-10 mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.1fr_2.4fr]">
            <div>
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-400">{{ $brand }} makes travel and international connectivity simple for African travellers and global professionals: instant eSIMs, numbers for calls, texts and verification, and support when you need it.</p>
                @if (Route::has('download'))
                    <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-slate-500">Get Naara on</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($apps as [$logo, $label])
                            <a href="{{ route('download') }}" title="{{ $label }}" aria-label="Naara for {{ $label }}" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/5 text-slate-300 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white">@include('marketing.partials.platform-logo', ['name' => $logo, 'class' => 'h-5 w-5'])</a>
                        @endforeach
                    </div>
                @endif
                @if (! empty($socials))
                    <div class="mt-6 flex flex-wrap gap-2">
                        @foreach ($socials as $s)
                            <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 text-slate-300 transition hover:bg-white/10 hover:text-white"><x-service-icon :slug="$s['icon']" class="h-4 w-4" /></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <nav aria-label="Footer" class="grid gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($groups as $g)
                    <div x-data="{ open: false, wide: matchMedia('(min-width:640px)').matches }" x-init="matchMedia('(min-width:640px)').addEventListener('change', e => wide = e.matches)" class="border-b border-white/10 py-1 sm:border-0 sm:py-0">
                        <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center justify-between py-3 text-left text-xs font-semibold uppercase tracking-widest text-accent sm:cursor-default sm:py-0 sm:pb-3">
                            {{ $g['heading'] }}<span class="text-slate-500 transition sm:hidden" :class="open && 'rotate-45'">+</span>
                        </button>
                        <ul class="space-y-2.5 pb-4 text-sm sm:pb-6" x-show="open || wide">
                            @foreach ($g['links'] as $link)
                                <li><a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif class="transition hover:text-white">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>

        {{-- 3. Popular eSIM destinations --}}
        @if ($dest)
            <div class="relative z-10 border-t border-white/10">
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Popular eSIM destinations</p>
                    <ul class="mt-4 flex flex-wrap gap-2" data-footer-destinations>
                        @foreach ($dest as $d)
                            <li><a href="{{ $d['url'] }}" class="inline-flex rounded-full bg-white/5 px-3.5 py-1.5 text-xs font-medium text-slate-300 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white">{{ $d['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif

    {{-- 4. Legal bar --}}
    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row sm:px-6">
            <x-footer-credit />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
