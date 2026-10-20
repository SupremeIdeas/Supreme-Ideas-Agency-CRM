// Naara chat voice-note player (MASTER-ONLY). Replaces the browser's stock <audio controls>: a round play button, the note's real waveform
// (stored with the message), tap/drag to seek, 1x/1.5x/2x, a "new" dot on notes you have not heard, bars that dance with the sound while it
// plays, and the next unheard note plays on by itself. One note plays at a time. Everything is progressive: without WebAudio the bars still
// dance from the recorded levels; without JS the server-rendered Download link remains.
import { drawBars, seededPeaks, cssColor } from '../composer/wave.js';
import { gcfg, haptic, stat } from './gesture-config.js';

const SPEEDS = [1, 1.5, 2];
const PLAY = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg>';
const PAUSE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4.2" height="14" rx="1.4"/><rect x="13.8" y="5" width="4.2" height="14" rx="1.4"/></svg>';
const fmt = (s) => { s = Math.max(0, Math.round(s || 0)); return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`; };
const store = { get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* storage unavailable */ } } };

let current = null; let actx = null;
const peakCache = new Map();
const players = new Set();
const played = new Set(store.get('n2n_vn_played', []));
const rememberPlayed = (id) => { played.add(id); store.set('n2n_vn_played', Array.from(played).slice(-500)); };

class Player {
  constructor(root) {
    this.root = root; root.setAttribute('data-ready', '1');
    this.id = root.dataset.id; this.src = root.dataset.src; this.mine = root.dataset.mine === '1';
    this.btn = root.querySelector('.nvp__btn'); this.cv = root.querySelector('.nvp__wave'); this.time = root.querySelector('.nvp__time'); this.speedBtn = root.querySelector('.nvp__speed'); this.dot = root.querySelector('.nvp__dot'); this.body = root.querySelector('.nvp__body');
    const raw = (root.dataset.peaks || '').split(',').map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n));
    this.peaks = raw.length >= 8 ? raw.map((n) => Math.max(0.05, Math.min(1, n / 100))) : seededPeaks(this.id, 48);
    this.dur = Number(root.dataset.dur) || 0; this.frac = 0; this.playing = false; this.a = null; this.an = null; this.buf = null; this.raf = 0; this.colKey = ''; this.col = null;
    this.speed = store.get('n2n_vn_speed', 1);
    this.time.textContent = fmt(this.dur);
    if (!this.mine && !played.has(this.id)) { this.dot.hidden = false; root.classList.add('is-new'); }
    this.btn.addEventListener('click', () => this.toggle());
    this.speedBtn.addEventListener('click', () => this.cycleSpeed());
    this.body.addEventListener('keydown', (e) => this.key(e));
    const seek = (e) => { const r = this.cv.getBoundingClientRect(); this.seekTo(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width))); };
    // Tap or drag on the waveform to seek. Double-tap the LEFT half to go back 5 seconds, the RIGHT half to skip 5 ahead (the first tap's seek is undone, so a skip
    // is exact). Keyboard: the arrow keys on the slider do the same. "Buttons only" turns the double-tap off; scrubbing stays, it is the control itself.
    this.cv.addEventListener('pointerdown', (e) => {
      this.scrub = true; this.dragged = false; this.down = { x: e.clientX, y: e.clientY, t: Date.now(), before: this.frac };
      try { this.cv.setPointerCapture(e.pointerId); } catch (_) { /* synthetic pointers */ }
      const prev = this.lastTap;
      if (prev && !gcfg().buttons_only && Date.now() - prev.t < 300 && Math.hypot(prev.x - e.clientX, prev.y - e.clientY) < 30) {
        this.lastTap = null; this.skipTap = true;
        const r = this.cv.getBoundingClientRect(); const right = (e.clientX - r.left) > r.width / 2;
        this.seekTo(prev.before); this.skip(right ? 5 : -5); stat('vn_skip', 'committed', e.pointerType); haptic(8); this.flashSkip(right);
        return;
      }
      this.skipTap = false; seek(e);
    });
    this.cv.addEventListener('pointermove', (e) => { if (this.scrub && !this.skipTap) { if (this.down && Math.abs(e.clientX - this.down.x) > 6) { this.dragged = true; } seek(e); } });
    ['pointerup', 'pointercancel'].forEach((t) => this.cv.addEventListener(t, (e) => {
      this.scrub = false;
      if (!this.skipTap && !this.dragged && this.down && Date.now() - this.down.t < 300) { this.lastTap = { t: Date.now(), x: this.down.x, y: this.down.y, before: this.down.before }; }
    }));
    this.draw();
    if (raw.length < 8) { this.wantRealPeaks(); }
  }

  /** A note that arrived without stored levels (the support centre's) gets its REAL waveform: when it scrolls into view, its file is decoded once and 48 peaks are drawn, so the shape matches the sound. */
  wantRealPeaks() {
    if (!('IntersectionObserver' in window)) { return; }
    const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { io.disconnect(); this.decodePeaks(); } }, { rootMargin: '200px' });
    io.observe(this.root);
  }

  async decodePeaks() {
    try {
      let cached = peakCache.get(this.src);
      if (!cached) {
        const Ctx = window.AudioContext || window.webkitAudioContext; if (!Ctx) { return; }
        const res = await fetch(this.src, { credentials: 'same-origin' }); if (!res.ok) { return; }
        const data = await res.arrayBuffer(); const ctx = new Ctx();
        const buf = await new Promise((ok, no) => ctx.decodeAudioData(data, ok, no)); try { ctx.close(); } catch (e) { /* fine */ }
        const ch = buf.getChannelData(0); const n = 48; const step = Math.max(1, Math.floor(ch.length / n)); const peaks = [];
        for (let i = 0; i < n; i++) { let sum = 0; const from = i * step; const to = Math.min(ch.length, from + step); for (let j = from; j < to; j++) { sum += ch[j] * ch[j]; } peaks.push(Math.sqrt(sum / Math.max(1, to - from))); }
        const max = Math.max(...peaks, 0.0001);
        cached = { peaks: peaks.map((p) => Math.max(0.06, Math.min(1, Math.pow(p / max, 0.7)))), dur: buf.duration };
        peakCache.set(this.src, cached);
      }
      this.peaks = cached.peaks; if (!this.dur) { this.dur = cached.dur; this.time.textContent = fmt(this.dur); }
      this.draw();
    } catch (e) { /* the seeded shape stays; the note still plays */ }
  }

  colors() {
    const key = document.documentElement.className + '|' + (document.documentElement.dataset.nxSkin || '') + '|' + (this.cv.closest('.ncb') ? this.cv.closest('.ncb').className : '');
    if (key !== this.colKey || !this.col) { this.colKey = key; this.col = { on: cssColor(this.cv, '--nvp-on', '#0a6e6e'), off: cssColor(this.cv, '--nvp-off', 'rgba(100,116,139,.6)') }; }
    return this.col;
  }

  duration() { const a = this.a; return a && Number.isFinite(a.duration) && a.duration > 0 ? a.duration : this.dur; }

  energy() {
    if (this.an) {
      this.an.getByteTimeDomainData(this.buf); let sum = 0;
      for (let i = 0; i < this.buf.length; i++) { const v = (this.buf[i] - 128) / 128; sum += v * v; }
      const rms = Math.sqrt(sum / this.buf.length); return rms < 0.008 ? 0 : Math.min(1, Math.pow((rms - 0.008) * 6, 0.55));
    }
    return this.peaks[Math.min(this.peaks.length - 1, Math.floor(this.frac * this.peaks.length))] || 0;
  }

  draw() {
    if (!this.cv.isConnected) { return; }
    const c = this.colors();
    drawBars(this.cv, { mode: 'fit', bars: this.peaks, progress: this.frac, playing: this.playing, energy: this.playing ? this.energy() : 0, t: performance.now(), on: c.on, off: c.off, barW: 3, gap: 2.4 });
    this.body.setAttribute('aria-valuenow', String(Math.round(this.frac * 100)));
  }

  ensureAudio() {
    if (this.a) { return this.a; }
    const a = new Audio(); a.preload = 'metadata'; a.src = this.src; a.playbackRate = this.speed;
    a.addEventListener('timeupdate', () => { if (this.pending !== null && this.pending !== undefined) { return; } const d = this.duration(); this.frac = d ? Math.min(1, a.currentTime / d) : 0; if (this.playing || this.scrub) { this.time.textContent = fmt(a.currentTime); } });
    a.addEventListener('loadedmetadata', () => { if (this.pending !== null && this.pending !== undefined && Number.isFinite(a.duration) && a.duration > 0) { try { a.currentTime = this.pending * a.duration; } catch (e) { /* ok */ } this.pending = null; } if (!this.dur && Number.isFinite(a.duration)) { this.dur = a.duration; } if (!this.playing) { this.time.textContent = fmt(this.duration()); } });
    a.addEventListener('ended', () => this.ended());
    a.addEventListener('error', () => { this.playing = false; this.setIcon(); this.root.classList.add('is-error'); this.time.textContent = 'Unavailable'; this.draw(); });
    this.a = a; return a;
  }

  tap() {   // WebAudio tap for the live level; optional, the note plays either way
    if (this.an || this.tapFailed) { return; }
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext; if (!Ctx) { this.tapFailed = true; return; }
      actx = actx || new Ctx();
      const src = actx.createMediaElementSource(this.a); const an = actx.createAnalyser(); an.fftSize = 512;
      src.connect(an); an.connect(actx.destination); this.an = an; this.buf = new Uint8Array(an.fftSize);
    } catch (e) { this.tapFailed = true; }
  }

  setIcon() {
    this.btn.innerHTML = this.playing ? PAUSE : PLAY; this.btn.setAttribute('aria-label', this.playing ? 'Pause voice message' : 'Play voice message');
    this.root.classList.toggle('is-playing', this.playing); this.speedBtn.hidden = !(this.playing || this.frac > 0);
    this.speedBtn.textContent = `${this.speed}x`;
  }

  async play() {
    if (current && current !== this) { current.pause(); }
    const a = this.ensureAudio(); this.root.classList.remove('is-error'); this.tap();
    if (actx && actx.state === 'suspended') { try { await actx.resume(); } catch (e) { /* needs a gesture */ } }
    if (a.ended || this.frac >= 0.999) { a.currentTime = 0; this.frac = 0; }
    a.playbackRate = this.speed;
    try { await a.play(); } catch (e) { this.playing = false; this.setIcon(); return; }
    current = this; this.playing = true; this.setIcon();
    if (this.dot) { this.dot.hidden = true; } this.root.classList.remove('is-new'); rememberPlayed(this.id);
    this.mediaSession();
    const loop = () => { if (!this.playing || !this.root.isConnected) { if (!this.root.isConnected) { this.pause(); } return; } this.draw(); this.raf = requestAnimationFrame(loop); };
    cancelAnimationFrame(this.raf); loop();
  }

  pause() {
    if (this.a) { this.a.pause(); }
    this.playing = false; cancelAnimationFrame(this.raf); this.setIcon(); this.draw();
    if (current === this) { current = null; }
  }

  toggle() { if (this.playing) { this.pause(); } else { this.play(); } }

  ended() {
    this.playing = false; cancelAnimationFrame(this.raf); this.frac = 0; this.time.textContent = fmt(this.duration()); this.setIcon(); this.draw(); if (current === this) { current = null; }
    // Play on: the next note from them that you have not heard yet.
    const all = Array.from(document.querySelectorAll('.nvp[data-ready]')); const i = all.indexOf(this.root);
    for (let j = i + 1; j < all.length; j++) { const p = all[j].__nvp; if (p && !p.mine && !played.has(p.id)) { p.play(); break; } if (all[j].dataset.mine === '0' && !played.has(all[j].dataset.id)) { break; } }
  }

  /** Move by whole seconds (negative = back), clamped to the note. */
  skip(sec) { const d = this.duration(); if (!d) { return; } this.seekTo(Math.max(0, Math.min(1, this.frac + sec / d))); }

  flashSkip(right) {
    const n = document.createElement('span'); n.className = 'nvp__skip' + (right ? ' is-right' : ''); n.textContent = right ? '+5s' : '-5s'; n.setAttribute('aria-hidden', 'true');
    this.body.append(n); setTimeout(() => n.remove(), 650);
  }

  seekTo(frac) {
    const a = this.ensureAudio(); const d = this.duration(); this.frac = frac;
    // A note that has not loaded yet cannot be seeked: remember where the person pointed and apply it the moment the file is ready (before, the first tap was lost).
    if (a.readyState < 1) { this.pending = frac; } else { this.pending = null; }
    if (d) { try { a.currentTime = frac * d; } catch (e) { /* not seekable yet */ } this.time.textContent = fmt(frac * d); }
    this.setIcon(); this.draw();
  }

  cycleSpeed() {
    this.speed = SPEEDS[(SPEEDS.indexOf(this.speed) + 1) % SPEEDS.length]; store.set('n2n_vn_speed', this.speed);
    this.speedBtn.textContent = `${this.speed}x`; if (this.a) { this.a.playbackRate = this.speed; }
  }

  key(e) {
    if (e.key === ' ' || e.key === 'Enter') { if (e.target === this.body) { e.preventDefault(); this.toggle(); } return; }
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); const d = this.duration() || 1; this.seekTo(Math.max(0, Math.min(1, this.frac + (e.key === 'ArrowRight' ? 5 : -5) / d))); }
  }

  mediaSession() {
    if (!('mediaSession' in navigator)) { return; }
    try {
      navigator.mediaSession.metadata = new MediaMetadata({ title: 'Voice message', artist: 'Naara chat' });
      navigator.mediaSession.setActionHandler('play', () => this.play()); navigator.mediaSession.setActionHandler('pause', () => this.pause());
    } catch (e) { /* unsupported */ }
  }
}

function scan() {
  document.querySelectorAll('[data-nvp]:not([data-ready])').forEach((el) => { const p = new Player(el); el.__nvp = p; players.add(p); });
  players.forEach((p) => { if (!p.root.isConnected) { players.delete(p); } });
}

let queued = false;
const schedule = () => { if (queued) { return; } queued = true; requestAnimationFrame(() => { queued = false; scan(); }); };
if (!window.__n2nNvp) {
  window.__n2nNvp = true;
  new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
  // A skin / light-dark switch repaints every waveform in the new colours.
  new MutationObserver(() => players.forEach((p) => { p.colKey = ''; p.draw(); })).observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-nx-skin', 'data-nx-mode'] });
  window.addEventListener('resize', () => players.forEach((p) => p.draw()), { passive: true });
  scan();
}
