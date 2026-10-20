@php
    $hero = \App\Support\EsimHeroContent::current();
    $images = $hero['images'];
    // MASTER ONLY: the Travel Handbooks slide joins the carousel as its own slide (own copy, own link). A fork has no such class, so nothing changes there.
    $hb = class_exists(\App\Support\Handbooks\EsimHeroSlide::class) ? \App\Support\Handbooks\EsimHeroSlide::data() : null;
    $slides = [];
    foreach ($images as $src) {
        $slides[] = ['kind' => 'image', 'src' => $src];
    }
    if ($hb && $slides !== []) {
        array_splice($slides, min(1, count($slides)), 0, [['kind' => 'handbook']]);   // second in line: seen soon, never first
    } elseif ($hb) {
        $slides[] = ['kind' => 'handbook'];
    }
    $count = count($slides);
    $peek = $count >= 3; // a peek carousel needs 3+ to look right
@endphp
@if ($count > 0)
    @if ($peek)
        {{--
            Responsive hero: PHONES keep the single-slide full-bleed banner (peeks are too cramped on a small screen); TABLET + DESKTOP get the Apple-style
            3-up PEEK carousel. One Alpine instance drives both (shared `active`). Each slide carries its OWN headline and call to action.
        --}}
        <div x-data="esimHero({{ $count }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             tabindex="0" @keydown.arrow-left.prevent="prev()" @keydown.arrow-right.prevent="next()"
             role="region" aria-label="eSIM highlights" aria-roledescription="carousel">

            {{-- Phones (<md): the single-slide banner. --}}
            <div class="nx-imghero mb-8 aspect-[2/1] w-full overflow-hidden rounded-3xl border border-slate-200/60 shadow-sm dark:border-white/10 md:hidden">
                @foreach ($slides as $i => $s)
                    <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                        @if ($s['kind'] === 'handbook')
                            @include('partials.esim-hero-handbook', ['hb' => $hb, 'compact' => true])
                        @else
                            <img src="{{ $s['src'] }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="640">
                        @endif
                    </div>
                @endforeach
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                @foreach ($slides as $i => $s)
                    <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4" x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif>
                        @include('partials.esim-hero-copy', ['s' => $s, 'hero' => $hero, 'hb' => $hb, 'size' => 'sm'])
                    </div>
                @endforeach
                <div class="absolute right-4 top-4 flex gap-1.5">
                    @foreach ($slides as $i => $s)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            </div>

            {{-- Tablet + desktop (md+): the 3-up peek carousel, capped so it never swallows the screen. --}}
            <div class="nx-imghero nx-imghero--peek relative mx-auto mb-8 hidden h-64 w-full max-w-[64rem] md:block lg:h-[17.5rem]">
                <div class="nx-imghero__track" :style="`transform: translateX(${8 - active * 84}%)`">
                    @foreach ($slides as $i => $s)
                        <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'"
                             @click="go({{ $i }})" role="button" tabindex="-1"
                             aria-label="Show highlight {{ $i + 1 }}">
                            <div class="relative h-full w-full border border-slate-200/60 bg-slate-100 shadow-sm dark:border-white/10 dark:bg-white/5">
                                @if ($s['kind'] === 'handbook')
                                    @include('partials.esim-hero-handbook', ['hb' => $hb, 'compact' => false])
                                @else
                                    <img src="{{ $s['src'] }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="640" class="h-full w-full object-cover">
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="pointer-events-none absolute inset-y-0 left-1/2 w-[84%] -translate-x-1/2 overflow-hidden rounded-[var(--radius-card,1.5rem)]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                    @foreach ($slides as $i => $s)
                        <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-5" x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif>
                            @include('partials.esim-hero-copy', ['s' => $s, 'hero' => $hero, 'hb' => $hb, 'size' => 'lg'])
                        </div>
                    @endforeach
                </div>
                <div class="absolute right-[9%] top-4 flex gap-1.5">
                    @foreach ($slides as $i => $s)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        {{-- Fewer than 3 slides: the single-slide crossfade banner at every breakpoint (a peek with 1-2 slides would render broken peeks). --}}
        <div class="nx-imghero mx-auto mb-8 aspect-[2/1] w-full max-w-[64rem] overflow-hidden rounded-3xl border border-slate-200/60 shadow-sm dark:border-white/10"
             x-data="esimHero({{ $count }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             role="region" aria-label="eSIM highlights">
            @foreach ($slides as $i => $s)
                <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                    @if ($s['kind'] === 'handbook')
                        @include('partials.esim-hero-handbook', ['hb' => $hb, 'compact' => false])
                    @else
                        <img src="{{ $s['src'] }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="640">
                    @endif
                </div>
            @endforeach

            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

            @foreach ($slides as $i => $s)
                <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4 sm:p-5" x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif>
                    @include('partials.esim-hero-copy', ['s' => $s, 'hero' => $hero, 'hb' => $hb, 'size' => 'md'])
                </div>
            @endforeach

            @if ($count > 1)
                <div class="absolute right-4 top-4 flex gap-1.5">
                    @foreach ($slides as $i => $s)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @once
        <script>
            function esimHero(count) {
                return {
                    active: 0, timer: null, sx: 0,
                    init() { this.start(); },
                    start() { if (count > 1) this.timer = setInterval(() => this.next(), 5000); },
                    pause() { clearInterval(this.timer); },
                    resume() { this.pause(); this.start(); },
                    next() { this.active = (this.active + 1) % count; },
                    prev() { this.active = (this.active - 1 + count) % count; this.resume(); },
                    go(i) { this.active = i; this.resume(); },
                    touchStart(e) { this.sx = e.changedTouches[0].screenX; },
                    touchEnd(e) {
                        const dx = e.changedTouches[0].screenX - this.sx;
                        if (Math.abs(dx) < 40) return;
                        this.active = dx < 0 ? (this.active + 1) % count : (this.active - 1 + count) % count;
                        this.resume();
                    },
                };
            }
        </script>
    @endonce
@endif
