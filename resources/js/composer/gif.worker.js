// GIF encoder worker (GIF Studio). Runs off the main thread so scrubbing and the live preview stay smooth while a clip is being built.
// Protocol: {t:'init', w, h, quality, sample?: Uint8ClampedArray[]} -> {t:'frame', data, delay} per frame -> {t:'finish'} => posts {t:'progress', n} per frame and {t:'done', bytes}.
import { GIFEncoder, quantize, applyPalette } from 'gifenc';

let enc = null; let opts = null; let palette = null; let n = 0;

self.onmessage = (e) => {
    const m = e.data;
    try {
        if (m.t === 'init') {
            opts = m; enc = GIFEncoder(); n = 0; palette = null;
            if (m.quality !== 'best' && m.sample && m.sample.length) {
                // One palette for the whole clip, built from frames spread across it: no flicker between frames, smaller file.
                const total = m.sample.reduce((a, s) => a + s.length, 0); const all = new Uint8ClampedArray(total); let o = 0;
                for (const s of m.sample) { all.set(s, o); o += s.length; }
                palette = quantize(all, 256, { format: m.quality === 'fast' ? 'rgb444' : 'rgb565' });
            }
        } else if (m.t === 'frame') {
            const d = m.data; const pal = palette || quantize(d, 256, { format: 'rgb565' });
            const idx = applyPalette(d, pal, palette ? (opts.quality === 'fast' ? 'rgb444' : 'rgb565') : 'rgb565');
            enc.writeFrame(idx, opts.w, opts.h, { palette: pal, delay: m.delay, repeat: 0 });
            n++; self.postMessage({ t: 'progress', n });
        } else if (m.t === 'finish') {
            enc.finish(); const bytes = enc.bytes(); self.postMessage({ t: 'done', bytes }, [bytes.buffer]);
        }
    } catch (err) { self.postMessage({ t: 'error', message: String(err && err.message || err) }); }
};
