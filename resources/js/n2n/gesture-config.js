/* Shared gesture settings for the Naara chat engine (MASTER-ONLY). The server renders the member's merged, validated preferences into
   <script type="application/json" id="ng-config">; this reads it fresh (cached by text) so a change on the settings screen applies to the very next gesture
   without a reload. Also the haptic helper and the privacy-safe counters (anonymous totals per gesture and outcome, batched, never a user id). */
const DEFAULTS = { double_tap_react: true, double_tap_code: 'love', swipe_reply: true, swipe_start: 'info', list_swipe: true, haptics: true, long_press_ms: 450, swipe_commit_px: 56, buttons_only: false, glyph: '', stat_url: '' };
let raw = null; let parsed = null;

export function gcfg() {
  const el = document.getElementById('ng-config');
  const txt = el ? el.textContent : '';
  if (txt !== raw) { raw = txt; try { parsed = txt ? JSON.parse(txt) : null; } catch (e) { parsed = null; } }
  return Object.assign({}, DEFAULTS, parsed || {});
}

export const reduceMotion = () => !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

export function haptic(ms = 10) {
  if (!gcfg().haptics || !navigator.vibrate) { return; }
  try { navigator.vibrate(ms); } catch (e) { /* unsupported: silent */ }
}

const pending = new Map(); let timer = null;
function flush() {
  timer = null;
  const c = gcfg(); if (!c.stat_url || pending.size === 0) { pending.clear(); return; }
  const s = Array.from(pending.entries()).map(([k, n]) => { const [g, o, d] = k.split('|'); return { g, o, d, n }; });
  pending.clear();
  const token = document.querySelector('meta[name="csrf-token"]');
  try {
    fetch(c.stat_url, { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }, body: JSON.stringify({ s }) }).catch(() => {});
  } catch (e) { /* counters are best effort */ }
}
export function stat(gesture, outcome, pointerType = 'touch') {
  const k = `${gesture}|${outcome}|${pointerType === 'mouse' ? 'mouse' : 'touch'}`;
  pending.set(k, (pending.get(k) || 0) + 1);
  if (!timer) { timer = setTimeout(flush, 15000); }
}
window.addEventListener('pagehide', flush);
document.addEventListener('visibilitychange', () => { if (document.hidden) { flush(); } });
