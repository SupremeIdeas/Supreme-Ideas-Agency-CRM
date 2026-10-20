{{-- Hero (CMS: home.hero). Optional admin image becomes the backdrop. --}}
<section class="relative" data-hero-sentinel>
    {{-- The backdrop (globe/admin image + scrim + decorative flag-orbit) is
         `absolute inset-0` — scoped to THIS wrapper, sized to just the
         original hero content, so it never stretches across the extra
         marketing bands added below it. --}}
    <div class="relative overflow-hidden rounded-t-[30px]">
    @if (! empty($s['image']))
        {{-- Apple-style hero media: admin-uploaded image with a slow GSAP
             parallax scale as the visitor scrolls (Module 27.5). --}}
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ $s['image'] }}" alt="" data-hero-media class="h-full w-full object-cover will-change-transform">
            <div class="absolute inset-0 bg-gradient-to-b from-navy/60 via-navy/70 to-navy/85"></div>
        </div>
    @else
        {{-- Branded WebGL "connected planet" hero backdrop (lazy-loaded, bundled
             through Vite so it's CSP-safe, and self-pausing off-screen). The
             glow orbs are the reduced-motion / no-WebGL fallback. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span class="absolute bottom-0 left-1/2 h-[34rem] w-[34rem] -translate-x-1/2 translate-y-1/4 rounded-full bg-primary/20 blur-3xl dark:bg-primary/25"></span>
            <span class="absolute right-[12%] top-[42%] h-56 w-56 rounded-full bg-accent/20 blur-3xl"></span>
            <canvas data-webgl-hero="planet"
                    class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
            {{-- Legibility scrim: firm only at the very top where the eyebrow
                 sits, then fades out fast — the owner asked for the globe to
                 stay genuinely visible in light mode too, not washed out by a
                 near-opaque wash the way an earlier pass had it. --}}
            <div class="absolute inset-0 bg-gradient-to-b from-[#F8F9FA] via-[#F8F9FA]/20 to-transparent dark:from-[#0D1B2A] dark:via-[#0D1B2A]/10 dark:to-transparent"></div>
        </div>
        {{-- Flag + comms nodes over the planet: our brand's "connect across
             borders" message, above the scrim so they read clearly.
             Frontend-UX-fix blueprint Phase C — root-caused via Playwright:
             node positions are percentages of this section's own height,
             tuned for a short, wide desktop hero. On a narrow phone the same
             hero reflows much taller (headline wraps to 2 lines, full-width
             paragraph, stacked CTA/stat grid), so those same percentages
             land the flag chips directly on top of the headline, paragraph,
             CTA buttons and stat numbers (reproduced live at 375px — visibly
             overlapping every one of those). Purely decorative with zero
             information loss if hidden; `lg:` is where the hero is short
             enough for the periphery these positions assume to actually
             exist. --}}
        <x-flag-orbit tone="light" class="z-[1] hidden lg:block" />
    @endif
    @php
        $showcase = \App\Support\SiteContent::imageUrl($s['showcase_image'] ?? '');
        // The committed mockup/stat-card/avatar-cluster trio (owner-supplied
        // assets) is the DEFAULT visual richness on the WebGL branch — same
        // pattern as that branch itself: an admin override always wins, so a
        // site that already configured its own showcase image keeps showing
        // exactly that, undisturbed.
        $showDeviceGroup = empty($s['image']) && ! $showcase;
    @endphp
    {{-- Deliberately NOT `z-10` here: an explicit z-index would open its own
         stacking context and trap the subheadline's mix-blend-mode inside
         it, unable to see the globe canvas at all (reproduced live — the
         blend fell back to flat white text with zero contrast). Plain
         `relative` (z-index:auto) creates no such boundary, and this div
         still paints above the absolutely-positioned backdrop because it
         comes later in the DOM — same visual result, blend actually works. --}}
    <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-16 sm:pt-24 {{ ! empty($s['image']) ? 'text-white' : '' }} {{ $showDeviceGroup ? 'lg:grid lg:grid-cols-[0.95fr_1fr] lg:items-center lg:gap-6 lg:text-left' : 'text-center' }}">
        <div class="{{ $showDeviceGroup ? 'text-center lg:text-left' : '' }}">
        {{-- Admin image branch sits on a dark navy scrim regardless of the
             site's own light/dark mode, so accent (not accent-dark) is the
             right pick there; the WebGL/no-image branch's scrim follows the
             site's mode, so it needs the usual light/dark pairing. --}}
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] {{ ! empty($s['image']) ? 'text-accent' : 'text-accent-dark dark:text-accent' }}">{{ $s['eyebrow'] }}</p>
        @php
            // Accent only the LAST word of the headline with the gradient — the
            // rest stays solid so it reads clean and professional.
            $hlWords = preg_split('/\s+/', trim($s['headline']));
            $hlLast = array_pop($hlWords) ?: '';
            $hlLead = implode(' ', $hlWords);
        @endphp
        <h1 data-reveal style="--reveal-delay:.08s"
            class="mt-4 text-4xl font-bold leading-tight sm:text-6xl {{ $showDeviceGroup ? '' : 'mx-auto max-w-3xl' }} {{ empty($s['image']) ? 'text-slate-900 dark:text-white' : 'text-white' }}">
            {{ $hlLead }}@if ($hlLead) @endif<span class="nx-gradient-text">{{ $hlLast }}</span>
        </h1>
        {{-- "Text mirror" description (owner-requested creative touch): on the
             WebGL branch this paragraph sits directly over the globe, so
             instead of picking ONE fixed colour we blend it against whatever
             is behind it — `mix-blend-mode: difference` on white text reads
             DARK over the page's light background and LIGHT over the globe's
             dark patches, so the same line of text is legible everywhere it
             crosses, with a genuinely two-tone look where it straddles both.
             The admin-image branch keeps a plain, fixed colour — that scrim
             is already a flat, known tone, so the blend trick has nothing to
             react to there and would just look muddy. An admin who picks an
             explicit colour (Site Editor → Home → Hero → "Description text
             colour") gets exactly that fixed colour instead — a deliberate
             choice overrides the automatic blend rather than fighting it. --}}
        @php
            $subColor = trim((string) ($s['subheadline_color'] ?? ''));
        @endphp
        <p data-reveal
           style="--reveal-delay:.16s; {{ $subColor ? 'color:'.$subColor.';' : (empty($s['image']) ? 'mix-blend-mode: difference; color: #fff;' : '') }}"
           class="mt-5 max-w-2xl text-lg leading-relaxed {{ $showDeviceGroup ? '' : 'mx-auto' }} {{ ! $subColor && ! empty($s['image']) ? 'text-slate-200' : '' }}">
            {{ $s['subheadline'] }}
        </p>

        {{-- Hero customization foundation, part (a) — "Glass card depth below
             the text description" (owner request, 2026-09-21): admin-tunable
             0 (off, default — zero visual change on an untouched install) to
             100 (deep blur/shadow), computed here and applied to the CTA row
             below — the first real content directly under the description —
             following the reference file's own "Premium Glass" technique
             (backdrop-blur + soft translucent fill + border + shadow) rather
             than a flat, empty card. --}}
        @php
            $glassDepth = max(0, min(100, (int) ($s['glass_depth'] ?? 0)));
            $glassBlurPx = 6 + (int) round($glassDepth * 0.18); // 6px..24px
            $glassAlpha = 0.05 + ($glassDepth / 100) * 0.10; // 0.05..0.15
        @endphp

        {{-- BUILD-13 (marketing): a real, visible showcase image (product /
             device mockup) directly under the description and above the CTAs.
             Distinct from the full-bleed `image` backdrop — a discrete, centered
             graphic with a capped height so it never pushes the CTAs + stat grid
             below the fold. Optional; the hero renders cleanly without it. --}}
        @if ($showcase)
            <div data-reveal style="--reveal-delay:.20s" class="mx-auto mt-8 max-w-3xl">
                <img src="{{ $showcase }}" alt="{{ $s['headline'] }}" loading="lazy" decoding="async"
                     class="mx-auto max-h-[300px] w-auto max-w-full object-contain sm:max-h-[360px]">
            </div>
        @endif

        {{-- Mobile/tablet device-group visual: the same mockup shown in the
             desktop right column below, stacked under the CTAs on narrower
             screens (source-of-truth reference stacks it here too). Hidden on
             lg: where it moves into the dedicated right column instead. The
             whole group scales together (mockup + stat card keep their
             relative position) via the admin's "Hero image size" dial —
             100% (the default) renders byte-identical to the untouched hero. --}}
        @php
            $imgScale = max(0.5, min(1.5, ((int) ($s['image_scale'] ?? 100)) / 100));
        @endphp
        @if ($showDeviceGroup)
            <div data-reveal style="--reveal-delay:.20s; transform: scale({{ $imgScale }})" class="relative mx-auto mt-5 max-w-xs lg:hidden">
                <img src="{{ asset('images/home/hero-mockup.webp') }}" width="400" height="495" alt="The NaaraSim app open on a phone"
                     loading="lazy" decoding="async" class="relative z-10 mx-auto w-full max-w-[230px] object-contain sm:max-w-[300px]">
                <img src="{{ asset('images/home/hero-stat-card.webp') }}" alt="190+ countries covered, 3 simple ways to connect"
                     loading="lazy" decoding="async" class="absolute right-2 top-2 z-20 w-24 object-contain drop-shadow-xl sm:right-4 sm:w-32">
            </div>
        @endif

        {{-- CTA row locked to two columns — never stacks (BUILD-13 §2.5).
             Compact padding/text at the smallest width so both buttons genuinely
             fit side by side at 375px, stepping up from sm:. When "Glass card
             depth" is dialled up, the row sits inside a frosted glass panel
             (backdrop-blur + soft translucent fill), the reference file's own
             "Premium Glass" treatment — off (0) renders the plain row exactly
             as before. --}}
        @php
            $glassStyle = '--reveal-delay:.24s;';
            if ($glassDepth > 0) {
                $glassStyle .= " backdrop-filter: blur({$glassBlurPx}px); -webkit-backdrop-filter: blur({$glassBlurPx}px); background: rgba(255,255,255,{$glassAlpha}); border-color: rgba(255,255,255,".($glassAlpha * 2).'); box-shadow: 0 20px 40px rgba(13,27,42,0.12);';
            }
        @endphp
        <div data-reveal style="{{ $glassStyle }}" class="mt-8 max-w-md {{ $showDeviceGroup ? 'mx-auto lg:mx-0' : 'mx-auto' }} {{ $glassDepth > 0 ? 'rounded-2xl border p-3' : '' }}">
            <div class="grid grid-cols-2 items-center gap-3">
                <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary w-full justify-center !px-4 !py-3 !text-sm sm:!px-8 sm:!text-base">{{ $s['cta_primary'] }}</a>
                <a href="{{ route('how-it-works') }}" class="nx-btn nx-btn--ghost w-full justify-center !px-4 !py-3 !text-sm sm:!px-8 sm:!text-base">{{ $s['cta_secondary'] }}</a>
            </div>
        </div>
        <p data-reveal style="--reveal-delay:.32s" class="mt-6 text-xs {{ empty($s['image']) ? 'text-slate-400' : 'text-slate-300' }}">{{ $s['social_proof'] }}</p>

        {{-- Stat row — a single bordered strip with a real icon per figure
             (matches the reference exactly: globe / lightning / SIM / headset),
             not bare numbers in separate boxes. --}}
        @php
            $statIcons = ['globe', 'zap', 'sim', 'headset'];
        @endphp
        <dl data-reveal style="--reveal-delay:.4s"
            class="mt-8 grid max-w-3xl grid-cols-2 gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 sm:grid-cols-4 dark:border-white/10 dark:bg-white/10 {{ $showDeviceGroup ? 'mx-auto lg:mx-0' : 'mx-auto' }}">
            @foreach (['stat_1', 'stat_2', 'stat_3', 'stat_4'] as $i => $stat)
                @php [$value, $label] = array_pad(explode(' ', $s[$stat], 2), 2, ''); @endphp
                @php($numeric = preg_match('/^(\d+)(\+?)$/', $value, $m))
                <div class="flex flex-col items-center gap-1.5 bg-white/90 p-4 text-center dark:bg-[#0D1B2A]">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                        <x-icon :name="$statIcons[$i]" class="h-4 w-4" />
                    </span>
                    <dt class="sr-only">{{ $label }}</dt>
                    <dd class="font-display text-xl font-bold text-primary dark:text-teal-300"
                        @if ($numeric) data-countup="{{ $m[1] }}" data-suffix="{{ $m[2] }}" @endif>{{ $value }}</dd>
                    <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dd>
                </div>
            @endforeach
        </dl>
        </div>

        {{-- Desktop-only right column: the mockup floats over the existing
             WebGL globe (which stays exactly where it always was, full-bleed
             behind this whole section — the globe is genuinely BEHIND the
             device here, not replaced or reparented). The stat card and
             avatar cluster are the same owner-supplied assets, positioned to
             match the reference composition. --}}
        @if ($showDeviceGroup)
            <div class="relative mt-12 hidden lg:mt-0 lg:block" data-reveal style="--reveal-delay:.28s; transform: scale({{ $imgScale }}); transform-origin: top right;">
                <img src="{{ asset('images/home/hero-mockup.webp') }}" width="400" height="495" alt="The NaaraSim app open on a phone"
                     loading="lazy" decoding="async" class="relative z-10 mx-auto w-full max-w-[400px] object-contain xl:max-w-[440px]">
                <img src="{{ asset('images/home/hero-stat-card.webp') }}" alt="190+ countries covered, 3 simple ways to connect"
                     loading="lazy" decoding="async" class="absolute right-2 top-10 z-20 w-44 object-contain drop-shadow-xl xl:right-2 xl:w-52">
            </div>
        @endif
    </div>
    </div>

    {{-- Two extra marketing bands (owner-supplied composition, reference
         screenshots) that sit just under the fold: a dark "spotlight" feature
         card, then a stats-plus-trust band. Both are real, bordered/shadowed
         sections — not bare floating text — and only render on the same
         WebGL/no-override branch as the rest of the device group above. --}}
    @if ($showDeviceGroup)
        <div class="relative z-10 mx-auto -mt-4 max-w-6xl px-4 pb-16 sm:-mt-8">
            <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:items-center">
                {{-- "One App. Every Connection." — a deliberately dark card even
                     on the light theme (a common "spotlight" pattern): it always
                     reads exactly like the reference regardless of site mode. --}}
                <div data-reveal class="flex flex-col overflow-hidden rounded-3xl bg-gradient-to-br from-[#0D1B2A] to-[#0A6E6E] sm:flex-row sm:items-center">
                    <div class="flex-1 p-8">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> {{ $s['spot_status'] ?? '' }}
                        </span>
                        <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">{{ $s['spot_title_1'] ?? '' }}<br><span class="text-accent">{{ $s['spot_title_2'] ?? '' }}</span></h2>
                        <a href="{{ route('how-it-works') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">
                            {{ \App\Support\BrandSettings::rebrand($s['spot_link_label'] ?? '') }} <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>
                    <img src="{{ \App\Support\SiteContent::imageUrl($s['spot_image'] ?? '') ?: asset('images/home/hero-one-app-connect.webp') }}" alt="{{ $s['spot_title_1'] ?? '' }} {{ $s['spot_title_2'] ?? '' }}"
                         loading="lazy" decoding="async" class="h-40 w-full object-cover sm:h-full sm:w-48">
                </div>

                {{-- "Your World. One Connection." — headline + the 190+ figure
                     with the trust cluster, a divider, then the description and
                     the explore arrow, all inside one real bordered card. --}}
                <div data-reveal style="--reveal-delay:.08s" class="rounded-3xl border border-slate-200 bg-white p-8 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $s['world_title_1'] ?? '' }}<br><span class="text-primary dark:text-teal-300">{{ $s['world_title_2'] ?? '' }}</span></h2>
                        <div class="shrink-0 text-right">
                            <img src="{{ \App\Support\SiteContent::imageUrl($s['world_avatars_image'] ?? '') ?: asset('images/home/hero-avatar-cluster.webp') }}" alt="" loading="lazy" decoding="async" class="ml-auto h-10 w-auto object-contain">
                            <p class="mt-1 font-display text-4xl font-bold text-primary dark:text-accent">{{ $s['world_stat'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="mt-6 border-t border-slate-200 pt-6 dark:border-white/10">
                        <div class="flex items-end justify-between gap-4">
                            <p class="max-w-sm text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ \App\Support\BrandSettings::rebrand($s['world_body'] ?? '') }}</p>
                            <a href="{{ route('register') }}" wire:navigate aria-label="Get started"
                               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-primary hover:text-primary dark:border-white/10 dark:text-slate-300">
                                <x-icon name="arrow-up-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- "Who Naara Is For" auto time-based persona switcher — owner request,
         emphasized twice: "just put it in our hero." Always renders here
         regardless of $showDeviceGroup (it's independent persona content,
         not tied to whether an admin overrode the backdrop image). --}}
    @include('marketing.home._who-for')
</section>
