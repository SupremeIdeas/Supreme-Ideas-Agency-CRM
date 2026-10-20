/*
 * Read aloud for the Naara handbook reader (MASTER-ONLY). Uses the browser's own speech engine (the Web Speech API): free, no key, nothing is sent to us. On Chrome and Android
 * that is Google's natural voices; on Edge the Microsoft "Natural" voices; on iPhone and Mac the Siri / Enhanced voices. The best installed voice for the book's language is
 * picked automatically and can be changed. The book is read one sentence at a time (which also avoids Chrome's habit of stopping long speech), the sentence being read is
 * highlighted, the page or scroll follows along, and at the end of a section it carries on into the next. Play, pause, previous/next sentence, speed, voice and a sleep timer.
 */
const RATES = [0.85, 1, 1.15, 1.3, 1.5, 1.75, 2];
const store = {
    get: (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } },
};

/** Higher = more natural. Names are matched loosely because every browser words them differently. */
export function voiceScore(v, lang) {
    const l = (v.lang || '').toLowerCase().replace('_', '-');
    const want = (lang || 'en').toLowerCase();
    let s = 0;
    if (l === want) s += 60; else if (l.split('-')[0] === want.split('-')[0]) s += 40; else return -1;
    const n = (v.name || '').toLowerCase();
    if (/natural|neural|online/.test(n)) s += 70;          // Microsoft Edge natural voices
    if (/google/.test(n)) s += 55;                           // Google voices on Chrome and Android
    if (/premium|enhanced|siri/.test(n)) s += 45;           // Apple's better voices
    if (v.localService === false) s += 8;                    // network voices are usually the newest
    if (/compact|espeak|festival/.test(n)) s -= 25;
    return s;
}

export function pickVoice(voices, lang, savedName) {
    const saved = voices.find((v) => v.name === savedName && voiceScore(v, lang) >= 0);
    if (saved) return saved;
    return voices.map((v) => [voiceScore(v, lang), v]).filter(([s]) => s >= 0).sort((a, b) => b[0] - a[0]).map(([, v]) => v)[0] || null;
}

/** Split one block of text into sentences (Intl.Segmenter where the browser has it). */
export function sentences(text, lang) {
    const t = text.replace(/\s+/g, ' ').trim();
    if (!t) return [];
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
        try { return Array.from(new Intl.Segmenter(lang || 'en', { granularity: 'sentence' }).segment(t), (s) => s.segment.trim()).filter(Boolean); } catch (e) { /* fall through */ }
    }
    return t.match(/[^.!?]+[.!?]+["')\]]*|[^.!?]+$/g)?.map((s) => s.trim()).filter(Boolean) || [t];
}

export function initReadAloud({ root, getRendition, getBook, getFlow, say, lang }) {
    const bar = root.querySelector('[data-listen]');
    const synth = window.speechSynthesis;
    if (!bar || !synth || typeof SpeechSynthesisUtterance === 'undefined') {
        root.querySelectorAll('[data-act="listen"]').forEach((b) => b.remove());

        return;
    }
    const $ = (s) => bar.querySelector(s);
    const st = { rate: store.get('naara-tts-rate', 1), voiceName: store.get('naara-tts-voice', ''), follow: store.get('naara-tts-follow', true), items: [], i: 0, playing: false, token: 0, timer: null, sleepAt: 0 };
    const bookLang = () => (lang || getBook()?.packaging?.metadata?.language || 'en');

    // ---- voices ---------------------------------------------------------------------------------------------------------------------------
    let voices = [];
    const loadVoices = () => {
        voices = synth.getVoices().filter((v) => voiceScore(v, bookLang()) >= 0).sort((a, b) => voiceScore(b, bookLang()) - voiceScore(a, bookLang()));
        const sel = $('[data-tts-voice]');
        sel.textContent = '';
        voices.forEach((v) => { const o = document.createElement('option'); o.value = v.name; o.textContent = v.name.replace(/^(Microsoft|Google)\s+/i, '$1 ') + (v.localService === false ? ' (online)' : ''); sel.appendChild(o); });
        const cur = pickVoice(voices, bookLang(), st.voiceName);
        if (cur) sel.value = cur.name;
        $('[data-tts-novoice]').hidden = voices.length > 0;
    };
    loadVoices();
    synth.addEventListener?.('voiceschanged', loadVoices);
    const voice = () => pickVoice(voices, bookLang(), $('[data-tts-voice]').value || st.voiceName);

    // ---- reading the current section ------------------------------------------------------------------------------------------------------
    const contents = () => getRendition()?.getContents?.()[0] || null;

    /** Every text node of the section, as sentences with a DOM Range each, in reading order. */
    function collect(doc) {
        const out = [];
        const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, { acceptNode: (n) => (/\S/.test(n.nodeValue) && !n.parentElement.closest('script,style,nav') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT) });
        let n; let carry = null;
        while ((n = walker.nextNode())) {
            const text = n.nodeValue;
            let from = 0;
            for (const s of sentences(text, bookLang())) {
                const at = text.indexOf(s, from);
                if (at < 0) continue;
                from = at + s.length;
                const r = doc.createRange(); r.setStart(n, at); r.setEnd(n, at + s.length);
                out.push({ text: s, range: r, node: n });
            }
        }
        return out;
    }

    /** The first sentence the reader can see right now (so "listen" starts where the person is looking). */
    function firstVisible(items, win) {
        const w = win.innerWidth; const h = win.innerHeight;
        const idx = items.findIndex((it) => { const r = it.range.getBoundingClientRect(); return r.width > 0 && r.bottom > 0 && r.top < h && r.right > 0 && r.left < w && r.top >= -4 && r.left >= -4; });
        return idx < 0 ? 0 : idx;
    }

    function highlight(it) {
        const c = contents();
        if (!c) return;
        try {
            if (c.window.CSS?.highlights && c.window.Highlight) { c.window.CSS.highlights.set('naara-tts', new c.window.Highlight(it.range)); }
        } catch (e) { /* highlight is a nicety */ }
        if (!st.follow) return;
        const r = it.range.getBoundingClientRect();
        const rend = getRendition();
        if (getFlow() === 'scroll') {
            const mid = c.window.innerHeight / 2;
            if (r.top < 60 || r.bottom > c.window.innerHeight - 60) c.window.scrollBy({ top: r.top - mid + r.height / 2, behavior: 'smooth' });
        } else if (r.left >= c.window.innerWidth - 4) {
            rend.next();   // the sentence is on the next page of the section
        }
    }

    function clearHighlight() { try { contents()?.window.CSS?.highlights?.delete('naara-tts'); } catch (e) { /* none */ } }

    function say1() {
        const it = st.items[st.i];
        if (!it) { nextSection(); return; }
        const my = ++st.token;
        synth.cancel();
        const u = new SpeechSynthesisUtterance(it.text);
        const v = voice();
        try { if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = bookLang(); } } catch (e) { u.lang = bookLang(); }   // a stale voice object must never stop the reading
        u.rate = st.rate;
        u.onend = () => { if (my !== st.token || !st.playing) return; if (st.sleepAt && Date.now() > st.sleepAt) { stop(); say('Sleep timer finished.'); return; } st.i += 1; say1(); };
        u.onerror = (e) => { if (my !== st.token) return; if (e.error === 'interrupted' || e.error === 'canceled') return; st.playing = false; paint(); say('Could not read aloud: ' + (e.error || 'the voice failed') + '.'); };
        highlight(it);
        paint();
        synth.speak(u);
    }

    async function nextSection() {
        const rend = getRendition();
        clearHighlight();
        // Turn to the next section and wait for the reader to confirm it moved (a "relocated" event); no move within a few seconds means the book has ended.
        const moved = new Promise((res) => { const t = setTimeout(() => res(null), 3500); rend.once('relocated', (loc) => { clearTimeout(t); res(loc); }); });
        try { await rend.next(); } catch (e) { /* end of the book */ }
        const loc = await moved;
        if (!loc || !st.playing) { if (st.playing) { stop(); say('You have reached the end of the book.'); } return; }
        await new Promise((r) => setTimeout(r, 350));   // let the new section finish drawing
        begin(false);
    }

    function begin(fromVisible = true) {
        const c = contents();
        if (!c) { say('Open a page first.'); return; }
        st.items = collect(c.document);
        if (!st.items.length) { nextSection(); return; }
        st.i = fromVisible ? firstVisible(st.items, c.window) : 0;
        st.playing = true;
        mediaSession();
        say1();
    }

    function play() { if (st.playing) return; if (st.items.length && contents() && st.items[st.i]?.range?.startContainer?.isConnected) { st.playing = true; say1(); } else begin(true); }
    function pause() { st.playing = false; st.token += 1; synth.cancel(); paint(); }
    function stop() { st.playing = false; st.token += 1; synth.cancel(); clearHighlight(); st.items = []; st.i = 0; paint(); }
    function skip(d) { if (!st.items.length) return; st.i = Math.max(0, Math.min(st.items.length - 1, st.i + d)); if (st.playing) say1(); else { highlight(st.items[st.i]); } }

    // ---- bar ------------------------------------------------------------------------------------------------------------------------------
    function paint() {
        bar.classList.toggle('is-playing', st.playing);
        $('[data-tts-play]').innerHTML = st.playing
            ? '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4.2" height="14" rx="1.4"/><rect x="13.8" y="5" width="4.2" height="14" rx="1.4"/></svg>'
            : '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg>';
        $('[data-tts-play]').setAttribute('aria-label', st.playing ? 'Pause reading' : 'Read aloud');
        $('[data-tts-rate]').textContent = st.rate + 'x';
        $('[data-tts-now]').textContent = st.items[st.i] ? st.items[st.i].text : 'Press play to hear this page.';
        $('[data-tts-follow]').setAttribute('aria-pressed', st.follow ? 'true' : 'false');
    }

    function mediaSession() {
        if (!('mediaSession' in navigator)) return;
        try {
            navigator.mediaSession.metadata = new MediaMetadata({ title: root.querySelector('.hbr__title')?.textContent || 'Handbook', artist: 'Naara Travel Handbooks' });
            navigator.mediaSession.setActionHandler('play', play); navigator.mediaSession.setActionHandler('pause', pause);
            navigator.mediaSession.setActionHandler('previoustrack', () => skip(-1)); navigator.mediaSession.setActionHandler('nexttrack', () => skip(1));
        } catch (e) { /* unsupported */ }
    }

    bar.addEventListener('click', (e) => {
        const t = e.target.closest('[data-tts-play],[data-tts-prev],[data-tts-next],[data-tts-rate],[data-tts-stop],[data-tts-follow],[data-tts-sleep],[data-tts-close]');
        if (!t) return;
        if (t.matches('[data-tts-play]')) st.playing ? pause() : play();
        else if (t.matches('[data-tts-prev]')) skip(-1);
        else if (t.matches('[data-tts-next]')) skip(1);
        else if (t.matches('[data-tts-rate]')) { st.rate = RATES[(RATES.indexOf(st.rate) + 1) % RATES.length]; store.set('naara-tts-rate', st.rate); if (st.playing) say1(); else paint(); }
        else if (t.matches('[data-tts-stop]')) stop();
        else if (t.matches('[data-tts-follow]')) { st.follow = !st.follow; store.set('naara-tts-follow', st.follow); paint(); }
        else if (t.matches('[data-tts-sleep]')) {
            const opts = [0, 10, 20, 30, 60]; const cur = Number(t.dataset.minutes || 0); const nx = opts[(opts.indexOf(cur) + 1) % opts.length];
            t.dataset.minutes = String(nx); st.sleepAt = nx ? Date.now() + nx * 60000 : 0; t.textContent = nx ? 'Sleep ' + nx + ' min' : 'Sleep timer off';
        } else if (t.matches('[data-tts-close]')) { stop(); bar.hidden = true; }
    });
    $('[data-tts-voice]').addEventListener('change', (e) => { st.voiceName = e.target.value; store.set('naara-tts-voice', st.voiceName); if (st.playing) say1(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && st.playing) { /* keep reading: the media session lets the phone keep it going */ } });
    window.addEventListener('beforeunload', () => synth.cancel());

    // The "listen" button in the bar opens the player and starts reading from what is on screen.
    root.addEventListener('click', (e) => {
        if (!e.target.closest('[data-act="listen"]')) return;
        bar.hidden = !bar.hidden;
        if (!bar.hidden) { loadVoices(); paint(); play(); } else { stop(); }
    });
    getRendition()?.on?.('relocated', () => { if (st.playing && !st.moving) { /* the reading itself moves the page; a manual page turn restarts from the new page */ } });
    paint();

    return { play, pause, stop, skip };
}
