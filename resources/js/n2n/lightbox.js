// Naara chat photo lightbox (MASTER-ONLY). Any `a[data-nlb]` inside the thread opens a full-screen viewer over every photo in that
// conversation: previous/next, swipe, arrow keys, Esc / backdrop to close, focus restored on close. Without JS the link still opens the image.
// Touch and trackpad gestures (Gestures blueprint, media): pinch to zoom, double-tap to zoom in and out, drag to pan while zoomed, swipe sideways to change photo,
// swipe DOWN to close (the picture follows the finger and the backdrop fades), wheel to zoom on a desktop. Every gesture has a button or key beside it (arrows, Esc,
// the close and next buttons). "Buttons only" turns off the swipes and the double-tap; pinching and the wheel still work, since they handle the picture itself.
import { gcfg, haptic, reduceMotion, stat } from './gesture-config.js';

let root = null; let items = []; let idx = 0; let opener = null;
const MAX_SCALE = 5; const DT_MS = 300; const DT_DIST = 32; const LOCK = 10;
const view = { s: 1, x: 0, y: 0 };
const ptrs = new Map(); let pinch = null; let drag = null; let lastTap = null; let moved = false; let swallowClick = 0;

const collect = (from) => {
  const scope = from.closest('[data-nlb-scope]') || document;
  return Array.from(scope.querySelectorAll('a[data-nlb]'));
};

const show = (i) => {
  if (!items.length) return;
  idx = (i + items.length) % items.length;
  const a = items[idx];
  resetView(false);
  const img = root.querySelector('.nlb__img'); img.removeAttribute('src'); img.alt = a.dataset.nlbAlt || 'Photo'; img.src = a.getAttribute('href');
  root.querySelector('.nlb__count').textContent = items.length > 1 ? `${idx + 1} / ${items.length}` : '';
  root.querySelector('.nlb__dl').href = a.dataset.nlbDl || a.getAttribute('href');
  root.querySelectorAll('.nlb__nav').forEach((b) => { b.hidden = items.length < 2; });
};

const close = () => {
  if (!root) return;
  root.remove(); root = null; ptrs.clear(); pinch = null; drag = null; lastTap = null; document.documentElement.classList.remove('nlb-open');
  document.removeEventListener('keydown', onKey, true);
  if (opener && document.contains(opener)) opener.focus();
  opener = null;
};

const onKey = (e) => {
  if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
  else if (e.key === 'ArrowRight') show(idx + 1);
  else if (e.key === 'ArrowLeft') show(idx - 1);
  else if (e.key === 'Tab' && root) { const f = Array.from(root.querySelectorAll('button:not([hidden]), a[href]')); if (!f.length) return; const first = f[0]; const last = f[f.length - 1]; if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } }
};


const imgEl = () => root && root.querySelector('.nlb__img');

function paint(animate = false) {
  const img = imgEl(); if (!img) return;
  img.style.transition = animate && !reduceMotion() ? 'transform .22s cubic-bezier(.2, 1, .3, 1)' : 'none';
  img.style.transform = `translate3d(${view.x}px, ${view.y}px, 0) scale(${view.s})`;
}

function resetView(animate = true) { view.s = 1; view.x = 0; view.y = 0; if (root) { root.style.removeProperty('--nlb-fade'); root.style.background = ''; paint(animate); } }

/** Keep a zoomed picture covering the screen: it may be panned only as far as its own edges. */
function clamp() {
  const img = imgEl(); if (!img) return;
  const w = img.offsetWidth * view.s; const h = img.offsetHeight * view.s;
  const mx = Math.max(0, (w - innerWidth) / 2); const my = Math.max(0, (h - innerHeight) / 2);
  view.x = Math.min(mx, Math.max(-mx, view.x)); view.y = Math.min(my, Math.max(-my, view.y));
}

/** Zoom to `next`, keeping the point under (cx, cy) where it is. */
function zoomAt(next, cx, cy) {
  next = Math.min(MAX_SCALE, Math.max(1, next));
  const ratio = next / view.s;
  const ox = cx - innerWidth / 2; const oy = cy - innerHeight / 2;
  view.x = ox - (ox - view.x) * ratio; view.y = oy - (oy - view.y) * ratio; view.s = next;
  if (next === 1) { view.x = 0; view.y = 0; }
  clamp();
}

/** Is the point over the picture itself (pointer capture makes e.target the stage, so ask by position)? */
const insideImg = (x, y) => { const i = imgEl(); if (!i) return false; const r = i.getBoundingClientRect(); return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom; };

const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
const mid = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

function wireGestures() {
  const stage = root;
  stage.style.touchAction = 'none';
  stage.addEventListener('pointerdown', (e) => {
    if (e.target.closest('button, a')) return;
    ptrs.set(e.pointerId, { x: e.clientX, y: e.clientY });
    try { stage.setPointerCapture(e.pointerId); } catch (_) { /* synthetic pointers */ }
    moved = false;
    if (ptrs.size === 2) {
      const [a, b] = Array.from(ptrs.values());
      pinch = { d: dist(a, b), s: view.s, m: mid(a, b), x: view.x, y: view.y }; drag = null; lastTap = null;
    } else if (ptrs.size === 1) {
      drag = { x: e.clientX, y: e.clientY, vx: view.x, vy: view.y, axis: '', t: Date.now(), type: e.pointerType };
    }
  });
  stage.addEventListener('pointermove', (e) => {
    if (!ptrs.has(e.pointerId)) return;
    ptrs.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && ptrs.size >= 2) {
      const [a, b] = Array.from(ptrs.values()); const m = mid(a, b);
      moved = true;
      const target = Math.min(MAX_SCALE, Math.max(1, pinch.s * dist(a, b) / pinch.d));
      zoomAt(target, m.x, m.y);
      view.x += m.x - pinch.m.x; view.y += m.y - pinch.m.y; pinch.m = m; clamp(); paint();
      return;
    }
    if (!drag) return;
    const dx = e.clientX - drag.x; const dy = e.clientY - drag.y;
    if (!drag.axis) {
      if (Math.abs(dx) < LOCK && Math.abs(dy) < LOCK) return;
      if (view.s > 1) { drag.axis = 'pan'; }
      else if (gcfg().buttons_only) { drag = null; return; }
      else drag.axis = Math.abs(dy) > Math.abs(dx) ? (dy > 0 ? 'down' : 'up') : 'side';
    }
    moved = true;
    if (drag.axis === 'pan') { view.x = drag.vx + dx; view.y = drag.vy + dy; clamp(); paint(); }
    else if (drag.axis === 'down') {
      view.y = Math.max(0, dy); view.x = dx * 0.25; paint();
      root.style.background = `rgb(0 0 0 / ${Math.max(0.25, 0.92 - view.y / 420)})`;
    } else if (drag.axis === 'side') { view.x = dx * 0.35; paint(); }
  });
  const end = (e) => {
    if (!ptrs.has(e.pointerId)) return;
    const wasPinch = !!pinch; ptrs.delete(e.pointerId);
    if (wasPinch) {
      if (ptrs.size < 2) { pinch = null; if (view.s < 1.05) resetView(true); else paint(true); stat('lightbox_zoom', 'committed', e.pointerType); swallowClick = Date.now() + 250; drag = null; }
      return;
    }
    const d = drag; drag = null; if (!d) return;
    const dx = e.clientX - d.x; const dy = e.clientY - d.y; const dt = Math.max(1, Date.now() - d.t);
    if (d.axis === 'down') {
      swallowClick = Date.now() + 250;
      if (view.y > 110 || (view.y > 40 && dy / dt > 0.6)) { stat('lightbox_dismiss', 'committed', d.type); haptic(8); close(); return; }
      stat('lightbox_dismiss', 'cancelled', d.type); root.style.background = ''; view.x = 0; view.y = 0; paint(true); return;
    }
    if (d.axis === 'side' && view.s === 1) {
      swallowClick = Date.now() + 250; view.x = 0; paint(true);
      if (Math.abs(dx) > 60 || Math.abs(dx) / dt > 0.5) { show(idx + (dx < 0 ? 1 : -1)); }
      return;
    }
    if (d.axis === 'pan') { swallowClick = Date.now() + 250; return; }
    // a tap: a second one close by is a double-tap
    if (!moved && Math.abs(dx) < 10 && Math.abs(dy) < 10 && !gcfg().buttons_only) {
      const now = Date.now();
      if (lastTap && now - lastTap.t < DT_MS && Math.hypot(lastTap.x - e.clientX, lastTap.y - e.clientY) < DT_DIST && insideImg(e.clientX, e.clientY)) {
        lastTap = null; swallowClick = now + 350;
        zoomAt(view.s > 1.05 ? 1 : 2.5, e.clientX, e.clientY); paint(true); stat('lightbox_zoom', 'committed', d.type); haptic(8);
      } else { lastTap = { t: now, x: e.clientX, y: e.clientY }; }
    }
  };
  stage.addEventListener('pointerup', end);
  stage.addEventListener('pointercancel', (e) => { ptrs.delete(e.pointerId); pinch = null; drag = null; resetView(true); });
  stage.addEventListener('wheel', (e) => {
    e.preventDefault();
    const f = Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0018));
    zoomAt(view.s * f, e.clientX, e.clientY); paint(); stat('lightbox_zoom', 'committed', 'mouse');
  }, { passive: false });
}

const open = (a) => {
  items = collect(a); idx = Math.max(0, items.indexOf(a)); opener = a;
  root = document.createElement('div'); root.className = 'nlb'; root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-label', 'Photo viewer');
  root.innerHTML = '<button type="button" class="nlb__x" aria-label="Close">&times;</button><button type="button" class="nlb__nav nlb__prev" aria-label="Previous photo">&#8249;</button>'
    + '<img class="nlb__img" alt=""><button type="button" class="nlb__nav nlb__next" aria-label="Next photo">&#8250;</button>'
    + '<div class="nlb__bar"><span class="nlb__count" aria-live="polite"></span><a class="nlb__dl" download>Download</a></div>';
  root.addEventListener('click', (e) => {
    if (Date.now() < swallowClick) return;
    if (e.target.closest('.nlb__x') || e.target === root) close();
    else if (e.target.closest('.nlb__prev')) show(idx - 1);
    else if (e.target.closest('.nlb__next')) show(idx + 1);
  });
  wireGestures();
  document.body.append(root); document.documentElement.classList.add('nlb-open');
  document.addEventListener('keydown', onKey, true);
  show(idx); root.querySelector('.nlb__x').focus();
};

document.addEventListener('click', (e) => {
  const a = e.target.closest && e.target.closest('a[data-nlb]');
  if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
  e.preventDefault(); open(a);
});
