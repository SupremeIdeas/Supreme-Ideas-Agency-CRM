/**
 * N2N chat page glue (MASTER-ONLY): wires the realtime client to the Livewire page. One transport (realtime.js), no extra sockets or loops.
 *  - message / read / accept events -> refresh the page and play the tone for that event
 *  - typing: the composer's cc:typing is throttled and sent to the open conversation's peer; incoming typing shows for a few seconds
 */
import './ctx.js';
import './gestures.js';
import './nvp.js';
import './lightbox.js';
import './select.js';
import './touch-extras.js';
import './live.js';
import './realtime.js';

const rt = window.N2NRealtime;
let typingTimer = null;
let lastTypingSent = 0;

function refresh() {
  if (window.Livewire && typeof window.Livewire.dispatch === 'function') { window.Livewire.dispatch('n2n-refresh'); }
}

function peer() { return document.querySelector('[data-n2n-peer]')?.getAttribute('data-n2n-peer') || ''; }

function showTyping(from) {
  const el = document.querySelector('[data-n2n-typing]');
  if (!el || !from || from !== peer()) { return; }
  el.hidden = false;
  clearTimeout(typingTimer);
  typingTimer = setTimeout(() => { el.hidden = true; }, 4000);
}

/** Scroll to a message in the open thread and flash it (used by search, starred, pinned and reply quotes). */
window.n2nJump = function (id) {
  const el = document.getElementById('m-' + id);
  if (!el) { return false; }
  el.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  el.classList.add('ncb--flash');
  setTimeout(() => el.classList.remove('ncb--flash'), 1600);
  return true;
};

function wireId() { const root = document.querySelector('[data-n2n-chat]'); return root ? root.closest('[wire\\:id]')?.getAttribute('wire:id') : null; }

function boot() {
  ['message.new', 'message.sent', 'message.read', 'message.poll', 'message.reaction', 'conversation.updated', 'conversation.accepted'].forEach((t) => rt.subscribe(t, (payload) => {
    refresh();
    if (t === 'message.new' && payload && payload.tone && window.NaaraSound) { window.NaaraSound.play(payload.tone); }
  }));
  rt.subscribe('event.reminder', (payload) => { refresh(); if (payload && payload.tone && window.NaaraSound) { window.NaaraSound.play(payload.tone); } });
  rt.subscribe('followup.due', (payload) => { if (payload && payload.tone && window.NaaraSound) { window.NaaraSound.play(payload.tone); } });
  rt.subscribe('credit.received', () => { if (window.NaaraSound) { window.NaaraSound.play('credit_alert.default'); } });
  rt.subscribe('credit.low', () => { if (window.NaaraSound) { window.NaaraSound.play('credit_alert.default'); } });
  rt.subscribe('typing', (payload) => showTyping(payload && payload.from));
  document.addEventListener('cc:typing', () => {
    const p = peer();
    if (!p || Date.now() - lastTypingSent < 3000) { return; }
    lastTypingSent = Date.now();
    rt.typing(p, 'chat');
  });
  // A reply in progress rides along with the next send (the page keeps the chosen message in data-n2n-reply).
  // Contact and location cards ride as formatted text (no links, no live tracking): the composer's items become a banner at the end of the message.
  const cards = (items) => (items || []).map((i) => {
    if (i.type === 'contact') { return ':::blue\n**Contact** ' + String(i.label || '').slice(0, 80) + (i.phone ? '\n' + String(i.phone).slice(0, 30) : '') + '\n:::'; }
    if (i.type === 'location') { return ':::green\n**Location** ' + String(i.label || '').replace(/^[^0-9-]*/, '').slice(0, 40) + '\n:::'; }
    return '';
  }).filter(Boolean);
  document.addEventListener('cc:transmit', (e) => {
    const pl = e.detail && e.detail.payload;
    if (pl && Array.isArray(pl.items)) {   // a poll rides as structured data (question + options), never as text
      const pi = pl.items.find((i) => i.type === 'poll');
      if (pi) { pl.extra = Object.assign({}, pl.extra, { poll: { question: pi.label, options: pi.options } }); pl.items = pl.items.filter((i) => i.type !== 'poll'); }
    }
    if (pl && Array.isArray(pl.items)) {   // a live location rides as structured data (duration + first point), never as text
      const li = pl.items.find((i) => i.type === 'location' && i.live > 0);
      if (li) { pl.extra = Object.assign({}, pl.extra, { live: { minutes: li.live, lat: li.lat, lng: li.lng, acc: li.acc } }); pl.items = pl.items.filter((i) => i !== li); }
    }
    if (pl && Array.isArray(pl.items) && pl.items.some((i) => i.type !== 'file')) {
      pl.text = [pl.text, ...cards(pl.items)].filter(Boolean).join('\n');
      pl.items = pl.items.filter((i) => i.type === 'file');
    }
    const holder = document.querySelector('[data-n2n-reply]');
    const id = holder ? Number(holder.getAttribute('data-n2n-reply')) : 0;
    if (id > 0 && e.detail && e.detail.payload) { e.detail.payload.extra = Object.assign({}, e.detail.payload.extra, { reply_to_id: id }); }
  });
  document.addEventListener('cc:sent', () => { const holder = document.querySelector('[data-n2n-reply]'); const w = wireId(); if (holder && holder.getAttribute('data-n2n-reply') && w && window.Livewire) { window.Livewire.find(w).call('clearReply'); } });
  // After the first message of a brand-new chat, ask the page to open the conversation it created.
  document.addEventListener('cc:sent', () => { const root = document.querySelector('[data-n2n-new]'); if (root) { setTimeout(() => window.Livewire && window.Livewire.find(root.closest('[wire\\:id]').getAttribute('wire:id')).call('afterStart'), 300); } });
  // Keyboard shortcuts that need the page (the composer only reports them): Up on an empty box edits my last text message, Esc drops a reply in progress, Ctrl or Cmd + K jumps to the chat search.
  document.addEventListener('cc:edit-last', (e) => {
    const w = wireId(); if (!w || !window.Livewire) { return; }
    const mine = Array.from(document.querySelectorAll('.ncb[data-ctx]')).map((n) => { try { return JSON.parse(n.dataset.ctx); } catch (x) { return null; } }).filter((d) => d && d.k === 'msg' && d.mine && d.canEdit);
    const last = mine[mine.length - 1]; if (!last) { return; }
    e.preventDefault(); window.Livewire.find(w).call('startEdit', last.id);
  });
  document.addEventListener('cc:cancel-reply', () => {
    const holder = document.querySelector('[data-n2n-reply]'); const w = wireId();
    if (holder && holder.getAttribute('data-n2n-reply') && w && window.Livewire) { window.Livewire.find(w).call('clearReply'); }
  });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k') {
      const box = document.querySelector('[data-n2n-chat] input[type=search][wire\\:model\\.live\\.debounce\\.400ms="q"]') || document.querySelector('input[type=search][placeholder="Search messages"]');
      if (box) { e.preventDefault(); box.focus(); box.select(); }
    }
  });
  // The shell (shell.js) owns start() and the presence ping: one transport per page.
}

if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }

/* Phone: the open conversation follows your finger. Drag it sideways (from anywhere on the thread, the composer included) and the conversation list
   slides in underneath; let go past a third of the screen, or flick, and it commits. Vertical scrolling is never taken over: the gesture only locks
   to horizontal when the first movement is clearly sideways. Touch and pen only, phone layout only. */
(function swipeBack() {
  if (window.__n2nSwipe) { return; }
  window.__n2nSwipe = true;
  const mq = window.matchMedia('(max-width: 1023px)');
  let g = null;
  const skip = 'input[type=range], audio, video, .cc-panel, .cc-voice, [data-no-swipe], .nc__panel, .ncb__pop, .nc__menu, .ncf-code';
  document.addEventListener('pointerdown', (e) => {
    if (!mq.matches || (e.pointerType !== 'touch' && e.pointerType !== 'pen') || !e.isPrimary) { return; }
    const th = e.target.closest && e.target.closest('.nc__thread');
    if (!th || th.classList.contains('is-hidden') || e.target.closest(skip)) { return; }
    const ae = document.activeElement;
    if (ae && ae.tagName === 'TEXTAREA' && ae.selectionStart !== ae.selectionEnd) { return; }
    g = { th, nc: th.closest('.nc'), id: e.pointerId, x0: e.clientX, y0: e.clientY, t0: performance.now(), lock: null, dx: 0, samples: [[performance.now(), e.clientX]] };
  }, { passive: true });
  document.addEventListener('pointermove', (e) => {
    if (!g || e.pointerId !== g.id) { return; }
    const dx = e.clientX - g.x0; const dy = e.clientY - g.y0;
    if (!g.lock) {
      if (Math.abs(dx) < 10 && Math.abs(dy) < 10) { return; }
      if (Math.abs(dx) > Math.abs(dy) * 1.4) { g.lock = 'h'; g.nc.classList.add('is-dragging'); try { g.th.setPointerCapture(e.pointerId); } catch (_) {} } else { g = null; return; }
    }
    g.dx = dx; const w = g.th.offsetWidth || window.innerWidth;
    const eased = Math.sign(dx) * Math.min(Math.abs(dx), w);
    g.th.style.setProperty('--nc-x', eased + 'px');
    g.nc.style.setProperty('--nc-p', String(Math.min(Math.abs(eased) / w, 1)));
    g.nc.style.setProperty('--nc-dir', dx >= 0 ? '1' : '-1');
    g.samples.push([performance.now(), e.clientX]); if (g.samples.length > 6) { g.samples.shift(); }
    if (e.cancelable) { e.preventDefault(); }
  }, { passive: false });
  const end = (e) => {
    if (!g || e.pointerId !== g.id) { return; }
    const s = g; g = null;
    if (s.lock !== 'h') { return; }
    const w = s.th.offsetWidth || window.innerWidth; const a = s.samples[0]; const b = s.samples[s.samples.length - 1];
    const v = b[0] > a[0] ? (b[1] - a[1]) / (b[0] - a[0]) : 0;   // px per ms, signed
    const commit = e.type === 'pointerup' && (Math.abs(s.dx) > w * 0.32 || (Math.abs(v) > 0.55 && Math.sign(v) === Math.sign(s.dx) && Math.abs(s.dx) > 30));
    s.nc.classList.remove('is-dragging');
    if (!commit) { s.th.style.removeProperty('--nc-x'); s.nc.style.setProperty('--nc-p', '0'); return; }
    const to = (s.dx >= 0 ? 1 : -1) * w;
    s.nc.classList.add('is-committing');
    s.th.style.setProperty('--nc-x', to + 'px'); s.nc.style.setProperty('--nc-p', '1');
    let done = false;
    const finish = () => {
      if (done) { return; } done = true;
      const back = s.th.querySelector('[data-n2n-back]'); if (back) { back.click(); }
      setTimeout(() => { s.th.style.removeProperty('--nc-x'); s.nc.style.setProperty('--nc-p', '0'); s.nc.classList.remove('is-committing'); }, 380);
    };
    s.th.addEventListener('transitionend', finish, { once: true }); setTimeout(finish, 420);
  };
  document.addEventListener('pointerup', end, { passive: true });
  document.addEventListener('pointercancel', end, { passive: true });
})();
