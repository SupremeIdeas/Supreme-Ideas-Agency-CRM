// Chat Composer Pro: message formatting tools (opt-in via the `format` feature). Writes a small closed markdown dialect into the textarea
// (see App\Support\N2N\ChatFormat for the matching, escaping renderer). Everything operates on the textarea's own value and selection, so
// drafts, undo, character limits and the send pipeline are untouched. Strings are labels or markers, never user data inserted as HTML.
import { ICONS } from './icons.js';

const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
const svg = (body, extra = '') => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ${extra}>${body}</svg>`;

export const FORMAT_ICONS = {
    format: svg('<path d="M4 7V5h16v2M9 19h6M12 5v14"/>'),
    list: svg('<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" fill="currentColor"/><circle cx="4.5" cy="12" r="1" fill="currentColor"/><circle cx="4.5" cy="18" r="1" fill="currentColor"/>'),
    olist: svg('<path d="M10 6h10M10 12h10M10 18h10M4 5l1.5-1v4M4 14.5c0-1 2-1 2 0s-2 1.5-2 2.5h2"/>'),
    task: svg('<rect x="3.5" y="4" width="6" height="6" rx="1.4"/><path d="m5 7 1 1 1.8-2M13 7h8M13 17h8"/><rect x="3.5" y="14" width="6" height="6" rx="1.4"/>'),
    quote: svg('<path d="M7 7h4v5c0 2-1 3.5-3.5 4M14 7h4v5c0 2-1 3.5-3.5 4"/>'),
    code: svg('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>'),
    block: svg('<rect x="3" y="4" width="18" height="16" rx="3"/><path d="m9.5 9.5-2.5 2.5 2.5 2.5M14.5 9.5l2.5 2.5-2.5 2.5"/>'),
    banner: svg('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M7 10h10M7 14h6"/>'),
    text: svg('<path d="M5 6V4.5h14V6M12 4.5V19.5M9 19.5h6"/>'),
    lock: svg('<rect x="5" y="11" width="14" height="9" rx="2.4"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'),
};

/** Banner colours: the swatch is only a hint; the bubble paints from skin tokens (see nx-layout.css), so contrast is enforced there. */
export const BANNERS = ['red', 'orange', 'yellow', 'blue', 'purple', 'pink', 'green', 'grey'];

const PREFIX_RX = /^(#{1,3} |> ?|[-*] \[[ xX]\] |\[[ xX]\] |[-*] |\d{1,3}\. )/;
const LIST_RX = /^(\s*)([-*] \[[ xX]\] |\[[ xX]\] |[-*] |(\d{1,3})\. )(.*)$/;

export function buildFormat(c, ref) {
    const L = c.L; const p = c.panel('pFormat', ref, L.format); p.classList.add('cc-fmt');
    const mk = (cls, label, icon, act, arg) => {
        const b = el('button', cls); b.type = 'button'; b.dataset.act = act; if (arg) b.dataset.arg = arg;
        b.setAttribute('aria-label', label); b.title = label; if (icon) b.innerHTML = icon; return b;
    };
    const glyph = (cls, label, txt, act, arg) => { const b = mk(cls, label, '', act, arg); b.append(el('span', 'cc-fmt-g', txt)); return b; };

    const row1 = el('div', 'cc-fmt-row'); row1.setAttribute('role', 'toolbar'); row1.setAttribute('aria-label', L.format);
    row1.append(glyph('cc-fmt-b', L.bold, 'B', 'wrap', '**'), glyph('cc-fmt-b cc-i', L.italic, 'I', 'wrap', '_'), glyph('cc-fmt-b cc-s', L.strike, 'S', 'wrap', '~'), mk('cc-fmt-b', L.inlineCode, FORMAT_ICONS.code, 'wrap', '`'));
    p.append(row1);

    p.append(el('div', 'cc-label', L.turnInto));
    const grid = el('div', 'cc-fmt-grid');
    const tile = (label, icon, glyphText, act, arg) => {
        const b = el('button', 'cc-fmt-tile'); b.type = 'button'; b.dataset.act = act; if (arg !== undefined) b.dataset.arg = arg;
        const ic = el('span', 'cc-fmt-ic'); if (icon) ic.innerHTML = icon; else ic.append(el('span', 'cc-fmt-g', glyphText));
        b.append(ic, el('span', 'cc-fmt-l', label)); return b;
    };
    grid.append(tile(L.text, FORMAT_ICONS.text, '', 'line', ''), tile(`${L.heading} 1`, '', 'H1', 'line', '# '), tile(`${L.heading} 2`, '', 'H2', 'line', '## '), tile(`${L.heading} 3`, '', 'H3', 'line', '### '),
        tile(L.bulletList, FORMAT_ICONS.list, '', 'line', '- '), tile(L.numberList, FORMAT_ICONS.olist, '', 'line', '1. '), tile(L.checkList, FORMAT_ICONS.task, '', 'line', '[ ] '), tile(L.quote, FORMAT_ICONS.quote, '', 'line', '> '),
        tile(L.codeBlock, FORMAT_ICONS.block, '', 'block'));
    p.append(grid);

    p.append(el('div', 'cc-label', L.banners));
    const bn = el('div', 'cc-fmt-banners');
    BANNERS.forEach((col) => { const b = el('button', `cc-fmt-banner cc-bn-${col}`); b.type = 'button'; b.dataset.act = 'banner'; b.dataset.arg = col; b.setAttribute('aria-label', `${L.banner}: ${L[`b_${col}`] || col}`); b.innerHTML = '<i></i><i></i>'; b.append(el('span', '', L[`b_${col}`] || col)); bn.append(b); });
    p.append(bn);
    p.append(el('div', 'cc-fmt-hint', L.formatHint));
    return p;
}

export function bindFormat(c) {
    const r = c.refs; const t = r.text;
    const commit = (value, a, b = a) => {
        t.value = value; t.setSelectionRange(a, b); t.dispatchEvent(new Event('input', { bubbles: true })); t.focus();
    };
    const lines = () => {
        const s = t.selectionStart; const e = t.selectionEnd; const v = t.value;
        const from = v.lastIndexOf('\n', s - 1) + 1; let to = v.indexOf('\n', e); if (to < 0) to = v.length;
        return { v, from, to, s, e };
    };
    const wrap = (m) => {
        const { v } = lines(); const s = t.selectionStart; const e = t.selectionEnd; const n = m.length;
        if (s === e) { commit(v.slice(0, s) + m + m + v.slice(e), s + n); return; }
        if (v.slice(s - n, s) === m && v.slice(e, e + n) === m) { commit(v.slice(0, s - n) + v.slice(s, e) + v.slice(e + n), s - n, e - n); return; }   // already wrapped: unwrap
        commit(v.slice(0, s) + m + v.slice(s, e) + m + v.slice(e), s + n, e + n);
    };
    const line = (prefix) => {
        const { v, from, to, s } = lines(); const block = v.slice(from, to).split('\n');
        const same = prefix !== '' && block.every((l) => l.startsWith(prefix.replace(/^\d+/, '')) || (/^\d+\. $/.test(prefix) && /^\d{1,3}\. /.test(l)));
        const out = block.map((l, i) => { const bare = l.replace(PREFIX_RX, ''); if (prefix === '' || same) return bare; return (/^\d+\. $/.test(prefix) ? `${i + 1}. ` : prefix) + bare; });
        const text = out.join('\n'); const delta = text.length - (to - from);
        commit(v.slice(0, from) + text + v.slice(to), Math.max(from, s + (block.length === 1 ? delta : 0)), from + text.length);
    };
    const fenced = (open, close) => {
        const { v, from, to } = lines(); const body = v.slice(from, to);
        const ins = `${open}\n${body}\n${close}`; const caret = from + open.length + 1;
        commit(v.slice(0, from) + ins + v.slice(to), caret, caret + body.length);
    };
    const act = { wrap, line, block: () => fenced('```', '```'), banner: (col) => fenced(`:::${col}`, ':::') };

    r.pFormat.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-act]'); if (!b) return;
        act[b.dataset.act]?.(b.dataset.arg ?? '');
    });
    if (r.fmtBtn) c.on(r.fmtBtn, 'click', () => c.toggle('pFormat', r.fmtBtn));

    // Keyboard: Ctrl/Cmd+B, I; Ctrl/Cmd+Enter always sends; Enter inside a list continues the list (an empty item ends it).
    c.on(t, 'keydown', (e) => {
        const mod = e.ctrlKey || e.metaKey;
        if (mod && !e.shiftKey && (e.key === 'b' || e.key === 'B')) { e.preventDefault(); wrap('**'); return; }
        if (mod && !e.shiftKey && (e.key === 'i' || e.key === 'I')) { e.preventDefault(); wrap('_'); return; }
        if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
        if (mod) return;   // handled by the base send path below
        const { v, from, to, s, e: se } = lines(); if (s !== se || s !== to) return;
        const m = LIST_RX.exec(v.slice(from, to)); if (!m) return;
        e.preventDefault(); e.stopImmediatePropagation();
        if (m[4].trim() === '') { commit(v.slice(0, from) + v.slice(to), from); return; }
        const next = m[3] ? `${m[1]}${parseInt(m[3], 10) + 1}. ` : m[2].includes('[') ? `${m[1]}[ ] ` : `${m[1]}${m[2]}`;
        commit(`${v.slice(0, to)}\n${next}${v.slice(to)}`, to + 1 + next.length);
    }, true);
}
