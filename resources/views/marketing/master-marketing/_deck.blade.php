{{-- MASTER ONLY. The one video player of the marketing site and the app: a poster card with a play button that turns into the player in place, for ONE video or a CAROUSEL of
     them (the next one starts by itself when a video ends; swipe, arrows and dots too). Expects $slot, $variant (hero | section | intro) and $items (VideoSlots::deck()).
     - Orientation: each video says landscape or portrait; the card, and full screen, follow it (landscape locks the screen to landscape, portrait to portrait, where the device allows).
     - Self-hosted files: no download button, no right-click menu, no picture-in-picture, played through a signed expiring link (VideoVault). In the apps, the screen is also
       protected from capture while a protected video plays.
     - Solid: the poster never depends on the video; nothing loads until play; a broken file shows its poster as a still card; a failure after play shows a retry. --}}
@php
    use App\Support\SiteContent;
    $items = array_values($items ?? []);
    $variant = $variant ?? 'hero';
    $heading = $heading ?? null;
    $count = count($items);
    $first = $items[0] ?? null;
@endphp
@if ($count)
<section class="nx-deck-wrap mx-auto w-full px-4 sm:px-6 {{ $variant === 'intro' ? 'max-w-3xl py-4' : 'max-w-5xl py-8 sm:py-12' }}" aria-label="{{ $heading ?: $first['title'] }}" data-video-deck="{{ $slot }}" data-variant="{{ $variant }}">
    <div x-data="naaraDeck(@js(['items' => $items, 'slot' => $slot, 'variant' => $variant]))" x-init="init()" x-show="!hidden" @keydown.escape.window="stop()" @fullscreenchange.window="fsChanged()" @message.window="onMessage($event)">
        @if ($heading)<h2 class="mb-4 text-center text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $heading }}</h2>@endif
        <div class="mx-auto" :class="portrait ? 'max-w-[min(100%,24rem)]' : 'w-full'">
            @if ($variant === 'intro')
                <div class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><span>Watch first</span><button type="button" @click="hide()" class="rounded-full px-2 py-1 normal-case tracking-normal hover:bg-slate-100 dark:hover:bg-white/10">Hide</button></div>
            @endif
            <div x-ref="stage" @contextmenu.prevent="if (cur.protect) $event.preventDefault()" @touchstart.passive="ts($event)" @touchend.passive="te($event)"
                 class="nx-deck-stage group relative overflow-hidden rounded-[22px] bg-[#050d16] shadow-2xl shadow-slate-900/30 ring-1 ring-black/10 sm:rounded-[30px] dark:shadow-black/60 dark:ring-white/10"
                 :class="portrait ? 'aspect-[9/16] max-h-[78vh]' : 'aspect-video'" style="background-image: linear-gradient(135deg, #0D1B2A, #0A6E6E)">

                {{-- picture first: each layer removes itself if it fails, so the next one shows --}}
                <template x-if="cur.poster"><img :src="cur.poster" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" draggable="false" class="absolute inset-0 h-full w-full object-cover" x-on:error="$el.remove()"></template>

                {{-- the media: only for the current video, only once it is playing or previewing --}}
                <template x-for="n in (cur.kind === 'file' ? [idx] : [])" :key="n">
                    <video x-ref="media" playsinline preload="metadata" muted :poster="cur.poster || null" :loop="!started && (cur.preview || cur.loop)"
                           :controlslist="cur.protect ? 'nodownload noremoteplayback noplaybackrate' : null" :disablepictureinpicture="cur.protect" :disableremoteplayback="cur.protect" draggable="false"
                           @loadeddata="ready = true; meta()" @loadedmetadata="meta()" @durationchange="meta()" @timeupdate="tick()" @progress="buffer()" @volumechange="muted = $el.muted" x-on:error="fail()" @ended="ended()" @play="playing = true; secure(true)" @pause="playing = false; started && secure(false)" @click="started && toggle()"
                           class="absolute inset-0 h-full w-full bg-black" :class="started ? 'object-contain' : 'object-cover'"><source :src="cur.src + '#t=0.1'" :type="cur.mime" x-on:error="fail()"></video>
                </template>
                <template x-if="cur.kind !== 'file' && started"><iframe x-ref="frame" :src="embedUrl()" :title="cur.title" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" @load="frameLoaded()" class="absolute inset-0 h-full w-full border-0"></iframe></template>

                {{-- overlay: label, play, title --}}
                <div x-show="!started" class="absolute inset-0">
                    <span class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-black/30" aria-hidden="true"></span>
                    <span x-show="cur.label" class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/25 backdrop-blur sm:left-6 sm:top-6 sm:text-sm"><span class="h-1.5 w-1.5 rounded-full bg-[#D4A017]"></span><span x-text="cur.label"></span></span>
                    <button type="button" x-show="!dead[idx]" @click="start()" class="absolute inset-0 flex items-center justify-center focus-visible:outline focus-visible:outline-4 focus-visible:-outline-offset-8 focus-visible:outline-white" :aria-label="'Play: ' + cur.title">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-[#0A6E6E] shadow-2xl transition duration-200 group-hover:scale-110 sm:h-24 sm:w-24"><x-icon name="play" class="h-7 w-7 translate-x-0.5 sm:h-10 sm:w-10" /></span>
                    </button>
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4 sm:p-6">
                        <p class="max-w-[75%] text-sm font-semibold leading-snug text-white drop-shadow sm:text-lg" x-text="cur.title"></p>
                        <span x-show="cur.duration" class="shrink-0 rounded-full bg-black/55 px-3 py-1 text-xs font-semibold text-white backdrop-blur" x-text="cur.duration"></span>
                    </div>
                </div>

                {{-- the wave bar: play, time, a wave of the video's own loudness that fills as it plays (click or drag to seek), sound, full screen --}}
                <div x-show="started && hasBar && !failed" x-cloak @pointerdown="poke()" @pointermove="poke()" @focusin="poke()" :class="ui ? 'opacity-100' : 'pointer-events-none opacity-0'"
                     class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/85 via-black/45 to-transparent px-3 pb-3 pt-12 transition-opacity duration-300 sm:px-5 sm:pb-4" data-wave-bar>
                    <div class="flex items-center gap-2.5 text-white sm:gap-3.5">
                        <button type="button" @click="toggle()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-[#0A6E6E] shadow-lg transition hover:scale-105" :aria-label="playing ? 'Pause' : 'Play'">
                            <svg x-show="!playing" class="h-4 w-4 translate-x-px" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z"/></svg>
                            <svg x-show="playing" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                        </button>
                        <span class="w-10 shrink-0 text-xs font-semibold tabular-nums sm:text-sm" x-text="fmt(t)">0:00</span>
                        <div x-ref="wave" class="relative h-11 min-w-0 flex-1 cursor-pointer touch-none select-none" role="slider" tabindex="0" aria-label="Seek" :aria-valuemin="0" :aria-valuemax="Math.round(dur)" :aria-valuenow="Math.round(t)" :aria-valuetext="fmt(t) + ' of ' + fmt(dur)"
                             @pointerdown.prevent="scrubStart($event)" @pointermove="scrubMove($event)" @pointerup="scrubEnd($event)" @pointercancel="scrubEnd($event)" @pointerleave="hoverT = null"
                             @keydown.arrow-left.prevent="seekBy(-5)" @keydown.arrow-right.prevent="seekBy(5)" @keydown.space.prevent="toggle()">
                            <svg viewBox="0 0 400 44" preserveAspectRatio="none" class="h-full w-full overflow-visible" aria-hidden="true">
                                <defs>
                                    <linearGradient :id="'wg' + uid" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0A6E6E"/><stop offset=".55" stop-color="#2DD4BF"/><stop offset="1" stop-color="#D4A017"/></linearGradient>
                                    <linearGradient :id="'wf' + uid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2DD4BF" stop-opacity=".55"/><stop offset="1" stop-color="#0A6E6E" stop-opacity="0"/></linearGradient>
                                    <clipPath :id="'wc' + uid"><rect x="0" y="-4" :width="pct * 400" height="52"/></clipPath>
                                    <clipPath :id="'wb' + uid"><rect x="0" y="-4" :width="bufPct * 400" height="52"/></clipPath>
                                </defs>
                                <path :d="area" fill="rgba(255,255,255,.10)"/>
                                <g :clip-path="'url(#wb' + uid + ')'"><path :d="area" fill="rgba(255,255,255,.16)"/></g>
                                <path :d="line" fill="none" stroke="rgba(255,255,255,.38)" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/>
                                <g :clip-path="'url(#wc' + uid + ')'">
                                    <path :d="area" :fill="'url(#wf' + uid + ')'"/>
                                    <path :d="line" fill="none" :stroke="'url(#wg' + uid + ')'" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 5px rgba(45,212,191,.85))"/>
                                </g>
                            </svg>
                            <span class="pointer-events-none absolute top-0 h-full w-px bg-white/60" :style="'left:' + (pct * 100) + '%'"></span>
                            <span class="pointer-events-none absolute h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[#0A6E6E] shadow-[0_0_12px_rgba(45,212,191,.95)]" :style="'left:' + (pct * 100) + '%; top:' + (headY * 100) + '%'"></span>
                            <span x-show="hoverT !== null" x-cloak class="pointer-events-none absolute -top-7 -translate-x-1/2 rounded-md bg-black/80 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-white" :style="'left:' + hoverX + '%'" x-text="fmt(hoverT || 0)"></span>
                        </div>
                        <span class="hidden w-10 shrink-0 text-xs font-semibold tabular-nums text-white/70 sm:block sm:text-sm" x-text="fmt(dur)">0:00</span>
                        <button type="button" @click="mute()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white/90 transition hover:bg-white/15" :aria-label="muted ? 'Unmute' : 'Mute'">
                            <svg x-show="!muted" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
                            <svg x-show="muted" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m22 9-6 6M16 9l6 6"/></svg>
                        </button>
                        <button type="button" @click="fullscreen()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white/90 transition hover:bg-white/15" :aria-label="isFs ? 'Exit full screen' : (portrait ? 'Full screen (portrait)' : 'Full screen (landscape)')">
                            <svg x-show="!isFs" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                            <svg x-show="isFs" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h3a2 2 0 0 0 2-2V3M21 8h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3M21 16h-3a2 2 0 0 0-2 2v3"/></svg>
                        </button>
                    </div>
                </div>
                {{-- a Vimeo player keeps its own controls; we only add full screen --}}
                <button type="button" x-show="started && !hasBar" x-cloak @click="fullscreen()" class="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur transition hover:bg-black/75 sm:right-4 sm:top-4" :aria-label="isFs ? 'Exit full screen' : 'Full screen'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                </button>

                {{-- carousel arrows --}}
                <template x-if="items.length > 1">
                    <div>
                        <button type="button" x-show="idx > 0" @click="prev()" class="absolute left-2 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur hover:bg-black/75" aria-label="Previous video"><x-icon name="chevron-left" class="h-5 w-5" /></button>
                        <button type="button" x-show="idx < items.length - 1" @click="next(false)" class="absolute right-2 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur hover:bg-black/75" aria-label="Next video"><x-icon name="chevron-right" class="h-5 w-5" /></button>
                    </div>
                </template>

                <div x-show="started && !ready && !failed" x-cloak class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/30"><span class="h-12 w-12 animate-spin rounded-full border-4 border-white/25 border-t-white"></span></div>
                <div x-show="failed" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/70 p-6 text-center text-white" role="alert">
                    <p class="text-sm font-medium sm:text-base">This video could not load right now.</p>
                    <button type="button" @click="retry()" class="rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#0A6E6E]">Try again</button>
                </div>
            </div>

            {{-- carousel: which one is playing, and what comes next --}}
            <template x-if="items.length > 1">
                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2" role="tablist" aria-label="Videos">
                        <template x-for="(it, i) in items" :key="it.id + '-' + i"><button type="button" role="tab" @click="go(i, started)" :aria-selected="i === idx" :aria-label="'Video ' + (i + 1) + ': ' + it.title" class="h-2 rounded-full transition-all" :class="i === idx ? 'w-6 bg-primary dark:bg-teal-300' : 'w-2 bg-slate-300 hover:bg-slate-400 dark:bg-white/25'"></button></template>
                    </div>
                    <p class="min-w-0 truncate text-xs text-slate-500 dark:text-slate-400"><span x-text="(idx + 1) + ' of ' + items.length"></span><span x-show="idx < items.length - 1"> · Next: <span x-text="items[idx + 1] && items[idx + 1].title"></span></span></p>
                </div>
            </template>
        </div>
    </div>

    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $first['title'], 'description' => $first['description'] ?: $first['title'],
        'thumbnailUrl' => $first['poster'] ?: null, 'uploadDate' => $first['created'] ? \Illuminate\Support\Carbon::parse($first['created'])->toIso8601String() : null,
        'embedUrl' => $first['kind'] !== 'file' ? preg_replace('/[?&]autoplay=1/', '', $first['embed']) : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @if ($variant === 'hero')
    <script>
        // Sit directly under the page's hero: after the first top-level block of <main>.
        (function () {
            var card = document.currentScript.parentElement, main = card && card.closest('main');
            if (!main) return;
            var first = main.firstElementChild;
            while (first && (first === card || first.tagName === 'SCRIPT' || first.tagName === 'TEMPLATE' || first.tagName === 'STYLE')) first = first.nextElementSibling;
            if (first && first !== card) first.after(card);
        })();
    </script>
    @endif
</section>
@once
<style>
    .nx-deck-stage:fullscreen { width: 100vw; height: 100vh; max-height: none; aspect-ratio: auto; border-radius: 0; box-shadow: none; }
    .nx-deck-stage:fullscreen video { object-fit: contain; }
    .nx-deck-stage video::-webkit-media-controls-enclosure { overflow: hidden; }
    @media print { .nx-deck-wrap { display: none; } }
</style>
<script>
    // In the Android and desktop apps the screen is hidden from capture while a protected video plays (the app exposes these two hooks; elsewhere this does nothing).
    window.nxSecure = window.nxSecure || function (on) {
        try { if (window.NaaraSecure && NaaraSecure.set) NaaraSecure.set(!!on); } catch (e) {}
        try { if (window.naaraDesktop && naaraDesktop.setContentProtection) naaraDesktop.setContentProtection(!!on); } catch (e) {}
    };
    function naaraDeck(cfg) {
        return {
            items: cfg.items, idx: 0, started: false, ready: false, failed: false, hidden: false, isFs: false, dead: {}, io: null, t1: null, t2: null, x0: null,
            t: 0, dur: 0, buf: 0, playing: false, muted: false, ui: true, uiT: null, scrubbing: false, hoverT: null, hoverX: 0, uid: Math.random().toString(36).slice(2, 7),
            get cur() { return this.items[this.idx] || {}; },
            get portrait() { return this.cur.orientation === 'portrait'; },
            get hasBar() { return this.cur.kind === 'file' || /youtube/.test(this.cur.embed || ''); },
            get pct() { return this.dur > 0 ? Math.min(1, this.t / this.dur) : 0; },
            get bufPct() { return this.dur > 0 ? Math.min(1, Math.max(this.buf, this.t) / this.dur) : 0; },
            // the wave: this video's loudness, drawn as a smooth line over a filled area
            get pts() { const w = this.cur.wave || [], n = w.length; return w.map((v, i) => [n > 1 ? i * 400 / (n - 1) : 0, 40 - Math.max(0.12, Math.min(1, v)) * 33]); },
            get line() {
                const p = this.pts; if (p.length < 2) return '';
                let d = 'M' + p[0][0].toFixed(1) + ',' + p[0][1].toFixed(1);
                for (let i = 0; i < p.length - 1; i++) {   // Catmull-Rom to Bezier: a smooth wave, not a polygon
                    const a = p[i - 1] || p[i], b = p[i], c = p[i + 1], e = p[i + 2] || c, k = 0.18;
                    d += 'C' + (b[0] + (c[0] - a[0]) * k).toFixed(1) + ',' + (b[1] + (c[1] - a[1]) * k).toFixed(1) + ' ' + (c[0] - (e[0] - b[0]) * k).toFixed(1) + ',' + (c[1] - (e[1] - b[1]) * k).toFixed(1) + ' ' + c[0].toFixed(1) + ',' + c[1].toFixed(1);
                }
                return d;
            },
            get area() { const l = this.line; return l ? l + 'L400,44L0,44Z' : ''; },
            get headY() { const p = this.pts; if (!p.length) return 0.5; const f = this.pct * (p.length - 1), i = Math.floor(f), j = Math.min(p.length - 1, i + 1), y = p[i][1] + (p[j][1] - p[i][1]) * (f - i); return y / 44; },
            init() {
                if (cfg.variant === 'intro') { try { if (localStorage.getItem('nx_deck_hide_' + cfg.slot) === '1') this.hidden = true; } catch (e) {} }
                if (!('IntersectionObserver' in window)) return;
                // silent looping preview for a file the admin asked it for: only while on screen, and never for reduced motion or Data Saver
                const calm = !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) && !(navigator.connection && navigator.connection.saveData);
                this.io = new IntersectionObserver((es) => es.forEach((e) => {
                    const m = this.$refs.media; if (!m || this.started || !this.cur.preview || !calm) return;
                    e.isIntersecting ? m.play().catch(() => {}) : m.pause();
                }), { threshold: 0.4 });
                this.io.observe(this.$el);
            },
            fmt(s) { s = Math.max(0, Math.floor(s || 0)); const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0'); },
            poke() { this.ui = true; clearTimeout(this.uiT); if (this.playing) this.uiT = setTimeout(() => { if (!this.scrubbing) this.ui = false; }, 2600); },
            meta() { const m = this.$refs.media; if (m && isFinite(m.duration)) this.dur = m.duration; },
            tick() { const m = this.$refs.media; if (!m || this.scrubbing) return; this.t = m.currentTime; this.buffer(); },
            buffer() { const m = this.$refs.media; if (!m || !m.buffered || !m.buffered.length) return; this.buf = m.buffered.end(m.buffered.length - 1); },
            yt(func, args) { const f = this.$refs.frame; if (f && f.contentWindow) f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: args || [] }), '*'); },
            toggle() { const m = this.$refs.media; if (m) { m.paused ? m.play().catch(() => {}) : m.pause(); } else { this.yt(this.playing ? 'pauseVideo' : 'playVideo'); } this.poke(); },
            mute() { const m = this.$refs.media; if (m) { m.muted = !m.muted; } else { this.muted = !this.muted; this.yt(this.muted ? 'mute' : 'unMute'); } },
            seekTo(sec) { sec = Math.max(0, Math.min(this.dur || 0, sec)); this.t = sec; const m = this.$refs.media; if (m) { try { m.currentTime = sec; } catch (e) {} } else { this.yt('seekTo', [sec, true]); } },
            seekBy(d) { this.seekTo(this.t + d); this.poke(); },
            frac(e) { const r = this.$refs.wave.getBoundingClientRect(); return Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)); },
            scrubStart(e) { this.scrubbing = true; this.$refs.wave.setPointerCapture && this.$refs.wave.setPointerCapture(e.pointerId); this.t = this.frac(e) * this.dur; this.poke(); this.$refs.wave.focus(); },
            scrubMove(e) { const f = this.frac(e); this.hoverT = f * this.dur; this.hoverX = f * 100; if (this.scrubbing) this.t = f * this.dur; },
            scrubEnd(e) { if (!this.scrubbing) return; this.scrubbing = false; this.seekTo(this.frac(e) * this.dur); },
            hide() { this.hidden = true; this.stop(); try { localStorage.setItem('nx_deck_hide_' + cfg.slot, '1'); } catch (e) {} },
            start() {
                this.started = true; this.failed = false; this.ready = false; this.t = 0; this.dur = 0; this.buf = 0; this.playing = this.cur.kind !== 'file' ? false : this.playing; this.poke();
                if (this.cur.kind !== 'file') { this.secure(false); return; }
                this.$nextTick(() => {
                    const m = this.$refs.media; if (!m) return;
                    clearTimeout(this.t1); clearTimeout(this.t2);
                    // a 404 or blocked file errors before this component exists: look at the element; and give a slow file a fair, bounded wait
                    this.t1 = setTimeout(() => { if (this.started && (m.error || m.networkState === 3)) this.fail(); }, 1200);
                    this.t2 = setTimeout(() => { if (this.started && m.readyState < 2) this.fail(); }, 12000);
                    // the element may already be loaded (it was showing the preview): its events will not fire again
                    if (m.readyState >= 2) this.ready = true; this.meta();
                    m.muted = false; this.muted = false; m.loop = false; try { m.currentTime = 0; } catch (e) {}
                    m.play().catch(() => { m.muted = true; m.play().catch(() => this.fail()); });
                });
            },
            stop() {
                if (!this.started) return;
                this.secure(false); this.exitFs();
                const m = this.$refs.media; if (m) { m.pause(); }
                this.started = false; this.ready = false; this.failed = false; this.playing = false;
            },
            ended() { this.items[this.idx + 1] ? this.next(true) : this.stop(); },
            next(autoplay) { this.go(this.idx + 1, autoplay); },
            prev() { this.go(this.idx - 1, this.started); },
            go(i, play) {
                if (i < 0 || i >= this.items.length) return;
                const wasFs = this.isFs;
                this.secure(false); this.started = false; this.ready = false; this.failed = false; this.playing = false; this.t = 0; this.dur = 0; this.buf = 0; this.idx = i;
                if (play) { this.$nextTick(() => this.start()); }
                if (wasFs && !play) this.exitFs();
            },
            fail() { clearTimeout(this.t1); clearTimeout(this.t2); if (this.started) this.failed = true; else this.dead[this.idx] = true; },
            retry() { this.failed = false; this.ready = false; const m = this.$refs.media; if (m) { m.load(); m.play().catch(() => {}); } else { this.started = false; this.$nextTick(() => this.start()); } },
            // full screen the card; the device turns to match how the video was filmed
            async fullscreen() {
                if (this.isFs) { this.exitFs(); return; }
                const st = this.$refs.stage, m = this.$refs.media;
                try {
                    await (st.requestFullscreen ? st.requestFullscreen({ navigationUI: 'hide' }) : st.webkitRequestFullscreen());
                    if (screen.orientation && screen.orientation.lock) { await screen.orientation.lock(this.portrait ? 'portrait' : 'landscape').catch(() => {}); }
                } catch (e) { if (m && m.webkitEnterFullscreen) m.webkitEnterFullscreen(); }
            },
            exitFs() { try { if (document.fullscreenElement) document.exitFullscreen(); if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock(); } catch (e) {} },
            fsChanged() { this.isFs = document.fullscreenElement === this.$refs.stage; if (!this.isFs) { try { screen.orientation && screen.orientation.unlock && screen.orientation.unlock(); } catch (e) {} } },
            // the apps hide the screen from capture while a protected video plays (see content-lock.js)
            secure(on) { try { if (this.cur.protect && window.nxSecure) window.nxSecure(!!on); } catch (e) {} },
            ts(e) { this.x0 = e.touches && e.touches[0] ? e.touches[0].clientX : null; },
            te(e) { if (this.x0 === null || this.items.length < 2) return; const x1 = e.changedTouches[0].clientX, d = x1 - this.x0; this.x0 = null; if (Math.abs(d) > 60) { d < 0 ? this.go(this.idx + 1, this.started) : this.go(this.idx - 1, this.started); } },
            // YouTube and Vimeo tell the page when a video ends, so the carousel can move on by itself
            embedUrl() {
                const u = this.cur.embed || '';
                return /youtube/.test(u) ? u + '&enablejsapi=1&controls=0&disablekb=1&fs=0&origin=' + encodeURIComponent(location.origin) : u;
            },
            frameLoaded() {
                this.ready = true; const f = this.$refs.frame; if (!f || !f.contentWindow) return;
                if (/youtube/.test(this.cur.embed)) { f.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*'); f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'addEventListener', args: ['onStateChange'] }), '*'); }
                if (/vimeo/.test(this.cur.embed)) { f.contentWindow.postMessage(JSON.stringify({ method: 'addEventListener', value: 'finish' }), '*'); }
            },
            onMessage(e) {
                const f = this.$refs.frame; if (!f || e.source !== f.contentWindow) return;
                if (!/^https:\/\/(www\.)?(youtube(-nocookie)?\.com|player\.vimeo\.com)$/.test(e.origin)) return;
                let d = e.data; if (typeof d === 'string') { try { d = JSON.parse(d); } catch (x) { return; } }
                if (d.event === 'infoDelivery' && d.info) { if (typeof d.info.currentTime === 'number' && !this.scrubbing) this.t = d.info.currentTime; if (typeof d.info.duration === 'number' && d.info.duration > 0) this.dur = d.info.duration; if (typeof d.info.playerState === 'number') this.playing = d.info.playerState === 1 || d.info.playerState === 3; if (typeof d.info.muted === 'boolean') this.muted = d.info.muted; }
                if ((d.event === 'onStateChange' && d.info === 0) || (d.event === 'infoDelivery' && d.info && d.info.playerState === 0) || d.event === 'finish') this.ended();
            },
        };
    }
</script>
@endonce
@endif
