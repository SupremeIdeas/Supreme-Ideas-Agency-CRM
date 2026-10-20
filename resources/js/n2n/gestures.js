/* Naara chat gesture engine (MASTER-ONLY). Gestures blueprint A.2: Pointer Events with event delegation (one listener set for the whole page, nothing per bubble),
   axis lock so vertical scrolling stays native, a haptic tick at the threshold, RTL aware ("end" is the right edge in LTR, the left in RTL).
   Recognisers: swipe toward the END edge = reply; swipe toward the START edge = the member's chosen action (info / forward / star / none);
   double-tap (or double-click) = quick-react with the member's reaction (a second double-tap removes it, the server toggles).
   Conversation list (Gestures G3): swipe a chat row toward the END to pin or unpin it, toward the START to archive or unarchive it, each with an Undo snackbar;
   taking it back within 2 seconds counts as a mis-trigger (anonymous counter).
   Every gesture has a visible, keyboard-reachable alternative (the long-press / right-click / Menu-key menu): gestures are shortcuts, never the only path.
   Settings come from gesture-config.js. "Buttons only" turns all of this off. Cancels on a second pointer, a vertical scroll, the menu opening, or backgrounding. */
import { gcfg, haptic, reduceMotion, stat } from './gesture-config.js';

const MAX = 84;          // px the bubble may travel
const LOCK = 10;         // px of travel before we decide the axis
const TAP_MS = 250;      // a tap is shorter than this
const TAP_SLOP = 10;     // and moves less than this
const DT_MS = 280;       // second tap of a double-tap within this
const DT_DIST = 28;      // and within this distance of the first
const BLOCK = '.nvp__wave, .nvp__btn, .nvp__speed, .npoll, .nev, input, textarea, audio, video, [data-no-swipe], .ncb__react, .ncb__menu, a, button';
const ICONS = {
  reply: '<path d="M9 14 4 9l5-5"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
  info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
  forward: '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5V20"/>',
  star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
};
const dirSign = () => (getComputedStyle(document.documentElement).direction === 'rtl' ? -1 : 1);
const svg = (k) => `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${ICONS[k]}</svg>`;

let g = null;          // the swipe in flight
let down = null;       // the pointer currently down (for tap detection)
let lastTap = null;    // the previous tap, for double-tap

function bubble(t) { const el = t.closest && t.closest('.ncb[data-ctx]'); return el && !t.closest(BLOCK) ? el : null; }
function ctxOf(el) { try { return JSON.parse(el.dataset.ctx) || {}; } catch (e) { return {}; } }

function reset(spring = true, counted = true) {
  if (!g) { return; }
  const el = g.el;
  if (counted && g.locked && !g.committed) { stat(g.side === 'end' ? 'swipe_end' : 'swipe_start', 'cancelled', g.ptype); }
  el.style.transition = spring && !reduceMotion() ? 'transform .22s cubic-bezier(.2, 1.2, .3, 1)' : '';
  el.style.transform = '';
  el.classList.remove('is-swiping', 'is-armed');
  if (g.hint) { g.hint.remove(); }
  g = null;
}

/** Run a named action on a message bubble. A practice bubble (the settings screen) only reports what would have happened. */
function run(el, action) {
  const d = ctxOf(el);
  if (d.k === 'practice') { window.dispatchEvent(new CustomEvent('ng-practice', { detail: { action } })); return; }
  if (d.k !== 'msg' || !d.id) { return; }
  const host = el.closest('[wire\\:id]');
  if (!host || !window.Livewire) { return; }
  const lw = window.Livewire.find(host.getAttribute('wire:id'));
  if (action === 'reply') { lw.call('setReply', d.id); }
  else if (action === 'info') { lw.call('openInfo', d.id); }
  else if (action === 'forward') { if (d.canForward !== false) { lw.call('openForward', d.id); } }
  else if (action === 'star') { lw.call('toggleStar', d.id); }
  else if (action === 'react') { lw.call('react', d.id, gcfg().double_tap_code); }
}

function burst(el) {
  if (reduceMotion()) { return; }
  const c = gcfg(); const b = document.createElement('span');
  b.className = 'ncb__burst'; b.setAttribute('aria-hidden', 'true'); b.textContent = c.glyph || '';
  el.appendChild(b); setTimeout(() => b.remove(), 700);
}

function onDouble(el, ptype) {
  const c = gcfg(); if (!c.double_tap_react) { return; }
  const d = ctxOf(el);
  stat('double_tap', 'fired', ptype);
  const removing = d.k === 'msg' && d.reaction === c.double_tap_code;
  if (!removing) { burst(el); }
  haptic(10);
  run(el, 'react');
  stat('double_tap', 'committed', ptype);
  const sel = window.getSelection && window.getSelection(); if (sel && sel.removeAllRanges) { sel.removeAllRanges(); }   // a mouse double-click must not leave a selected word
}

document.addEventListener('pointerdown', (e) => {
  const c = gcfg(); if (c.buttons_only) { return; }
  if (g) { reset(false); return; }                       // a second pointer: abandon
  if (!e.isPrimary) { return; }
  const el = bubble(e.target); if (!el) { down = null; return; }
  down = { el, x: e.clientX, y: e.clientY, t: Date.now(), id: e.pointerId, ptype: e.pointerType === 'mouse' ? 'mouse' : 'touch', moved: false };
  if (e.pointerType === 'mouse') { return; }              // a mouse has no swipe (it has the menu and double-click)
  g = { el, x0: e.clientX, y0: e.clientY, dx: 0, locked: false, armed: false, committed: false, hint: null, id: e.pointerId, side: null, ptype: 'touch', commit: c.swipe_commit_px };
}, { passive: true });

document.addEventListener('pointermove', (e) => {
  if (down && e.pointerId === down.id && Math.hypot(e.clientX - down.x, e.clientY - down.y) > TAP_SLOP) { down.moved = true; }
  if (!g || e.pointerId !== g.id) { return; }
  const c = gcfg(); const sign = dirSign();
  const raw = (e.clientX - g.x0) * sign; const dy = e.clientY - g.y0;
  if (!g.locked) {
    if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(raw)) { reset(false, false); return; }          // it is a scroll
    if (Math.abs(raw) > LOCK && Math.abs(raw) > 1.6 * Math.abs(dy)) {
      const side = raw > 0 ? 'end' : 'start';
      const action = side === 'end' ? (c.swipe_reply ? 'reply' : 'none') : c.swipe_start;
      if (action === 'none') { reset(false, false); return; }                                       // that direction is switched off: leave the touch to the page
      g.locked = true; g.side = side; g.action = action; g.el.classList.add('is-swiping'); g.el.style.transition = 'none';
      stat(side === 'end' ? 'swipe_end' : 'swipe_start', 'fired', 'touch');
      const hint = document.createElement('span'); hint.className = 'ncb__swipehint' + (side === 'start' ? ' ncb__swipehint--start' : ''); hint.setAttribute('aria-hidden', 'true');
      hint.innerHTML = svg(action);
      g.el.parentElement.style.position = g.el.parentElement.style.position || 'relative'; g.el.parentElement.appendChild(hint); g.hint = hint;
    } else { return; }
  }
  const mag = Math.abs(raw);
  g.dx = Math.max(0, Math.min(MAX, mag > g.commit ? g.commit + (mag - g.commit) * 0.35 : mag));    // elastic past the commit point
  const dirn = (g.side === 'end' ? 1 : -1) * sign;
  g.el.style.transform = `translateX(${g.dx * dirn}px)`;
  const p = Math.min(1, g.dx / g.commit);
  if (g.hint) { g.hint.style.opacity = String(p); g.hint.style.transform = `scale(${0.6 + 0.4 * p})`; }
  if (g.dx >= g.commit && !g.armed) { g.armed = true; g.el.classList.add('is-armed'); haptic(10); }
  else if (g.dx < g.commit && g.armed) { g.armed = false; g.el.classList.remove('is-armed'); }
}, { passive: true });

const end = (e) => {
  // 1. A swipe in flight (one that never locked an axis was just a tap: fall through to the tap logic below).
  if (g && e.pointerId === g.id && !g.locked) { g = null; }
  if (g && e.pointerId === g.id) {
    const commit = g.locked && g.armed && e.type === 'pointerup';
    const el = g.el; const action = g.action; const side = g.side; const ptype = g.ptype;
    if (commit) { g.committed = true; stat(side === 'end' ? 'swipe_end' : 'swipe_start', 'committed', ptype); }
    reset(true);
    down = null; lastTap = null;
    if (commit) { run(el, action); }
    return;
  }
  // 2. A tap (any pointer): two quick ones on the same bubble are a double-tap.
  if (down && e.pointerId === down.id) {
    const d = down; down = null;
    if (e.type !== 'pointerup' || d.moved || Date.now() - d.t > TAP_MS) { lastTap = null; return; }
    const now = Date.now();
    if (lastTap && lastTap.el === d.el && now - lastTap.t <= DT_MS && Math.hypot(e.clientX - lastTap.x, e.clientY - lastTap.y) <= DT_DIST) { lastTap = null; onDouble(d.el, d.ptype); }
    else { lastTap = { el: d.el, x: e.clientX, y: e.clientY, t: now }; }
  }
};
document.addEventListener('pointerup', end, { passive: true });
document.addEventListener('pointercancel', end, { passive: true });
// A scroll, the menu opening, or the app backgrounding abandons the gesture and restores the bubble.
document.addEventListener('scroll', () => { lastTap = null; reset(true); }, { capture: true, passive: true });
window.addEventListener('n2n-ctx', () => { lastTap = null; reset(false); });
document.addEventListener('visibilitychange', () => { if (document.hidden) { lastTap = null; reset(false); } });

/* ---- Conversation list swipe (Gestures G3) ---------------------------------------------------------------------------------------------------------
   Touch only. The row's own button keeps working; a swipe that commits swallows the click that follows it. Every swipe has an Undo snackbar, and the same
   actions are in the row menu (long-press / right-click / Menu key), so a gesture is never the only way. */
const ROW_ICONS = { pin: '<path d="M12 17v5"/><path d="M9 10.8V4h6v6.8l2 3.2H7z"/>', archive: '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/><path d="M10 13h4"/>' };
let rg = null;
let swallowClick = 0;
let undoTimer = null;

function rowOf(t) { const li = t.closest && t.closest('.nc__rows > li[data-ctx]'); if (!li) { return null; } const d = ctxOf(li); return d.k === 'row' && d.id && li.hasAttribute('data-sel') ? { li, d } : null; }

function rowReset(spring = true, counted = true) {
  if (!rg) { return; }
  const inner = rg.inner;
  if (counted && rg.locked && !rg.committed) { stat(rg.action === 'pin' ? 'list_pin' : 'list_archive', 'cancelled', 'touch'); }
  inner.style.transition = spring && !reduceMotion() ? 'transform .22s cubic-bezier(.2, 1.2, .3, 1)' : '';
  inner.style.transform = '';
  rg.li.classList.remove('is-rowswiping', 'is-armed');
  if (rg.hint) { rg.hint.remove(); }
  rg = null;
}

function snack(text, onUndo, gesture) {
  let bar = document.getElementById('ng-undo');
  if (!bar) {
    bar = document.createElement('div'); bar.id = 'ng-undo'; bar.className = 'ng-undo'; bar.setAttribute('role', 'status'); bar.setAttribute('aria-live', 'polite');
    bar.innerHTML = '<span data-ng-undo-text></span><button type="button" data-ng-undo-btn>Undo</button>';
    document.body.appendChild(bar);
  }
  const shown = Date.now();
  bar.querySelector('[data-ng-undo-text]').textContent = text;
  const btn = bar.querySelector('[data-ng-undo-btn]');
  btn.onclick = () => {
    if (Date.now() - shown <= 2000) { stat(gesture, 'undone', 'touch'); }
    clearTimeout(undoTimer); bar.classList.remove('is-on'); onUndo();
  };
  bar.classList.add('is-on');
  clearTimeout(undoTimer); undoTimer = setTimeout(() => bar.classList.remove('is-on'), 5000);
}

function rowRun(li, d, action) {
  const host = li.closest('[wire\\:id]'); if (!host || !window.Livewire) { return; }
  const lw = window.Livewire.find(host.getAttribute('wire:id'));
  const name = d.name || 'Chat';
  if (action === 'pin') {
    lw.call('rowPin', d.id);
    snack(d.pinned ? `${name} unpinned` : `${name} pinned`, () => lw.call('rowPin', d.id), 'list_pin');
  } else {
    lw.call('rowArchive', d.id);
    snack(d.archived ? `${name} unarchived` : `${name} archived`, () => lw.call('rowArchive', d.id), 'list_archive');
  }
}

document.addEventListener('pointerdown', (e) => {
  const c = gcfg(); if (c.buttons_only || !c.list_swipe || e.pointerType === 'mouse' || !e.isPrimary) { return; }
  if (rg) { rowReset(false); return; }
  const r = rowOf(e.target); if (!r) { return; }
  const inner = r.li.firstElementChild; if (!inner) { return; }
  rg = { li: r.li, d: r.d, inner, x0: e.clientX, y0: e.clientY, dx: 0, locked: false, armed: false, committed: false, hint: null, id: e.pointerId, action: null, side: null, commit: c.swipe_commit_px };
}, { passive: true });

document.addEventListener('pointermove', (e) => {
  if (!rg || e.pointerId !== rg.id) { return; }
  const sign = dirSign(); const raw = (e.clientX - rg.x0) * sign; const dy = e.clientY - rg.y0;
  if (!rg.locked) {
    if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(raw)) { rowReset(false, false); return; }      // it is a scroll
    if (Math.abs(raw) > LOCK && Math.abs(raw) > 1.6 * Math.abs(dy)) {
      rg.side = raw > 0 ? 'end' : 'start'; rg.action = rg.side === 'end' ? 'pin' : 'archive'; rg.locked = true;
      rg.li.classList.add('is-rowswiping'); rg.inner.style.transition = 'none';
      stat(rg.action === 'pin' ? 'list_pin' : 'list_archive', 'fired', 'touch');
      const label = rg.action === 'pin' ? (rg.d.pinned ? 'Unpin' : 'Pin') : (rg.d.archived ? 'Unarchive' : 'Archive');
      const hint = document.createElement('span'); hint.className = 'ng-rowhint ng-rowhint--' + rg.action + (rg.side === 'start' ? ' ng-rowhint--start' : ''); hint.setAttribute('aria-hidden', 'true');
      hint.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${ROW_ICONS[rg.action]}</svg><b>${label}</b>`;
      rg.li.appendChild(hint); rg.hint = hint;
    } else { return; }
  }
  const mag = Math.abs(raw); const MAXR = 120;
  rg.dx = Math.max(0, Math.min(MAXR, mag > rg.commit ? rg.commit + (mag - rg.commit) * 0.35 : mag));
  const dirn = (rg.side === 'end' ? 1 : -1) * sign;
  rg.inner.style.transform = `translateX(${rg.dx * dirn}px)`;
  if (rg.dx >= rg.commit && !rg.armed) { rg.armed = true; rg.li.classList.add('is-armed'); haptic(10); }
  else if (rg.dx < rg.commit && rg.armed) { rg.armed = false; rg.li.classList.remove('is-armed'); }
}, { passive: true });

const rowEnd = (e) => {
  if (!rg || e.pointerId !== rg.id) { return; }
  if (!rg.locked) { rg = null; return; }                          // never locked an axis: it was a tap, leave the click alone
  const commit = rg.armed && e.type === 'pointerup';
  const { li, d, action } = rg;
  if (commit) { rg.committed = true; stat(action === 'pin' ? 'list_pin' : 'list_archive', 'committed', 'touch'); }
  swallowClick = Date.now() + 450;                                // a swipe must never also open the chat
  rowReset(true);
  if (commit) { rowRun(li, d, action); }
};
document.addEventListener('pointerup', rowEnd, { passive: true });
document.addEventListener('pointercancel', rowEnd, { passive: true });
document.addEventListener('click', (e) => { if (Date.now() < swallowClick) { e.preventDefault(); e.stopPropagation(); } }, true);
document.addEventListener('scroll', () => rowReset(true), { capture: true, passive: true });
document.addEventListener('visibilitychange', () => { if (document.hidden) { rowReset(false); } });
