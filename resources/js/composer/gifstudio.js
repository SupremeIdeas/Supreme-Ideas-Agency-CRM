// GIF Studio: make a GIF from a video clip, frame-accurately and in real time. Everything runs in the browser, nothing is uploaded.
//  - a filmstrip timeline with draggable start / end handles (they snap to the output frame grid) and a playhead you can scrub;
//  - the live view is a canvas drawn from the video exactly as the GIF will be framed (original or square), so what you see is what you get;
//  - "Loop preview" plays only the chosen range (optionally ping-pong, at the chosen speed) while you keep adjusting it;
//  - frame stepping (buttons, ← → and , . keys), "start = now" / "end = now", width / frame rate / quality / speed;
//  - the encoder is gifenc in a worker (real colour palettes, progress per frame), so the page never freezes;
//  - the result is shown with its real size, then saved to "my GIFs" (the presets) and optionally sent.
import { fmt } from './labels.js';

const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
const svg = (body) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
const IC = {
    play: svg('<path d="M7 5v14l12-7z" fill="currentColor"/>'), pause: svg('<path d="M8 5v14M16 5v14"/>'),
    back: svg('<path d="M15 6l-6 6 6 6"/><path d="M5 5v14"/>'), fwd: svg('<path d="M9 6l6 6-6 6"/><path d="M19 5v14"/>'),
    swap: svg('<path d="M4 8h13l-3-3M20 16H7l3 3"/>'),
};
const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
const MAX_SECONDS = 8; const MIN_LEN = 0.2; const PIXEL_BUDGET = 96e6;       // bytes of raw RGBA held while building
export const WIDTHS = [240, 320, 480, 640]; export const FPS = [8, 10, 12, 15, 20]; export const SPEEDS = [0.5, 1, 1.5, 2]; export const QUALITY = ['fast', 'good', 'best'];
const bytesLabel = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
const clock = (t) => { const m = Math.floor(t / 60); const s = t - m * 60; return `${m}:${s.toFixed(2).padStart(5, '0')}`; };
const readAsDataUrl = (b) => new Promise((r) => { const f = new FileReader(); f.onload = () => r(f.result); f.readAsDataURL(b); });

/** Some recorders (MediaRecorder, a few phones) write webm/mp4 without a duration: the browser reports Infinity until it has seen the end. Seeking far past the end makes it work it out. */
export function knowDuration(v) {
    return new Promise((res) => {
        if (Number.isFinite(v.duration)) { res(); return; }
        const fin = () => { v.removeEventListener('timeupdate', fin); v.currentTime = 0; res(); };
        v.addEventListener('timeupdate', fin); v.currentTime = 1e101; setTimeout(res, 4000);
    });
}

/** Seek and wait for the frame to be ready (never hangs: gives up after 2.5 s). */
export function seekTo(v, t) {
    return new Promise((res) => {
        if (Math.abs(v.currentTime - t) < 1e-4 && v.readyState >= 2) { res(); return; }
        let done = false; const fin = () => { if (done) return; done = true; v.removeEventListener('seeked', fin); res(); };
        v.addEventListener('seeked', fin); v.currentTime = t; setTimeout(fin, 2500);
    });
}

/** Draw `src` cover-fitted into W x H (square shape crops the centre). */
export function drawCover(ctx, src, sw, sh, W, H) {
    const s = Math.max(W / sw, H / sh);
    ctx.drawImage(src, (W - sw * s) / 2, (H - sh * s) / 2, sw * s, sh * s);
}

/** Output geometry for a video size, width and shape; even numbers keep every decoder happy. */
export function outSize(vw, vh, width, shape) {
    const w = Math.min(width, vw) & ~1; const h = shape === 'square' ? w : Math.max(2, Math.round((w * vh) / vw) & ~1);
    return { w, h };
}

/** Frame timestamps for a range at an fps (forward only; the boomerang adds the way back at encode time). */
export function frameTimes(t0, t1, fps) {
    const n = Math.max(2, Math.round((t1 - t0) * fps)); const step = (t1 - t0) / n;
    return Array.from({ length: n }, (_, i) => t0 + i * step);
}

/** Encode RGBA frames to a GIF in a worker. `quality`: fast (one rgb444 palette), good (one rgb565 palette), best (a palette per frame). */
export function encodeFrames({ frames, w, h, fps, speed = 1, quality = 'good', boomerang = false, onProgress, signal }) {
    return new Promise((resolve, reject) => {
        const worker = new Worker(new URL('./gif.worker.js', import.meta.url), { type: 'module' });
        const seq = boomerang && frames.length > 2 ? frames.concat(frames.slice(1, -1).reverse()) : frames;
        const delay = Math.max(20, Math.round(1000 / fps / speed));
        let sent = 0;
        const stop = (fn) => (v) => { worker.terminate(); fn(v); };
        const ok = stop(resolve); const bad = stop(reject);
        if (signal) signal.addEventListener('abort', () => bad(Object.assign(new Error('cancelled'), { cancelled: true })));
        worker.onerror = (e) => bad(new Error(e.message || 'worker'));
        worker.onmessage = (e) => {
            const m = e.data;
            if (m.t === 'progress') { sent = m.n; onProgress && onProgress(sent / seq.length); if (sent === seq.length) worker.postMessage({ t: 'finish' }); }
            else if (m.t === 'done') ok(m.bytes);
            else if (m.t === 'error') bad(new Error(m.message));
        };
        const sample = [];
        if (quality !== 'best') {
            const k = Math.min(8, seq.length);
            for (let i = 0; i < k; i++) { const f = seq[Math.floor((i * (seq.length - 1)) / Math.max(1, k - 1))]; const sub = new Uint8ClampedArray(Math.floor(f.length / 16) * 4); for (let p = 0, o = 0; p + 3 < f.length && o < sub.length; p += 16, o += 4) { sub[o] = f[p]; sub[o + 1] = f[p + 1]; sub[o + 2] = f[p + 2]; sub[o + 3] = 255; } sample.push(sub); }
        }
        worker.postMessage({ t: 'init', w, h, quality, sample });
        for (const f of seq) { const copy = f.slice(); worker.postMessage({ t: 'frame', data: copy, delay }, [copy.buffer]); }
    });
}

export function openGifStudio(c, file) {
    if (!file) return;
    c._gsDispose && c._gsDispose();
    const L = c.L; const T = (k, vars) => fmt(L[k], vars);
    const url = URL.createObjectURL(file);
    const mk = c.refs.pGifMaker; c.show('pGifMaker'); mk.replaceChildren();

    const S = { dur: 0, vw: 0, vh: 0, t0: 0, t1: 3, cur: 0, width: 320, fps: 12, quality: 'good', speed: 1, shape: 'orig', boom: false, playing: false, rev: false, built: null, building: false, abort: null };
    const disposers = [];
    const dispose = () => { S.playing = false; S.abort && S.abort.abort(); [v, tv, cap].forEach((x) => { try { x.pause(); x.removeAttribute('src'); x.load(); } catch { /* gone */ } }); if (S.built) URL.revokeObjectURL(S.built.url); URL.revokeObjectURL(url); disposers.forEach((f) => f()); c._gsDispose = null; };
    c._gsDispose = dispose;

    // ----- media elements: v = what you watch, tv = thumbnails, cap = frame capture (separate so scrubbing never fights the build)
    const mkVideo = () => { const x = el('video'); x.src = url; x.muted = true; x.playsInline = true; x.preload = 'auto'; return x; };
    const v = mkVideo(); const tv = mkVideo(); const cap = mkVideo();

    // ----- layout
    const root = el('div', 'cc-gs'); const title = el('div', 'cc-dtitle', L.gs_title);
    const stage = el('div', 'cc-gs-stage'); stage.tabIndex = 0; stage.setAttribute('role', 'group'); stage.setAttribute('aria-label', L.gs_title);
    const view = el('canvas', 'cc-gs-view'); const tag = el('span', 'cc-gs-tag', '0:00.00'); stage.append(view, tag);
    const msg = el('div', 'cc-gs-msg'); msg.hidden = true;

    const ctl = el('div', 'cc-gs-ctl');
    const btn = (cls, label, html) => { const b = el('button', cls); b.type = 'button'; b.setAttribute('aria-label', label); b.title = label; if (html) b.innerHTML = html; return b; };
    const bBack = btn('cc-gs-ib', L.gs_stepBack, IC.back); const bPlay = btn('cc-gs-ib cc-gs-play', L.gs_play, IC.play); const bFwd = btn('cc-gs-ib', L.gs_stepFwd, IC.fwd);
    const bChange = btn('cc-chip cc-gs-change', L.gs_change); bChange.innerHTML = `${IC.swap}<span>${L.gs_change}</span>`;
    ctl.append(bBack, bPlay, bFwd, el('span', 'cc-gs-sp'), bChange);

    const strip = el('div', 'cc-gs-strip'); const thumbs = el('canvas', 'cc-gs-thumbs');
    const shL = el('i', 'cc-gs-shade'); const shR = el('i', 'cc-gs-shade'); const range = el('i', 'cc-gs-range'); const head = el('i', 'cc-gs-head');
    const mkH = (cls, label) => { const h = el('div', `cc-gs-h ${cls}`); h.tabIndex = 0; h.setAttribute('role', 'slider'); h.setAttribute('aria-label', label); h.append(el('b')); return h; };
    const h0 = mkH('cc-gs-h0', L.start); const h1 = mkH('cc-gs-h1', L.end);
    strip.append(thumbs, shL, shR, range, head, h0, h1);

    const read = el('div', 'cc-gs-read');
    const stat = (label) => { const d = el('div'); const b = el('b', '', '–'); d.append(el('span', '', label), b); read.append(d); return b; };
    const rS = stat(L.start); const rE = stat(L.end); const rL = stat(L.gs_length); const rF = stat(L.gs_frames);
    const now = el('div', 'cc-gs-now'); const bS = el('button', 'cc-chip', L.startNow); const bE = el('button', 'cc-chip', L.endNow); bS.type = bE.type = 'button'; now.append(bS, bE);

    const opts = el('div', 'cc-gs-opts');
    const seg = (label, values, fmtv, get, set) => {
        const row = el('div', 'cc-gs-row'); row.append(el('span', 'cc-gs-lab', label)); const g = el('div', 'cc-gs-seg'); g.setAttribute('role', 'group'); g.setAttribute('aria-label', label);
        const bs = values.map((val) => { const b = el('button', '', fmtv(val)); b.type = 'button'; b.onclick = () => { set(val); sync(); }; g.append(b); return [b, val]; });
        row.append(g); opts.append(row); return () => bs.forEach(([b, val]) => { const on = get() === val; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    };
    const toggle = (label, get, set) => { const b = el('button', 'cc-gs-tg', label); b.type = 'button'; b.onclick = () => { set(!get()); sync(); }; return [b, () => { b.classList.toggle('is-on', get()); b.setAttribute('aria-pressed', get() ? 'true' : 'false'); }]; };
    const paint = [
        seg(L.gs_width, WIDTHS, (x) => `${x}`, () => S.width, (x) => { S.width = x; }),
        seg(L.gs_fps, FPS, (x) => `${x}`, () => S.fps, (x) => { S.fps = x; }),
        seg(L.gs_quality, QUALITY, (q) => L[`gs_q_${q}`], () => S.quality, (q) => { S.quality = q; }),
        seg(L.speed, SPEEDS, (x) => `${x}×`, () => S.speed, (x) => { S.speed = x; v.playbackRate = x; }),
        seg(L.gs_shape, ['orig', 'square'], (s) => (s === 'orig' ? L.gs_orig : L.gs_square), () => S.shape, (s) => { S.shape = s; }),
    ];
    const tgRow = el('div', 'cc-gs-row cc-gs-tgs'); const [bBoom, pBoom] = toggle(L.gs_boomerang, () => S.boom, (x) => { S.boom = x; }); const [bLoop, pLoop] = toggle(L.gs_loop, () => S.playing, (x) => { x ? play() : pause(); });
    tgRow.append(bLoop, bBoom); opts.append(tgRow); paint.push(pBoom, pLoop);

    const est = el('div', 'cc-gs-est'); est.setAttribute('aria-live', 'polite');
    const out = el('div', 'cc-gs-out'); out.hidden = true; const outImg = el('img'); outImg.alt = ''; const outCap = el('div', 'cc-gs-outcap'); out.append(outImg, outCap);
    const bar = el('div', 'cc-gs-bar'); bar.hidden = true; const barFill = el('i'); bar.append(barFill);

    const form = el('div', 'cc-form'); const nm = el('input'); nm.placeholder = L.nameGif; nm.setAttribute('aria-label', L.nameGif); form.append(nm);
    const acts = el('div', 'cc-actions');
    const bCancel = el('button', 'cc-chip', L.cancel); const bPrev = el('button', 'cc-chip', L.gs_make); const bSave = el('button', 'cc-chip cc-primary', L.saveGif); const bUse = el('button', 'cc-chip cc-primary', L.gs_use);
    [bCancel, bPrev, bSave, bUse].forEach((b) => { b.type = 'button'; }); acts.append(bCancel, bPrev, bSave); if (!c.onGifMade) acts.append(bUse);
    const foot = el('div', 'cc-gs-foot'); foot.append(bar, form, acts);   // pinned to the bottom of the panel so Make / Save / Use are always reachable
    root.append(title, msg, stage, ctl, strip, read, now, opts, est, out, foot); mk.append(root);

    // ----- geometry helpers
    const fpsNow = () => S.fps; const frameLen = () => 1 / fpsNow();
    const snap = (t) => Math.round(t * fpsNow()) / fpsNow();
    const geo = () => outSize(S.vw || 16, S.vh || 9, S.width, S.shape);
    const pad = 16; const trackW = () => Math.max(1, strip.clientWidth - pad * 2); const xOf = (t) => pad + (S.dur ? (t / S.dur) * trackW() : 0);
    const tOf = (clientX) => { const r = strip.getBoundingClientRect(); return clamp(((clientX - r.left - pad) / trackW()) * S.dur, 0, S.dur); };

    // ----- the live view: the frame, framed exactly like the GIF
    const vctx = view.getContext('2d');
    const draw = () => {
        if (!S.vw) return; const { w, h } = geo(); const pw = Math.min(w, 480); const ph = Math.round((pw * h) / w);
        if (view.width !== pw || view.height !== ph) { view.width = pw; view.height = ph; }
        vctx.fillStyle = '#000'; vctx.fillRect(0, 0, pw, ph); drawCover(vctx, v, S.vw, S.vh, pw, ph);
        tag.textContent = clock(v.currentTime);
    };
    const place = () => {
        const x0 = xOf(S.t0); const x1 = xOf(S.t1); const W = strip.clientWidth;
        shL.style.cssText = `left:${pad}px;width:${Math.max(0, x0 - pad)}px`; shR.style.cssText = `left:${x1}px;width:${Math.max(0, W - pad - x1)}px`;
        range.style.cssText = `left:${x0}px;width:${Math.max(0, x1 - x0)}px`; h0.style.left = `${x0}px`; h1.style.left = `${x1}px`; head.style.left = `${xOf(v.currentTime || 0)}px`;
        h0.setAttribute('aria-valuemin', '0'); h0.setAttribute('aria-valuemax', String(+S.dur.toFixed(2))); h0.setAttribute('aria-valuenow', S.t0.toFixed(2)); h0.setAttribute('aria-valuetext', clock(S.t0));
        h1.setAttribute('aria-valuemin', '0'); h1.setAttribute('aria-valuemax', String(+S.dur.toFixed(2))); h1.setAttribute('aria-valuenow', S.t1.toFixed(2)); h1.setAttribute('aria-valuetext', clock(S.t1));
    };
    const estimate = () => { const { w, h } = geo(); const n = frameTimes(S.t0, S.t1, S.fps).length * (S.boom ? 2 : 1); const bpp = { fast: 0.11, good: 0.14, best: 0.2 }[S.quality]; return { n: frameTimes(S.t0, S.t1, S.fps).length, bytes: Math.round(n * w * h * bpp), w, h }; };
    function sync() {
        place(); paint.forEach((p) => p());
        const e = estimate(); const len = S.t1 - S.t0;
        rS.textContent = clock(S.t0); rE.textContent = clock(S.t1); rL.textContent = `${len.toFixed(2)}s`; rF.textContent = `${e.n}${S.boom ? ` ×2` : ''} · ${e.w}×${e.h}`;
        est.textContent = `${T('gs_est', { size: bytesLabel(e.bytes) })}${e.bytes > 8 * 1048576 ? ` · ${T('gs_tooBig', { size: bytesLabel(e.bytes), max: '8 MB' })}` : ''}`;
        bPlay.innerHTML = S.playing ? IC.pause : IC.play; bPlay.setAttribute('aria-label', S.playing ? L.pause : L.gs_play);
        draw();
    }
    const clampRange = (which, t) => {
        t = snap(t);
        if (which === 0) S.t0 = clamp(t, Math.max(0, S.t1 - MAX_SECONDS), S.t1 - MIN_LEN); else S.t1 = clamp(t, S.t0 + MIN_LEN, Math.min(S.dur, S.t0 + MAX_SECONDS));
        S.built = S.built && (URL.revokeObjectURL(S.built.url), null); out.hidden = true;
    };

    // ----- seeking while dragging: coalesced so the video always lands on the latest request
    let pending = null;
    const seek = (t) => { if (!Number.isFinite(t)) return; t = clamp(t, 0, Math.max(0, S.dur - 0.001)); if (v.seeking) { pending = t; return; } v.currentTime = t; };
    v.addEventListener('seeked', () => { if (pending != null) { const p = pending; pending = null; v.currentTime = p; return; } draw(); head.style.left = `${xOf(v.currentTime)}px`; });

    // ----- playback of just the chosen range (optionally ping-pong)
    let raf = 0; let last = 0;
    function loop(ts) {
        if (!S.playing) return;
        const dt = Math.min(0.1, (ts - last) / 1000); last = ts;
        if (S.rev) { const t = v.currentTime - dt * S.speed; if (t <= S.t0) { S.rev = false; v.currentTime = S.t0; v.play().catch(() => {}); } else v.currentTime = t; }
        else if (v.currentTime >= S.t1 - 0.01) { if (S.boom) { S.rev = true; v.pause(); } else { v.currentTime = S.t0; } }
        draw(); head.style.left = `${xOf(v.currentTime)}px`; raf = requestAnimationFrame(loop);
    }
    function play() { if (v.currentTime < S.t0 || v.currentTime >= S.t1) v.currentTime = S.t0; S.playing = true; S.rev = false; v.playbackRate = S.speed; v.play().catch(() => {}); last = performance.now(); cancelAnimationFrame(raf); raf = requestAnimationFrame(loop); sync(); }
    function pause() { S.playing = false; S.rev = false; v.pause(); cancelAnimationFrame(raf); sync(); }
    disposers.push(() => cancelAnimationFrame(raf));
    bPlay.onclick = () => (S.playing ? pause() : play());
    const step = (n) => { if (S.playing) pause(); const t = snap(v.currentTime) + n * frameLen(); seek(clamp(t, 0, S.dur)); };
    bBack.onclick = () => step(-1); bFwd.onclick = () => step(1);
    stage.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft' || e.key === ',') { e.preventDefault(); step(e.shiftKey ? -fpsNow() : -1); } else if (e.key === 'ArrowRight' || e.key === '.') { e.preventDefault(); step(e.shiftKey ? fpsNow() : 1); } else if (e.key === ' ') { e.preventDefault(); S.playing ? pause() : play(); } });
    bS.onclick = () => { clampRange(0, v.currentTime); sync(); }; bE.onclick = () => { clampRange(1, v.currentTime); sync(); };
    bChange.onclick = () => c.pickFiles('video/*', false, (fs) => openGifStudio(c, fs[0]));

    // ----- timeline: handles snap to the frame grid; the video follows the handle so you see the exact boundary frame
    let drag = null;
    strip.addEventListener('pointerdown', (e) => {
        e.preventDefault(); if (S.playing) pause();
        const which = e.target.closest('.cc-gs-h0') ? 0 : e.target.closest('.cc-gs-h1') ? 1 : 'scrub'; drag = which; try { strip.setPointerCapture(e.pointerId); } catch { /* not capturable */ }
        move(e);
    });
    const move = (e) => {
        if (drag === null) return; const t = tOf(e.clientX);
        if (drag === 'scrub') seek(t); else { clampRange(drag, t); seek(drag === 0 ? S.t0 : S.t1); sync(); }
    };
    strip.addEventListener('pointermove', move); strip.addEventListener('pointerup', () => { drag = null; }); strip.addEventListener('pointercancel', () => { drag = null; });
    [h0, h1].forEach((h, i) => h.addEventListener('keydown', (e) => {
        const d = { ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1 }[e.key]; if (!d) return; e.preventDefault();
        clampRange(i, (i === 0 ? S.t0 : S.t1) + d * (e.shiftKey ? 1 : frameLen())); seek(i === 0 ? S.t0 : S.t1); sync();
    }));

    // ----- filmstrip (progressive, cancellable, rebuilt on resize)
    let thumbToken = 0;
    async function filmstrip() {
        const token = ++thumbToken; const W = strip.clientWidth; const H = 54; const dpr = Math.min(2, window.devicePixelRatio || 1);
        thumbs.width = W * dpr; thumbs.height = H * dpr; thumbs.style.height = `${H}px`; const g = thumbs.getContext('2d'); g.scale(dpr, dpr); g.fillStyle = '#111'; g.fillRect(0, 0, W, H);
        const cellW = Math.max(24, Math.round(H * (S.vw / S.vh))); const cells = Math.max(1, Math.ceil(trackW() / cellW));
        for (let i = 0; i < cells; i++) {
            if (token !== thumbToken) return;
            await seekTo(tv, clamp(((i + 0.5) / cells) * S.dur, 0, S.dur - 0.01));
            if (token !== thumbToken) return;
            const cw = trackW() / cells; g.save(); g.translate(pad + i * cw, 0); g.beginPath(); g.rect(0, 0, cw + 1, H); g.clip(); drawCover(g, tv, S.vw, S.vh, cw + 1, H); g.restore();
        }
    }
    let rzT = 0; const ro = new ResizeObserver(() => { clearTimeout(rzT); rzT = setTimeout(() => { if (S.dur) { place(); filmstrip(); } }, 120); }); ro.observe(strip); disposers.push(() => ro.disconnect());

    // ----- build the GIF (capture frames from `cap`, encode in the worker); cached until a setting changes
    const key = () => JSON.stringify([S.t0, S.t1, S.width, S.fps, S.quality, S.speed, S.shape, S.boom, file.name, file.size]);
    const busy = (on) => { S.building = on; [bPrev, bSave, bUse, bChange].forEach((b) => { b.disabled = on; }); bar.hidden = !on; };
    async function build() {
        if (S.built && S.built.key === key()) return S.built;
        if (S.playing) pause(); busy(true); out.hidden = true; barFill.style.width = '0%'; S.abort = new AbortController();
        try {
            let { w, h } = geo(); let fps = S.fps; let ts = frameTimes(S.t0, S.t1, fps);
            const maxFrames = Math.floor(PIXEL_BUDGET / (w * h * 4 * (S.boom ? 1 : 1)));
            if (ts.length > maxFrames) { fps = Math.max(4, Math.floor(maxFrames / (S.t1 - S.t0))); ts = frameTimes(S.t0, S.t1, fps); msg.textContent = T('gs_reduced', { fps }); msg.hidden = false; }
            const cv = el('canvas'); cv.width = w; cv.height = h; const cx = cv.getContext('2d', { willReadFrequently: true }); const frames = [];
            for (let i = 0; i < ts.length; i++) {
                if (S.abort.signal.aborted) throw Object.assign(new Error('cancelled'), { cancelled: true });
                await seekTo(cap, ts[i]); cx.fillStyle = '#fff'; cx.fillRect(0, 0, w, h); drawCover(cx, cap, S.vw, S.vh, w, h);
                frames.push(cx.getImageData(0, 0, w, h).data); barFill.style.width = `${Math.round(((i + 1) / ts.length) * 55)}%`;
            }
            const bytes = await encodeFrames({ frames, w, h, fps, speed: S.speed, quality: S.quality, boomerang: S.boom, signal: S.abort.signal, onProgress: (p) => { barFill.style.width = `${55 + Math.round(p * 45)}%`; } });
            const blob = new Blob([bytes], { type: 'image/gif' }); const u = URL.createObjectURL(blob);
            if (S.built) URL.revokeObjectURL(S.built.url); S.built = { key: key(), blob, url: u, w, h, n: ts.length };
            outImg.src = u; outCap.textContent = T('gs_result', { size: bytesLabel(blob.size) }); out.hidden = false;
            const limit = c.gifMaxBytes || 8 * 1048576; if (blob.size > limit) { msg.textContent = T('gs_tooBig', { size: bytesLabel(blob.size), max: bytesLabel(limit) }); msg.hidden = false; }
            return S.built;
        } finally { busy(false); }
    }
    const fail = (e) => { if (e && e.cancelled) return; msg.textContent = L.gifFailed; msg.hidden = false; };
    bPrev.onclick = () => { msg.hidden = true; build().catch(fail); };
    const save = async (send) => {
        msg.hidden = true;
        try {
            const b = await build();
            if (c.onGifMade) {                                   // a host that collects the GIF itself (the admin library maker) instead of "my GIFs"
                await c.onGifMade({ blob: b.blob, name: nm.value.trim(), w: b.w, h: b.h }); dispose(); c.show('pGif'); return;
            }
            const dataUrl = await readAsDataUrl(b.blob);
            const preset = { id: Date.now(), name: nm.value.trim() || `GIF ${c.presets.length + 1}`, url: dataUrl };
            c.presets.unshift(preset); const saved = await c.savePresets();
            if (!saved) { c.presets.shift(); msg.textContent = L.gifStorage; msg.hidden = false; return; }
            c.say(T('gifSaved', { name: preset.name })); dispose();
            if (send) { await c.sendPreset(preset); } else { c.show('pGif'); c.renderGifs(''); }
        } catch (e) { fail(e); }
    };
    bSave.onclick = () => save(false); bUse.onclick = () => save(true);
    bCancel.onclick = () => { if (S.building) { S.abort && S.abort.abort(); return; } dispose(); c.show('pGif'); c.renderGifs(''); };

    // ----- load
    v.addEventListener('error', () => { msg.textContent = L.gs_unreadable; msg.hidden = false; bPrev.disabled = bSave.disabled = bUse.disabled = true; });
    v.addEventListener('loadedmetadata', async () => {
        await knowDuration(v); await Promise.all([knowDuration(tv), knowDuration(cap)]);
        S.dur = Number.isFinite(v.duration) ? v.duration : 0; S.vw = v.videoWidth; S.vh = v.videoHeight;
        if (!S.dur || !S.vw) { v.dispatchEvent(new Event('error')); return; }
        S.t0 = 0; S.t1 = snap(Math.min(S.dur, 3)); S.width = Math.min(S.width, WIDTHS.filter((x) => x <= S.vw).pop() || 240);
        if (S.dur > MAX_SECONDS) { msg.textContent = T('gs_toolong', { s: MAX_SECONDS }); msg.hidden = false; }
        v.currentTime = 0; place(); filmstrip(); sync();
    });
    v.addEventListener('loadeddata', () => { draw(); });
    sync();
}
