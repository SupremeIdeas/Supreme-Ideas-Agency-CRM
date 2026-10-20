/**
 * N2N realtime client (MASTER-ONLY). The ONLY place a page talks to the realtime layer: no feature opens its own WebSocket or its own
 * poll loop. It asks /n2n/realtime/config once, then runs ONE transport:
 *   reverb -> Laravel Echo over WebSocket (lazy-loaded), and on every (re)connect it replays missed events from the log;
 *   poll   -> one adaptive loop against /n2n/realtime/sync (fast while the tab is visible, slow when hidden, exponential back-off on errors).
 * Both deliver the same envelope {id, type, payload, at}, once and in order (de-duplicated by id).
 *
 *   const rt = window.N2NRealtime;  rt.subscribe('message.new', (payload, env) => {...});  rt.start();
 */
const subs = new Map();
let state = { started: false, cursor: 0, timer: null, errors: 0, cfg: null, echo: null, stopped: false };
const base = '/n2n/realtime';

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }

async function getJson(url, opts = {}) {
  const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, ...opts });
  if (!r.ok) { const e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
  return r.json();
}

function deliver(env) {
  if (!env || typeof env.id !== 'number') { return; }
  if (env.id !== 0) { // id 0 = ephemeral (typing): never part of the cursor
    if (env.id <= state.cursor) { return; }
    state.cursor = env.id;
  }
  for (const key of [env.type, '*']) {
    (subs.get(key) || []).forEach((cb) => { try { cb(env.payload, env); } catch (e) { /* a bad subscriber must not stop the stream */ } });
  }
}

async function syncOnce() {
  const batch = await getJson(`${base}/sync?cursor=${state.cursor}`);
  batch.events.forEach(deliver);
  if (batch.cursor > state.cursor) { state.cursor = batch.cursor; }
  return batch.has_more;
}

function nextDelay() {
  const p = state.cfg.poll;
  if (state.errors > 0) { return Math.min(p.max_backoff_ms, p.visible_ms * 2 ** Math.min(state.errors, 6)); }
  return document.visibilityState === 'visible' ? p.visible_ms : p.hidden_ms;
}

function schedule(ms) {
  clearTimeout(state.timer);
  if (state.stopped) { return; }
  state.timer = setTimeout(tick, ms);
}

async function tick() {
  try {
    const more = await syncOnce();
    state.errors = 0;
    schedule(more ? 0 : nextDelay());
  } catch (e) {
    if (e.status === 401 || e.status === 403 || e.status === 404) { state.stopped = true; return; } // signed out, or N2N switched off
    state.errors += 1;
    schedule(nextDelay());
  }
}

async function startReverb() {
  const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);
  window.Pusher = Pusher;
  const r = state.cfg.reverb;
  state.echo = new Echo({
    broadcaster: 'reverb', key: r.key, wsHost: r.host, wsPort: r.port, wssPort: r.port, forceTLS: r.scheme === 'https', enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth', auth: { headers: { 'X-CSRF-TOKEN': csrf() } },
  });
  state.echo.private(r.channel.replace(/^private-/, '')).listen(r.event, deliver);
  // Replay everything missed while disconnected, on every (re)connect.
  const conn = state.echo.connector?.pusher?.connection;
  if (conn) { conn.bind('connected', () => { syncOnce().catch(() => {}); }); }
  // Safety net: a slow poll still runs, so a silently dead socket cannot strand the user.
  state.cfg.poll.visible_ms = Math.max(state.cfg.poll.visible_ms, 15000);
  state.cfg.poll.hidden_ms = Math.max(state.cfg.poll.hidden_ms, 60000);
  schedule(state.cfg.poll.visible_ms);
}

const api = {
  subscribe(type, cb) { if (!subs.has(type)) { subs.set(type, []); } subs.get(type).push(cb); return () => { subs.set(type, subs.get(type).filter((f) => f !== cb)); }; },
  async start() {
    if (state.started) { return; }
    state.started = true;
    try { state.cfg = await getJson(`${base}/config`); } catch (e) { state.started = false; return; }
    if (state.cfg.driver === 'null') { return; }
    state.cursor = state.cfg.cursor || 0;
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && !state.stopped) { schedule(0); } });
    if (state.cfg.driver === 'reverb') { try { await startReverb(); return; } catch (e) { /* fall back to polling */ } }
    schedule(0);
  },
  typing(to, context = '') { return getJson(`${base}/typing`, { method: 'POST', body: JSON.stringify({ to, context }), headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } }).catch(() => {}); },
  ping() { return getJson(`${base}/ping`, { method: 'POST' }).catch(() => {}); },
  _state: () => ({ cursor: state.cursor, errors: state.errors, driver: state.cfg?.driver }),
};

window.N2NRealtime = window.N2NRealtime || api;
export default window.N2NRealtime;
