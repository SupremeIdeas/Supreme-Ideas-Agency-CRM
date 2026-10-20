// Voice capture for one composer instance: record -> pause (scrub-preview) -> resume -> send. No globals.
// The MIME is chosen from what the browser can really record (webm/opus, mp4 on Safari, ogg) — never assumed — and the server's
// existing voice-note validation + transcoder remain the authority on what is accepted.

export const SAMPLE_MS = 50;
const MIMES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'];

export function pickMime() {
    if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') return '';
    return MIMES.find((t) => MediaRecorder.isTypeSupported(t)) || '';
}

export const extFor = (mime) => (mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm');

export function voiceSupported() {
    return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function' && typeof MediaRecorder !== 'undefined');
}

export class VoiceCapture {
    constructor({ onLevel = () => {}, onTick = () => {}, onCaption = () => {}, lang = 'en', captions = true, maxSeconds = 300 } = {}) {
        Object.assign(this, { onLevel, onTick, onCaption, lang, captions, maxSeconds });
        this.state = 'idle'; // idle | active | paused
        this.levels = [];   // 0..1 loudness, one per SAMPLE_MS while recording (the waveform of the note)
        this.level = 0;     // the instantaneous, smoothed level (drives the live bars between samples)
        this.lastSampleAt = 0;
        this.chunks = [];
        this.mime = '';
        this.transcript = '';
        this.startedAt = 0; this.pausedAt = 0; this.totalPaused = 0;
        this._timers = [];
    }

    get elapsed() {
        const end = this.state === 'paused' ? this.pausedAt : Date.now();
        return Math.max(0, (end - this.startedAt - this.totalPaused) / 1000);
    }

    async start() {
        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        this.chunks = []; this.levels = []; this.level = 0; this.totalPaused = 0; this.transcript = '';
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (Ctx) {
            this.ctx = new Ctx();
            if (this.ctx.state === 'suspended') { try { await this.ctx.resume(); } catch { /* autoplay policy */ } }
            const src = this.ctx.createMediaStreamSource(this.stream);
            this.analyser = this.ctx.createAnalyser(); this.analyser.fftSize = 512; this.buf = new Uint8Array(this.analyser.fftSize);
            src.connect(this.analyser);
        }
        this.mime = pickMime();
        this.recorder = this.mime ? new MediaRecorder(this.stream, { mimeType: this.mime }) : new MediaRecorder(this.stream);
        this.mime = this.recorder.mimeType || this.mime || 'audio/webm';
        this.recorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) this.chunks.push(e.data); };
        this.recorder.start(250);
        this.startedAt = Date.now();
        this.state = 'active';
        this._run();
        this._startCaptions();
    }

    /** Loudness 0..1 from the time-domain signal (RMS), gated against the noise floor and curved so speech fills the bars. */
    _measure() {
        if (!this.analyser) return 0;
        this.analyser.getByteTimeDomainData(this.buf);
        let sum = 0; for (let i = 0; i < this.buf.length; i++) { const v = (this.buf[i] - 128) / 128; sum += v * v; }
        const rms = Math.sqrt(sum / this.buf.length);
        return rms < 0.008 ? 0 : Math.min(1, Math.pow((rms - 0.008) * 6, 0.55));
    }

    _run() {
        this._timers.push(setInterval(() => {
            if (!this.analyser) return;
            const raw = this._measure();
            this.level = Math.max(raw, this.level * 0.72);   // fast attack, gentle fall: bars dance instead of flicker
            this.levels.push(this.level); this.lastSampleAt = performance.now();
            this.onLevel(this.level);
        }, SAMPLE_MS));
        this._timers.push(setInterval(() => {
            this.onTick(this.elapsed);
            if (this.elapsed >= this.maxSeconds) this.onTick(this.elapsed, true);
        }, 250));
    }

    /** The note's waveform for the server and the chat bubble: n integers 0..100 (max-pooled so short peaks survive). */
    peaks(n = 64) {
        const L = this.levels; if (!L.length) return new Array(n).fill(6);
        const out = []; const size = L.length / n;
        for (let i = 0; i < n; i++) { const a = Math.floor(i * size); const b = Math.max(a + 1, Math.floor((i + 1) * size)); let m = 0; for (let j = a; j < b && j < L.length; j++) m = Math.max(m, L[j]); out.push(Math.max(4, Math.round(m * 100))); }
        return out;
    }

    _clearTimers() { this._timers.forEach(clearInterval); this._timers = []; }

    _startCaptions() {
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!this.captions || !SR) { this.onCaption(null); return; }
        try {
            this.rec = new SR();
            this.rec.continuous = true; this.rec.interimResults = true; this.rec.lang = this.lang;
            this.rec.onresult = (e) => {
                let t = '';
                for (const r of e.results) t += r[0].transcript;
                this.transcript = t;
                this.onCaption(t);
            };
            this.rec.onend = () => { if (this.state === 'active') { try { this.rec.start(); } catch { /* already started */ } } };
            this.rec.start();
        } catch { this.rec = null; }
    }

    _stopCaptions() { try { this.rec && this.rec.stop(); } catch { /* not running */ } }

    pause() {
        if (this.state !== 'active' || this.recorder.state !== 'recording') return;
        this.recorder.pause();
        this._clearTimers(); this._stopCaptions();
        this.pausedAt = Date.now(); this.state = 'paused';
        try { this.recorder.requestData(); } catch { /* not supported */ }
    }

    resume() {
        if (this.state !== 'paused' || this.recorder.state !== 'paused') return;
        this.recorder.resume();
        this.totalPaused += Date.now() - this.pausedAt;
        this.state = 'active';
        this._run();
        if (this.rec) { try { this.rec.start(); } catch { /* already started */ } }
    }

    /** Build a playable preview URL from what has been recorded so far (caller revokes it). */
    previewUrl() { return URL.createObjectURL(new Blob(this.chunks, { type: this.mime })); }

    /** Stop and return the finished recording. */
    async finish() {
        const duration = this.elapsed;
        this._clearTimers(); this._stopCaptions();
        if (this.recorder && this.recorder.state !== 'inactive') {
            await new Promise((resolve) => { this.recorder.onstop = resolve; this.recorder.stop(); });
        }
        const blob = new Blob(this.chunks, { type: this.mime });
        const out = { blob, duration, transcript: this.transcript, mime: this.mime, ext: extFor(this.mime), levels: this.levels.slice(), peaks: this.peaks(64) };
        this.release();
        return out;
    }

    discard() {
        this._clearTimers(); this._stopCaptions();
        try { if (this.recorder && this.recorder.state !== 'inactive') this.recorder.stop(); } catch { /* already stopped */ }
        this.release();
    }

    release() {
        if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
        if (this.ctx) { try { this.ctx.close(); } catch { /* closed */ } }
        this.stream = null; this.ctx = null; this.analyser = null; this.recorder = null; this.rec = null;
        this.state = 'idle';
    }
}
