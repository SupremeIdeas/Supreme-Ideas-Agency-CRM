/**
 * NaaraSound: foreground notification sounds, vibration and the looping ringtone (Batch 2).
 *
 * What this can and cannot do, stated once and honestly:
 *  - It plays custom tones only while the app is open on screen. A closed or backgrounded tab belongs to the OS/browser (push
 *    notifications use the device's own sound), so nothing here pretends otherwise.
 *  - Browsers refuse audio until the person has interacted with the page. We unlock on the first tap/click/key; a sound that would play
 *    before that is skipped silently (never queued, so it can't blast out later). That is normal browser behaviour, not a bug.
 *  - navigator.vibrate exists on Android (Chrome/Firefox); iOS Safari has never shipped it, so vibration is simply a no-op there.
 *
 * Config comes from <meta name="nx-sound">: {enabled, vibrate, tones: {"category.slot": url}}, per member (their pick, else the admin default).
 * Web Audio (not <audio>) for low-latency, overlap-safe playback; decoded buffers are cached per URL.
 */
const PATTERNS = {
    'credit_alert.default': [60, 40, 60],
    'message.general': [40],
    'message.naara': [40, 30, 40],
    'message.naaraline': [25, 25, 25, 25, 25],
    'reward.default': [30, 30, 30, 30, 90],
    'push.default': [90],
    'ringtone.default': [450, 250],
    'beep.send': [15],
    'beep.error': [70, 40, 70],
};

function readConfig() {
    try {
        const meta = document.querySelector('meta[name="nx-sound"]');
        const parsed = meta ? JSON.parse(meta.getAttribute('content') || '{}') : {};
        return { enabled: parsed.enabled !== false, vibrate: parsed.vibrate !== false, tones: parsed.tones || {} };
    } catch (e) {
        return { enabled: false, vibrate: false, tones: {} };
    }
}

class SoundEngine {
    constructor() {
        this.ctx = null;
        this.buffers = new Map();
        this.pending = new Map();
        this.ringSource = null;
        this.ringVibrateTimer = null;
        this.unlocked = false;
    }

    config() { return readConfig(); }

    /** Create/resume the AudioContext. Only effective inside a user gesture; harmless anywhere else. */
    unlock() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return false;
        if (!this.ctx) this.ctx = new AC();
        if (this.ctx.state === 'suspended') this.ctx.resume().catch(() => {});
        this.unlocked = this.ctx.state === 'running' || this.unlocked;
        return this.ctx.state !== 'closed';
    }

    async buffer(url) {
        if (this.buffers.has(url)) return this.buffers.get(url);
        if (this.pending.has(url)) return this.pending.get(url);
        const job = fetch(url, { credentials: 'same-origin' })
            .then((r) => { if (!r.ok) throw new Error('tone ' + r.status); return r.arrayBuffer(); })
            .then((data) => new Promise((res, rej) => this.ctx.decodeAudioData(data, res, rej)))
            .then((buf) => { this.buffers.set(url, buf); this.pending.delete(url); return buf; })
            .catch((e) => { this.pending.delete(url); throw e; });
        this.pending.set(url, job);
        return job;
    }

    vibrate(pattern) {
        try { if (navigator.vibrate && pattern) navigator.vibrate(pattern); } catch (e) { /* unsupported */ }
    }

    async start(url, loop) {
        if (!this.ctx || this.ctx.state !== 'running') return null;   // not unlocked yet: skip, never queue
        const buf = await this.buffer(url);
        const src = this.ctx.createBufferSource();
        src.buffer = buf; src.loop = !!loop;
        const gain = this.ctx.createGain(); gain.gain.value = 0.9;
        src.connect(gain).connect(this.ctx.destination);
        src.start();
        return src;
    }

    /** Play the member's tone for a "category.slot" key (or "category" = its default slot). Honours the member's sound/vibrate switches. */
    async play(key, opts = {}) {
        const cfg = this.config();
        if (!key.includes('.')) key += key === 'message' ? '.general' : '.default';
        if (cfg.enabled && cfg.tones[key]) { try { await this.start(cfg.tones[key], false); } catch (e) { /* a broken tone must never break the page */ } }
        if (cfg.vibrate && opts.vibrate !== false) this.vibrate(PATTERNS[key]);
    }

    /**
     * Settings-screen preview: plays regardless of the master switch (the person just asked to hear it). Press again to stop; starting another tone stops the first.
     * Tells the page which tone is sounding (nx-sound-preview) so a row can show a stop icon and moving bars, and clears it the instant the tone ends.
     */
    async preview(url) {
        this.unlock();
        const same = this.previewing && this.previewing.url === url;
        this.stopPreview();
        if (same) return;
        try {
            const src = await this.start(url, false);
            if (!src) return;
            this.previewing = { url, src };
            this.announcePreview(url, true);
            src.onended = () => { if (this.previewing && this.previewing.src === src) { this.previewing = null; this.announcePreview(url, false); } };
        } catch (e) { /* ignore */ }
    }

    stopPreview() {
        const p = this.previewing;
        if (!p) return;
        this.previewing = null;
        try { p.src.onended = null; p.src.stop(); } catch (e) { /* already stopped */ }
        this.announcePreview(p.url, false);
    }

    announcePreview(url, playing) {
        window.dispatchEvent(new CustomEvent('nx-sound-preview', { detail: { url, playing } }));
    }

    /** Incoming-call ring: loops until stopRing(). Idempotent. */
    async startRing(key = 'ringtone.default') {
        const cfg = this.config();
        this.stopRing();
        if (cfg.enabled && cfg.tones[key]) { try { this.ringSource = await this.start(cfg.tones[key], true); } catch (e) { /* ignore */ } }
        if (cfg.vibrate) {
            const pat = PATTERNS[key] || [450, 250];
            this.vibrate(pat);
            this.ringVibrateTimer = setInterval(() => this.vibrate(pat), pat.reduce((a, b) => a + b, 0));
        }
    }

    stopRing() {
        try { this.ringSource && this.ringSource.stop(); } catch (e) { /* already stopped */ }
        this.ringSource = null;
        if (this.ringVibrateTimer) { clearInterval(this.ringVibrateTimer); this.ringVibrateTimer = null; }
        this.vibrate(0);
    }
}

export function registerNotificationSound() {
    if (window.NaaraSound) return;
    const engine = new SoundEngine();
    window.NaaraSound = engine;

    const unlock = () => engine.unlock();
    ['pointerdown', 'touchend', 'keydown', 'click'].forEach((ev) => window.addEventListener(ev, unlock, { passive: true }));

    // Server- or page-triggered: window.dispatchEvent(new CustomEvent('nx-sound', {detail: {key: 'reward.default'}})) or $this->dispatch('nx-sound', key: ...)
    window.addEventListener('nx-sound', (e) => {
        const d = e.detail || {};
        const key = d.key || (d.category ? d.category + (d.slot ? '.' + d.slot : '') : null);
        if (key) engine.play(key);
    });
    // The settings screen pushes a fresh config after a save so SPA navigation does not keep serving the old picks.
    window.addEventListener('nx-sound-config', (e) => {
        const meta = document.querySelector('meta[name="nx-sound"]');
        if (meta && e.detail) meta.setAttribute('content', JSON.stringify(e.detail));
    });
    // Beep tones: the shared chat composer announces a send (cc:sent) and a final failure (cc:error). The member's own sound switch and tone pick apply as for every sound.
    window.addEventListener('cc:sent', () => engine.play('beep.send'));
    window.addEventListener('cc:error', (e) => { if (e.detail && e.detail.fatal) engine.play('beep.error'); });
    window.addEventListener('nx-ring-start', (e) => engine.startRing((e.detail && e.detail.key) || 'ringtone.default'));
    window.addEventListener('nx-ring-stop', () => engine.stopRing());
}
