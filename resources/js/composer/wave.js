// Shared waveform renderer for every voice surface: the recorder in the composer (live, scrolling), the paused scrub-preview, and the voice-note
// player in a chat bubble. One canvas, one function, so recording and playback look like the same instrument.
//
// Two modes:
//   live  - bars scroll in from the right as you speak (right-aligned, smooth sub-sample motion, newest bar glows)
//   fit   - the whole note across the width with a playhead; while `playing`, the bars around the playhead dance with the sound

const roundBar = (g, x, y, w, h) => {
    const r = Math.min(w / 2, h / 2);
    g.beginPath();
    if (g.roundRect) g.roundRect(x, y, w, h, r); else g.rect(x, y, w, h);
    g.fill();
};

/** Max-pool a list of 0..1 levels down (or up) to exactly n bars. */
export function resample(levels, n) {
    if (!levels || !levels.length) return new Array(n).fill(0.05);
    const out = new Array(n);
    const size = levels.length / n;
    for (let i = 0; i < n; i++) {
        const a = Math.floor(i * size); const b = Math.max(a + 1, Math.floor((i + 1) * size));
        let m = 0; for (let j = a; j < b && j < levels.length; j++) m = Math.max(m, levels[j]);
        out[i] = m;
    }
    return out;
}

/** A stable speech-like envelope for notes recorded before waveforms were stored: same id, same shape, never random per render. */
export function seededPeaks(seed, n = 48) {
    let s = (Number(seed) || 1) >>> 0;
    const rnd = () => { s = (s + 0x6d2b79f5) >>> 0; let t = s; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
    const out = []; let level = 0.4;
    for (let i = 0; i < n; i++) { level = Math.max(0.12, Math.min(1, level * 0.55 + rnd() * 0.62)); out.push(level); }
    return out;
}

/** Size the canvas backing store to its CSS box (crisp on retina) and return the CSS size. */
export function fitCanvas(c) {
    const r = c.getBoundingClientRect(); const d = Math.min(3, window.devicePixelRatio || 1);
    const w = Math.max(1, Math.round(r.width * d)); const h = Math.max(1, Math.round(r.height * d));
    if (c.width !== w || c.height !== h) { c.width = w; c.height = h; }
    return { w: r.width, h: r.height, d };
}

/**
 * @param {HTMLCanvasElement} c
 * @param {object} o
 * @param {'live'|'fit'} o.mode
 * @param {number[]} o.bars        0..1 levels (live: the whole history; fit: any length, resampled)
 * @param {string} o.on            colour of the played / live part
 * @param {string} o.off           colour of the unplayed part
 * @param {number} [o.barW=3] @param {number} [o.gap=2.5]
 * @param {number} [o.progress=0]  fit: 0..1 playhead
 * @param {boolean} [o.playing]    fit: bars near the playhead dance
 * @param {number} [o.energy=0]    0..1 how loud the sound is right now (drives the dance); falls back to the bar's own level
 * @param {number} [o.t=0]         ms clock for the dance phase
 * @param {number} [o.current=0]   live: instantaneous level (the newest bar)
 * @param {number} [o.shift=0]     live: 0..1 how far the next bar has travelled (smooth scroll between samples)
 * @param {boolean} [o.head=true]  show the playhead / live head
 */
export function drawBars(c, o) {
    const { w, h, d } = fitCanvas(c); const g = c.getContext('2d'); if (!g) return;
    g.setTransform(d, 0, 0, d, 0, 0); g.clearRect(0, 0, w, h);
    const barW = o.barW || 3; const gap = o.gap || 2.5; const step = barW + gap; const mid = h / 2; const minH = Math.max(3, h * 0.08);
    const n = Math.max(4, Math.floor((w + gap) / step));
    if (o.mode === 'live') {
        const hist = o.bars || []; const shift = Math.max(0, Math.min(1, o.shift || 0)) * step;
        const bars = hist.slice(-n + 1); bars.push(Math.max(o.current || 0, bars.length ? bars[bars.length - 1] * 0.5 : 0));
        const right = w - 4;
        for (let i = bars.length - 1, k = 0; i >= 0; i--, k++) {
            const x = right - barW - k * step - shift; if (x < -barW) break;
            const age = Math.min(1, k / Math.max(8, n * 0.92));
            const v = Math.max(0, Math.min(1, bars[i])); const bh = Math.max(minH, v * h * 0.94);
            g.globalAlpha = 1 - age * 0.78; g.fillStyle = o.on; roundBar(g, x, mid - bh / 2, barW, bh);
        }
        g.globalAlpha = 1;
        if (o.head !== false) { // newest-bar glow
            const v = Math.max(minH / h, Math.min(1, o.current || 0)); const bh = Math.max(minH, v * h * 0.94);
            g.shadowColor = o.on; g.shadowBlur = 10; g.fillStyle = o.on; roundBar(g, right - barW, mid - bh / 2, barW, bh); g.shadowBlur = 0;
        }
        return;
    }
    // fit
    const peaks = resample(o.bars, n); const prog = Math.max(0, Math.min(1, o.progress || 0)); const p = prog * n; const t = o.t || 0;
    for (let i = 0; i < n; i++) {
        let v = Math.max(0.06, Math.min(1, peaks[i])); const played = i + 0.5 <= p;
        if (o.playing) {
            const dist = Math.abs(i + 0.5 - p);
            if (dist < 7) { const k = 1 - dist / 7; const e = o.energy > 0 ? o.energy : v; v = Math.min(1, v * (1 + k * (0.35 + 0.9 * e) * (0.55 + 0.45 * Math.sin(t / 85 + i * 1.7)))); }
        }
        const bh = Math.max(minH, v * h * 0.94); g.fillStyle = played ? o.on : o.off;
        roundBar(g, i * step, mid - bh / 2, barW, bh);
    }
    if (o.head !== false && (prog > 0 || o.playing)) {
        const x = Math.max(3, Math.min(w - 3, prog * (n * step - gap)));
        g.fillStyle = o.on;
        if (o.playing) { g.shadowColor = o.on; g.shadowBlur = 8; }
        g.beginPath(); g.arc(x, mid, 4.2, 0, Math.PI * 2); g.fill(); g.shadowBlur = 0;
    }
}

/** Resolve a CSS custom property (a colour, possibly var()/color-mix() chains) to a concrete colour string canvas can paint, as seen from `el`. */
export function cssColor(el, name, fallback) {
    const host = el.parentNode || el;
    const probe = document.createElement('span');
    probe.style.cssText = `position:absolute;visibility:hidden;pointer-events:none;color:var(${name}, ${fallback})`;
    host.appendChild(probe);
    const c = getComputedStyle(probe).color; probe.remove();
    return c || fallback;
}
