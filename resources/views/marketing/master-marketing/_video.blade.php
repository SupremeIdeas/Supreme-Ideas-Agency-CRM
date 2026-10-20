{{-- The White Label video (MASTER-ONLY). Shown only once the admin has added a video (uploaded or a YouTube / Vimeo / .mp4 link) in Admin > Marketing site > White Label > Video.
     Branding comes from the section's own fields: player colour, logo watermark, poster, autoplay and loop. Expects $vd (the `video` section). --}}
@php
    use App\Support\MasterMarketing\VideoSource;
    use App\Support\SiteContent;
    $v = VideoSource::resolve($vd);
    $color = VideoSource::color($vd);
    $logo = VideoSource::on($vd, 'opt_logo', true) ? \App\Support\BrandSettings::resolvedLogo('product', 'dark') : null;
    $cfg = $v ? [
        'kind' => $v['kind'], 'embed' => $v['embed'], 'autoplay' => VideoSource::on($vd, 'opt_autoplay', false) && $v['kind'] === 'file', 'loop' => VideoSource::on($vd, 'opt_loop', false),
    ] : [];
    $src = $v && $v['kind'] === 'file' ? SiteContent::imageUrl($v['src']) : '';
@endphp
@if ($v)
<section id="watch" class="relative isolate overflow-hidden rounded-t-[30px] bg-[#F4FAFA] px-4 py-20 sm:py-24 -mt-8 dark:bg-[#0F2033] nx-ambient" data-bg="light">
    <div class="mx-auto max-w-5xl">
        <div class="mx-auto max-w-3xl text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[#6B4D00] dark:text-accent">{{ $vd['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $vd['headline'] }}</h2>
            <p data-reveal class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $vd['subtext'] }}</p>
        </div>

        <div data-reveal class="mt-10" x-data="mmPlayer(@js($cfg))">
            <div x-ref="root" tabindex="-1" role="region" aria-label="{{ $vd['video_title'] }}" @keydown="keys($event)" @mousemove.passive="poke()" @touchstart.passive="poke()"
                 :class="{ 'is-idle': idle && playing, 'is-fs': fs, 'is-playing': playing }" style="--mm-accent: {{ $color }}"
                 class="mm-player group relative aspect-video w-full overflow-hidden rounded-[1.25rem] bg-[#050d16] shadow-2xl shadow-slate-900/30 ring-1 ring-black/10 sm:rounded-[1.75rem] dark:shadow-black/60 dark:ring-white/10">

                {{-- The media --}}
                @if ($v['kind'] === 'file')
                    <video x-ref="video" playsinline preload="metadata" @if ($v['poster'] !== '') poster="{{ $v['poster'] }}" @endif @if ($cfg['loop']) loop @endif @click="toggle()"
                           class="absolute inset-0 h-full w-full bg-black object-contain"><source src="{{ $src }}#t=0.1" type="{{ $v['mime'] }}"></video>
                @else
                    <iframe x-ref="frame" x-show="started" x-cloak title="{{ $vd['video_title'] }}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 h-full w-full border-0"></iframe>
                @endif

                {{-- Poster + play (the only thing a visitor loads from YouTube or Vimeo before pressing play is this thumbnail) --}}
                <div x-show="!started" class="absolute inset-0 flex items-center justify-center" style="background: linear-gradient(135deg, #0D1B2A, color-mix(in srgb, var(--mm-accent) 55%, #0D1B2A))">
                    @if ($v['kind'] !== 'file' && $v['poster'] !== '')
                        <img src="{{ $v['poster'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                    @endif
                    <span class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-black/25" aria-hidden="true"></span>
                    <button type="button" @click="start()" class="mm-play relative flex h-20 w-20 items-center justify-center rounded-full text-white shadow-2xl transition hover:scale-105 focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-white sm:h-24 sm:w-24" aria-label="{{ $vd['play_label'] }}">
                        <x-icon name="play" class="relative h-9 w-9 translate-x-0.5 sm:h-10 sm:w-10" />
                    </button>
                    @if (! empty($vd['duration_label']))
                        <span class="absolute bottom-4 right-4 rounded-full bg-black/55 px-3 py-1 text-xs font-semibold text-white backdrop-blur">{{ $vd['duration_label'] }}</span>
                    @endif
                    <span class="absolute bottom-4 left-5 hidden text-sm font-semibold text-white/90 sm:block">{{ $vd['play_label'] }}</span>
                </div>

                {{-- Brand watermark --}}
                @if ($logo)
                    <img src="{{ SiteContent::imageUrl($logo) }}" alt="" class="pointer-events-none absolute left-4 top-4 z-10 h-6 w-auto opacity-80 drop-shadow sm:left-6 sm:top-5 sm:h-7" loading="lazy" decoding="async">
                @endif

                {{-- Spinner --}}
                <div x-show="loading && started" x-cloak class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center"><span class="h-12 w-12 animate-spin rounded-full border-4 border-white/25 border-t-white"></span></div>

                {{-- Own controls for files; a single full-screen button over YouTube and Vimeo --}}
                @if ($v['kind'] === 'file')
                    <div x-show="started && !ended" x-cloak class="mm-controls absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/75 via-black/35 to-transparent px-3 pb-3 pt-10 sm:px-5 sm:pb-4">
                        <input type="range" min="0" max="1000" step="1" :value="duration ? Math.round((current / duration) * 1000) : 0" @input="seekTo($event)" aria-label="{{ $vd['lbl_seek'] }}"
                               :aria-valuetext="fmt(current) + ' / ' + fmt(duration)" :style="`--p:${pct()}%;--b:${bufPct()}%`" class="mm-range w-full">
                        <div class="mt-1 flex items-center gap-1.5 text-white sm:gap-3">
                            <button type="button" @click="toggle()" class="mm-ctl" :aria-label="playing ? @js($vd['lbl_pause']) : @js($vd['lbl_play'])">
                                <x-icon name="play" class="h-5 w-5" x-show="!playing" /><x-icon name="pause" class="h-5 w-5" x-show="playing" x-cloak />
                            </button>
                            <button type="button" @click="mute()" class="mm-ctl" :aria-label="muted ? @js($vd['lbl_unmute']) : @js($vd['lbl_mute'])">
                                <x-icon name="volume-2" class="h-5 w-5" x-show="!muted" /><x-icon name="volume-x" class="h-5 w-5" x-show="muted" x-cloak />
                            </button>
                            <span class="select-none text-xs font-semibold tabular-nums sm:text-sm"><span x-text="fmt(current)">0:00</span> <span class="opacity-60">/</span> <span class="opacity-80" x-text="fmt(duration)">0:00</span></span>
                            <span class="flex-1"></span>
                            <button type="button" @click="cycleSpeed()" class="mm-ctl hidden !w-auto px-2.5 text-xs font-bold sm:inline-flex" aria-label="{{ $vd['lbl_speed'] }}"><span x-text="speed + 'x'">1x</span></button>
                            <button type="button" @click="toggleFullscreen()" class="mm-ctl" :aria-label="fs ? @js($vd['lbl_exit']) : @js($vd['lbl_fullscreen'])">
                                <x-icon name="maximize" class="h-5 w-5" x-show="!fs" /><x-icon name="minimize" class="h-5 w-5" x-show="fs" x-cloak />
                            </button>
                        </div>
                    </div>
                @else
                    <button type="button" x-show="started" x-cloak @click="toggleFullscreen()" class="mm-ctl mm-ctl--float absolute right-3 top-3 z-20 text-white sm:right-4 sm:top-4" :aria-label="fs ? @js($vd['lbl_exit']) : @js($vd['lbl_fullscreen'])">
                        <x-icon name="maximize" class="h-5 w-5" x-show="!fs" /><x-icon name="minimize" class="h-5 w-5" x-show="fs" x-cloak />
                    </button>
                @endif

                {{-- When a file ends: a call to action and replay --}}
                @if ($v['kind'] === 'file')
                    <div x-show="ended" x-cloak x-transition.opacity class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-5 bg-[#050d16]/85 p-6 text-center text-white backdrop-blur-sm">
                        <p class="max-w-md text-xl font-bold sm:text-2xl">{{ $vd['end_title'] }}</p>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ $vd['end_cta_href'] }}" @click="leaveFullscreen()" class="nx-btn nx-btn--primary !px-6 !py-2.5">{{ $vd['end_cta'] }}</a>
                            <button type="button" @click="replay()" class="nx-btn !px-6 !py-2.5 text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .5)"><x-icon name="rotate-ccw" class="h-4 w-4" />{{ $vd['replay_label'] }}</button>
                        </div>
                    </div>
                @endif

                <p x-show="failed" x-cloak class="absolute inset-0 z-30 flex items-center justify-center bg-black/80 p-6 text-center text-sm text-white">This video could not be loaded.</p>
            </div>
        </div>

        @if (! empty($vd['transcript']))
            <details data-reveal class="mx-auto mt-6 max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ $vd['transcript_label'] }}</summary>
                <div class="mt-4 space-y-3 text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                    @foreach (preg_split('/\R{2,}/', trim($vd['transcript'])) as $para)<p>{{ $para }}</p>@endforeach
                </div>
            </details>
        @endif
    </div>
</section>
@endif
