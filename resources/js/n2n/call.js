/**
 * N2N voice calls, browser side (MASTER-ONLY). WebRTC peer-to-peer audio; signalling goes through the server (/n2n/api/calls/*) and comes back
 * on the realtime stream (call.incoming / call.accepted / call.signal / call.ended). The SERVER decides when a call starts, ends and is billed:
 * this file only shows state, sends heartbeats and tears down when the server says the call is over. Nothing here is a timing authority.
 * Content is encrypted in transit by WebRTC (DTLS-SRTP). We never claim end-to-end encryption.
 */
import { gcfg, haptic, stat } from './gesture-config.js';
import { create as createBeauty, PRESETS as BEAUTY, saved as beautySaved, remember as beautyRemember, supported as beautySupported } from './beauty.js';

const rt = window.N2NRealtime;
const base = '/n2n/api/calls';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const J = { 'Content-Type': 'application/json', Accept: 'application/json' };

async function api(path, method = 'POST', body) {
  const res = await fetch(base + path, { method, credentials: 'same-origin', headers: { ...J, 'X-CSRF-TOKEN': csrf() }, body: body ? JSON.stringify(body) : undefined });
  let data = {};
  try { data = await res.json(); } catch (e) { /* empty */ }
  return { ok: res.ok, status: res.status, data };
}

const S = { soundOff: false, swapped: false, sharing: false, screen: null, minimized: false, video: false, cam: null, camOn: false, vsender: null, black: null, out: null, beauty: null, facing: 'user', peerCam: false, cams: 0, idleUi: null, look: beautySaved(), call: null, pc: null, stream: null, audio: null, hb: null, stats: null, pending: [], remoteSet: false, muted: false, relay: false, ended: false, cfg: { heartbeat_s: 8 }, timer: null, startedAt: 0, ringTimer: null };
window.N2NCall = {
  state: () => ({ id: S.call?.id || null, phase: ui.phase, relay: S.relay, ice: S.pc?.iceConnectionState || null }),
  /** What the video side is doing right now (used by tests/visual/n2n/video-call-smoke.mjs). */
  video() {
    const t = S.vsender?.track; const rv = document.querySelector('#n2n-call [data-call-remote]');
    return { on: S.video, camOn: S.camOn, sending: t ? { kind: t.kind, state: t.readyState, black: t === S.black } : null, remoteW: rv ? rv.videoWidth : 0, peerCam: S.peerCam, look: S.look.preset };
  },
  /** Measured, not assumed: bytes of audio received so far and whether the selected pair uses a relay (used by tests/visual/n2n/call-smoke.mjs). */
  async stats() {
    if (!S.pc) { return null; }
    const rep = await S.pc.getStats(); let bytes = 0; let vbytes = 0; let relay = false; const cands = {}; const pairs = [];
    rep.forEach((r) => { if (r.type === 'inbound-rtp' && r.kind === 'audio') { bytes += r.bytesReceived || 0; } if (r.type === 'inbound-rtp' && r.kind === 'video') { vbytes += r.bytesReceived || 0; } if (r.type.endsWith('-candidate')) { cands[r.id] = r; } if (r.type === 'candidate-pair' && (r.selected || r.nominated) && r.state === 'succeeded') { pairs.push(r); } });
    const sel = pairs[0]; if (sel) { relay = [cands[sel.localCandidateId], cands[sel.remoteCandidateId]].some((c) => c && c.candidateType === 'relay'); }
    return { audioBytes: bytes, videoBytes: vbytes, relay, beauty: S.beauty ? S.beauty.settings.preset : 'off' };
  },
};

// ---------------------------------------------------------------------------------------------- UI
/** Swap the glyph inside a round control (the sprite id is i-<name>, or nx-<name> for the wireframe glyphs). */
const setIcon = (btn, name) => { const u = btn?.querySelector('use'); if (u) { u.setAttribute('href', '#' + (name === 'volume' ? 'nx-volume' : 'i-' + name)); } };

const ui = {
  phase: 'idle',
  el() { return document.getElementById('n2n-call'); },
  set(phase, extra = {}) {
    this.phase = phase;
    const el = this.el();
    if (!el) { return; }
    el.hidden = phase === 'idle';
    el.dataset.phase = phase;
    const q = (s) => el.querySelector(s);
    const labels = { calling: 'Calling...', ringing: 'Incoming call', connecting: 'Connecting...', active: 'In call', reconnecting: 'Reconnecting...', ended: 'Call ended', error: 'Call failed' };
    q('[data-call-name]').textContent = extra.name ?? q('[data-call-name]').textContent;
    q('[data-call-status]').textContent = extra.status ?? labels[phase] ?? '';
    q('[data-call-answer]').hidden = phase !== 'ringing';
    q('[data-call-decline]').hidden = phase !== 'ringing';
    const vid = !!(S.call && S.call.video) || (extra.video === true);
    el.classList.toggle('is-video', vid && phase !== 'idle');
    const live = ['calling', 'connecting', 'active', 'reconnecting'].includes(phase);
    const voiceShown = !vid && (live || phase === 'ringing');
    el.classList.toggle('is-voice', voiceShown);
    const stage = q('[data-call-stage]'); stage.hidden = !((vid && live) || voiceShown);
    q('[data-call-answer-audio]').hidden = !(phase === 'ringing' && vid);
    q('[data-call-answer] span').textContent = vid ? 'Video' : 'Answer';
    if (vid) {
      const on = ['active', 'reconnecting'].includes(phase);
      const cam = q('[data-call-cam]'); cam.hidden = !live; cam.classList.toggle('is-on', S.camOn); setIcon(cam, S.camOn ? 'video' : 'video-off'); cam.setAttribute('aria-pressed', S.camOn ? 'false' : 'true'); cam.setAttribute('aria-label', S.camOn ? 'Turn the camera off' : 'Turn the camera on');
      q('[data-call-more]').hidden = !on;
      const wand = q('[data-call-beauty]'); wand.hidden = !(live && beautySupported()); wand.classList.toggle('is-on', S.look.preset !== 'off');
      q('[data-call-flip]').hidden = !(live && S.cams > 1 && S.camOn);
      const sp = q('[data-call-out]'); sp.hidden = !live; sp.classList.toggle('is-on', !S.soundOff); setIcon(sp, S.soundOff ? 'volume-x' : 'volume');
      q('[data-call-vname]').textContent = q('[data-call-name]').textContent;
      q('[data-call-vstatus]').textContent = phase === 'active' ? '' : q('[data-call-status]').textContent;
      if (extra.quality !== undefined) { q('[data-call-vquality]').textContent = on ? extra.quality : ''; }
      if (extra.qkey !== undefined) { q('[data-call-qicon]').dataset.q = extra.qkey; }
      const away = q('[data-call-away]'); away.hidden = on ? S.peerCam : false;
      q('[data-call-away-text]').textContent = on ? 'Camera is off' : (q('[data-call-status]').textContent || 'Calling...');
      const w = q('[data-call-vwarn]'); const warn = q('[data-call-warn]'); w.hidden = warn.hidden; w.textContent = warn.textContent;
    } else {
      q('[data-call-cam]').hidden = true; q('[data-call-flip]').hidden = true; q('[data-call-beauty]').hidden = true;
      q('[data-call-more]').hidden = !['active', 'reconnecting'].includes(phase);
      q('[data-call-vname]').textContent = q('[data-call-name]').textContent;
      if (extra.quality !== undefined) { q('[data-call-vquality]').textContent = ['active', 'reconnecting'].includes(phase) ? extra.quality : ''; }
      if (extra.qkey !== undefined) { q('[data-call-qicon]').dataset.q = extra.qkey; }
      const sp = q('[data-call-out]'); sp.classList.toggle('is-on', !S.soundOff); setIcon(sp, S.soundOff ? 'volume-x' : 'volume');
      const w = q('[data-call-vwarn]'); const warn = q('[data-call-warn]'); w.hidden = warn.hidden; w.textContent = warn.textContent;
    }
    if (vid || voiceShown) {
      // The timer slot shows what is happening until the call is live, then the clock.
      if (!['active', 'reconnecting'].includes(phase)) { q('[data-call-vclock]').textContent = q('[data-call-status]').textContent; }
      const av = q('[data-call-avatar]'); const nm = q('[data-call-name]').textContent || '';
      if (av && av.dataset.for !== nm) { av.dataset.for = nm; av.textContent = (nm.split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0].toUpperCase()).join('')) || '?'; let h = 0; for (const c of nm) { h = (h * 31 + c.charCodeAt(0)) % 360; } av.style.background = `hsl(${h} 45% 38%)`; }
    }
    if (!(vid && live)) { q('[data-beauty-sheet]').hidden = true; q('[data-more-sheet]').hidden = true; el.classList.remove('is-menu', 'is-min'); }
    q('[data-call-hangup]').hidden = !['calling', 'connecting', 'active', 'reconnecting'].includes(phase);
    q('[data-call-mute]').hidden = !['connecting', 'active', 'reconnecting'].includes(phase) && !(vid && phase === 'calling');
    if (!vid) { q('[data-call-out]').hidden = !['active', 'reconnecting'].includes(phase); }
    q('[data-call-actions]').hidden = !extra.actions;
    if (extra.actions) { q('[data-call-buy]').href = extra.actions.buy; q('[data-call-ask]').href = extra.actions.ask; }
    if (extra.warn !== undefined) { q('[data-call-warn]').hidden = !extra.warn; q('[data-call-warn]').textContent = extra.warn || ''; }
    if (extra.quality !== undefined) { q('[data-call-quality]').textContent = extra.quality; }
  },
  clock() {
    const el = this.el()?.querySelector('[data-call-clock]');
    if (!el) { return; }
    clearInterval(S.timer);
    const vclock = this.el()?.querySelector('[data-call-vclock]');
    S.timer = setInterval(() => { const s = Math.max(0, Math.floor((Date.now() - S.startedAt) / 1000)); const t = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); el.textContent = t; if (vclock) { vclock.textContent = t; } }, 500);   // display only: billing uses the server clock
  },
};

// ---------------------------------------------------------------------------------------------- audio / peer connection
function tuneOpus(sdp) { return sdp.replace(/a=fmtp:(\d+) ([^\r\n]*useinbandfec=1[^\r\n]*)/g, (m, pt, rest) => (/usedtx=1/.test(rest) ? m : `a=fmtp:${pt} ${rest};usedtx=1`)); }   // Opus with DTX: silence costs almost nothing

async function getMic() {
  try { return await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false }); } catch (e) {
    ui.set('error', { status: e && e.name === 'NotAllowedError' ? 'Allow the microphone to make calls, then try again.' : 'No microphone was found.' });
    return null;
  }
}

// ---------------------------------------------------------------------------------------------- video
const $q = (sel) => ui.el()?.querySelector(sel);
const camConstraints = () => ({ width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30, max: 30 }, facingMode: S.facing });

/** A tiny black track that keeps the video line open while the camera is off: turning the camera on later is then a track swap, never a renegotiation. */
function blackTrack() {
  const c = document.createElement('canvas'); c.width = 16; c.height = 16; const x = c.getContext('2d'); x.fillStyle = '#000'; x.fillRect(0, 0, 16, 16);
  const t = c.captureStream(2).getVideoTracks()[0]; t.enabled = true; S.blackCanvas = c; return t;
}
const sendTrack = () => { const t = S.beauty ? S.beauty.stream.getVideoTracks()[0] : S.cam?.getVideoTracks()[0]; return t || S.black; };
function showSelf() {
  const v = $q('[data-call-local]'); const t = S.camOn ? sendTrack() : null;
  if (v) { v.srcObject = t ? new MediaStream([t]) : null; if (t) { v.play().catch(() => {}); } }
  $q('[data-call-pip]')?.classList.toggle('is-back', S.facing === 'environment');
  const off = $q('[data-call-pipoff]'); if (off) { off.hidden = S.camOn; }
  const cam = $q('[data-call-cam]'); if (cam) { cam.setAttribute('aria-pressed', S.camOn ? 'false' : 'true'); cam.querySelector('span').textContent = S.camOn ? 'Camera' : 'Camera off'; }
}
async function countCams() { try { S.cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput').length; } catch (e) { S.cams = 0; } }

async function startCamera() {
  try {
    S.cam = await navigator.mediaDevices.getUserMedia({ audio: false, video: camConstraints() });
  } catch (e) {
    S.cam = null; S.camOn = false; showSelf();
    ui.set(ui.phase, { warn: e && e.name === 'NotAllowedError' ? 'Camera is blocked. Allow it in your browser to turn video on.' : 'No camera was found, so this call continues without video.' });
    setTimeout(() => ui.set(ui.phase, { warn: '' }), 6000);
    return false;
  }
  S.camOn = true;
  await countCams();
  if (S.look.preset !== 'off' && beautySupported()) { S.beauty = createBeauty(S.cam, { preset: S.look.preset, intensity: S.look.intensity }); }
  try { await S.vsender?.replaceTrack(sendTrack()); } catch (e) { /* the call carries on with audio */ }
  showSelf(); signalMedia(); ui.set(ui.phase);
  return true;
}

function stopCamera() {
  S.beauty?.destroy(); S.beauty = null;
  try { S.cam?.getTracks().forEach((t) => t.stop()); } catch (e) { /* ok */ }
  S.cam = null; S.camOn = false;
  try { S.vsender?.replaceTrack(S.black); } catch (e) { /* ok */ }
  showSelf(); signalMedia(); ui.set(ui.phase);
}

function signalMedia() { if (S.call && S.pc) { api(`/${S.call.id}/signal`, 'POST', { kind: 'media', data: { cam: S.camOn } }); } }

async function setLook(name, intensity) {
  if (!(name in BEAUTY)) { return; }
  S.look = { preset: name, intensity: intensity ?? S.look.intensity }; beautyRemember(S.look);
  if (S.camOn && S.cam) {
    if (name === 'off') { S.beauty?.destroy(); S.beauty = null; }
    else if (!S.beauty) { S.beauty = createBeauty(S.cam, { preset: name, intensity: S.look.intensity }); }
    else { S.beauty.setPreset(name); S.beauty.setIntensity(S.look.intensity); }
    try { await S.vsender?.replaceTrack(sendTrack()); } catch (e) { /* ok */ }
    showSelf();
  }
  drawLooks();
}

function drawLooks() {
  const list = $q('[data-beauty-list]'); if (!list) { return; }
  const sw = { off: 'linear-gradient(135deg,#3a3f47,#1b1f25)', natural: 'linear-gradient(135deg,#f3d3b8,#d89a73)', soft: 'linear-gradient(135deg,#fbe6dd,#e7b6a1)', glow: 'linear-gradient(135deg,#fff3d6,#f2b97a)', radiant: 'linear-gradient(135deg,#ffd7b5,#e9805b)', warm: 'linear-gradient(135deg,#ffcf8a,#d9803a)', studio: 'linear-gradient(135deg,#e8edf2,#9aa7b5)' };
  list.textContent = '';
  Object.entries(BEAUTY).forEach(([k, v]) => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'nc-beauty__chip'; b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', S.look.preset === k ? 'true' : 'false'); b.title = v.hint;
    const c = document.createElement('span'); c.className = 'nc-beauty__sw'; c.style.setProperty('--sw', sw[k]); if (k === 'off') { c.textContent = 'Off'; c.style.fontSize = '12px'; }
    const l = document.createElement('span'); l.textContent = v.label;
    b.append(c, l); b.addEventListener('click', () => setLook(k)); list.appendChild(b);
  });
  const sl = $q('[data-beauty-slider]'); if (sl) { sl.hidden = S.look.preset === 'off'; sl.querySelector('input').value = Math.round(S.look.intensity * 100); }
}

const REACTION_LABELS = { '\u{1F44D}': 'Thumbs up', '\u2764\uFE0F': 'Heart', '\u{1F602}': 'Laughing', '\u{1F62E}': 'Surprised', '\u{1F622}': 'Sad', '\u{1F64F}': 'Thank you' };
const REACTIONS = Object.keys(REACTION_LABELS);
const shareSupported = () => !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) && !/Android|iPhone|iPad/i.test(navigator.userAgent);

function floatReaction(emoji, mine) {
  const box = $q('[data-call-float]'); if (!box || !REACTIONS.includes(emoji)) { return; }
  const sp = document.createElement('span'); sp.textContent = emoji;
  sp.style.insetInlineStart = (mine ? 62 + Math.random() * 26 : 12 + Math.random() * 40) + '%';
  box.appendChild(sp); setTimeout(() => sp.remove(), 2800);
}

async function startShare() {
  if (!S.vsender || !shareSupported()) { return; }
  try {
    S.screen = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
  } catch (e) { return; }
  const t = S.screen.getVideoTracks()[0]; S.sharing = true;
  t.addEventListener('ended', stopShare);
  try { await S.vsender.replaceTrack(t); } catch (e) { stopShare(); return; }
  const v = $q('[data-call-local]'); if (v) { v.srcObject = new MediaStream([t]); }
  $q('[data-tile="self"]')?.classList.add('is-share');
  api(`/${S.call.id}/signal`, 'POST', { kind: 'media', data: { cam: true, share: true } });
  const lab = $q('[data-call-share-label]'); if (lab) { lab.textContent = 'Stop sharing'; }
}
async function stopShare() {
  if (!S.sharing) { return; }
  S.sharing = false; try { S.screen?.getTracks().forEach((t) => t.stop()); } catch (e) { /* ok */ } S.screen = null;
  try { await S.vsender?.replaceTrack(S.camOn ? sendTrack() : S.black); } catch (e) { /* ok */ }
  $q('[data-tile="self"]')?.classList.remove('is-share'); showSelf();
  if (S.call) { api(`/${S.call.id}/signal`, 'POST', { kind: 'media', data: { cam: S.camOn, share: false } }); }
  const lab = $q('[data-call-share-label]'); if (lab) { lab.textContent = 'Share screen'; }
}

function swapTiles() {
  const r = $q('[data-tile="remote"]'); const m = $q('[data-tile="self"]'); if (!r || !m) { return; }
  S.swapped = !S.swapped;
  [r, m].forEach((t) => { t.style.cssText = ''; });
  r.classList.toggle('ncv__tile--main', !S.swapped); r.classList.toggle('ncv__tile--pip', S.swapped);
  m.classList.toggle('ncv__tile--main', S.swapped); m.classList.toggle('ncv__tile--pip', !S.swapped);
  m.setAttribute('aria-label', S.swapped ? 'Swap the two pictures' : 'Swap the two pictures');
}

function wireVideo(el) {
  const $ = (s) => el.querySelector(s);
  const sheet = $('[data-beauty-sheet]'); const wand = $('[data-call-beauty]'); const more = $('[data-more-sheet]'); const moreBtn = $('[data-call-more]');
  const closeMenu = () => { more.hidden = true; moreBtn.setAttribute('aria-expanded', 'false'); el.classList.remove('is-menu'); $('[data-call-qicon]').hidden = true; $('[data-call-lock]').hidden = true; $('[data-call-spacer]').hidden = false; };
  const openMenu = () => { hideHint(true); more.hidden = false; moreBtn.setAttribute('aria-expanded', 'true'); el.classList.add('is-menu'); $('[data-call-qicon]').hidden = false; $('[data-call-lock]').hidden = false; $('[data-call-spacer]').hidden = true; $('[data-call-share]').hidden = !shareSupported() || !S.video; };
  moreBtn.addEventListener('click', () => (more.hidden ? openMenu() : closeMenu()));
  document.addEventListener('pointerdown', (e) => { if (!more.hidden && !e.target.closest('[data-more-sheet]') && !e.target.closest('[data-call-more]')) { closeMenu(); } });
  const rl = $('[data-react-list]');
  REACTIONS.forEach((e) => {
    const b = document.createElement('button'); b.type = 'button'; b.dataset.react = e; b.textContent = e; b.setAttribute('aria-label', REACTION_LABELS[e]);
    b.addEventListener('click', () => { floatReaction(e, true); closeMenu(); if (S.call) { api(`/${S.call.id}/signal`, 'POST', { kind: 'media', data: { cam: S.camOn, react: e } }); } });
    rl.appendChild(b);
  });
  $('[data-call-share]').addEventListener('click', () => { closeMenu(); (S.sharing ? stopShare() : startShare()); });
  $('[data-call-msg]').addEventListener('click', () => {
    closeMenu(); const id = S.call?.peer?.naara_id; el.classList.add('is-min');
    if (id) { const url = '/n2n/chat?to=' + encodeURIComponent(id); if (window.Livewire?.navigate) { window.Livewire.navigate(url); } else { location.href = url; } }
  });
  // Minimise to a small live window; tap it to come back.
  $('[data-call-minimize]').addEventListener('click', (e) => { e.stopPropagation(); closeMenu(); sheet.hidden = true; el.classList.add('is-min'); });
  el.addEventListener('click', (e) => { if (Date.now() - Number(el.dataset.dragged || 0) < 300) { return; } if (el.classList.contains('is-min') && !e.target.closest('[data-call-hangup-mini]')) { el.classList.remove('is-min'); el.style.cssText = ''; S.wake?.(); } });
  $('[data-call-hangup-mini]').addEventListener('click', (e) => { e.stopPropagation(); hangUp(); });

  $('[data-call-cam]').addEventListener('click', () => (S.camOn ? stopCamera() : startCamera()));
  $('[data-call-flip]').addEventListener('click', async (e) => {
    e.stopPropagation();
    S.facing = S.facing === 'user' ? 'environment' : 'user';
    S.beauty?.destroy(); S.beauty = null; try { S.cam?.getTracks().forEach((t) => t.stop()); } catch (er) { /* ok */ }
    S.cam = null; await startCamera();
  });
  const openBeauty = (on) => { sheet.hidden = !on; wand.setAttribute('aria-expanded', on ? 'true' : 'false'); if (on) { closeMenu(); drawLooks(); } };
  wand.addEventListener('click', (e) => { e.stopPropagation(); openBeauty(sheet.hidden); });
  $('[data-beauty-close]').addEventListener('click', () => openBeauty(false));
  $('[data-beauty-intensity]').addEventListener('input', (e) => { S.look.intensity = Number(e.target.value) / 100; S.beauty?.setIntensity(S.look.intensity); beautyRemember(S.look); });
  $('[data-call-answer-audio]').addEventListener('click', () => answerCall(false));

  // Speaker: on = you hear them. (A browser cannot route to the loudspeaker; this controls the call's sound.)
  $('[data-call-out]').addEventListener('click', (e) => {
    e.stopImmediatePropagation(); S.soundOff = !S.soundOff;
    if (S.audio) { S.audio.muted = S.soundOff; } ui.set(ui.phase);
  }, true);

  // First-time hint above More (only where screen sharing can work).
  const hint = $('[data-call-hint]');
  const hideHint = (remember) => { hint.hidden = true; if (remember) { try { localStorage.setItem('naara-call-hint-share', '1'); } catch (e) { /* private mode */ } } };
  $('[data-call-hint-x]').addEventListener('click', () => hideHint(true));
  S.showHint = () => { try { if (localStorage.getItem('naara-call-hint-share')) { return; } } catch (e) { /* ok */ } if (!shareSupported()) { return; } hint.hidden = false; setTimeout(() => { hint.hidden = true; }, 9000); };

  // Controls fade away on an active video call and come back on any tap or move.
  const wake = () => { el.classList.remove('is-idle-ui'); clearTimeout(S.idleUi); if (S.video && ui.phase === 'active') { S.idleUi = setTimeout(() => { if (sheet.hidden && more.hidden) { el.classList.add('is-idle-ui'); } }, 6000); } };
  ['pointerdown', 'pointermove', 'keydown'].forEach((ev) => el.addEventListener(ev, wake)); S.wake = wake;

  // Your tile (or theirs, when swapped) is the small one: tap it to swap, drag it out of the way.
  el.querySelector('.ncv').addEventListener('pointerdown', (e) => {
    const pip = e.target.closest('.ncv__tile--pip'); if (!pip || e.target.closest('.ncv__round')) { return; }
    const r = pip.getBoundingClientRect(); const drag = { dx: e.clientX - r.left, dy: e.clientY - r.top, x0: e.clientX, y0: e.clientY, moved: false };
    pip.setPointerCapture(e.pointerId);
    const mv = (ev) => {
      if (!drag.moved && Math.hypot(ev.clientX - drag.x0, ev.clientY - drag.y0) < 8) { return; }
      drag.moved = true; pip.style.insetInlineEnd = 'auto'; pip.style.insetBlockEnd = 'auto';
      pip.style.left = Math.min(innerWidth - pip.offsetWidth - 6, Math.max(6, ev.clientX - drag.dx)) + 'px'; pip.style.top = Math.min(innerHeight - pip.offsetHeight - 6, Math.max(6, ev.clientY - drag.dy)) + 'px';
    };
    const up = () => { pip.removeEventListener('pointermove', mv); pip.removeEventListener('pointerup', up); if (!drag.moved) { swapTiles(); } };
    pip.addEventListener('pointermove', mv); pip.addEventListener('pointerup', up);
  });

  // Gestures (Gestures blueprint, call screen). Swipe DOWN anywhere on the picture of an active video call to shrink it to the small window (the Minimise button does
  // the same); drag the small window to any corner, where it snaps to the nearest one; a tap on it still brings the call back. "Buttons only" turns both off.
  const MIN_PAD = 12;
  const shrink = (type) => { closeMenu(); sheet.hidden = true; el.classList.add('is-min'); el.style.cssText = ''; stat('call_minimize', 'committed', type); haptic(10); };
  let sw = null;
  el.addEventListener('pointerdown', (e) => {
    if (el.classList.contains('is-min') || gcfg().buttons_only || ui.phase !== 'active' || !S.video || e.pointerType === 'mouse') { return; }
    if (e.target.closest('button, a, input, .ncv__round, .ncv__tile--pip, [data-beauty-sheet], [data-more-sheet], .ncv__bar')) { return; }
    sw = { id: e.pointerId, x: e.clientX, y: e.clientY, t: Date.now(), type: e.pointerType };
  });
  el.addEventListener('pointerup', (e) => {
    if (!sw || sw.id !== e.pointerId) { return; }
    const dx = e.clientX - sw.x; const dy = e.clientY - sw.y; const dt = Math.max(1, Date.now() - sw.t); const type = sw.type; sw = null;
    if (dy > 110 && Math.abs(dy) > Math.abs(dx) * 1.6 && (dy / dt > 0.35 || dy > 160)) { shrink(type); }
  });
  el.addEventListener('pointercancel', () => { sw = null; });
  let mini = null;
  el.addEventListener('pointerdown', (e) => {
    if (!el.classList.contains('is-min') || gcfg().buttons_only || e.target.closest('[data-call-hangup-mini]')) { return; }
    const r = el.getBoundingClientRect(); mini = { id: e.pointerId, dx: e.clientX - r.left, dy: e.clientY - r.top, x0: e.clientX, y0: e.clientY, moved: false, type: e.pointerType };
    try { el.setPointerCapture(e.pointerId); } catch (_) { /* synthetic */ }
  });
  el.addEventListener('pointermove', (e) => {
    if (!mini || mini.id !== e.pointerId) { return; }
    if (!mini.moved && Math.hypot(e.clientX - mini.x0, e.clientY - mini.y0) < 8) { return; }
    mini.moved = true; el.style.transition = 'none'; el.style.inset = 'auto';
    el.style.left = Math.min(innerWidth - el.offsetWidth - MIN_PAD, Math.max(MIN_PAD, e.clientX - mini.dx)) + 'px';
    el.style.top = Math.min(innerHeight - el.offsetHeight - MIN_PAD, Math.max(MIN_PAD, e.clientY - mini.dy)) + 'px';
  });
  const dropMini = (e) => {
    if (!mini || mini.id !== e.pointerId) { return; }
    const m = mini; mini = null;
    if (!m.moved) { return; }
    const r = el.getBoundingClientRect(); const left = (r.left + r.width / 2) < innerWidth / 2; const top = (r.top + r.height / 2) < innerHeight / 2;
    el.style.transition = 'left .2s ease, top .2s ease';
    el.style.left = (left ? MIN_PAD : innerWidth - el.offsetWidth - MIN_PAD) + 'px';
    el.style.top = (top ? MIN_PAD + 56 : innerHeight - el.offsetHeight - MIN_PAD - 92) + 'px';
    el.dataset.miniCorner = (top ? 't' : 'b') + (left ? 'l' : 'r');
    stat('call_minimize', 'fallback', m.type);   // "fallback" here = moved the small window
    el.dataset.dragged = String(Date.now());      // swallows the click that follows a drag
  };
  el.addEventListener('pointerup', dropMini); el.addEventListener('pointercancel', dropMini);
  el.querySelector('[data-call-pip]').addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); swapTiles(); } });
}

function makePc(ice) {
  const pc = new RTCPeerConnection({ iceServers: ice || [], iceTransportPolicy: new URLSearchParams(location.search).get('relay') === '1' ? 'relay' : 'all' });
  pc.onicecandidate = (e) => { if (e.candidate && S.call) { api(`/${S.call.id}/signal`, 'POST', { kind: 'candidate', data: e.candidate.toJSON() }); } };
  pc.ontrack = (e) => {
    if (e.track.kind === 'video') {
      const rv = $q('[data-call-remote]'); if (rv) { rv.srcObject = e.streams[0] || new MediaStream([e.track]); rv.play().catch(() => {}); }
      return;
    }
    if (!S.audio) { S.audio = new Audio(); S.audio.autoplay = true; }
    S.audio.srcObject = e.streams[0]; S.audio.play().catch(() => {});
  };
  pc.oniceconnectionstatechange = () => {
    const st = pc.iceConnectionState;
    if (st === 'connected' || st === 'completed') { if (ui.phase !== 'active') { S.reached = true; S.startedAt = Date.now(); ui.set('active'); ui.clock(); signalMedia(); S.wake?.(); if (S.video) { setTimeout(() => S.showHint?.(), 1200); } } startStats(); }
    if (st === 'disconnected') { ui.set('reconnecting'); }
    if (st === 'failed') { restartIce(); }
  };
  return pc;
}

async function restartIce() {
  if (!S.pc || !S.call || S.call.role !== 'caller') { return; }   // only the caller re-offers: no glare
  ui.set('reconnecting');
  try {
    const r = await api(`/${S.call.id}/ice`, 'GET');
    if (r.ok && r.data.ice && S.pc.setConfiguration) { S.pc.setConfiguration({ ...S.pc.getConfiguration(), iceServers: r.data.ice }); }
    const offer = await S.pc.createOffer({ iceRestart: true });
    offer.sdp = tuneOpus(offer.sdp);
    await S.pc.setLocalDescription(offer);
    await api(`/${S.call.id}/signal`, 'POST', { kind: 'offer', data: { type: offer.type, sdp: offer.sdp } });
  } catch (e) { /* the heartbeat verdict ends the call if this never recovers */ }
}

function startStats() {
  if (S.stats) { return; }
  S.stats = setInterval(async () => {
    if (!S.pc) { return; }
    try {
      const rep = await S.pc.getStats();
      let rtt = null; let lost = 0; let recv = 0; let relay = false;
      const pairs = []; const cands = {};
      rep.forEach((r) => {
        if (r.type === 'candidate-pair' && (r.nominated || r.state === 'succeeded') && r.currentRoundTripTime !== undefined) { pairs.push(r); }
        if (r.type === 'local-candidate' || r.type === 'remote-candidate') { cands[r.id] = r; }
        if (r.type === 'inbound-rtp' && r.kind === 'audio') { lost = r.packetsLost || 0; recv = r.packetsReceived || 0; }
      });
      const sel = pairs.find((p) => p.selected) || pairs[0];
      if (sel) { rtt = sel.currentRoundTripTime; relay = [cands[sel.localCandidateId], cands[sel.remoteCandidateId]].some((c) => c && c.candidateType === 'relay'); }
      S.relay = S.relay || relay;
      const lossPct = recv + lost > 0 ? (lost / (recv + lost)) * 100 : 0;
      const q = rtt === null ? '' : (rtt < 0.25 && lossPct < 3 ? 'Good connection' : (rtt < 0.5 && lossPct < 8 ? 'Fair connection' : 'Weak connection'));
      ui.set(ui.phase, { quality: q, qkey: q.startsWith('Good') ? 'good' : (q.startsWith('Fair') ? 'fair' : 'weak') });
    } catch (e) { /* stats are a hint only */ }
  }, 3000);
}

async function flushPending() {
  for (const m of S.pending.splice(0)) { await handleSignal(m); }
}

// Laravel's TrimStrings middleware trims JSON strings, which drops the SDP's final CRLF; a description must end with one or the browser refuses to parse it.
function fixSdp(d) { return d && typeof d.sdp === 'string' && !d.sdp.endsWith('\n') ? { ...d, sdp: d.sdp + '\r\n' } : d; }

async function handleSignal(msg) {
  if (!S.pc || !S.call || msg.call_id !== S.call.id) { if (S.call && msg.call_id === S.call.id && msg.kind !== 'media') { S.pending.push(msg); } return; }
  try {
    if (msg.kind === 'media') {
      const d = msg.data || {};
      if (typeof d.react === 'string') { floatReaction(d.react, false); }
      if ('cam' in d) { S.peerCam = !!d.cam; const away = $q('[data-call-away]'); if (away) { away.hidden = S.peerCam; } }
      if ('share' in d) { $q('[data-tile="remote"]')?.classList.toggle('is-share', !!d.share); }
      return;
    }
    if (msg.kind === 'offer') {
      await S.pc.setRemoteDescription(fixSdp(msg.data)); S.remoteSet = true;
      const answer = await S.pc.createAnswer(); answer.sdp = tuneOpus(answer.sdp);
      await S.pc.setLocalDescription(answer);
      await api(`/${S.call.id}/signal`, 'POST', { kind: 'answer', data: { type: answer.type, sdp: answer.sdp } });
      await drainCandidates();
    } else if (msg.kind === 'answer') {
      await S.pc.setRemoteDescription(fixSdp(msg.data)); S.remoteSet = true; await drainCandidates();
    } else if (msg.kind === 'candidate') {
      if (S.remoteSet) { await S.pc.addIceCandidate(msg.data).catch(() => {}); } else { S.cand = (S.cand || []).concat([msg.data]); }
    }
  } catch (e) { console.warn('N2N call: signal failed', msg.kind, e && e.message); }   // a bad signal must not kill the call; the server's verdict does
}

async function drainCandidates() { for (const c of (S.cand || []).splice(0)) { await S.pc.addIceCandidate(c).catch(() => {}); } }

// ---------------------------------------------------------------------------------------------- lifecycle
function teardown(reason) {
  if (S.ended) { return; }
  S.ended = true;
  const reviewId = S.reached ? S.call?.id : null;   // only a call that actually connected is worth asking about
  clearInterval(S.hb); clearInterval(S.stats); clearInterval(S.timer); clearTimeout(S.ringTimer);
  S.hb = S.stats = S.timer = null;
  window.dispatchEvent(new CustomEvent('nx-ring-stop'));
  try { S.stream?.getTracks().forEach((t) => t.stop()); } catch (e) { /* ok */ }
  S.beauty?.destroy(); S.beauty = null; try { S.cam?.getTracks().forEach((t) => t.stop()); S.black?.stop(); } catch (e) { /* ok */ }
  ['[data-call-local]', '[data-call-remote]'].forEach((q) => { const v = $q(q); if (v) { v.srcObject = null; } });
  clearTimeout(S.idleUi); ui.el()?.classList.remove('is-idle-ui', 'is-min', 'is-menu'); try { S.screen?.getTracks().forEach((t) => t.stop()); } catch (e) { /* ok */ }
  const rt0 = $q('[data-tile="remote"]'); const st0 = $q('[data-tile="self"]'); if (rt0 && st0) { [rt0, st0].forEach((t) => { t.style.cssText = ''; t.classList.remove('is-share'); }); rt0.className = 'ncv__tile ncv__tile--main'; st0.className = 'ncv__tile ncv__tile--pip'; }
  try { S.pc?.close(); } catch (e) { /* ok */ }
  if (S.audio) { S.audio.srcObject = null; }
  const texts = { hangup: 'Call ended', declined: 'Call declined', no_answer: 'No answer', cancelled: 'Call cancelled', credit: 'Call ended: out of credit', peer_lost: 'Connection lost', busy: 'Busy', failed: 'Call failed' };
  ui.set('ended', { status: texts[reason] || 'Call ended', warn: '' });
  setTimeout(() => { ui.set('idle'); if (reviewId) { askReview(reviewId); } Object.assign(S, { reached: false, soundOff: false, swapped: false, sharing: false, screen: null, video: false, cam: null, camOn: false, vsender: null, black: null, out: null, peerCam: false, call: null, pc: null, stream: null, pending: [], remoteSet: false, cand: [], relay: false, muted: false, ended: false }); }, 2200);
}

function beat() {
  clearInterval(S.hb);
  S.hb = setInterval(async () => {
    if (!S.call) { return; }
    const r = await api(`/${S.call.id}/heartbeat`);
    if (!r.ok) { if (r.status === 404 || r.status === 410) { teardown('failed'); } return; }
    const d = r.data;
    if (!['active', 'ringing'].includes(d.state)) { teardown(d.reason || 'hangup'); return; }
    if (d.warn) { ui.set(ui.phase, { warn: `Low credit: this call ends in ${d.seconds_left}s.` }); } else if (ui.phase !== 'idle') { ui.set(ui.phase, { warn: '' }); }
  }, Math.max(3, S.cfg.heartbeat_s) * 1000);
}

async function preparePc(ice, withCamera = true) {
  S.stream = await getMic();
  if (!S.stream) { return false; }
  S.pc = makePc(ice);
  S.video = !!S.call?.video;
  S.out = new MediaStream();
  S.stream.getAudioTracks().forEach((t) => { S.out.addTrack(t); S.pc.addTrack(t, S.out); });
  if (S.video) {
    S.black = blackTrack();
    S.vsender = S.pc.addTrack(S.black, S.out);   // the video line is always open; the camera is swapped in when (and only if) the person turns it on
    const away = $q('[data-call-away]'); if (away) { away.hidden = false; }
    $q('[data-call-pip]')?.removeAttribute('style');
    if (withCamera) { await startCamera(); } else { showSelf(); }
    S.look = beautySaved(); drawLooks(); S.wake?.();
  }
  return true;
}

async function placeCall(handle, video = false) {
  if (S.call) { return; }
  S.ended = false;
  ui.set('calling', { name: 'Calling...', status: 'Calling...', warn: '' });
  const r = await api('', 'POST', { to: handle, video });
  if (!r.ok) {
    if (r.status === 402) { ui.set('error', { status: r.data.message, actions: r.data.actions }); setTimeout(() => ui.set('idle'), 15000); return; }
    ui.set('error', { status: r.data.message || 'We could not place this call.' }); setTimeout(() => ui.set('idle'), 4000); return;
  }
  S.call = r.data.call; S.cfg.heartbeat_s = r.data.heartbeat_s || 8;
  if (S.call.state === 'busy') { ui.set('ended', { name: S.call.peer.name, status: 'Busy' }); S.call = null; setTimeout(() => ui.set('idle'), 2500); return; }
  ui.set('calling', { name: S.call.peer.name || 'Calling...', video: !!S.call.video });
  if (!(await preparePc(r.data.ice))) { api(`/${S.call.id}/hangup`); S.call = null; setTimeout(() => ui.set('idle'), 5000); return; }
  beat();
}

async function answerCall(withCamera = true) {
  if (!S.call) { return; }
  window.dispatchEvent(new CustomEvent('nx-ring-stop'));
  ui.set('connecting');
  const r = await api(`/${S.call.id}/accept`);
  if (!r.ok) { teardown('failed'); return; }
  S.cfg.heartbeat_s = r.data.heartbeat_s || 8;
  if (!(await preparePc(r.data.ice, withCamera))) { api(`/${S.call.id}/hangup`); teardown('failed'); return; }
  beat();
  await flushPending();
}

async function declineCall() {
  if (!S.call) { return; }
  await api(`/${S.call.id}/decline`);
  teardown('declined');
}

async function hangUp() {
  if (!S.call) { return; }
  await api(`/${S.call.id}/hangup`, 'POST', { relay: S.relay });
  teardown('hangup');
}

function wire() {
  const el = ui.el();
  if (!el || el.dataset.wired) { return; }
  el.dataset.wired = '1';
  wireVideo(el);
  el.querySelector('[data-call-answer]').addEventListener('click', () => answerCall(true));
  el.querySelector('[data-call-decline]').addEventListener('click', declineCall);
  el.querySelector('[data-call-hangup]').addEventListener('click', hangUp);
  el.querySelector('[data-call-mute]').addEventListener('click', (e) => {
    S.muted = !S.muted; S.stream?.getAudioTracks().forEach((t) => { t.enabled = !S.muted; });
    e.currentTarget.setAttribute('aria-pressed', S.muted ? 'true' : 'false'); e.currentTarget.querySelector('span').textContent = S.muted ? 'Unmute' : 'Mute'; setIcon(e.currentTarget, S.muted ? 'mic-off' : 'mic');
  });
  const out = el.querySelector('[data-call-out]');
  out.addEventListener('click', async () => {
    try {
      const devs = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'audiooutput');
      if (!devs.length || !S.audio) { return; }
      S.outIdx = ((S.outIdx ?? -1) + 1) % devs.length;
      await S.audio.setSinkId(devs[S.outIdx].deviceId);
      out.querySelector('span').textContent = devs[S.outIdx].label || 'Speaker';
    } catch (e) { /* not supported here */ }
  });
}

// ---------------------------------------------------------------------------------------------- after-call rating (admin-controlled, Trustpilot invitation)
const Rv = { id: null, stars: 0 };
async function askReview(id) {
  const box = document.querySelector('[data-call-review]'); if (!box || !id) { return; }
  const r = await api(`/${id}/review`, 'GET');
  if (!r.ok || !r.data.show) { return; }
  Rv.id = id; Rv.stars = 0;
  box.querySelectorAll('[data-star]').forEach((b) => { b.classList.remove('is-on'); b.setAttribute('aria-checked', 'false'); });
  box.querySelector('[data-review-submit]').disabled = true;
  box.querySelector('[data-review-step="rate"]').hidden = false; box.querySelector('[data-review-step="thanks"]').hidden = true;
  box.hidden = false; box.querySelector('[data-star]').focus();
}
function wireReview() {
  const box = document.querySelector('[data-call-review]'); if (!box || box.dataset.wired) { return; }
  box.dataset.wired = '1';
  const close = () => { if (box.hidden) { return; } box.hidden = true; if (Rv.id && !Rv.rated) { api(`/${Rv.id}/review/dismiss`); } Rv.rated = false; };
  box.querySelectorAll('[data-review-close]').forEach((b) => b.addEventListener('click', close));
  box.querySelector('[data-review-scrim]').addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); } });
  const stars = [...box.querySelectorAll('[data-star]')];
  const pick = (n) => { Rv.stars = n; stars.forEach((b) => { const on = Number(b.dataset.star) <= n; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', Number(b.dataset.star) === n ? 'true' : 'false'); }); box.querySelector('[data-review-submit]').disabled = false; };
  stars.forEach((b, i) => { b.addEventListener('click', () => pick(Number(b.dataset.star))); b.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight' && stars[i + 1]) { stars[i + 1].focus(); pick(i + 2); } if (e.key === 'ArrowLeft' && stars[i - 1]) { stars[i - 1].focus(); pick(i); } }); });
  box.querySelector('[data-review-submit]').addEventListener('click', async (e) => {
    if (!Rv.stars || !Rv.id) { return; }
    e.currentTarget.disabled = true;
    const r = await api(`/${Rv.id}/review`, 'POST', { stars: Rv.stars });
    if (!r.ok) { close(); return; }
    Rv.rated = true;
    const link = box.querySelector('[data-review-link]'); link.href = r.data.review_url;
    const sup = box.querySelector('[data-review-support]'); sup.hidden = !r.data.support_url; if (r.data.support_url) { sup.href = r.data.support_url; }
    box.querySelector('[data-review-step="rate"]').hidden = true; box.querySelector('[data-review-step="thanks"]').hidden = false;
  });
  box.querySelector('[data-review-link]').addEventListener('click', () => { if (Rv.id) { api(`/${Rv.id}/review/opened`); } });
}

function boot() {
  if (!rt) { return; }
  wire(); wireReview();
  document.addEventListener('click', (e) => {
    const v = e.target.closest('[data-n2n-video-call]'); if (v) { e.preventDefault(); placeCall(v.getAttribute('data-n2n-video-call'), true); return; }
    const b = e.target.closest('[data-n2n-call]'); if (b) { e.preventDefault(); placeCall(b.getAttribute('data-n2n-call')); }
  });
  rt.subscribe('call.incoming', async (p) => {
    if (S.call) { api(`/${p.call_id}/decline`); return; }   // already in a call: the server also answers busy
    S.ended = false; S.call = { id: p.call_id, role: 'callee', state: 'ringing', video: !!p.video, peer: { name: p.name } };
    ui.set('ringing', { name: p.name || 'Someone', warn: '', status: p.video ? 'Incoming video call' : 'Incoming call', video: !!p.video });
    window.dispatchEvent(new CustomEvent('nx-ring-start', { detail: { key: p.tone || 'ringtone.default' } }));
    clearTimeout(S.ringTimer); S.ringTimer = setTimeout(() => { if (ui.phase === 'ringing') { teardown('no_answer'); } }, (p.ring_s || 40) * 1000);
  });
  rt.subscribe('call.accepted', async (p) => {
    if (!S.call || p.call_id !== S.call.id || !S.pc) { return; }
    ui.set('connecting');
    const offer = await S.pc.createOffer(); offer.sdp = tuneOpus(offer.sdp);
    await S.pc.setLocalDescription(offer);
    await api(`/${S.call.id}/signal`, 'POST', { kind: 'offer', data: { type: offer.type, sdp: offer.sdp } });
  });
  rt.subscribe('call.signal', (p) => handleSignal(p));
  rt.subscribe('call.ended', (p) => { if (S.call && p.call_id === S.call.id) { teardown(p.reason); } });
  // Resume an incoming-call deep link from a web-push tap: /n2n/chat?call=ID (the realtime catch-up delivers call.incoming if still ringing).
}

if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
