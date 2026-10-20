/* Naara chat touch extras (MASTER-ONLY, Gestures blueprint): drag-to-select and pull-down search. Both are shortcuts to something that already has a visible control
   (the tick circles and Select all / the Search chip and the search box), both respect "Buttons only", and neither takes over ordinary scrolling.

   DRAG-SELECT  While choosing chats or messages, press and hold an item for a moment, then drag up or down: every item you pass over is picked (or un-picked, if the one
                you started on was already picked). Near the top or bottom edge the list scrolls by itself. A mouse needs no hold: just press and drag.
   PULL-DOWN    On a phone the conversation list keeps its search box tucked away. At the top of the list, pull down and let go to reveal it with the keyboard open; the
                Search chip does the same without a gesture. A box with text in it, or a box you are using, never hides. */
import { gcfg, haptic, reduceMotion, stat } from './gesture-config.js';

const HOLD_MS = 260;      // hold this long on an item before a drag selects
const MOVE_SLOP = 8;      // moving more than this before the hold ends means "scroll", not "select"
const EDGE = 64;          // px from the scroller's edge where it scrolls by itself
const PULL_PX = 70;       // pull distance that triggers search
const phone = () => window.matchMedia && window.matchMedia('(max-width: 767px)').matches;

function dataOf(host) {
  if (!host || !window.Alpine) { return null; }
  try { const d = window.Alpine.$data(host); return d && d.on ? d : null; } catch (e) { return null; }
}

/** What a touch point is over, if it is a selectable item inside the same host. */
function targetAt(x, y) {
  const el = document.elementFromPoint(x, y);
  if (!el) { return null; }
  const row = el.closest('.nc__list li[data-sel]');
  if (row) { return { kind: 'row', el: row, host: row.closest('.nc__list'), scroller: row.closest('.nc__rows') }; }
  const msg = el.closest('.nc__thread .ncb[data-ctx]');
  if (msg) { return { kind: 'msg', el: msg, host: msg.closest('.nc__thread'), scroller: msg.closest('.nc__msgs') }; }
  return null;
}

const idOf = (t, d) => (t.kind === 'row' ? Number(t.el.dataset.sel) : d.idOf(t.el));
const itemsOf = (t, d) => (t.kind === 'row' ? Array.from(t.host.querySelectorAll('li[data-sel]')) : d.bubbles());

let ds = null;   // the drag-select in flight
let swallowUntil = 0;

function apply() {
  const { d, items, anchor, cur, base, add, kind } = ds;
  const lo = Math.min(anchor, cur); const hi = Math.max(anchor, cur);
  const span = items.slice(lo, hi + 1).map((el) => (kind === 'row' ? Number(el.dataset.sel) : d.idOf(el))).filter(Boolean);
  const set = new Set(base);
  span.forEach((id) => (add ? set.add(id) : set.delete(id)));
  d.ids = Array.from(set);
  if (typeof d.paint === 'function') { d.paint(); }
}

function arm(t, d, x, y, type) {
  const items = itemsOf(t, d); const anchor = items.indexOf(t.el);
  if (anchor < 0) { return; }
  const id = idOf(t, d); if (!id) { return; }
  ds = { kind: t.kind, d, items, anchor, cur: anchor, base: d.ids.slice(), add: !d.has(id), scroller: t.scroller, y, type, armed: true, raf: 0 };
  apply(); haptic(10);
  t.el.classList.add('is-dragselect');
}

function edgeScroll() {
  if (!ds || !ds.armed || !ds.scroller) { return; }
  const r = ds.scroller.getBoundingClientRect(); let step = 0;
  if (ds.y < r.top + EDGE) { step = -Math.ceil((r.top + EDGE - ds.y) / 4); } else if (ds.y > r.bottom - EDGE) { step = Math.ceil((ds.y - (r.bottom - EDGE)) / 4); }
  if (step) {
    ds.scroller.scrollTop += step;
    const t = targetAt(ds.x, ds.y);
    if (t && t.host === ds.el0host) { const i = ds.items.indexOf(t.el); if (i >= 0 && i !== ds.cur) { ds.cur = i; apply(); } }
  }
  ds.raf = requestAnimationFrame(edgeScroll);
}

function endDrag(committed) {
  if (!ds) { return; }
  cancelAnimationFrame(ds.raf);
  document.querySelectorAll('.is-dragselect').forEach((n) => n.classList.remove('is-dragselect'));
  const had = ds.armed && ds.cur !== ds.anchor;
  if (ds.armed) { swallowUntil = Date.now() + 350; stat('drag_select', had && committed ? 'committed' : 'cancelled', ds.type); }
  ds = null;
}

// ---- touch: hold then drag
let pending = null;
document.addEventListener('touchstart', (e) => {
  if (e.touches.length !== 1 || gcfg().buttons_only) { return; }
  const p = e.touches[0]; const t = targetAt(p.clientX, p.clientY);
  if (!t) { return; }
  const d = dataOf(t.host); if (!d) { return; }
  pending = { t, d, x: p.clientX, y: p.clientY, timer: setTimeout(() => { if (!pending) { return; } const pp = pending; pending = null; arm(pp.t, pp.d, pp.x, pp.y, 'touch'); if (ds) { ds.x = pp.x; ds.el0host = pp.t.host; ds.raf = requestAnimationFrame(edgeScroll); } }, HOLD_MS) };
}, { passive: true });

document.addEventListener('touchmove', (e) => {
  const p = e.touches[0]; if (!p) { return; }
  if (pending && Math.hypot(p.clientX - pending.x, p.clientY - pending.y) > MOVE_SLOP) { clearTimeout(pending.timer); pending = null; }
  if (!ds || !ds.armed || ds.type !== 'touch') { return; }
  if (e.cancelable) { e.preventDefault(); }   // a drag-select owns the finger: no scrolling underneath
  ds.x = p.clientX; ds.y = p.clientY;
  const t = targetAt(p.clientX, p.clientY);
  if (t && t.host === ds.el0host) { const i = ds.items.indexOf(t.el); if (i >= 0 && i !== ds.cur) { ds.cur = i; apply(); haptic(4); } }
}, { passive: false });

['touchend', 'touchcancel'].forEach((ev) => document.addEventListener(ev, () => { if (pending) { clearTimeout(pending.timer); pending = null; } endDrag(ev === 'touchend'); }, { passive: true }));

// ---- mouse: press and drag, no hold
document.addEventListener('pointerdown', (e) => {
  if (e.pointerType !== 'mouse' || e.button !== 0 || gcfg().buttons_only) { return; }
  const t = targetAt(e.clientX, e.clientY); if (!t) { return; }
  const d = dataOf(t.host); if (!d) { return; }
  pending = { t, d, x: e.clientX, y: e.clientY, mouse: true, timer: 0 };
});
document.addEventListener('pointermove', (e) => {
  if (e.pointerType !== 'mouse') { return; }
  if (pending && pending.mouse && Math.hypot(e.clientX - pending.x, e.clientY - pending.y) > 6) {
    const pp = pending; pending = null; arm(pp.t, pp.d, pp.x, pp.y, 'mouse'); if (ds) { ds.x = e.clientX; ds.y = e.clientY; ds.el0host = pp.t.host; ds.raf = requestAnimationFrame(edgeScroll); }
  }
  if (!ds || ds.type !== 'mouse') { return; }
  ds.x = e.clientX; ds.y = e.clientY;
  const t = targetAt(e.clientX, e.clientY);
  if (t && t.host === ds.el0host) { const i = ds.items.indexOf(t.el); if (i >= 0 && i !== ds.cur) { ds.cur = i; apply(); } }
});
['pointerup', 'pointercancel'].forEach((ev) => document.addEventListener(ev, (e) => { if (e.pointerType !== 'mouse') { return; } pending = null; endDrag(ev === 'pointerup'); }));

// A drag that just finished must not also "tap" the item it ended on.
document.addEventListener('click', (e) => { if (Date.now() < swallowUntil) { e.preventDefault(); e.stopPropagation(); } }, true);

// ---- pull-down search (phones)
const root = document.documentElement;
const searchBox = () => document.querySelector('.nc__list .nc__search input');
const collapsedWanted = () => phone() && !gcfg().buttons_only;

function syncCollapsed() {
  const input = searchBox();
  const keep = !input || input.value.trim() !== '' || document.activeElement === input;
  root.classList.toggle('nc-search-collapsed', collapsedWanted() && !keep);
}

function revealSearch(type) {
  root.classList.remove('nc-search-collapsed');
  const input = searchBox();
  if (input) { input.focus({ preventScroll: true }); }
  stat('pull_search', 'committed', type);
}

document.addEventListener('click', (e) => { if (e.target.closest && e.target.closest('[data-pull-search]')) { e.preventDefault(); revealSearch('touch'); } });
document.addEventListener('focusout', (e) => { if (e.target && e.target.matches && e.target.matches('.nc__search input')) { setTimeout(syncCollapsed, 150); } });

let pull = null; let hint = null;
const hintEl = (list) => {
  if (hint && hint.isConnected) { return hint; }
  hint = document.createElement('div'); hint.className = 'nc__pullhint'; hint.setAttribute('aria-hidden', 'true'); hint.textContent = 'Pull down to search';
  list.prepend(hint); return hint;
};
const clearHint = () => { if (hint) { hint.remove(); hint = null; } };

document.addEventListener('touchstart', (e) => {
  if (!collapsedWanted() || e.touches.length !== 1 || ds || pending) { return; }
  const rows = e.target.closest && e.target.closest('.nc__list .nc__rows'); if (!rows || rows.scrollTop > 0) { return; }
  if (!root.classList.contains('nc-search-collapsed')) { return; }
  if (dataOf(rows.closest('.nc__list'))) { return; }   // choosing chats: no pull-to-search
  pull = { list: rows.closest('.nc__list'), rows, y: e.touches[0].clientY, x: e.touches[0].clientX, dy: 0, armed: false };
}, { passive: true });

document.addEventListener('touchmove', (e) => {
  if (!pull) { return; }
  const p = e.touches[0]; const dy = p.clientY - pull.y;
  if (Math.abs(p.clientX - pull.x) > Math.abs(dy) || dy < 0 || pull.rows.scrollTop > 0) { clearHint(); pull = null; return; }
  pull.dy = dy;
  const h = hintEl(pull.list); const ratio = Math.min(1, dy / PULL_PX);
  h.style.opacity = String(Math.min(1, ratio * 1.2)); h.style.transform = `translateY(${Math.round(Math.min(dy, PULL_PX + 20) / 2)}px)`;
  const armed = dy >= PULL_PX;
  if (armed !== pull.armed) { pull.armed = armed; h.textContent = armed ? 'Release to search' : 'Pull down to search'; if (armed) { haptic(8); } }
}, { passive: true });

['touchend', 'touchcancel'].forEach((ev) => document.addEventListener(ev, () => {
  if (!pull) { return; }
  const was = pull; pull = null; clearHint();
  if (ev === 'touchend' && was.armed) { revealSearch('touch'); } else if (was.dy > 20) { stat('pull_search', 'cancelled', 'touch'); }
}, { passive: true }));

function init() { if (document.querySelector('[data-n2n-chat]')) { syncCollapsed(); } else { root.classList.remove('nc-search-collapsed'); } }
document.addEventListener('livewire:navigated', init);
window.addEventListener('resize', init);
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
void reduceMotion;
