{{-- Public marketing shell (Module 27): sticky glassy nav, CMS-driven pages,
     footer with Supreme Ideas Agency attribution + legal quick-links. Guests
     get Sign in / Get Started; signed-in visitors go straight to their
     dashboard. --}}
@props(['title' => null, 'description' => null, 'ogImage' => null, 'preloaderType' => 'marketing', 'bare' => false])
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name().' — Stay Connected. No Borders. No Swaps.'" :page-type="$preloaderType" :description="$description" :og-image="$ogImage">
    <div class="mkt-bg min-h-screen">
        {{-- Floating, curved, glass header (Webflow-inspired): a contained pill
             that never touches the edges, with a few primary links inline and a
             Menu button that opens a full glass panel of everything. --}}
        @php
            // Every label/description below is a Site-Editor field (Marketing site
            // -> Navigation); routes, icons and the master-only/Soon gating are code.
            $nv = \App\Support\SiteContent::page('navigation', includeHidden: true)['menu'] ?? [];
            // Mega-menu columns. Products = what Naara sells; Developers = the API;
            // Company = who we are. Naara Gift carries a "Soon" badge until its keys
            // are live (advertised now, links live automatically).
            $navProducts = [
                ['mega' => 'family', 'route' => 'products', 'label' => $nv['family_label'] ?? '', 'desc' => $nv['family_desc'] ?? '', 'icon' => 'layers'],
                ['mega' => 'esim', 'route' => 'pricing', 'label' => $nv['esim_label'] ?? '', 'desc' => $nv['esim_desc'] ?? '', 'icon' => 'globe'],
                ['mega' => 'how-it-works', 'route' => 'how-it-works', 'label' => $nv['how_label'] ?? '', 'desc' => $nv['how_desc'] ?? '', 'icon' => 'signal'],
            ];
            if (\App\Support\FeatureFlags::adminEnabled('naara_gift')) {
                $navProducts[] = ['mega' => 'gift', 'route' => 'gift-cards', 'label' => $nv['gift_label'] ?? '', 'desc' => $nv['gift_desc'] ?? '', 'icon' => 'gift',
                    'badge' => \App\Support\FeatureFlags::configured('naara_gift') ? null : ($nv['gift_badge'] ?? ''), 'authOnly' => true,
                    // A visitor who is not signed in lands on the Naara Gift page (when the admin has it on) instead of the register wall.
                    'guestRoute' => (class_exists(\App\Support\MasterMarketing\PageSwitch::class) && \App\Support\MasterMarketing\PageSwitch::isLive('naara-gift')) ? 'gift.landing' : null];
            }
            // Master-only sales pages (Naara Chat, White Label): each links only while the admin has its page switched on, and never on a fork.
            if (class_exists(\App\Support\MasterMarketing\PageSwitch::class)) {
                if (\App\Support\MasterMarketing\PageSwitch::isLive('naara-chat')) {
                    $navProducts[] = ['mega' => 'chat', 'route' => 'n2n.landing', 'label' => $nv['chat_label'] ?? '', 'desc' => $nv['chat_desc'] ?? '', 'icon' => 'message-circle'];
                }
                if (\App\Support\MasterMarketing\PageSwitch::isLive('naara-brain')) {
                    $navProducts[] = ['mega' => 'brain', 'route' => 'brain.landing', 'label' => $nv['brain_label'] ?? '', 'desc' => $nv['brain_desc'] ?? '', 'icon' => 'sparkles'];
                }
            }
            // Travel Handbooks (master-only; present only while the store is open).
            if (class_exists(\App\Support\Handbooks\HandbookSettings::class) && rescue(fn () => \App\Support\Handbooks\HandbookSettings::get('store_enabled'), false, report: false)) {
                $navProducts[] = ['mega' => 'handbooks', 'route' => 'handbooks.store', 'label' => 'Travel Handbooks', 'desc' => 'Guides to living, studying and working abroad.', 'icon' => 'book-open'];
            }
            // NaaraBusiness Suite (master-only; present only while the Suite is switched on).
            if (class_exists(\App\Support\Business\BusinessSuiteSettings::class) && rescue(fn () => \App\Support\Business\BusinessSuiteSettings::enabled(), false, report: false)) {
                $navProducts[] = ['mega' => 'business', 'route' => \App\Support\Business\BusinessNav::entryRoute(auth()->user()), 'label' => 'NaaraBusiness Suite', 'desc' => 'Get listed, run your team inbox and calls.', 'icon' => 'briefcase'];
            }
            $navDevelopers = [
                ['mega' => 'developers', 'route' => 'developers', 'label' => $nv['dev_label'] ?? '', 'desc' => $nv['dev_desc'] ?? '', 'icon' => 'key'],
                ['mega' => 'pricing', 'route' => 'pricing', 'label' => $nv['pricing_label'] ?? '', 'desc' => $nv['pricing_desc'] ?? '', 'icon' => 'credit-card'],
            ];
            // FAQ page is master-only (owner decision, 2026-09-21) — its
            // route 404s on a fork, so never link to it there either.
            if (\App\Support\FeatureEntitlements::isMaster()) {
                $navDevelopers[] = ['mega' => 'faq', 'route' => 'faq', 'label' => $nv['faq_label'] ?? '', 'desc' => $nv['faq_desc'] ?? '', 'icon' => 'help-circle'];
            }
            $navCompany = [
                ['mega' => 'about', 'route' => 'about', 'label' => $nv['about_label'] ?? '', 'desc' => $nv['about_desc'] ?? '', 'icon' => 'info'],
                ['mega' => 'blog', 'route' => 'blog', 'label' => $nv['blog_label'] ?? '', 'desc' => $nv['blog_desc'] ?? '', 'icon' => 'file-text'],
                ['mega' => 'contact', 'route' => 'contact', 'label' => $nv['contact_label'] ?? '', 'desc' => $nv['contact_desc'] ?? '', 'icon' => 'message-circle'],
            ];
            if (class_exists(\App\Support\MasterMarketing\PageSwitch::class) && \App\Support\MasterMarketing\PageSwitch::isLive('white-label')) {
                array_splice($navCompany, 1, 0, [['route' => 'white-label.landing', 'label' => $nv['wl_label'] ?? '', 'desc' => $nv['wl_desc'] ?? '', 'icon' => 'layers']]);
            }
        @endphp
        @unless ($bare)
        <header x-data="{ open: false }" class="pointer-events-none sticky top-0 z-50 px-3 pt-4 sm:px-4">
            <nav class="pointer-events-auto mx-auto flex max-w-5xl items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white px-2.5 py-2 shadow-xl shadow-slate-900/5 ring-1 ring-black/5 dark:border-white/10 dark:bg-[#0D1B2A] dark:ring-white/10">
                <a href="{{ route('home') }}" wire:navigate class="shrink-0 pl-1.5"><x-brand-logo variant="family" size="md" /></a>

                <div class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex dark:text-slate-300">
                    <a href="{{ route('how-it-works') }}" wire:navigate class="transition hover:text-primary">{{ $nv['how_label'] ?? '' }}</a>
                    <a href="{{ route('pricing') }}" wire:navigate class="transition hover:text-primary">{{ $nv['pricing_label'] ?? '' }}</a>
                    <a href="{{ route('developers') }}" wire:navigate class="transition hover:text-primary">{{ $nv['dev_label'] ?? '' }}</a>
                </div>

                <div class="flex items-center gap-2">
                    <x-lang-switcher />
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="hidden nx-btn nx-btn--primary !py-2 !px-4 sm:inline-flex">{{ $nv['cta_dashboard'] ?? '' }}</a>
                    @else
                        <a href="{{ route('register') }}" wire:navigate class="hidden nx-btn nx-btn--primary !py-2 !px-4 sm:inline-flex">{{ $nv['cta_start'] ?? '' }}</a>
                    @endauth

                    {{-- Menu button (animated bars → X) --}}
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Open menu"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/60 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                        <span class="hidden sm:inline">{{ $nv['menu_label'] ?? '' }}</span>
                        <span class="relative block h-3.5 w-5" aria-hidden="true">
                            <span class="absolute left-0 top-0 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'top-1.5 rotate-45'"></span>
                            <span class="absolute left-0 top-1.5 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'opacity-0'"></span>
                            <span class="absolute left-0 top-3 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'top-1.5 -rotate-45'"></span>
                        </span>
                    </button>
                </div>
            </nav>

            {{-- Glass mega-menu panel --}}
            <div x-show="open" x-cloak x-transition.origin.top
                 @click.outside="open = false" @keydown.escape.window="open = false"
                 {{-- HOTFIX §7: real scroll container (was overflow-hidden with
                      no max-h → tall content clipped off-viewport on mobile).
                      §5: raised opacity so it's legible without backdrop-blur. --}}
                 class="pointer-events-auto mx-auto mt-2 max-h-[calc(100dvh-6rem)] max-w-5xl overflow-y-auto overscroll-contain rounded-2xl border border-slate-200/70 bg-white shadow-2xl shadow-slate-900/10 ring-1 ring-black/5 [-webkit-overflow-scrolling:touch] dark:border-white/10 dark:bg-[#0D1B2A] dark:ring-white/10">
                <div class="grid gap-6 p-5 sm:grid-cols-3 sm:p-6">
                    @foreach (['Products' => $navProducts, 'Developers' => $navDevelopers, 'Company' => $navCompany] as $heading => $items)
                        @php $headingLabel = $nv['heading_'.strtolower($heading)] ?? ''; @endphp
                        <div>
                            <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $headingLabel }}</p>
                            @foreach ($items as $item)
                                @php $href = (($item['authOnly'] ?? false) && ! auth()->check()) ? route($item['guestRoute'] ?? 'register') : route($item['route']); @endphp
                                <a href="{{ $href }}" wire:navigate class="group flex items-start gap-3.5 rounded-xl px-2 py-2.5 transition hover:bg-primary/5 dark:hover:bg-white/5">
                                    @if (class_exists(\App\Support\MasterMarketing\MenuIcons::class) && ! empty($item['mega']) && \App\Support\MasterMarketing\MenuIcons::marketingSlot($item['mega']))
                                        <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center"><x-master-marketing.menu-icon :slot-key="\App\Support\MasterMarketing\MenuIcons::marketingSlot($item['mega'])" :fallback="$item['icon'] ?? 'chevron-right'" :size="30" /></span>
                                    @else
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="{{ $item['icon'] ?? 'chevron-right' }}" class="h-4 w-4" /></span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-900 group-hover:text-primary dark:text-slate-100">{{ $item['label'] }}
                                            @if ($item['badge'] ?? null)<span class="rounded-full bg-accent/15 px-1.5 py-px text-[9px] font-bold uppercase text-accent-dark dark:text-accent">{{ $item['badge'] }}</span>@endif
                                        </span>
                                        <span class="line-clamp-2 block text-xs text-slate-500 dark:text-slate-400">{{ $item['desc'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                            {{-- Custom pages sit under Company. --}}
                            @if ($heading === 'Company')
                                @foreach (\App\Models\CustomPage::navLinks() as $navPage)
                                    <a href="{{ url('/p/'.$navPage['slug']) }}" class="group flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-primary/5 dark:hover:bg-white/5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-300"><x-icon name="file-text" class="h-4 w-4" /></span>
                                        <span class="text-sm font-semibold text-slate-900 group-hover:text-primary dark:text-slate-100">{{ $navPage['title'] }}</span>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 bg-white/40 px-5 py-3.5 dark:border-white/10 dark:bg-white/5">
                    <span class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ $nv['tagline'] ?? '' }}</span>
                    <div class="flex items-center gap-2">
                        @auth
                            <a href="{{ route('dashboard') }}" wire:navigate class="nx-btn nx-btn--primary !py-2 !px-5">{{ $nv['cta_dashboard_full'] ?? '' }}</a>
                        @else
                            <a href="{{ route('login') }}" wire:navigate class="px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-primary dark:text-slate-300">{{ $nv['signin'] ?? '' }}</a>
                            <a href="{{ route('register') }}" wire:navigate class="nx-btn nx-btn--primary !py-2 !px-5">{{ $nv['cta_start'] ?? '' }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>
        @endunless

        <main>{{ $slot }}
            {{-- Hero video card (master only; admin: Marketing videos). Rendered once here for every page; the partial moves it under the page's hero. --}}
            @if (class_exists(\App\Support\MasterMarketing\VideoSlots::class) && ($heroSlot = \App\Support\MasterMarketing\VideoSlots::heroFor(request()->route()?->getName())))
                @include('marketing.master-marketing._hero-video', ['slot' => $heroSlot])
            @endif
        </main>
        @includeIf('marketing.master-marketing._content-lock', ['surface' => 'marketing'])

        {{-- Latest posts as a carousel above the footer (master only). Not on the blog itself. --}}
        @if (class_exists(\App\Support\MasterMarketing\VideoSlots::class) && ! request()->routeIs('blog', 'blog.show', 'onboarding', 'install.*'))
            @include('marketing.master-marketing._blog-carousel', ['exclude' => null])
        @endif

        {{-- Footer (Module 28: admin-assignable columns + legal via SiteChrome). --}}
        <x-site-footer variant="full" />
    </div>

    {{-- Floating navigation pill (Homepage floating-nav): admin-assignable slots +
         a glowing Wizard centrepiece, on desktop + mobile. On marketing there is
         no other bottom bar, so this is the sole floating element (no duplicate).
         The in-page Wizard is included only for signed-in visitors, so the
         centrepiece can open it; guests get a "Get started" CTA instead. --}}
    @auth
        @livewire('wizard')
    @endauth
    <x-floating-nav />

    {{-- ElevenLabs Convai voice assistant (task #17) — renders only when an admin
         enabled it for the public site. --}}
    <x-convai-widget context="marketing" />
</x-layouts.app>
