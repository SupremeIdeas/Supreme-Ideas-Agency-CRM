{{-- Naara Travel Handbooks: the public landing page (MASTER-ONLY). Same marketing shell and sheet seams as the Naara Gift page. Copy is admin-editable (Handbooks > Store and unlock settings); icons are code. --}}
@php
    $storeUrl = route('handbooks.directory');
    $cover = $popular->first(fn ($b) => $b->cover_path)?->slug;
    $deck = $hero->values();
    $cover = $deck->first(fn ($b) => $b->cover_path)?->slug ?? $cover;
    $coverage = collect((array) $s['esim_unlock_coverage'])->map(fn ($c) => strtolower(\App\Support\Handbooks\HandbookSettings::COVERAGE[$c] ?? $c))->all();
    $faq = [
        ['How do I get a handbook for free?', $s['esim_unlock_enabled'] ? 'Buy a Naara eSIM. '.($s['esim_unlock_flagship_only'] ? 'A single-country eSIM unlocks that country\'s handbook at once.' : 'A single-country eSIM unlocks the handbooks for that country.').' A regional or global eSIM lets you pick '.($s['esim_regional_picks'] > 1 ? $s['esim_regional_picks'].' handbooks' : 'one handbook').' from the countries it covers. Your handbook appears in your Library the moment your eSIM order goes through.' : 'Handbooks are bought one at a time from their page, and Naara Pro members can unlock some for free.'],
        ['What is the difference between unlocked and owned?', 'Unlocked books are yours to read in the Naara app, online or offline. Owned books can also be downloaded as an EPUB and a PDF with your name stamped on them. You can upgrade any unlocked book to owned for half the list price.'],
        ['Does it work without internet?', 'Yes. Open a handbook once while online and it is saved, encrypted, on your device. Your place, bookmarks and chapters are all there offline. It checks back in online every 30 days to make sure you still have access.'],
        ['How do I know the information is current?', 'Every handbook shows the date it was last verified, and when we publish a new edition it lands in your Library for free.'],
        ['Is this legal or immigration advice?', 'No. These are practical guides. Rules change, so always check the official source for your situation.'],
    ];
@endphp
<x-layouts.marketing :title="'Travel Handbooks | '.\App\Support\BrandSettings::name()" :description="$s['hero_subtext']">

{{-- Hero: the pitch and a fan of real covers --}}
<section class="relative isolate overflow-hidden px-4 pb-28 pt-12 sm:pt-16 nx-ambient nx-ambient--rich">
    <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="book-open" class="h-4 w-4" />{{ $s['hero_eyebrow'] }}</p>
            <h1 data-reveal class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {{ $s['hero_headline'] }}
                @if ($s['hero_accent'])<span class="mt-1 block bg-gradient-to-r from-primary via-[#13A39B] to-[#6B4D00] bg-clip-text text-transparent dark:from-accent dark:via-[#7FE0D8] dark:to-accent">{{ $s['hero_accent'] }}</span>@endif
            </h1>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['hero_subtext'] }}</p>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $storeUrl }}" wire:navigate class="nx-btn nx-btn--primary !px-7 !py-3" data-cta="browse">{{ $s['cta_primary'] }}</a>
                <a href="#how" class="nx-btn !px-7 !py-3 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">{{ $s['cta_secondary'] }}</a>
            </div>
            <ul data-reveal class="mt-8 flex flex-wrap gap-2">
                @foreach (['Read offline', 'Free with your eSIM', 'Dated and verified', 'Friendly on any phone'] as $chip)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-[13px] font-medium text-slate-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-200"><x-icon name="check" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $chip }}</li>
                @endforeach
            </ul>
            <p data-reveal class="mt-6 text-sm text-slate-500 dark:text-slate-400">{{ $total }} {{ \Illuminate\Support\Str::plural('handbook', $total) }} across {{ $countryTotal }} {{ \Illuminate\Support\Str::plural('country', $countryTotal) }}, and growing.</p>
        </div>

        {{-- The newest handbooks as a 3D deck that turns by itself: the front book is the latest, the rest fan out behind it. Pauses on hover and focus, swipes on touch, still for reduced motion. --}}
        <div data-reveal class="hbh" x-data="{ i: 0, pi: 0, n: {{ $deck->count() }}, t: null, paused: false, sx: null,
                go(d) { this.pi = this.i; this.i = (this.i + d + this.n) % this.n },
                to(k) { this.pi = this.i; this.i = k },
                offAt(k, at) { let o = (k - at + this.n) % this.n; return o > this.n / 2 ? o - this.n : o },
                off(k) { return this.offAt(k, this.i) },
                jump(k) { return Math.abs(this.offAt(k, this.i) - this.offAt(k, this.pi)) > 2 },
                start() { if (this.n < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; this.t = setInterval(() => { if (!this.paused && !document.hidden) this.go(1) }, 4200) } }"
             x-init="start()" x-on:mouseenter="paused = window.matchMedia('(hover: hover)').matches" x-on:mouseleave="paused = false" x-on:focusin="paused = $event.target.matches(':focus-visible')" x-on:focusout="paused = false"
             x-on:touchstart.passive="sx = $event.touches[0].clientX" x-on:touchend.passive="if (sx !== null) { const d = $event.changedTouches[0].clientX - sx; if (Math.abs(d) > 40) go(d < 0 ? 1 : -1); sx = null }"
             x-on:keydown.arrow-right="go(1)" x-on:keydown.arrow-left="go(-1)" role="region" aria-roledescription="carousel" aria-label="The newest Naara travel handbooks" data-hero-deck>
            @if ($deck->isEmpty())
                <div class="absolute inset-0 grid place-items-center text-center text-slate-500 dark:text-slate-400"><span><x-icon name="book-open" class="mx-auto h-12 w-12 text-primary dark:text-accent" /><span class="mt-3 block text-sm">The first handbooks are on their way.</span></span></div>
            @else
                <div class="hbh__stage">
                    <span class="hbh__glow" aria-hidden="true"></span>
                    @foreach ($deck as $k => $b)
                        <a href="{{ route('handbooks.show', $b->slug) }}" wire:navigate class="hbh__book" data-hero-book data-slug="{{ $b->slug }}"
                           :class="{ 'is-front': off({{ $k }}) === 0, 'is-far': Math.abs(off({{ $k }})) > 2, 'is-jump': jump({{ $k }}) }" :style="`--o: ${off({{ $k }})}; --a: ${Math.abs(off({{ $k }}))}`"
                           :tabindex="off({{ $k }}) === 0 ? 0 : -1" :aria-hidden="off({{ $k }}) === 0 ? 'false' : 'true'"
                           x-on:click="if (off({{ $k }}) !== 0) { $event.preventDefault(); to({{ $k }}) }" aria-label="{{ $b->title }}">
                            <x-handbook-cover :book="$b" :eager="$k < 3" />
                            <span class="hbh__shine" aria-hidden="true"></span>
                        </a>
                    @endforeach
                </div>
                <div class="hbh__caption" aria-live="polite">
                    @foreach ($deck as $k => $b)
                        <p x-show="i === {{ $k }}" @if ($k > 0) x-cloak @endif x-transition.opacity.duration.300ms class="hbh__cap"><span class="hbh__new">{{ $k === 0 ? 'Newest' : 'Recently added' }}</span> <b>{{ $b->title }}</b></p>
                    @endforeach
                </div>
                @if ($deck->count() > 1)
                    <div class="hbh__dots" role="group" aria-label="Choose a handbook">
                        @foreach ($deck as $k => $b)
                            <button type="button" x-on:click="to({{ $k }})" :class="{ 'is-on': i === {{ $k }} }" :aria-current="i === {{ $k }} ? 'true' : 'false'" aria-label="Show {{ $b->title }}"></button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</section>

{{-- Popular handbooks and the See more button --}}
<section id="popular" class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-16 text-white -mt-8 sm:py-20 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">Popular right now</p>
                <h2 data-reveal class="mt-2 text-3xl font-bold sm:text-4xl">Start with a reader favourite</h2>
            </div>
            <a href="{{ $storeUrl }}" wire:navigate class="hidden items-center gap-1.5 rounded-full border border-white/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10 sm:inline-flex" data-cta="see-more-top">See all handbooks <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
        @if ($popular->isEmpty())
            <p class="mt-10 text-slate-300">Handbooks will appear here as soon as the first titles are published.</p>
        @else
            <div class="hb-shelf mt-8" data-popular-shelf>@foreach ($popular as $b)@include('handbooks._shelf-book', ['b' => $b])@endforeach</div>
        @endif
        <div class="mt-4 text-center">
            <a href="{{ $storeUrl }}" wire:navigate class="nx-btn nx-btn--primary !px-8 !py-3" data-cta="see-more">See more handbooks <x-icon name="arrow-right" class="ml-1 inline h-4 w-4" /></a>
            <p class="mt-3 text-sm text-slate-400">Browse the full directory by country, topic and style.</p>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 -mt-8 sm:py-24 dark:bg-[#0F2033]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">How it works</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">From curious to confident in three steps</h2>
        </div>
        <div class="mt-12 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['search', 'Find your destination', 'Search the directory by country, topic or style. Every book shows what is inside and when it was last verified.'],
                ['sim', 'Unlock it or buy it', $s['esim_unlock_enabled'] ? 'Buy a Naara eSIM and the matching handbook unlocks for free. Or buy any handbook on its own.' : 'Buy any handbook on its own, one at a time, from its page.'],
                ['book-open', 'Read it anywhere', 'Open it in the Naara app on any phone, save it for offline, jump straight to the chapter you need, and pick up where you left off.'],
            ] as $i => [$ic, $t, $d])
                <div data-reveal style="--reveal-delay: {{ $i * 0.07 }}s" class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon :name="$ic" class="h-6 w-6" /></span>
                    <p class="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-primary dark:text-accent">Step {{ $i + 1 }}</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $t }}</h3>
                    <p class="mt-2 leading-relaxed text-slate-600 dark:text-slate-300">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Free with your eSIM: read live from the admin's unlock rules --}}
@if ($s['esim_unlock_enabled'])
<section id="free-with-esim" class="relative isolate overflow-hidden rounded-t-[30px] bg-gradient-to-br from-[#0A6E6E] to-[#0D1B2A] px-4 py-20 text-white -mt-8 sm:py-24 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-2">
        <div>
            <p data-reveal class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.25em] text-accent"><x-icon name="sim" class="h-4 w-4" />Free with your eSIM</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">Land connected. Land prepared.</h2>
            <p data-reveal class="mt-4 max-w-xl leading-relaxed text-teal-50/90">Every Naara eSIM for a country comes with that country's handbook. Visas, housing, money, local rules and the things nobody tells you, unlocked the moment your order goes through.</p>
            <ul data-reveal class="mt-6 space-y-3 text-teal-50">
                @foreach (array_filter([
                    in_array('local', (array) $s['esim_unlock_coverage'], true) ? 'Single-country eSIM: that country\'s handbook, unlocked at once.' : null,
                    count(array_intersect(['regional', 'global'], (array) $s['esim_unlock_coverage'])) ? 'Regional or global eSIM: you pick '.($s['esim_regional_picks'] > 1 ? $s['esim_regional_picks'].' handbooks' : 'one handbook').' from the countries it covers.' : null,
                    $s['pro_unlock_enabled'] && $s['pro_slots'] > 0 ? 'Naara Pro: '.$s['pro_slots'].' free handbook unlocks every term.' : null,
                ]) as $line)
                    <li class="flex items-start gap-3"><span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-full bg-accent text-[#0D1B2A]"><x-icon name="check" class="h-3.5 w-3.5" /></span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
            <div data-reveal class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('pricing') }}" wire:navigate class="nx-btn nx-btn--primary !px-7 !py-3">Get an eSIM</a>
                <a href="{{ $storeUrl }}" wire:navigate class="nx-btn !px-7 !py-3 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .5)">Browse handbooks</a>
            </div>
        </div>
        <div data-reveal class="relative mx-auto flex max-w-sm items-end justify-center gap-4" aria-hidden="true">
            @foreach ($popular->take(2)->values() as $i => $b)
                <div class="{{ $i === 0 ? 'w-[46%] -rotate-3' : 'mb-8 w-[46%] rotate-3' }}"><x-handbook-cover :book="$b" /></div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Browse by country and topic --}}
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 -mt-8 sm:py-24 dark:bg-[#0D1B2A] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">Browse</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Where are you headed?</h2>
        </div>
        @if ($countries->isNotEmpty())
            <ul data-reveal class="mt-10 flex flex-wrap justify-center gap-3" data-country-chips>
                @foreach ($countries as $c)
                    <li><a href="{{ route('handbooks.directory', ['country' => $c->country_code]) }}" wire:navigate class="inline-flex items-center gap-2.5 rounded-full border border-slate-200 bg-white py-2 pl-2.5 pr-4 text-[15px] font-semibold text-slate-800 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg dark:border-white/10 dark:bg-[#16233d] dark:text-white"><x-country-flag :country="$c->country_code" class="h-4 w-6 rounded-sm shadow-sm" />{{ \App\Support\CountryNames::name($c->country_code) ?? $c->country_code }}<span class="text-xs font-medium text-slate-400">{{ $c->n }}</span></a></li>
                @endforeach
            </ul>
        @endif
        @if ($categories->isNotEmpty())
            <ul data-reveal class="mt-8 flex flex-wrap justify-center gap-2.5">
                @foreach ($categories as $c)
                    <li><a href="{{ route('handbooks.directory', ['category' => $c->slug]) }}" wire:navigate class="rounded-full bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary hover:text-white dark:bg-accent/15 dark:text-accent dark:hover:bg-accent dark:hover:text-[#0D1B2A]">{{ $c->name }}</a></li>
                @endforeach
            </ul>
        @endif
        @if ($fresh->isNotEmpty())
            <div class="mt-16" x-data="{ prev: false, next: true, sync() { const t = this.$refs.track; this.prev = t.scrollLeft > 8; this.next = t.scrollLeft + t.clientWidth < t.scrollWidth - 8 }, by(d) { const t = this.$refs.track; t.scrollBy({ left: d * t.clientWidth * 0.85, behavior: 'smooth' }) } }" x-init="$nextTick(() => sync())" data-new-slider>
                <div class="flex items-end justify-between gap-3">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">New on the shelf</h3>
                    <span class="hbs__nav">
                        <button type="button" x-on:click="by(-1)" :disabled="!prev" aria-label="Earlier handbooks"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                        <button type="button" x-on:click="by(1)" :disabled="!next" aria-label="Newer handbooks"><x-icon name="chevron-right" class="h-4 w-4" /></button>
                    </span>
                </div>
                <div class="hb-shelf hb-shelf--slider mt-4 text-slate-900 dark:text-white" x-ref="track" x-on:scroll.passive="sync()" tabindex="0" role="region" aria-label="Recently added handbooks, swipe to see more">@foreach ($fresh as $b)@include('handbooks._shelf-book', ['b' => $b])@endforeach</div>
            </div>
        @endif
    </div>
</section>

{{-- Why --}}
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 -mt-8 sm:py-24 dark:bg-[#0F2033]" data-bg="light">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">Why Naara handbooks</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Made to be used, not just owned</h2>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['wifi', 'Reads offline', 'Save a handbook once and read it on a plane, on a bus, anywhere with no signal.'],
                ['list', 'Jump to what you need', 'A chapter list you can search, so you land on the visa section, not page one.'],
                ['smartphone', 'Built for phones', 'Pictures, maps and tables fit your screen. Change the text size, theme and spacing to suit your eyes.'],
                ['shield-check', 'Dated and verified', 'Every book carries the date it was last checked. New editions arrive in your Library free.'],
                ['refresh', 'Picks up where you stopped', 'Your place and bookmarks follow you from your phone to your laptop.'],
                ['download', 'Keep a copy', 'Own a book to download it as an EPUB and a PDF, stamped with your name.'],
            ] as $i => [$ic, $t, $d])
                <div data-reveal style="--reveal-delay: {{ ($i % 3) * 0.06 }}s" class="group rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10 dark:border-white/10 dark:bg-[#16233d]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:bg-accent/15 dark:text-accent dark:group-hover:bg-accent dark:group-hover:text-[#0D1B2A]"><x-icon :name="$ic" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $t }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- FAQ --}}
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-white px-4 py-20 -mt-8 sm:py-24 dark:bg-[#0D1B2A]" data-bg="light">
    <div class="mx-auto max-w-3xl">
        <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Questions, answered</h2>
        <div class="mt-10 divide-y divide-slate-200 rounded-3xl border border-slate-200 dark:divide-white/10 dark:border-white/10" data-faq>
            @foreach ($faq as [$q, $a])
                <details class="group p-5 sm:p-6"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900 dark:text-white">{{ $q }}<x-icon name="chevron-right" class="h-5 w-5 flex-none text-slate-400 transition group-open:rotate-90" /></summary><p class="mt-3 leading-relaxed text-slate-600 dark:text-slate-300">{{ $a }}</p></details>
            @endforeach
        </div>
        <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">{{ $disclaimer }}</p>
    </div>
</section>

{{-- Closing call to action --}}
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-center text-white -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto max-w-2xl">
        <h2 data-reveal class="text-3xl font-bold sm:text-4xl">Know before you go.</h2>
        <p data-reveal class="mt-4 text-slate-300">Pick your destination and start reading in a minute.</p>
        <a href="{{ $storeUrl }}" wire:navigate class="nx-btn nx-btn--primary mt-8 !px-8 !py-3">{{ $s['cta_primary'] }}</a>
    </div>
</section>
</x-layouts.marketing>
