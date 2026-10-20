/*
 * Naara handbook reader (MASTER-ONLY). epub.js renders the EPUB inside sandboxed frames (scripts never run, nothing remote loads). Features: chapter drawer (jump by need),
 * font size, light/dark/sepia, bookmarks, progress synced across devices, and an offline copy kept ENCRYPTED in IndexedDB with a per-person key the server derives and only
 * hands to someone who currently holds the book; the copy must be re-validated online every N days (default 30) and on every online open.
 * "Unlocked = read-only" is a product rule, not DRM: the file bytes necessarily reach the browser to be read, so the copy is encrypted at rest and never offered as a download.
 */
import ePub from 'epubjs';
import { initReadAloud } from './read-aloud.js';

const root = document.querySelector('[data-reader]');
if (root) {
    const cfg = JSON.parse(root.dataset.cfg);
    const $ = (s) => root.querySelector(s);
    const status = $('[data-status]');
    const DB = 'naara-handbooks';
    const prefs = { size: 100, theme: 'auto', flow: 'paginated', font: 'serif', spacing: 'normal', margin: 'normal', ...(JSON.parse(localStorage.getItem('naara-reader-prefs') || '{}')) };
    let book; let rendition; let currentLocator = cfg.locator; let saveTimer;

    // Lets the reader page, its scripts and styles open with no connection (the book itself comes from the encrypted copy below).
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/library/sw.js', { scope: '/library/' }).catch(() => {});

    const idb = () => new Promise((res, rej) => {
        const r = indexedDB.open(DB, 1);
        r.onupgradeneeded = () => { r.result.createObjectStore('books'); };
        r.onsuccess = () => res(r.result);
        r.onerror = () => rej(r.error);
    });
    const idbGet = async (k) => { const d = await idb(); return new Promise((res) => { const q = d.transaction('books').objectStore('books').get(k); q.onsuccess = () => res(q.result); q.onerror = () => res(undefined); }); };
    const idbPut = async (k, v) => { const d = await idb(); return new Promise((res, rej) => { const t = d.transaction('books', 'readwrite'); t.objectStore('books').put(v, k); t.oncomplete = () => res(); t.onerror = () => rej(t.error); }); };
    const idbDel = async (k) => { const d = await idb(); return new Promise((res) => { const t = d.transaction('books', 'readwrite'); t.objectStore('books').delete(k); t.oncomplete = () => res(); }); };
    const b64u = (s) => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

    async function cryptoKey(b64) {
        // Non-extractable: stored beside the ciphertext, but its bytes cannot be read back out by script.
        return crypto.subtle.importKey('raw', b64u(b64), 'AES-GCM', false, ['encrypt', 'decrypt']);
    }
    async function seal(buf, key) {
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, buf);
        return { iv, ct };
    }
    async function open(rec) { return crypto.subtle.decrypt({ name: 'AES-GCM', iv: rec.iv }, rec.key, rec.ct); }

    const slugKey = `${cfg.uid}:${cfg.slug}:${cfg.edition}`;   // per person: a shared device never mixes two people's copies
    const say = (t) => { if (status) status.textContent = t; };

    async function fetchEpub() {
        const r = await fetch(cfg.urls.epub, { credentials: 'same-origin', cache: 'no-store' });
        if (r.status === 404) throw new Error('gone');
        if (!r.ok) throw new Error('net');
        return r.arrayBuffer();
    }

    /** Online: re-validate the entitlement and refresh the offline copy. Offline: use the copy if it was validated recently enough. */
    async function load() {
        const rec = await idbGet(slugKey);
        try {
            const kr = await fetch(cfg.urls.key, { credentials: 'same-origin', cache: 'no-store' });
            if (kr.status === 404) { await idbDel(slugKey); say('You no longer have access to this handbook.'); return null; }
            if (!kr.ok) throw new Error('net');
            const k = await kr.json();
            const key = await cryptoKey(k.key);
            const buf = await fetchEpub();
            try { const sealed = await seal(buf, key); await idbPut(slugKey, { ...sealed, key, validatedAt: k.validated_at * 1000, days: k.valid_for_days }); } catch (e) { /* the copy is a convenience */ }
            return buf;
        } catch (e) {
            if (rec) {
                const age = (Date.now() - rec.validatedAt) / 86400000;
                if (age > (rec.days || cfg.revalidateDays)) { say('This offline copy needs to go online once to be checked again.'); return null; }
                say('Reading offline.');
                return open(rec);
            }
            say('You are offline and this handbook has not been saved for offline reading yet. Open it once while online.');
            return null;
        }
    }

    const FONTS = { serif: 'Georgia, "Iowan Old Style", "Times New Roman", serif', sans: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' };
    const SPACING = { compact: '1.4', normal: '1.65', relaxed: '1.95' };
    const MARGIN = { narrow: '0', normal: '0 4%', wide: '0 10%' };

    function applyTheme() {
        const dark = prefs.theme === 'dark' || (prefs.theme === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
        const c = prefs.theme === 'sepia' ? { bg: '#F4ECD8', fg: '#3B2F1E' } : dark ? { bg: '#0D1B2A', fg: '#E6EDF5' } : { bg: '#FFFFFF', fg: '#111827' };
        root.dataset.theme = prefs.theme === 'sepia' ? 'sepia' : dark ? 'dark' : 'light';
        root.querySelectorAll('[data-pref]').forEach((b) => b.classList.toggle('is-on', String(prefs[b.dataset.pref]) === b.dataset.value));
        if (rendition) {
            // Images, figures, tables and video always fit the screen (and a page): nothing is ever cut off or wider than the column.
            rendition.themes.default({
                body: { background: c.bg + ' !important', color: c.fg + ' !important', 'line-height': (SPACING[prefs.spacing] || '1.65') + ' !important', 'font-family': FONTS[prefs.font] + ' !important', padding: (MARGIN[prefs.margin] || '0 4%') + ' !important', 'overflow-wrap': 'anywhere', '-webkit-text-size-adjust': '100%' },
                a: { color: 'inherit !important', 'text-decoration': 'underline' },
                'img, svg, video, canvas': { 'max-width': '100% !important', height: 'auto !important', 'max-height': '82vh !important', 'object-fit': 'contain', display: 'block', margin: '0.8em auto !important', 'border-radius': '6px' },
                'figure': { margin: '1em 0 !important', 'max-width': '100% !important', 'break-inside': 'avoid' },
                'figcaption': { 'font-size': '0.85em', opacity: '.75', 'text-align': 'center' },
                'table': { 'max-width': '100% !important', display: 'block', 'overflow-x': 'auto' },
                'pre': { 'white-space': 'pre-wrap', 'overflow-wrap': 'anywhere' },
                'h1, h2, h3': { 'line-height': '1.25 !important', 'break-after': 'avoid' },
                '::highlight(naara-tts)': { 'background-color': 'rgba(254, 177, 4, 0.45)', color: 'inherit' },
                'p, li': { 'orphans': '2', 'widows': '2' },
            });
            rendition.themes.fontSize(prefs.size + '%');
            rendition.themes.font(FONTS[prefs.font]);
        }
    }
    const savePrefs = () => localStorage.setItem('naara-reader-prefs', JSON.stringify(prefs));

    let tocItems = [];
    const hrefBase = (h) => String(h || '').split('#')[0].split('/').pop();
    function drawToc(items, filter = '') {
        tocItems = items;
        const ul = $('[data-toc]');
        ul.textContent = '';
        const f = filter.trim().toLowerCase();
        const shown = f ? items.filter((t) => t.title.toLowerCase().includes(f)) : items;
        if (!shown.length) { const li = document.createElement('li'); li.className = 'hbr__empty'; li.textContent = f ? 'No section matches that.' : 'This book has no chapter list.'; ul.appendChild(li); return; }
        shown.forEach((t) => {
            const li = document.createElement('li');
            li.style.marginInlineStart = (f ? 0 : Math.min(3, t.depth || 0) * 14) + 'px';
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = t.title;
            b.dataset.base = hrefBase(t.href);
            b.addEventListener('click', () => { rendition.display(t.href); toggle('toc', false); });
            li.appendChild(b);
            ul.appendChild(li);
        });
        markCurrent();
    }
    let currentHref = '';
    function markCurrent() {
        const cur = hrefBase(currentHref);
        let hit = null;
        root.querySelectorAll('[data-toc] button').forEach((b) => { const on = b.dataset.base === cur; b.classList.toggle('is-current', on); if (on) hit = b; });
        if (hit) hit.scrollIntoView({ block: 'nearest' });
        const name = tocItems.find((t) => hrefBase(t.href) === cur);
        const el = $('[data-chapter]'); if (el) el.textContent = name ? name.title : '';
    }

    async function drawBookmarks() {
        const ul = $('[data-bookmarks]');
        try {
            const list = await (await fetch(cfg.urls.bookmarks, { credentials: 'same-origin' })).json();
            ul.textContent = '';
            if (!list.length) { ul.textContent = 'No bookmarks yet.'; return; }
            list.forEach((m) => {
                const li = document.createElement('li');
                const go = document.createElement('button'); go.type = 'button'; go.textContent = m.label || 'Bookmark'; go.addEventListener('click', () => { rendition.display(m.locator); toggle('marks', false); });
                const del = document.createElement('button'); del.type = 'button'; del.textContent = 'Remove'; del.className = 'is-quiet';
                del.addEventListener('click', async () => { await fetch(cfg.urls.bookmarks, { method: 'DELETE', credentials: 'same-origin', headers: hdr(), body: JSON.stringify({ id: m.id }) }); drawBookmarks(); });
                li.append(go, del); ul.appendChild(li);
            });
        } catch (e) { ul.textContent = 'Bookmarks need a connection.'; }
    }

    const hdr = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN=')) || '').slice(11)) });
    const toggle = (name, force) => { root.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel === name ? (force === undefined ? !p.hidden : !force) : true; }); };

    function queueSave(loc) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            fetch(cfg.urls.progress, { method: 'POST', credentials: 'same-origin', headers: hdr(), body: JSON.stringify({ locator: loc.start.cfi, percent: Math.round((book.locations.length() ? book.locations.percentageFromCfi(loc.start.cfi) : 0) * 10000) / 100 }) }).catch(() => {});
        }, 1200);
    }

    // Full-size picture viewer: tap a picture, then tap it again (or use pinch) to zoom; tap outside or press Esc to close.
    const box = $('[data-lightbox]');
    function openImage(img) {
        if (!box) return;
        const big = box.querySelector('img'); big.src = img.currentSrc || img.src; big.alt = img.alt || '';
        box.classList.remove('is-zoom'); box.hidden = false; box.focus();
    }
    function closeImage() { if (box && !box.hidden) { box.hidden = true; box.querySelector('img').removeAttribute('src'); } }
    if (box) {
        box.addEventListener('click', (e) => { if (e.target.tagName === 'IMG') box.classList.toggle('is-zoom'); else closeImage(); });
    }
    let scrubbing = false;
    const scrub = $('[data-scrub]');
    if (scrub) {
        scrub.addEventListener('input', () => { scrubbing = true; $('[data-pct]').textContent = scrub.value + '%'; });
        scrub.addEventListener('change', () => { if (book?.locations.length()) rendition.display(book.locations.cfiFromPercentage(scrub.value / 100)); scrubbing = false; });
    }
    const tocFilter = $('[data-toc-filter]');
    if (tocFilter) tocFilter.addEventListener('input', () => drawToc(tocItems, tocFilter.value));

    (async () => {
        say('Opening…');
        const buf = await load();
        if (!buf) return;
        book = ePub(buf);
        rendition = book.renderTo($('[data-stage]'), { width: '100%', height: '100%', flow: prefs.flow === 'scroll' ? 'scrolled-doc' : 'paginated', spread: 'none', allowScriptedContent: false });
        root.classList.toggle('is-scroll', prefs.flow === 'scroll');
        applyTheme();
        await rendition.display(currentLocator || undefined);
        say('');
        initReadAloud({ root, getRendition: () => rendition, getBook: () => book, getFlow: () => prefs.flow, say });
        book.loaded.navigation.then((nav) => {
            const flat = [];
            (function walk(items, d) { items.forEach((i) => { flat.push({ title: (i.label || '').trim(), href: i.href, depth: d }); if (i.subitems) walk(i.subitems, d + 1); }); })(nav.toc, 0);
            drawToc(flat.length ? flat : cfg.toc.map((t) => ({ title: t.title, href: t.href, depth: t.depth })));
        });
        rendition.on('relocated', (loc) => {
            currentLocator = loc.start.cfi; currentHref = loc.start.href; queueSave(loc);
            const p = book.locations.length() ? Math.round(book.locations.percentageFromCfi(loc.start.cfi) * 100) : null;
            $('[data-pct]').textContent = p === null ? '' : p + '%';
            const sc = $('[data-scrub]'); if (sc && p !== null && !scrubbing) sc.value = p;
            markCurrent();
        });
        book.ready.then(() => book.locations.generate(1200)).then(() => { const sc = $('[data-scrub]'); if (sc) { sc.disabled = false; const loc = rendition.currentLocation(); if (loc?.start) sc.value = Math.round(book.locations.percentageFromCfi(loc.start.cfi) * 100); } }).catch(() => {});

        // Taps and swipes inside the page: edges turn the page, the middle shows or hides the controls, a picture opens full-size.
        rendition.on('click', (e) => {
            if (e.target && e.target.closest && e.target.closest('a[href]')) return;
            if (e.target && e.target.tagName === 'IMG') { openImage(e.target); return; }
            const w = e.view?.innerWidth || window.innerWidth;
            const x = e.clientX;
            if (prefs.flow !== 'scroll' && x < w * 0.25) rendition.prev();
            else if (prefs.flow !== 'scroll' && x > w * 0.75) rendition.next();
            else root.classList.toggle('is-immersive');
        });
        let sx = 0; let sy = 0;
        rendition.on('touchstart', (e) => { sx = e.changedTouches[0].screenX; sy = e.changedTouches[0].screenY; });
        rendition.on('touchend', (e) => {
            if (prefs.flow === 'scroll') return;
            const dx = e.changedTouches[0].screenX - sx; const dy = e.changedTouches[0].screenY - sy;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) (dx < 0 ? rendition.next() : rendition.prev());
        });
        document.addEventListener('keydown', (e) => {
            if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
            if (e.key === 'ArrowRight' || e.key === 'PageDown') rendition.next();
            else if (e.key === 'ArrowLeft' || e.key === 'PageUp') rendition.prev();
            else if (e.key === 'Escape') { toggle('', false); closeImage(); }
            else if (e.key.toLowerCase() === 't') toggle('toc');
        });
    })();

    root.addEventListener('click', async (e) => {
        const t = e.target.closest('[data-act]');
        if (!t || !rendition) return;
        const a = t.dataset.act;
        if (a === 'next') rendition.next();
        else if (a === 'prev') rendition.prev();
        else if (a === 'toc') { toggle('toc'); const f = $('[data-toc-filter]'); if (f && !$('[data-panel=toc]').hidden && matchMedia('(min-width: 768px)').matches) f.focus(); }
        else if (a === 'marks') { toggle('marks'); drawBookmarks(); }
        else if (a === 'settings') toggle('settings');
        else if (a === 'bigger') { prefs.size = Math.min(220, prefs.size + 10); applyTheme(); savePrefs(); }
        else if (a === 'smaller') { prefs.size = Math.max(70, prefs.size - 10); applyTheme(); savePrefs(); }
        else if (a === 'theme') { prefs.theme = t.dataset.value; applyTheme(); savePrefs(); }
        else if (a === 'pref') {
            const k = t.dataset.pref; prefs[k] = t.dataset.value;
            if (k === 'flow') { rendition.flow(prefs.flow === 'scroll' ? 'scrolled-doc' : 'paginated'); root.classList.toggle('is-scroll', prefs.flow === 'scroll'); }
            applyTheme(); savePrefs();
        }
        else if (a === 'bookmark') { await fetch(cfg.urls.bookmarks, { method: 'POST', credentials: 'same-origin', headers: hdr(), body: JSON.stringify({ locator: currentLocator, label: (rendition.currentLocation()?.start?.href || '').split('/').pop().replace(/\.x?html?.*/, '') || 'Bookmark' }) }); t.classList.add('is-done'); setTimeout(() => t.classList.remove('is-done'), 1200); }
        else if (a === 'forget') { await idbDel(slugKey); say('The offline copy was removed from this device.'); }
    });
}
