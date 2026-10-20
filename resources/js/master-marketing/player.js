// MASTER-ONLY. The branded video player on the White Label sales page (resources/views/marketing/master-marketing/_video.blade.php).
// One component for four sources: an uploaded or linked file (own controls), YouTube and Vimeo (a click-to-load facade, so nothing from a third party loads
// until the visitor presses play). Full screen goes landscape on phones. Registered on alpine:init like the other components in app.js.
function register(Alpine) {
    Alpine.data('mmPlayer', (cfg) => ({
        cfg,
        started: false, playing: false, ended: false, muted: false, current: 0, duration: 0, buffered: 0,
        speed: 1, fs: false, idle: false, loading: false, failed: false, autoStarted: false,
        _hide: null, _io: null,
        speeds: [1, 1.25, 1.5, 2, 0.75],

        init() {
            const v = this.$refs.video;
            if (this.cfg.kind === 'file' && v) {
                v.addEventListener('loadedmetadata', () => { this.duration = v.duration || 0; });
                v.addEventListener('durationchange', () => { this.duration = v.duration || 0; });
                v.addEventListener('timeupdate', () => { this.current = v.currentTime; });
                v.addEventListener('progress', () => { try { this.buffered = v.buffered.length ? v.buffered.end(v.buffered.length - 1) : 0; } catch (e) {} });
                v.addEventListener('play', () => { this.playing = true; this.ended = false; this.loading = false; this.poke(); });
                v.addEventListener('pause', () => { this.playing = false; this.idle = false; });
                v.addEventListener('waiting', () => { this.loading = true; });
                v.addEventListener('playing', () => { this.loading = false; });
                v.addEventListener('ended', () => { this.ended = true; this.playing = false; this.idle = false; if (document.fullscreenElement || document.webkitFullscreenElement) { this.leaveFullscreen(); } });
                v.addEventListener('volumechange', () => { this.muted = v.muted || v.volume === 0; });
                v.addEventListener('error', () => { this.failed = true; this.loading = false; });
                if (this.cfg.autoplay && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
                    this._io = new IntersectionObserver((entries) => {
                        entries.forEach((e) => {
                            if (e.isIntersecting && e.intersectionRatio >= 0.6 && !this.started) {
                                this.autoStarted = true; v.muted = true; this.started = true; v.play().catch(() => { this.started = false; });
                            } else if (!e.isIntersecting && this.autoStarted && this.playing) {
                                v.pause();
                            } else if (e.isIntersecting && e.intersectionRatio >= 0.6 && this.autoStarted && !this.playing && !this.ended) {
                                v.play().catch(() => {});
                            }
                        });
                    }, { threshold: [0, 0.6] });
                    this._io.observe(this.$refs.root);
                }
            }
            const onFs = () => {
                this.fs = !!(document.fullscreenElement || document.webkitFullscreenElement) && (document.fullscreenElement || document.webkitFullscreenElement) === this.$refs.root;
                if (!this.fs) { this.unlockOrientation(); }
            };
            document.addEventListener('fullscreenchange', onFs);
            document.addEventListener('webkitfullscreenchange', onFs);
            if (v) { v.addEventListener('webkitendfullscreen', () => { this.fs = false; }); }
        },

        destroy() { if (this._io) { this._io.disconnect(); } clearTimeout(this._hide); },

        // ---- playback -------------------------------------------------------------------------------------------------------------------
        start() {
            this.started = true; this.ended = false;
            if (this.cfg.kind === 'file') {
                const v = this.$refs.video; this.loading = true;
                v.muted = false; v.play().catch(() => { v.muted = true; v.play().catch(() => { this.loading = false; }); });
            } else {
                this.$nextTick(() => { this.$refs.frame.src = this.cfg.embed; });
            }
        },
        toggle() { if (!this.started) { return this.start(); } const v = this.$refs.video; if (!v) { return; } if (v.paused || v.ended) { this.ended = false; v.play(); } else { v.pause(); } },
        replay() { const v = this.$refs.video; if (v) { v.currentTime = 0; this.ended = false; v.play(); } else { this.$refs.frame.src = this.cfg.embed + '&r=' + Date.now(); this.ended = false; } },
        seekTo(e) { const v = this.$refs.video; if (v && this.duration) { v.currentTime = (e.target.value / 1000) * this.duration; this.current = v.currentTime; } },
        skip(s) { const v = this.$refs.video; if (v) { v.currentTime = Math.max(0, Math.min(this.duration || 0, v.currentTime + s)); } },
        mute() { const v = this.$refs.video; if (v) { v.muted = !v.muted; if (!v.muted && v.volume === 0) { v.volume = 1; } } },
        cycleSpeed() { const v = this.$refs.video; const i = (this.speeds.indexOf(this.speed) + 1) % this.speeds.length; this.speed = this.speeds[i]; if (v) { v.playbackRate = this.speed; } },
        pct() { return this.duration ? Math.min(100, (this.current / this.duration) * 100) : 0; },
        bufPct() { return this.duration ? Math.min(100, (this.buffered / this.duration) * 100) : 0; },
        fmt(t) { t = Math.max(0, Math.floor(t || 0)); const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0'); },

        // ---- full screen (landscape on phones) ------------------------------------------------------------------------------------------
        toggleFullscreen() { return this.fs ? this.leaveFullscreen() : this.enterFullscreen(); },
        async enterFullscreen() {
            const r = this.$refs.root, v = this.$refs.video;
            try {
                if (r.requestFullscreen) { await r.requestFullscreen({ navigationUI: 'hide' }); }
                else if (r.webkitRequestFullscreen) { r.webkitRequestFullscreen(); }
                else if (v && v.webkitEnterFullscreen) { v.webkitEnterFullscreen(); return; }   // iPhone Safari only full-screens the <video> itself
                this.fs = true; this.lockLandscape();
            } catch (e) { /* the browser said no: stay inline */ }
        },
        leaveFullscreen() {
            if (document.exitFullscreen && document.fullscreenElement) { document.exitFullscreen(); }
            else if (document.webkitExitFullscreen && document.webkitFullscreenElement) { document.webkitExitFullscreen(); }
            this.fs = false; this.unlockOrientation();
        },
        lockLandscape() {
            try { if (window.matchMedia('(pointer: coarse)').matches && screen.orientation && screen.orientation.lock) { screen.orientation.lock('landscape').catch(() => {}); } } catch (e) {}
        },
        unlockOrientation() { try { if (screen.orientation && screen.orientation.unlock) { screen.orientation.unlock(); } } catch (e) {} },

        // ---- controls visibility + keyboard ---------------------------------------------------------------------------------------------
        poke() { this.idle = false; clearTimeout(this._hide); if (this.playing) { this._hide = setTimeout(() => { if (this.playing) { this.idle = true; } }, 2600); } },
        keys(e) {
            if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) && e.key !== 'Escape' && e.target.type !== 'range') { return; }
            const k = e.key.toLowerCase();
            if (k === ' ' || k === 'k') { e.preventDefault(); this.toggle(); }
            else if (k === 'f') { e.preventDefault(); this.toggleFullscreen(); }
            else if (k === 'm') { this.mute(); }
            else if (k === 'arrowright' && this.cfg.kind === 'file' && e.target.type !== 'range') { e.preventDefault(); this.skip(5); }
            else if (k === 'arrowleft' && this.cfg.kind === 'file' && e.target.type !== 'range') { e.preventDefault(); this.skip(-5); }
            this.poke();
        },
    }));
}

if (window.Alpine) { register(window.Alpine); } else { document.addEventListener('alpine:init', () => register(window.Alpine)); }
