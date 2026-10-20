/* Naara chat long-press menu (MASTER-ONLY). One liquid pop-over for the whole screen, opened by a long press on a phone, a right-click on a desktop, or the
   Menu key / Shift+F10 on a focused message or chat row. The element's own `data-ctx` JSON says what it is; the menu markup lives in chat.blade.php and every
   action is a Livewire call that the server re-authorises. Nothing here trusts the page for permissions: it only decides what to SHOW. */
import { gcfg, haptic, stat } from './gesture-config.js';
const HOLD_MS = 450;   // fallback; the member's setting (gesture-config) wins
const SLOP = 10;
const SKIP = '.ncb__react, .nvp__btn, .nvp__wave, .nvp__speed, audio, video, input, textarea, .ncb__menu, .nc__menu';

window.n2nCtx = function n2nCtx(bar0, catalog) {
  return {
    open: false, d: {}, peek: { state: '', messages: [] }, peekSeq: 0, menuStyle: '', previewStyle: '', trigger: null, clamped: false, previewOpen: false, vvOn: null,
    // The member's personal quick-reaction bar. `cat` is the closed catalogue (code -> glyph) from the server; the server re-validates every code it is sent.
    bar: Array.isArray(bar0) ? bar0.slice() : [], cat: catalog || {}, picker: false, editing: false, max: 24, hold: 0, held: false, defaults: ['like', 'love', 'laugh', 'wow', 'sad', 'pray'],
    get codes() { return Object.keys(this.cat); },
    react(code) { if (this.d.id) { this.$wire.react(this.d.id, code); } this.close(); },
    pick(code) {
      if (!this.editing) { this.react(code); return; }
      const at = this.bar.indexOf(code);
      if (at >= 0) { if (this.bar.length > 1) { this.bar.splice(at, 1); } } else if (this.bar.length < this.max) { this.bar.push(code); }
      this.$wire.saveReactionBar(this.bar.slice());
    },
    // Press and hold an emoji in the bar to edit it: every emoji grows a remove (x) badge. Tap one to drop it; the list can scroll sideways.
    holdStart() { this.held = false; clearTimeout(this.hold); this.hold = setTimeout(() => { this.held = true; this.editing = true; if (navigator.vibrate) { try { navigator.vibrate(10); } catch (e) {} } }, 420); },
    holdEnd() { clearTimeout(this.hold); },
    barTap(code) { if (this.held) { this.held = false; return; } if (this.editing) { this.pick(code); } else { this.react(code); } },
    resetBar() { this.bar = this.defaults.slice(); this.$wire.saveReactionBar(null); },
    show(detail) {
      const el = detail && detail.el; if (!el || !el.dataset.ctx) { return; }
      try { this.d = JSON.parse(el.dataset.ctx); } catch (e) { return; }
      if (this.d.k === 'practice') { window.dispatchEvent(new CustomEvent('ng-practice', { detail: { action: 'menu' } })); return; }   // the settings screen's sample bubble: report, never open a real menu
      this.loadPeek();
      this.trigger = el; this.open = true; this.picker = false; this.editing = false; this.held = false;
      if (navigator.vibrate) { try { navigator.vibrate(10); } catch (e) {} }
      this.$nextTick(() => this.place(el));
    },
    // The peek card: the last few messages of a pressed chat row, so it can be read without opening it. Plain text from the server (never HTML), read-only,
    // never for a locked chat, and it marks nothing as read. A late answer for a menu that has since closed or moved on is dropped (peekSeq).
    async loadPeek() {
      this.peek = { state: '', messages: [] };
      if (this.d.k !== 'row' || this.d.locked || !this.d.id || gcfg().buttons_only) { return; }
      const seq = ++this.peekSeq; this.peek.state = 'loading';
      try {
        const res = await fetch(`/n2n/api/conversations/${this.d.id}/peek`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (seq !== this.peekSeq) { return; }
        if (!res.ok) { this.peek = { state: 'error', messages: [] }; return; }
        const j = await res.json();
        this.peek = { state: j.messages && j.messages.length ? 'ready' : 'empty', messages: j.messages || [] };
        if (this.peek.state === 'ready') { stat('peek', 'fired', 'touch'); }
      } catch (e) { if (seq === this.peekSeq) { this.peek = { state: 'error', messages: [] }; } }
    },
    close() { this.peekSeq++; this.open = false; this.unsyncVv(); const t = this.trigger; this.trigger = null; if (t && t.isConnected) { try { t.focus({ preventScroll: true }); } catch (e) {} } },
    copy() { const b = this.d.body || ''; if (navigator.clipboard && b) { navigator.clipboard.writeText(b).catch(() => {}); } this.close(); },
    // Centre-centre dialog (Brand-Growth 1.2): NO positional maths relative to the pressed bubble, which is what used to push the menu off-screen for a huge message.
    // The layout is CSS only (a centred flex column inside a dvh-high frame: preview + reaction bar pinned, action list scrolls); this just fills the preview.
    place(el) {
      const prev = this.$refs.preview;
      // The preview is a literal copy of what was pressed, stripped of every directive, so it cannot act: it only shows what the menu is about.
      const clone = el.cloneNode(true);
      [clone, ...clone.querySelectorAll('*')].forEach((n) => { for (const a of Array.from(n.attributes)) { if (/^(wire:|x-|@|:|data-ctx|id$|tabindex)/.test(a.name)) { n.removeAttribute(a.name); } } });
      clone.querySelectorAll('.ncb__menu, .ncb__dots').forEach((n) => n.remove());
      prev.replaceChildren(clone);
      this.previewOpen = false; this.previewStyle = ''; this.menuStyle = '';
      requestAnimationFrame(() => {   // after Alpine has shown the dialog: a hidden element measures 0
        this.clamped = prev.scrollHeight > prev.clientHeight + 2;   // long message or tall image: clamped with a fade and a "Show more"
        this.syncVv();
        const first = this.$refs.menu.querySelector('[role=menuitem]:not([style*="display: none"])'); if (first && this.trigger && document.activeElement === this.trigger) { /* focus stays on the pressed element: Tab walks into the menu */ }
      });
    },
    // The on-screen keyboard or browser chrome shrinks the visual viewport; the frame follows it so the dialog is never pushed off-screen.
    syncVv() {
      const root = document.documentElement; const vv = window.visualViewport;
      const set = () => { root.style.setProperty('--n2nx-vh', Math.round(vv ? vv.height : window.innerHeight) + 'px'); };
      set();
      if (vv && !this.vvOn) { this.vvOn = set; vv.addEventListener('resize', set); }
    },
    unsyncVv() { const vv = window.visualViewport; if (vv && this.vvOn) { vv.removeEventListener('resize', this.vvOn); } this.vvOn = null; document.documentElement.style.removeProperty('--n2nx-vh'); },
  };
};

(function wire() {
  if (window.__n2nCtxWired) { return; }
  window.__n2nCtxWired = true;
  let timer = null; let start = null; let openedAt = 0; let pressOpen = false; let swallowUntil = 0;
  const fire = (el, x, y, fromPress = false) => {
    try { if ((JSON.parse(el.dataset.ctx) || {}).k === 'practice') { window.dispatchEvent(new CustomEvent('ng-practice', { detail: { action: 'menu' } })); return; } } catch (e) { /* not JSON: carry on */ }
    openedAt = Date.now(); pressOpen = fromPress; window.dispatchEvent(new CustomEvent('n2n-ctx', { detail: { el, x, y } })); };
  const cancel = () => { if (timer) { clearTimeout(timer); timer = null; } start = null; };
  document.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' || !e.isPrimary) { return; }
    const el = e.target.closest && e.target.closest('[data-ctx]');
    if (!el || e.target.closest(SKIP)) { return; }
    // While choosing chats or messages, a hold starts a drag-select (touch-extras.js), not the menu.
    const host = el.closest('.nc__list, .nc__thread');
    try { if (host && window.Alpine && window.Alpine.$data(host).on) { return; } } catch (err) { /* not an Alpine host */ }
    start = { x: e.clientX, y: e.clientY };
    const c = gcfg();
    if (c.buttons_only) { return; }
    timer = setTimeout(() => { timer = null; if (start) { stat('long_press', 'fired', 'touch'); fire(el, start.x, start.y, true); } start = null; }, c.long_press_ms || HOLD_MS);
  }, { passive: true });
  document.addEventListener('pointermove', (e) => { if (start && Math.hypot(e.clientX - start.x, e.clientY - start.y) > SLOP) { cancel(); } }, { passive: true });
  ['pointerup', 'pointercancel', 'scroll'].forEach((t) => document.addEventListener(t, (e) => { if (pressOpen && (e.type === 'pointerup' || e.type === 'pointercancel')) { pressOpen = false; swallowUntil = Date.now() + 450; } cancel(); }, { passive: true, capture: true }));
  // The press that opened the menu must not also "click" the row underneath.
  document.addEventListener('click', (e) => { if (Date.now() < swallowUntil) { swallowUntil = 0; e.preventDefault(); e.stopImmediatePropagation(); } }, true);   // the click synthesised by lifting the finger that opened the menu lands on the new scrim: swallow that one only
  // "Buttons only" mode: gestures are off, so a plain tap on a message or chat row opens the same action menu (the visible alternative to every gesture).
  document.addEventListener('click', (e) => {
    if (!gcfg().buttons_only) { return; }
    const el = e.target.closest && e.target.closest('.ncb[data-ctx]');
    if (!el || e.target.closest(SKIP) || e.target.closest('a, button, input, textarea')) { return; }
    stat('long_press', 'fallback', 'touch');
    fire(el, e.clientX, e.clientY);
  });
  // Desktop right-click, and Android's own long-press contextmenu (de-duplicated against the timer above).
  document.addEventListener('contextmenu', (e) => {
    const el = e.target.closest && e.target.closest('[data-ctx]');
    if (!el || e.target.closest(SKIP)) { return; }
    e.preventDefault();
    const host = el.closest('.nc__list, .nc__thread');
    try { if (host && window.Alpine && window.Alpine.$data(host).on && e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) { return; } } catch (err) { /* not an Alpine host */ }
    if (Date.now() - openedAt < 700) { return; }
    fire(el, e.clientX, e.clientY);
  });
  document.addEventListener('keydown', (e) => {
    if (!(e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10'))) { return; }
    const el = document.activeElement && document.activeElement.closest && document.activeElement.closest('[data-ctx]');
    if (!el) { return; }
    e.preventDefault(); const r = el.getBoundingClientRect(); fire(el, r.left + r.width / 2, r.top + r.height / 2);
  });
})();
