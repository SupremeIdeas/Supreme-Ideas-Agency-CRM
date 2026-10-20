{{-- Brand preloader (Module 26 + Preloader Studio). A full-screen overlay shown
     until the page finishes loading, then faded out. The visual is resolved
     per-PAGE-TYPE via PreloaderSettings::forPageType(): until an admin saves an
     assignment in Preloader Studio, forPageType() returns the exact legacy
     behaviour (BrandSettings enabled + style), so this is unchanged on every
     existing install. Configured page types can pick any Studio preset.

     Self-removing on `load` with a hard timeout fallback so it can never trap the
     page, and reduced-motion-aware. The background is painted inline before
     Alpine boots, so there is no theme flash. --}}
@props(['pageType' => 'default'])
@php
    $cfg = \App\Support\PreloaderSettings::forPageType($pageType);
    // NaaraBrain has its own animated splash: on by default, and the admin keeps control by assigning (or disabling) the 'brain' page type in Preloader Studio.
    if ($pageType === 'brain' && ! isset(\App\Support\PreloaderSettings::assignments()['brain'])) {
        $cfg = array_merge($cfg, ['enabled' => true]);
    }
    // Admin-only debug preview (BLUEPRINT §7.2): ?preloader_preview=slug forces a
    // preset on any page so the port can be verified preset-by-preset. Gated to
    // privileged users, so it can never be triggered by a public visitor.
    $nxPreview = request()->query('preloader_preview');
    if ($nxPreview && ($u = auth()->user()) && $u->hasAnyRole(['super_admin', 'admin'])) {
        if (\App\Support\PreloaderSettings::isLegacy($nxPreview) || isset(\App\Support\PreloaderSettings::PRESETS[$nxPreview])) {
            $cfg = array_merge(\App\Support\PreloaderSettings::safeDefault(), ['preset' => $nxPreview, 'enabled' => true]);
        }
    }
@endphp
@if (! empty($cfg['enabled']))
    @php($isLegacy = \App\Support\PreloaderSettings::isLegacy($cfg['preset']))
    @php($nxPreFavicon = \App\Support\BrandSettings::favicon())
    @php($vars = \App\Support\PreloaderSettings::resolveCssVars($cfg))
    @php($bgRgb = ! empty($cfg['bg_color']) ? \App\Support\BrandSettings::hexToChannels($cfg['bg_color']) : null)
    {{-- A custom (uploaded) animation was drawn for one mode, so with no explicit background it follows the visitor's mode (white / navy). --}}
    @php($plTheme = \App\Support\PreloaderSettings::theme($cfg))
    @php($bg = $bgRgb ? 'rgb('.$bgRgb.' / '.($cfg['bg_opacity'] ?? 1).')' : ($plTheme !== 'auto' ? \App\Support\PreloaderSettings::CANVAS[$plTheme] : (($cfg['preset'] ?? '') === \App\Support\PreloaderSettings::CUSTOM ? 'var(--nx-pl-custom-bg)' : 'rgb(var(--brand-navy))')))
    <div id="nx-preloader" role="status" aria-label="Loading"
         data-page-type="{{ $pageType }}" data-pl-theme="{{ $plTheme }}" data-min-display-ms="{{ (int) ($cfg['min_display_ms'] ?? 0) }}" data-max-per-day="{{ (int) ($cfg['max_per_day'] ?? 0) }}"
         style="position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:{{ $bg }};transition:opacity .4s ease;{{ $isLegacy ? '' : $vars.'backdrop-filter:blur(var(--nx-pl-blur,0));-webkit-backdrop-filter:blur(var(--nx-pl-blur,0));' }}">

        @if ($isLegacy)
            @php($nxPreStyle = $cfg['preset'])
            @if ($nxPreStyle === 'pulse-logo' && $nxPreFavicon)
                {{-- Impulse logo mark (shared .nx-pulse motif, compiled in app.css). --}}
                <span class="nx-pulse" style="width:72px;height:72px;">
                    <img src="{{ $nxPreFavicon }}" alt="" width="72" height="72" draggable="false">
                </span>
            @elseif ($nxPreStyle === 'bars')
                <span class="nx-pre-bars"><i></i><i></i><i></i><i></i></span>
            @elseif ($nxPreStyle === 'progress')
                {{-- Determinate-feel progress bar: eases toward 100% while the page
                     loads, then snaps full on `load` (JS below). Logo mark sits above
                     it when a favicon exists, so the brand still reads. --}}
                <span class="nx-pre-progress-wrap">
                    @if ($nxPreFavicon)
                        <img class="nx-pre-progress-logo" src="{{ $nxPreFavicon }}" alt="" width="56" height="56" draggable="false">
                    @endif
                    <span class="nx-pre-progress"><i></i></span>
                </span>
            @else
                <span class="nx-pre-spin"></span>
            @endif
        @elseif (view()->exists('components.preloaders.'.$cfg['preset']))
            {{-- Studio preset: markup-only partial, all CSS compiled in preloaders.css. --}}
            <div class="nx-pl nx-pl--{{ $cfg['preset'] }}" style="opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));">
                @include('components.preloaders.'.$cfg['preset'], ['cfg' => $cfg])
            </div>
        @else
            {{-- Assigned preset partial not present (defensive) — safe spinner. --}}
            <span class="nx-pre-spin"></span>
        @endif

        @if ($pageType === 'brain')
            {{-- NaaraBrain only: its own animated splash, centred over whatever background the preloader resolved, with the name beneath. Motion-reduced visitors get the still. --}}
            @php($nxBrainSplash = \App\Support\Brain\BrainSettings::logo('splash'))
            <div class="nx-pre-brain" aria-hidden="true">
                <picture>
                    <source media="(prefers-reduced-motion: reduce)" srcset="{{ \App\Support\Brain\BrainSettings::logo('splash', true) }}">
                    <img src="{{ $nxBrainSplash }}" alt="" width="200" height="186" draggable="false">
                </picture>
                <span>NaaraBrain</span>
            </div>
            <style>.nx-pre-brain { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; background:inherit; z-index:2; } .nx-pre-brain img { width:200px; height:auto; max-width:60vw; object-fit:contain; } .nx-pre-brain span { padding:8px 20px; border-radius:9999px; background:rgb(var(--brand-navy)); color:#fff; font:700 15px/1.2 system-ui,-apple-system,sans-serif; letter-spacing:.02em; }</style>
        @endif

        @if ($pageType === 'n2n')
            {{-- Naara chat only: say whose app this is, at a glance. A solid navy pill with white text, so it reads on any preloader background. --}}
            <div class="nx-pre-appname" aria-hidden="true">NaaraChat</div>
            <style>.nx-pre-appname { position:absolute; left:50%; bottom:max(28px, env(safe-area-inset-bottom)); transform:translateX(-50%); padding:8px 20px; border-radius:9999px; background:rgb(var(--brand-navy)); color:#fff; font:700 15px/1.2 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; letter-spacing:.04em; box-shadow:0 0 0 1px rgba(255,255,255,.18); }</style>
        @endif

        <style>
/* Spinner ring. */
            .nx-pre-spin { display:block; width:3rem; height:3rem; border-radius:9999px;
                border:3px solid rgba(255,255,255,.18); border-top-color:rgb(var(--brand-accent));
                animation:nx-preload-spin .8s linear infinite; }
            @keyframes nx-preload-spin { to { transform:rotate(360deg); } }

            /* Bars. */
            .nx-pre-bars { display:flex; gap:.4rem; align-items:flex-end; height:2.75rem; }
            .nx-pre-bars i { width:.4rem; height:100%; border-radius:.25rem; background:rgb(var(--brand-accent));
                animation:nx-pre-bar 1s ease-in-out infinite; }
            .nx-pre-bars i:nth-child(2){ animation-delay:.15s } .nx-pre-bars i:nth-child(3){ animation-delay:.3s } .nx-pre-bars i:nth-child(4){ animation-delay:.45s }
            @keyframes nx-pre-bar { 0%,100%{ transform:scaleY(.4); opacity:.6 } 50%{ transform:scaleY(1); opacity:1 } }

            /* Progress bar. */
            .nx-pre-progress-wrap { display:flex; flex-direction:column; align-items:center; gap:1.25rem; }
            .nx-pre-progress-logo { border-radius:14px; animation:nx-pre-breathe 1.6s ease-in-out infinite; }
            .nx-pre-progress { display:block; width:min(60vw,200px); height:4px; border-radius:9999px;
                background:rgba(255,255,255,.14); overflow:hidden; }
            .nx-pre-progress i { display:block; height:100%; width:0; border-radius:9999px;
                background:linear-gradient(90deg, rgb(var(--brand-primary)), rgb(var(--brand-accent)));
                transition:width .35s ease-out; }
            @keyframes nx-pre-breathe { 0%,100%{ transform:scale(1); opacity:.9 } 50%{ transform:scale(1.06); opacity:1 } }

            #nx-preloader.is-done { opacity:0; pointer-events:none; }
            @media (prefers-reduced-motion: reduce) {
                #nx-preloader .nx-pre-spin, #nx-preloader .nx-pre-bars i,
                #nx-preloader .nx-pre-progress-logo { animation:none; }
            }
        </style>
    </div>
    <script>
        (function () {
            var el = document.getElementById('nx-preloader');
            if (!el) return;

            // Phase D — daily-frequency cap. Checked FIRST, before any timers or
            // animation start, so a capped-out visitor never sees the overlay at
            // all for the rest of the day. Tracked client-side (localStorage) on
            // a rolling calendar-day key, per page-type — this codebase has no
            // server-side per-visitor session store cheap enough for this.
            var maxPerDay = parseInt(el.getAttribute('data-max-per-day'), 10) || 0;
            if (maxPerDay > 0) {
                var pageType = el.getAttribute('data-page-type') || 'default';
                var today = new Date().toISOString().slice(0, 10);
                var storageKey = 'nx_preloader_seen:' + pageType + ':' + today;
                var seen = 0;
                try { seen = parseInt(localStorage.getItem(storageKey), 10) || 0; } catch (e) {}
                if (seen >= maxPerDay) {
                    el.remove();
                    return;
                }
                try { localStorage.setItem(storageKey, String(seen + 1)); } catch (e) {}
            }

            var minDisplayMs = parseInt(el.getAttribute('data-min-display-ms'), 10) || 0;
            var shownAt = Date.now();
            var hide = function () {
                var elapsed = Date.now() - shownAt;
                var remaining = minDisplayMs - elapsed;
                if (remaining > 0) {
                    setTimeout(doHide, remaining);
                } else {
                    doHide();
                }
            };
            var doHide = function () {
                el.classList.add('is-done');
                setTimeout(function () { el.remove(); }, 500);
            };

            // Progress style: ease the bar toward ~90% while loading, then snap to
            // 100% on `load` just before fading out (honest, never stuck at 100).
            var bar = el.querySelector('.nx-pre-progress i');
            var timer = null;
            if (bar) {
                var pct = 0;
                timer = setInterval(function () {
                    pct += Math.max(1, (90 - pct) * 0.12); // decelerating approach
                    bar.style.width = Math.min(pct, 90) + '%';
                }, 200);
            }
            var finish = function () {
                if (timer) { clearInterval(timer); timer = null; }
                if (bar) { bar.style.width = '100%'; }
                setTimeout(hide, bar ? 250 : 150);
            };

            // Livewire `wire:navigate` swaps this whole overlay in fresh on every
            // in-app link click — but it's a client-side morph of an ALREADY-LOADED
            // page, not a real navigation, so the browser's `load` event never fires
            // again. Without this check every single in-app click sat here doing
            // nothing until the 4s hard-fallback below, which is exactly the
            // multi-second "hang on every page" bug this fixes: if the document is
            // already complete by the time this script runs (true on every
            // wire:navigate transition, never true on the real first load), finish
            // immediately instead of waiting on an event that will never come.
            if (document.readyState === 'complete') {
                finish();
            } else {
                window.addEventListener('load', finish);
            }
            setTimeout(finish, 4000); // hard fallback — never trap the page
        })();
    </script>
@endif
