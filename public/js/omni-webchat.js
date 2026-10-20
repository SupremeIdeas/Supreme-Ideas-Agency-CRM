/*! Naara web chat widget (Naara Respond). Embed: <script async src="https://YOUR-NAARA-DOMAIN/js/omni-webchat.js" data-site="SITE-KEY"></script>
   One file, no dependencies. Talks only to its own Naara install (the origin this script was loaded from). The visitor token lives in this browser's localStorage. */
(function () {
  'use strict';
  var me = document.currentScript || document.querySelector('script[data-site][src*="omni-webchat"]');
  if (!me) return;
  var site = me.getAttribute('data-site');
  if (!site || !/^[0-9a-f-]{36}$/i.test(site)) return;
  var base = new URL(me.src).origin + '/omni/webchat/' + site;
  var key = 'naara_wc_' + site, token = null, cfg = null, lastId = 0, open = false, timer = null, askedContact = false, seen = {};
  try { token = localStorage.getItem(key); } catch (e) {}

  function api(path, opts) {
    opts = opts || {};
    var h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) h.Authorization = 'Bearer ' + token;
    return fetch(base + path, { method: opts.method || 'GET', headers: h, body: opts.body ? JSON.stringify(opts.body) : undefined, mode: 'cors' }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); });
  }
  function uid() { return (window.crypto && crypto.randomUUID) ? crypto.randomUUID().replace(/-/g, '') : String(Date.now()) + Math.random().toString(16).slice(2, 12); }

  var host = document.createElement('div');
  host.style.cssText = 'position:fixed;z-index:2147483000;bottom:16px;right:16px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;';
  var root = host.attachShadow({ mode: 'open' });
  root.innerHTML = '<style>' +
    ':host{all:initial}*{box-sizing:border-box}' +
    '.fab{width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;color:#fff;background:var(--c,#0A6E6E);box-shadow:0 6px 20px rgba(0,0,0,.25);display:grid;place-items:center}' +
    '.fab svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}' +
    '.panel{position:absolute;bottom:68px;right:0;width:340px;max-width:calc(100vw - 32px);height:460px;max-height:calc(100vh - 110px);background:#fff;color:#0D1B2A;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden}' +
    '.panel.on{display:flex}' +
    '.head{background:var(--c,#0A6E6E);color:#fff;padding:14px 16px;font-weight:700;font-size:15px;display:flex;justify-content:space-between;align-items:center}' +
    '.x{background:none;border:0;color:#fff;cursor:pointer;font-size:20px;line-height:1}' +
    '.msgs{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:8px;background:#f4f7f8}' +
    '.m{max-width:80%;padding:8px 12px;border-radius:14px;font-size:14px;line-height:1.35;white-space:pre-wrap;word-wrap:break-word}' +
    '.m.v{align-self:flex-end;background:var(--c,#0A6E6E);color:#fff;border-bottom-right-radius:4px}' +
    '.m.t{align-self:flex-start;background:#fff;color:#0D1B2A;border:1px solid #dfe6e9;border-bottom-left-radius:4px}' +
    '.m.err{align-self:center;background:#fdecea;color:#8a1f11;font-size:12px}' +
    '.who{display:none;padding:8px 12px;gap:6px;border-top:1px solid #e3e8ec;background:#fff}.who.on{display:flex;flex-direction:column}' +
    '.who input,.row input{border:1px solid #cfd8dc;border-radius:10px;padding:9px 11px;font-size:14px;width:100%;font-family:inherit}' +
    '.row{display:flex;gap:8px;padding:10px;border-top:1px solid #e3e8ec;background:#fff}' +
    '.send{border:0;border-radius:10px;padding:0 14px;color:#fff;background:var(--c,#0A6E6E);font-weight:700;cursor:pointer}.send[disabled]{opacity:.5;cursor:default}' +
    '.hp{position:absolute;left:-9999px;opacity:0;height:0;width:0}' +
    '@media (prefers-color-scheme:dark){.panel{background:#0f1c2b;color:#e8eef2}.msgs{background:#0b1623}.m.t{background:#16283b;color:#e8eef2;border-color:#243a52}.who,.row{background:#0f1c2b;border-color:#243a52}.who input,.row input{background:#16283b;color:#e8eef2;border-color:#2c4663}}' +
    '</style>' +
    '<button class="fab" type="button" aria-label="Open chat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.8 7L3 21l2-5.2A8 8 0 1 1 21 12z"/></svg></button>' +
    '<section class="panel" role="dialog" aria-label="Chat"><div class="head"><span class="title">Chat</span><button class="x" type="button" aria-label="Close chat">&times;</button></div>' +
    '<div class="msgs" role="log" aria-live="polite"></div>' +
    '<div class="who"><input class="nm" placeholder="Your name (optional)" autocomplete="name" maxlength="120"><input class="em" type="email" placeholder="Your email (optional)" autocomplete="email" maxlength="190"></div>' +
    '<form class="row"><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><input class="txt" placeholder="Type a message" maxlength="2000" aria-label="Message"><button class="send" type="submit">Send</button></form></section>';
  var $ = function (s) { return root.querySelector(s); };
  var fab = $('.fab'), panel = $('.panel'), msgs = $('.msgs'), form = $('.row'), txt = $('.txt'), who = $('.who'), sendBtn = $('.send');

  function add(cls, text, id) {
    if (id) { if (seen[id]) return; seen[id] = 1; }
    var d = document.createElement('div'); d.className = 'm ' + cls; d.textContent = text; msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight;
  }
  function start() {
    return api('/session', { method: 'POST' }).then(function (r) {
      if (!r.ok) return false;
      token = r.json.token; cfg = r.json; try { localStorage.setItem(key, token); } catch (e) {}
      host.style.setProperty('--c', cfg.color || '#0A6E6E'); fab.style.setProperty('--c', cfg.color || '#0A6E6E'); panel.style.setProperty('--c', cfg.color || '#0A6E6E');
      $('.title').textContent = cfg.title || 'Chat';
      if (cfg.ask_email) who.classList.add('on');
      return true;
    }).catch(function () { return false; });
  }
  function poll() {
    if (!token) return;
    api('/messages?after=' + lastId).then(function (r) {
      if (r.status === 401) { token = null; try { localStorage.removeItem(key); } catch (e) {} return; }
      (r.json.messages || []).forEach(function (m) { lastId = Math.max(lastId, m.id); if (m.from === 'team') add('t', m.text, 'm' + m.id); else seen['m' + m.id] = 1; });
    }).catch(function () {});
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(function () { poll(); schedule(); }, document.hidden || !open ? 30000 : 4000); }
  function toggle(on) {
    open = on; panel.classList.toggle('on', on); fab.setAttribute('aria-label', on ? 'Close chat' : 'Open chat');
    if (on) {
      (token && cfg ? Promise.resolve(true) : start()).then(function (ok) {
        if (!ok) { add('err', 'Chat is not available right now.'); return; }
        if (!msgs.children.length) add('t', cfg.greeting || 'Hi! How can we help?');
        poll(); txt.focus();
      });
    }
    schedule();
  }
  fab.addEventListener('click', function () { toggle(!open); });
  $('.x').addEventListener('click', function () { toggle(false); });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = txt.value.trim(); if (!text || sendBtn.disabled) return;
    sendBtn.disabled = true; var mid = uid(), body = { text: text, mid: mid, website: form.querySelector('.hp').value };
    if (!askedContact) { var n = $('.nm').value.trim(), em = $('.em').value.trim(); if (n) body.name = n; if (em) body.email = em; }
    api('/messages', { method: 'POST', body: body }).then(function (r) {
      if (r.ok) { add('v', text); txt.value = ''; if (body.name || body.email) { askedContact = true; who.classList.remove('on'); } }
      else if (r.status === 401) { token = null; start().then(function () { add('err', 'Please send that again.'); }); }
      else add('err', r.status === 429 ? 'You are sending too fast. Wait a moment.' : 'That message could not be sent.');
    }).catch(function () { add('err', 'No connection. Try again.'); }).then(function () { sendBtn.disabled = false; txt.focus(); });
  });
  document.addEventListener('visibilitychange', schedule);
  function mount() { document.body.appendChild(host); }
  if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount);
})();
