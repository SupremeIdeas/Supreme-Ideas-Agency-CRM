/* Composer extras (Gestures G4, MASTER-ONLY surfaces opt in with the feature names below): a long-press send menu (schedule, send silently, send and disappear),
   a slash menu, ":" emoji autocomplete, a full-screen compose, desktop shortcuts and hold-to-record with slide-to-lock / slide-to-cancel.
   Everything here is a SHORTCUT: each action also exists as an ordinary button or panel, and each menu opens by keyboard (Context Menu key, Shift+F10, Alt+Up on the send button).
   Features: 'sendmenu', 'slash', 'colon', 'keys', 'expand', 'holdmic'. */
import { ICONS } from './icons.js';
import { loadEmoji, pushRecent } from './emoji.js';

const SEND_CHOICES = [[3600, 'after1h'], [86400, 'after24h'], [604800, 'after7d']];
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const el = (tag, cls, html) => { const n = document.createElement(tag); if (cls) n.className = cls; if (html !== undefined) n.innerHTML = html; return n; };
const holdMs = () => { try { const j = JSON.parse((document.getElementById('ng-config') || {}).textContent || '{}'); return Math.max(300, Math.min(1000, Number(j.long_press_ms) || 450)); } catch (e) { return 450; } };
const buzz = (ms = 10) => { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) { /* unsupported */ } } };
const svg = (body) => `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
const I = {
  bell: svg('<path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="m2 2 20 20"/>'),
  timer: svg('<path d="M10 2h4"/><path d="M12 14v-4"/><circle cx="12" cy="14" r="8"/>'),
  expand: svg('<path d="m15 3 6 0 0 6"/><path d="m9 21-6 0 0-6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/>'),
  shrink: svg('<path d="m4 14 6 0 0 6"/><path d="m20 10-6 0 0-6"/><path d="m14 10 7-7"/><path d="m3 21 7-7"/>'),
};

export function bindExtras(c) {
  const r = c.refs;
  if (c.field) { return; }                                            // a "field" composer only collects text; none of this applies
  if (c.has('sendmenu') && r.sendBtn) { sendMenu(c); }
  if (c.has('slash') || c.has('colon')) { suggestMenus(c); }
  if (c.has('keys')) { shortcuts(c); }
  if (c.has('expand')) { expandCompose(c); }
  if (c.has('holdmic') && r.micBtn && !c.isLocked('voice')) { holdMic(c); }
}

/* ------------------------------------------------------------------ send menu */
function sendMenu(c) {
  const r = c.refs; const L = c.L;
  const menu = el('div', 'cc-sendmenu'); menu.hidden = true; menu.setAttribute('role', 'menu'); menu.setAttribute('aria-label', L.sendOptions || 'More ways to send');
  const item = (cls, icon, label, fn) => { const b = el('button', 'cc-smi ' + cls, `${icon}<span>${esc(label)}</span>`); b.type = 'button'; b.setAttribute('role', 'menuitem'); b.addEventListener('click', () => { close(); fn(); }); return b; };
  const sched = c.has('schedule') ? item('', ICONS.clock, L.schedule, () => c.toggle('pSched', r.schedBtn)) : null;
  const silent = item('cc-needs', I.bell, L.sendSilently || 'Send silently', () => { c.nextSend = { silent: true }; c.handleSend(); });
  const wrap = el('div', 'cc-smg'); wrap.setAttribute('role', 'group'); wrap.setAttribute('aria-label', L.sendDisappear || 'Send and disappear after');
  wrap.append(el('div', 'cc-smt', `${I.timer}<span>${esc(L.sendDisappear || 'Send and disappear after')}</span>`));
  const timers = SEND_CHOICES.map(([sec, key]) => { const b = el('button', 'cc-smc cc-needs', esc(L[key] || key)); b.type = 'button'; b.setAttribute('role', 'menuitem'); b.addEventListener('click', () => { close(); c.nextSend = { disappear: sec }; c.handleSend(); }); return b; });
  const row = el('div', 'cc-smr'); row.append(...timers); wrap.append(row);
  if (c.o.disappearing !== false) { menu.append(...[sched, silent, wrap].filter(Boolean)); } else { menu.append(...[sched, silent].filter(Boolean)); }
  c.root.append(menu);

  let hold = null; let swallow = 0;
  function open() {
    const has = c.hasContent();
    menu.querySelectorAll('.cc-needs').forEach((b) => { b.disabled = !has || !!c.pending; });
    menu.hidden = false; r.sendBtn.setAttribute('aria-expanded', 'true'); buzz(10);
    const first = menu.querySelector('button:not(:disabled)'); if (first) { first.focus({ preventScroll: true }); }
  }
  function close() { if (menu.hidden) { return; } menu.hidden = true; r.sendBtn.setAttribute('aria-expanded', 'false'); }
  r.sendBtn.setAttribute('aria-haspopup', 'menu'); r.sendBtn.setAttribute('aria-expanded', 'false');
  c.on(r.sendBtn, 'pointerdown', (e) => {
    if (!e.isPrimary || c.pending || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
    const x = e.clientX; const y = e.clientY;
    clearTimeout(hold); hold = setTimeout(() => { hold = null; swallow = Date.now() + 600; open(); }, holdMs());
    const move = (m) => { if (Math.hypot(m.clientX - x, m.clientY - y) > 10) { clearTimeout(hold); } };
    const up = () => { clearTimeout(hold); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up); };
    window.addEventListener('pointermove', move, { passive: true }); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
  });
  c.on(r.sendBtn, 'click', (e) => { if (Date.now() < swallow) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);   // the release after a long press must not also send
  c.on(r.sendBtn, 'contextmenu', (e) => { e.preventDefault(); swallow = Date.now() + 600; open(); });
  c.on(r.sendBtn, 'keydown', (e) => { if (e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey) || (e.key === 'ArrowUp' && e.altKey)) { e.preventDefault(); open(); } });
  c.on(menu, 'keydown', (e) => {
    const items = Array.from(menu.querySelectorAll('button:not(:disabled)')); const i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); close(); r.sendBtn.focus(); }
    else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); items[(i + 1) % items.length]?.focus(); }
    else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
  });
  c.on(document, 'pointerdown', (e) => { if (!menu.hidden && !menu.contains(e.target) && e.target !== r.sendBtn && !r.sendBtn.contains(e.target)) { close(); } }, true);
}

/* ------------------------------------------------------- slash and colon menus */
function suggestMenus(c) {
  const r = c.refs; const L = c.L; const t = r.text;
  const list = el('div', 'cc-suggest'); list.hidden = true; list.setAttribute('role', 'listbox'); list.id = `cc-sg-${Math.random().toString(36).slice(2, 7)}`;
  const box = c.root.querySelector('.cc-box'); if (box) { box.before(list); } else { c.root.insertBefore(list, c.root.firstChild); }
  t.setAttribute('aria-controls', list.id);
  let items = []; let idx = 0; let span = null; let emojiData = null;

  const kinds = c.o.attachKinds || [];
  const cmds = [];
  const add = (key, label, hint, run) => cmds.push({ key, label, hint, run });
  if (c.has('slash')) {
    if (kinds.includes('poll')) { add('poll', L.poll, L.cmdPoll || 'Ask a question', () => c.pickKind('poll')); }
    if (c.has('schedule')) { add('schedule', L.schedule, L.cmdSchedule || 'Deliver later', () => c.toggle('pSched', r.schedBtn)); }
    if (kinds.includes('location')) { add('location', L.location, L.cmdLocation || 'Share where you are', () => c.pickKind('location')); }
    if (kinds.includes('contact')) { add('contact', L.contact, L.cmdContact || 'Share a contact card', () => c.pickKind('contact')); }
    if (c.has('gif') && r.gifBtn) { add('gif', L.gif, L.cmdGif || 'Find a GIF', () => r.gifBtn.click()); }
    if (r.emojiBtn) { add('emoji', L.emoji, L.cmdEmoji || 'Open the emoji picker', () => r.emojiBtn.click()); }
    if (c.has('snippets')) { ['date', 'time', 'addr'].forEach((k) => add(k, k, L.cmdSnippet || 'Insert text', null)); }
  }

  function hide() { list.hidden = true; list.replaceChildren(); items = []; t.removeAttribute('aria-activedescendant'); t.removeAttribute('aria-expanded'); }
  function paint() {
    list.replaceChildren();
    items.forEach((it, i) => {
      const o = el('div', 'cc-sgi' + (i === idx ? ' is-on' : ''), it.glyph ? `<span class="cc-sge">${esc(it.glyph)}</span><span class="cc-sgl">${esc(it.label)}</span>` : `<span class="cc-sgk">/${esc(it.key)}</span><span class="cc-sgl">${esc(it.hint || '')}</span>`);
      o.id = `${list.id}-${i}`; o.setAttribute('role', 'option'); o.setAttribute('aria-selected', i === idx ? 'true' : 'false');
      o.addEventListener('mousedown', (e) => e.preventDefault()); o.addEventListener('click', () => pick(i));
      list.append(o);
    });
    list.hidden = items.length === 0; t.setAttribute('aria-expanded', items.length ? 'true' : 'false');
    if (items.length) { t.setAttribute('aria-activedescendant', `${list.id}-${idx}`); }
  }
  function cut() { const v = t.value; t.value = v.slice(0, span.start) + v.slice(span.end); t.selectionStart = t.selectionEnd = span.start; t.dispatchEvent(new Event('input')); }
  function pick(i) {
    const it = items[i]; if (!it || !span) { return; }
    const s = span; hide();
    if (it.glyph) { const v = t.value; t.value = v.slice(0, s.start) + it.glyph + ' ' + v.slice(s.end); t.selectionStart = t.selectionEnd = s.start + it.glyph.length + 1; pushRecent(it.glyph); t.dispatchEvent(new Event('input')); t.focus(); return; }
    if (it.run === null) { const v = t.value; c.snippet(it.key, v, s.end, it.key.length); return; }   // /date, /time, /addr fill in text
    span = s; cut(); it.run(); t.focus();
  }
  async function refresh() {
    const before = t.value.slice(0, t.selectionStart ?? t.value.length);
    const sm = c.has('slash') ? /(^|\s)\/([a-z]{0,10})$/i.exec(before) : null;
    if (sm) {
      const q = sm[2].toLowerCase(); span = { start: before.length - sm[2].length - 1, end: before.length };
      items = cmds.filter((m) => m.key.startsWith(q)).slice(0, 8); idx = 0; paint(); return;
    }
    const em = c.has('colon') ? /(^|\s):([a-z0-9_+-]{2,20})$/i.exec(before) : null;
    if (em) {
      const q = em[2].toLowerCase().replace(/_/g, ' ');
      if (!emojiData) { try { emojiData = (await loadEmoji()).flatMap((g) => g.emojis); } catch (e) { emojiData = []; } }
      span = { start: before.length - em[2].length - 1, end: before.length };
      items = emojiData.filter((x) => x.n.toLowerCase().includes(q)).sort((a, b) => (a.n.toLowerCase().startsWith(q) ? 0 : 1) - (b.n.toLowerCase().startsWith(q) ? 0 : 1)).slice(0, 7).map((x) => ({ glyph: x.e, label: x.n }));
      idx = 0; paint(); return;
    }
    hide();
  }
  c.on(t, 'input', () => { refresh(); });
  c.on(t, 'blur', () => setTimeout(hide, 120));
  // Capture on the whole composer so Enter / Tab / arrows choose from the list BEFORE the textarea's own Enter-to-send runs.
  c.on(c.root, 'keydown', (e) => {
    if (list.hidden || e.target !== t) { return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); e.stopPropagation(); idx = (idx + 1) % items.length; paint(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); e.stopPropagation(); idx = (idx - 1 + items.length) % items.length; paint(); }
    else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); pick(idx); }
    else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hide(); }
  }, true);
}

/* ------------------------------------------------------------ desktop shortcuts */
function shortcuts(c) {
  const t = c.refs.text; const L = c.L;
  c.on(t, 'keydown', (e) => {
    if (e.isComposing) { return; }
    const empty = t.value.trim() === '';
    if (e.key === 'ArrowUp' && empty && !e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) { if (!c.emit('edit-last', {}, true).defaultPrevented) { /* the page did not take it */ } else { e.preventDefault(); } }   // edit my last message (the page decides what that means)
    else if (e.key === 'Escape') { c.emit('cancel-reply', {}); }
    else if (e.key === '?' && empty) { e.preventDefault(); help(c); }
  });
}

function help(c) {
  let box = document.getElementById('cc-keys');
  if (box) { box.remove(); return; }
  const rows = [['Enter', c.L.keySend || 'Send'], ['Shift + Enter', c.L.keyNewline || 'New line'], ['Up', c.L.keyEdit || 'Edit your last message (empty box)'], ['Esc', c.L.keyEsc || 'Close a menu, cancel a reply'], ['/', c.L.keySlash || 'Commands: poll, schedule, location, GIF'], [':', c.L.keyColon || 'Emoji by name, for example :smile'], ['Ctrl or Cmd + K', c.L.keySwitch || 'Jump to a chat'], ['?', c.L.keyHelp || 'This list']];
  box = el('div', 'cc-keys'); box.id = 'cc-keys'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', c.L.keyboard || 'Keyboard shortcuts');
  box.innerHTML = `<div class="cc-keys-in"><b>${esc(c.L.keyboard || 'Keyboard shortcuts')}</b><dl>${rows.map(([k, d]) => `<dt><kbd>${esc(k)}</kbd></dt><dd>${esc(d)}</dd>`).join('')}</dl><button type="button" class="cc-chip cc-primary">${esc(c.L.ok || 'OK')}</button></div>`;
  const done = () => { box.remove(); c.refs.text.focus(); };
  box.querySelector('button').addEventListener('click', done);
  box.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.preventDefault(); done(); } });
  box.addEventListener('click', (e) => { if (e.target === box) { done(); } });
  document.body.append(box); box.querySelector('button').focus();
}

/* ----------------------------------------------------------- full-screen compose */
function expandCompose(c) {
  const r = c.refs; const L = c.L;
  const btn = el('button', 'cc-expand', I.expand); btn.type = 'button'; btn.setAttribute('aria-label', L.expand || 'Full-screen compose'); btn.setAttribute('aria-pressed', 'false');
  const handle = el('div', 'cc-handle'); handle.setAttribute('aria-hidden', 'true'); handle.innerHTML = '<i></i>';
  const bx = c.root.querySelector('.cc-box') || c.root; bx.prepend(handle); bx.append(btn);
  const set = (on) => {
    c.root.classList.toggle('cc-expanded', on); btn.innerHTML = on ? I.shrink : I.expand; btn.setAttribute('aria-pressed', on ? 'true' : 'false'); btn.setAttribute('aria-label', on ? (L.collapse || 'Back to the chat') : (L.expand || 'Full-screen compose'));
    document.documentElement.classList.toggle('cc-noscroll', on);
    if (on) { r.text.style.height = ''; r.text.focus(); } else { c.autoGrow(); }
  };
  c.on(btn, 'click', () => set(!c.root.classList.contains('cc-expanded')));
  c.on(c.root, 'keydown', (e) => { if (e.key === 'Escape' && c.root.classList.contains('cc-expanded') && !c.openPanel) { e.preventDefault(); set(false); } });
  // Swipe up on the handle expands, swipe down collapses.
  let y0 = null;
  c.on(handle, 'pointerdown', (e) => { y0 = e.clientY; try { handle.setPointerCapture(e.pointerId); } catch (x) { /* not capturable */ } });
  c.on(handle, 'pointerup', (e) => { if (y0 === null) { return; } const dy = e.clientY - y0; y0 = null; if (dy < -36) { set(true); } else if (dy > 36) { set(false); } else { set(!c.root.classList.contains('cc-expanded')); } });
  c.on(handle, 'pointercancel', () => { y0 = null; });
  c.cleanups.push(() => document.documentElement.classList.remove('cc-noscroll'));
}

/* ----------------------------------------------- hold the mic: lock or cancel */
function holdMic(c) {
  const r = c.refs; const L = c.L;
  let st = null; let swallow = 0;
  const hintOf = () => { let h = c.root.querySelector('.cc-holdhint'); if (!h) { h = el('div', 'cc-holdhint'); h.setAttribute('aria-hidden', 'true'); c.root.append(h); } return h; };
  const clear = () => { const h = c.root.querySelector('.cc-holdhint'); if (h) { h.remove(); } };
  c.on(r.micBtn, 'pointerdown', (e) => {
    if (e.pointerType === 'mouse' || !e.isPrimary || c.voice) { return; }
    st = { x: e.clientX, y: e.clientY, started: false, locked: false, cancelled: false, timer: null, at: 0 };
    st.timer = setTimeout(async () => {
      if (!st) { return; }
      st.started = true; st.at = Date.now(); swallow = Date.now() + 5000; await c.startVoice();
      if (!c.voice || !st) { st = null; return; }
      const h = hintOf(); h.innerHTML = `<span>${esc(L.holdCancel || 'Slide left to cancel')}</span><span>${esc(L.holdLock || 'Slide up to lock')}</span>`;
    }, 320);
    const move = (m) => {
      if (!st) { return; }
      const dx = m.clientX - st.x; const dy = m.clientY - st.y;
      if (!st.started) { if (Math.hypot(dx, dy) > 12) { clearTimeout(st.timer); st = null; } return; }
      if (st.locked || st.cancelled || !c.voice) { return; }
      const h = c.root.querySelector('.cc-holdhint');
      if (dy < -70) { st.locked = true; buzz(12); clear(); c.say(L.holdLocked || 'Recording locked. Tap send when you are done.', null, null, 3500); }
      else if (dx < -90) { st.cancelled = true; buzz([10, 40, 10]); clear(); c.voice.discard(); c.endVoiceUi(); c.say(L.holdCancelled || 'Recording cancelled.', null, null, 2500); }
      else if (h) { h.classList.toggle('is-cancel', dx < -45); }
    };
    const up = async () => {
      window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
      const s = st; st = null; if (!s) { return; }
      clearTimeout(s.timer); clear();
      if (!s.started || s.locked || s.cancelled || !c.voice) { return; }     // a tap is the ordinary click; locked recordings carry on hands-free; cancelled ones are gone
      if (Date.now() - s.at < 900) { c.voice.discard(); c.endVoiceUi(); c.say(L.holdLonger || 'Hold the mic to record, release to send.', null, null, 3000); return; }
      c.sendVoice();
    };
    window.addEventListener('pointermove', move, { passive: true }); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
  });
  c.on(r.micBtn, 'click', (e) => { if (Date.now() < swallow) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);   // the release after a hold is not a tap
  c.on(r.micBtn, 'contextmenu', (e) => e.preventDefault());                                                                  // a long press on a phone must not open the system menu
}
