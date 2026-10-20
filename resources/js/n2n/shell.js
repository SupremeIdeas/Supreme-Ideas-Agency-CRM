/** N2N shell glue (MASTER-ONLY): ONE realtime connection for every N2N page (chat, calls, updates...), plus the call client. */
import './realtime.js';
import './call.js';

const rt = window.N2NRealtime;
function boot() {
  if (!rt) { return; }
  rt.start();
  if (!window.__n2nPing) { window.__n2nPing = setInterval(() => rt.ping(), 45000); rt.ping(); }
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }

/* Desktop panels are draggable and resizable (rail width, conversation-list width). Delegated, so it survives Livewire re-renders and wire:navigate.
   Widths persist per browser; double-click or Home resets; arrow keys nudge. Phones never show the grips (CSS), so nothing here runs there. */
const PANELS = {
  list: { key: 'n2n_list_w', cssVar: '--nc-list-w', def: 360, min: 260, max: () => Math.min(560, Math.round(window.innerWidth * 0.5)), rtlFlip: true },
  rail: { key: 'n2n_rail_w', cssVar: '--nn-rail-w', def: 92, min: 72, max: () => 200, rtlFlip: true },
};
const isRtl = () => getComputedStyle(document.documentElement).direction === 'rtl';
const clamp = (p, v) => Math.max(p.min, Math.min(p.max(), Math.round(v)));
function applyPanel(name, w) {
  const p = PANELS[name]; const v = clamp(p, w);
  document.documentElement.style.setProperty(p.cssVar, v + 'px');
  if (name === 'rail') { document.documentElement.toggleAttribute('data-nn-rail-narrow', v < 84); }
  document.querySelectorAll('[data-nn-grip="' + name + '"]').forEach((g) => g.setAttribute('aria-valuenow', String(v)));
  return v;
}
function restorePanels() {
  for (const name of Object.keys(PANELS)) {
    let saved = NaN; try { saved = parseInt(localStorage.getItem(PANELS[name].key), 10); } catch (e) {}
    applyPanel(name, Number.isFinite(saved) ? saved : PANELS[name].def);
  }
}
function savePanel(name, v) { try { localStorage.setItem(PANELS[name].key, String(v)); } catch (e) {} }
function startDrag(e, grip) {
  const name = grip.dataset.nnGrip; const p = PANELS[name]; if (!p) { return; }
  const target = name === 'rail' ? grip.closest('.nn__rail') : grip.previousElementSibling; if (!target) { return; }
  const startX = e.clientX; const startW = target.getBoundingClientRect().width; const flip = p.rtlFlip && isRtl() ? -1 : 1;
  e.preventDefault(); grip.setPointerCapture?.(e.pointerId); document.body.classList.add('nn-dragging');
  const move = (ev) => { applyPanel(name, startW + (ev.clientX - startX) * flip); };
  const up = () => {
    window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); document.body.classList.remove('nn-dragging');
    savePanel(name, parseInt(getComputedStyle(document.documentElement).getPropertyValue(p.cssVar), 10) || p.def);
  };
  window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
}
if (!window.__nnGrips) {
  window.__nnGrips = true;
  document.addEventListener('pointerdown', (e) => { const g = e.target.closest?.('[data-nn-grip]'); if (g && e.button === 0) { startDrag(e, g); } });
  document.addEventListener('dblclick', (e) => { const g = e.target.closest?.('[data-nn-grip]'); if (g) { savePanel(g.dataset.nnGrip, applyPanel(g.dataset.nnGrip, PANELS[g.dataset.nnGrip].def)); } });
  document.addEventListener('keydown', (e) => {
    const g = e.target.closest?.('[data-nn-grip]'); if (!g) { return; }
    const name = g.dataset.nnGrip; const p = PANELS[name]; const cur = parseInt(g.getAttribute('aria-valuenow'), 10) || p.def;
    const flip = isRtl() ? -1 : 1; let next = null;
    if (e.key === 'ArrowRight') { next = cur + 16 * flip; } else if (e.key === 'ArrowLeft') { next = cur - 16 * flip; } else if (e.key === 'Home') { next = p.def; }
    if (next !== null) { e.preventDefault(); savePanel(name, applyPanel(name, next)); }
  });
  window.addEventListener('resize', restorePanels);
  document.addEventListener('livewire:navigated', restorePanels);
}
restorePanels();
