// Live location in a Naara chat (MASTER-ONLY). Viewer: polls the server for the other person's latest point while the card is on screen. Sharer: while this
// tab is open, watches the device position and reports it (at most every 15 s); a share that has ended (time up, stopped, blocked) stops the watcher at once.
// Coordinates are fetched on demand from an authenticated endpoint and are never written into the page or the message text.
(function () {
  if (window.__n2nLive) { return; }
  window.__n2nLive = true;
  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const J = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '' };
  const hdr = () => Object.assign({}, J, { 'X-CSRF-TOKEN': csrf() });
  const watchers = new Map();   // message id -> { wid, last }
  const ago = (iso) => { const s = Math.max(0, Math.round((Date.now() - Date.parse(iso)) / 1000)); return s < 20 ? 'just now' : s < 90 ? s + ' seconds ago' : Math.round(s / 60) + ' minutes ago'; };
  const left = (iso) => { const m = Math.max(0, Math.round((Date.parse(iso) - Date.now()) / 60000)); return m >= 90 ? Math.round(m / 60) + ' hours left' : m + (m === 1 ? ' minute left' : ' minutes left'); };
  const stopWatch = (id) => { const w = watchers.get(id); if (w && w.wid != null) { navigator.geolocation.clearWatch(w.wid); } watchers.delete(id); };

  async function read(card) {
    const id = card.dataset.liveId;
    let r;
    try { r = await fetch('/n2n/api/live/' + id, { headers: hdr(), credentials: 'same-origin' }); } catch (_) { return; }
    if (!r.ok) { card.dataset.liveState = 'gone'; setEnded(card); return; }
    const d = await r.json();
    const st = card.querySelector('[data-live-status]'); const open = card.querySelector('[data-live-open]'); const stop = card.querySelector('[data-live-stop]'); const note = card.querySelector('[data-live-note]');
    if (!d.active) { card.dataset.liveState = 'ended'; setEnded(card); stopWatch(id); return; }
    card.dataset.liveState = 'on';
    card.classList.add('is-on');
    st.textContent = d.mine ? 'Sharing with them · ' + left(d.expires_at) : 'Updated ' + ago(d.updated_at) + ' · ' + left(d.expires_at);
    if (!d.mine) {
      open.hidden = false; open.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(d.lat + ',' + d.lng);
    } else {
      if (stop) { stop.hidden = false; } if (note) { note.hidden = false; }
      startWatch(id);
    }
  }

  function setEnded(card) {
    card.classList.remove('is-on'); card.classList.add('is-ended');
    const st = card.querySelector('[data-live-status]'); if (st) { st.textContent = 'Live location ended'; }
    ['data-live-open', 'data-live-stop', 'data-live-note'].forEach((a) => { const e = card.querySelector('[' + a + ']'); if (e) { e.hidden = true; } });
  }

  function startWatch(id) {
    if (watchers.has(id) || !navigator.geolocation) { return; }
    const w = { wid: null, last: 0 };
    watchers.set(id, w);
    w.wid = navigator.geolocation.watchPosition((p) => {
      if (Date.now() - w.last < 15000) { return; }
      w.last = Date.now();
      fetch('/n2n/api/live/' + id + '/update', { method: 'POST', headers: hdr(), credentials: 'same-origin', body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy }) })
        .then((r) => r.ok ? r.json() : { active: false }).then((d) => { if (!d.active) { stopWatch(id); } }).catch(() => {});
    }, () => { stopWatch(id); }, { enableHighAccuracy: false, maximumAge: 10000, timeout: 20000 });
  }

  document.addEventListener('click', (e) => {
    const b = e.target.closest && e.target.closest('[data-live-stop]'); if (!b) { return; }
    const card = b.closest('[data-live-id]'); const id = card.dataset.liveId; b.disabled = true;
    fetch('/n2n/api/live/' + id + '/stop', { method: 'POST', headers: hdr(), credentials: 'same-origin' }).finally(() => { stopWatch(id); setEnded(card); card.dataset.liveState = 'ended'; });
  });

  const tick = () => { document.querySelectorAll('[data-live-id]').forEach((c) => { if (c.dataset.liveState !== 'ended' && c.dataset.liveState !== 'gone') { read(c); } }); };
  const start = () => { tick(); setInterval(() => { if (!document.hidden) { tick(); } }, 15000); };
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
  // a card that arrives (new message, thread switch) is read straight away
  new MutationObserver((muts) => { for (const m of muts) { m.addedNodes.forEach((n) => { if (n.nodeType === 1) { const cs = n.matches && n.matches('[data-live-id]') ? [n] : Array.from(n.querySelectorAll ? n.querySelectorAll('[data-live-id]') : []); cs.forEach((c) => { if (!c.dataset.liveState) { read(c); } }); } }); } }).observe(document.body, { childList: true, subtree: true });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); } });
})();
