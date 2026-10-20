// <naara-gif-maker> (registered in index.js) — the GIF Studio on its own, for the admin GIF library: choose a video, trim it, build the GIF, and hand the finished file to the page
// (event `gifmaker-made` with { blob, name }). It reuses the composer's studio unchanged, so what an admin makes here is exactly what any member can make in chat.
import { LABELS } from './labels.js';
import { openGifStudio } from './gifstudio.js';

const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };

/** Build the maker inside `host` (a <naara-gif-maker>). Returns a disposer. */
export function mountGifMaker(host) {
    let o = {}; try { o = JSON.parse(host.getAttribute('data-config') || '{}'); } catch { /* defaults */ }
    const L = { ...LABELS, ...(o.labels || {}) };
    const root = el('div', 'cc'); const panel = el('div', 'cc-panel cc-gmaker'); const start = el('div', 'cc-gmk-start');
    const note = el('p', 'cc-gmk-note', o.hint || ''); const pick = el('button', 'cc-chip cc-primary', L.videoToGif); pick.type = 'button';
    start.append(note, pick); root.append(panel); host.append(root);
    const c = {
        L, refs: { pGifMaker: panel }, presets: [], gifMaxBytes: o.maxBytes || 0, _gsDispose: null,
        show: () => { if (!c._gsDispose) { panel.replaceChildren(start); } },
        renderGifs: () => {}, say: (m) => host.dispatchEvent(new CustomEvent('gifmaker-say', { detail: m, bubbles: true })),
        savePresets: async () => true, sendPreset: async () => {},
        pickFiles: (accept, multiple, cb) => { const i = el('input'); i.type = 'file'; i.accept = accept; i.multiple = multiple; i.onchange = () => i.files.length && cb(Array.from(i.files)); i.click(); },
        onGifMade: async ({ blob, name }) => { host.dispatchEvent(new CustomEvent('gifmaker-made', { detail: { blob, name }, bubbles: true })); },
    };
    pick.onclick = () => c.pickFiles('video/*', false, (fs) => openGifStudio(c, fs[0]));
    panel.replaceChildren(start);

    return () => { c._gsDispose && c._gsDispose(); };
}
