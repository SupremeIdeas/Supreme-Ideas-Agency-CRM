/*! Naara Widgets SDK v3 - vanilla JS, no dependencies, no eval, no inline script injection.
 *  <script src="https://YOUR-NAARA-HOST/widgets/merchant-widget.js" data-naara-key="wk_live_..." defer></script>
 *  <div id="naara-widget"></div>
 *  Optional data-*: data-product-type="esim|giftcard", data-theme-color="#0A6E6E", data-theme="light|dark|auto",
 *  data-mode="modal|redirect", data-target="#css-selector", data-account="on|off" (the shopper "My orders" area), data-start="account" (open on My orders).
 *  Product types: esim, number (verification numbers), giftcard. The only credential here is the PUBLIC key: it identifies, never authorises -
 *  the server checks this page's Origin against the key's domain allowlist on every call. Styled in the Naara "Clay" skin. */
(function () {
  'use strict';
  var script = document.currentScript || document.querySelector('script[data-naara-key]');
  if (!script) { return; }
  var KEY = script.getAttribute('data-naara-key') || '';
  if (!/^wk_[A-Za-z0-9_]+$/.test(KEY)) { if (window.console) { console.warn('[Naara] missing or malformed data-naara-key'); } return; }
  var BASE;
  try { BASE = new URL(script.src).origin; } catch (e) { return; }
  var API = BASE + '/api/v1/merchant-widget';
  var KIND = script.getAttribute('data-product-type') || '';
  var ACCOUNT_ATTR = script.getAttribute('data-account') !== 'off';
  var START = script.getAttribute('data-start') === 'account' ? 'account' : 'shop';   // a dedicated "My orders" page opens straight on the account area
  var MODE = script.getAttribute('data-mode') === 'redirect' ? 'redirect' : 'modal';
  var ATTR_COLOR = script.getAttribute('data-theme-color') || '';
  var ATTR_THEME = script.getAttribute('data-theme') || 'auto';
  var STORE = 'naara_widget_pending';
  var TOKEN_STORE = 'naara_widget_shopper:' + KEY;   // the shopper's sign-in token: this key, this browser, expires with the session
  var PAGE = 9;          // plans shown before "Show more"
  var SEARCH_FROM = 8;   // a search box appears once there are this many plans to wade through

  function safeColor(c) { return /^#[0-9a-fA-F]{6}$/.test(c || '') ? c : ''; }
  function isHttps(u) { try { var x = new URL(u); return x.protocol === 'https:' || (x.protocol === 'http:' && x.hostname === 'localhost'); } catch (e) { return false; } }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text != null) { n.textContent = text; } return n; }
  // Readable text on the merchant's accent: a pale accent must not get white text.
  function ink(hex) {
    var h = hex.replace('#', ''), c = [0, 2, 4].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return (0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]) > 0.4 ? '#0d1b2a' : '#ffffff';
  }
  var names; try { names = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { names = null; }
  function country(code) { if (names && /^[A-Za-z]{2}$/.test(code)) { try { return names.of(code.toUpperCase()) || code; } catch (e) {} } return code; }
  function uuid() {
    var a = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }
  function api(path, opts) {
    opts = opts || {};
    var headers = { 'X-Naara-Widget-Key': KEY, 'Accept': 'application/json' };
    if (opts.token) { headers['X-Naara-Shopper'] = opts.token; }
    if (opts.body) { headers['Content-Type'] = 'application/json'; }
    return fetch(API + path, { method: opts.method || 'GET', headers: headers, credentials: 'omit', body: opts.body ? JSON.stringify(opts.body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(j.message || (j.errors && j.errors.email && j.errors.email[0]) || 'Request failed'); e.status = r.status; throw e; } return j; }); });
  }
  // Local storage can be blocked (private mode, strict settings): every use is wrapped and the widget works without it.
  function lsGet(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
  function lsDel(k) { try { window.localStorage.removeItem(k); } catch (e) {} }
  function when(iso) { try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return iso || ''; } }
  // Best guess of the shopper's own country, used only to pre-select a list entry. Nothing is sent anywhere.
  function guessCountry() {
    try { var l = (navigator.language || '').split('-')[1]; if (l) { return l.toUpperCase(); } } catch (e) {}
    return '';
  }
  // Fire-and-forget funnel beacon: never blocks render, never throws. sendBeacon cannot set headers, so the public key rides in the body.
  function beacon(type, meta) {
    try {
      var body = JSON.stringify({ key: KEY, type: type, meta: meta || {} });
      // fetch with credentials omitted, not sendBeacon: a JSON beacon is sent WITH credentials, which fails the cross-origin preflight on a merchant's site and logs a console error.
      fetch(API + '/events', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'omit' }).catch(function () {});
    } catch (e) {}
  }

  var LIGHT = '--link:var(--brand);--bg:#e8eef3;--fg:#0d1b2a;--muted:#43566a;--field:#eef3f7;--ok:#067647;--bad:#b42318;--card:linear-gradient(145deg,#fff,#eef3f7);' +
    '--raise:10px 12px 24px -10px rgba(10,36,49,.28),-8px -8px 18px #fff,inset 2px 2px 4px #fff,inset -3px -3px 6px rgba(10,36,49,.06);' +
    '--press:inset 3px 4px 8px rgba(10,36,49,.14),inset -3px -3px 7px #fff;--soft:6px 8px 16px -8px rgba(10,36,49,.22),-5px -5px 12px #fff;';
  var DARK = '--link:color-mix(in srgb,var(--brand) 50%,#fff);--bg:#0b1624;--fg:#f0f4f8;--muted:#a9bdd1;--field:#0c1a29;--ok:#4ade9a;--bad:#ff9a8f;--card:linear-gradient(145deg,#173048,#0f2133);' +
    '--raise:10px 12px 26px -8px rgba(0,0,0,.55),-6px -6px 16px rgba(255,255,255,.05),inset 2px 2px 4px rgba(255,255,255,.07),inset -3px -3px 7px rgba(0,0,0,.25);' +
    '--press:inset 3px 4px 8px rgba(0,0,0,.4),inset -3px -3px 7px rgba(255,255,255,.05);--soft:6px 8px 16px -8px rgba(0,0,0,.5),-4px -4px 10px rgba(255,255,255,.04);';

  var CSS = ':host{all:initial;display:block;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--fg)}' +
    '.w{--brand:#0A6E6E;--on-brand:#fff;' + LIGHT + 'background:var(--bg);color:var(--fg);border-radius:30px;padding:18px;box-sizing:border-box}' +
    '.w.dark{' + DARK + '}@media(prefers-color-scheme:dark){.w.auto{' + DARK + '}}' +
    '.w *{box-sizing:border-box}svg{width:1.1em;height:1.1em;flex:none;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}' +
    ':focus-visible{outline:3px solid var(--brand);outline-offset:2px}' +
    '.h{font-weight:800;font-size:1.2rem;margin:0}.sub{color:var(--muted);font-size:.88rem;margin:2px 0 0}' +
    '.hero{position:relative;margin:6px 0 14px;border-radius:24px;overflow:hidden;background:linear-gradient(135deg,color-mix(in srgb,var(--brand) 16%,var(--bg)),color-mix(in srgb,var(--brand) 4%,var(--bg)));box-shadow:var(--press)}' +
    '.slides{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;scroll-behavior:smooth}.slides::-webkit-scrollbar{display:none}' +
    '.slide{flex:0 0 100%;scroll-snap-align:start;display:flex;align-items:center;gap:14px;padding:18px 18px 34px;box-sizing:border-box;min-width:0}' +
    '.slide .tx{flex:1;min-width:0}.slide .sart{width:38%;max-width:170px;height:auto;flex:none}' +
    '.dots{position:absolute;left:18px;bottom:12px;display:flex;gap:6px}.dots button{width:8px;height:8px;border-radius:99px;border:0;padding:0;background:color-mix(in srgb,var(--fg) 25%,transparent);cursor:pointer;transition:width .2s}.dots button[aria-current=true]{width:22px;background:var(--brand)}' +
    '.cta2{margin-top:10px;border:0;border-radius:999px;padding:8px 16px;font:inherit;font-weight:700;font-size:.82rem;background:var(--brand);color:var(--on-brand);cursor:pointer}' +
    '.how{display:flex;gap:8px;margin:14px 0;padding:0;list-style:none;font-size:.8rem;color:var(--muted)}' +
    '.how li{flex:1;display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:16px;background:var(--field);box-shadow:var(--press)}' +
    '.how b{display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:var(--brand);color:var(--on-brand);font-size:.75rem;flex:none}' +
    '.tabs{display:flex;gap:8px;margin:0 0 12px;flex-wrap:wrap}' +
    '.tab{border:0;background:var(--card);color:var(--fg);border-radius:999px;padding:9px 18px;font:inherit;font-weight:600;cursor:pointer;box-shadow:var(--soft)}' +
    '.tab[aria-selected=true]{background:var(--brand);color:var(--on-brand);box-shadow:inset 0 3px 6px rgba(255,255,255,.28),inset 0 -4px 8px rgba(0,0,0,.2)}' +
    '.find{position:relative;margin:0 0 12px}.find svg{position:absolute;left:14px;top:50%;margin-top:-.55em;color:var(--muted)}' +
    '.find input{width:100%;padding:12px 14px 12px 38px;border-radius:999px;border:0;background:var(--field);box-shadow:var(--press);color:var(--fg);font:inherit}' +
    '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px}' +
    '.card{background:var(--card);border-radius:26px;padding:16px;display:flex;flex-direction:column;gap:8px;box-shadow:var(--raise)}' +
    '.top{display:flex;align-items:center;justify-content:space-between;gap:8px}.big{font-size:1.5rem;font-weight:800;line-height:1.1}' +
    '.pill{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;padding:3px 10px;border-radius:999px;background:var(--field);box-shadow:var(--press);white-space:nowrap}' +
    '.name{font-weight:600}.meta{color:var(--muted);font-size:.85rem}.price{font-size:1.3rem;font-weight:800}.sp{flex:1}' +
    '.logo{height:34px;max-width:110px;object-fit:contain;align-self:flex-start}' +
    '.dens{display:flex;flex-wrap:wrap;gap:8px}.den{border:0;border-radius:999px;padding:7px 13px;font:inherit;font-size:.88rem;font-weight:600;cursor:pointer;color:var(--fg);background:var(--card);box-shadow:var(--soft)}' +
    '.den[aria-pressed=true]{box-shadow:var(--press);outline:2px solid var(--brand)}' +
    'select,.amt{width:100%;padding:11px 14px;border-radius:18px;border:0;background:var(--field);box-shadow:var(--press);color:var(--fg);font:inherit}' +
    '.buy{display:flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:999px;color:var(--on-brand);padding:12px;font:inherit;font-weight:800;cursor:pointer;background:var(--brand);' +
    'background:linear-gradient(180deg,color-mix(in srgb,var(--brand) 82%,#fff),var(--brand) 55%,color-mix(in srgb,var(--brand) 88%,#000));' +
    'box-shadow:inset 0 4px 8px rgba(255,255,255,.3),inset 0 -5px 10px rgba(0,0,0,.22),0 10px 18px -8px color-mix(in srgb,var(--brand) 70%,transparent)}' +
    '.buy[disabled]{opacity:.65;cursor:wait}.buy.off{opacity:.55;cursor:not-allowed}' +
    '.spin{width:1em;height:1em;border:2.5px solid currentColor;border-right-color:transparent;border-radius:50%;animation:sp .8s linear infinite}@keyframes sp{to{transform:rotate(360deg)}}' +
    '.more{display:block;margin:16px auto 0;border:0;border-radius:999px;padding:10px 22px;font:inherit;font-weight:700;cursor:pointer;color:var(--fg);background:var(--card);box-shadow:var(--soft)}' +
    '.kinds{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}' +
    '.kind{border:0;text-align:left;background:var(--card);color:var(--fg);border-radius:22px;padding:12px 14px;font:inherit;cursor:pointer;box-shadow:var(--soft);display:flex;flex-direction:column;gap:2px}' +
    '.kind b{font-size:.95rem}.kind span{font-size:.78rem;color:var(--muted);line-height:1.35}' +
    '.kind[aria-selected=true]{background:var(--brand);box-shadow:inset 0 3px 6px rgba(255,255,255,.28),inset 0 -4px 8px rgba(0,0,0,.2)}.kind[aria-selected=true] b,.kind[aria-selected=true] span{color:var(--on-brand)}' +
    '.bar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:0 0 12px}' +
    '.sort{width:auto;padding:8px 14px;font-size:.85rem}' +
    '.acct{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:8px 14px;font:inherit;font-size:.85rem;font-weight:700;cursor:pointer;color:var(--fg);background:var(--card);box-shadow:var(--soft)}' +
    '.pick{display:grid;gap:12px;max-width:520px}.pick label{display:grid;gap:6px;font-weight:600;font-size:.88rem}' +
    '.svc{display:flex;flex-wrap:wrap;gap:8px}' +
    '.quote{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-radius:20px;background:var(--field);box-shadow:var(--press)}' +
    '.quote .price{margin:0}.how2{font-size:.82rem;color:var(--muted);margin:0}' +
    '.num{font-family:ui-monospace,monospace;font-size:1.35rem;font-weight:800;letter-spacing:.02em}' +
    '.code{font-family:ui-monospace,monospace;font-size:2rem;font-weight:800;letter-spacing:.18em;color:var(--ok)}' +
    '.row{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}' +
    '.mini{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:7px 13px;font:inherit;font-size:.82rem;font-weight:700;cursor:pointer;color:var(--fg);background:var(--card);box-shadow:var(--soft);text-decoration:none}' +
    '.mini.bad{color:var(--bad)}' +
    '.ord{display:grid;gap:4px;padding:14px;border-radius:22px;background:var(--card);box-shadow:var(--raise);margin-bottom:12px}' +
    '.badge{display:inline-block;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:2px 9px;border-radius:999px;background:var(--field);box-shadow:var(--press)}' +
    '.badge.ok{color:var(--ok)}.badge.bad{color:var(--bad)}' +
    '.frm{display:grid;gap:10px;max-width:420px}.frm input{width:100%;padding:12px 16px;border-radius:999px;border:0;background:var(--field);box-shadow:var(--press);color:var(--fg);font:inherit}' +
    '.frm input.otp{letter-spacing:.4em;text-align:center;font-weight:800}' +
    '.fine{font-size:.78rem;color:var(--muted);margin:0}' +
    '.by{margin-top:16px;text-align:center;font-size:.78rem;color:var(--muted)}.by a{color:inherit;font-weight:700}' +
    '.msg{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border-radius:22px;background:var(--card);box-shadow:var(--raise);margin-bottom:14px}.msg>div{flex:1;min-width:0}' +
    '.msg b{display:block}.err{color:var(--bad)}.ok{color:var(--ok)}.msg .t{color:var(--fg)}' +
    '.res{display:flex;gap:8px;justify-content:space-between;margin-top:8px;padding:8px 12px;border-radius:14px;background:var(--field);box-shadow:var(--press);word-break:break-all;font-family:ui-monospace,monospace;font-size:.85rem;color:var(--fg)}' +
    '.lnk{border:0;background:none;color:var(--link);font:inherit;font-weight:700;text-decoration:underline;cursor:pointer;padding:0;margin-top:6px}' +
    '.sk{border-radius:26px;height:150px;background:var(--card);box-shadow:var(--raise);animation:pu 1.4s ease-in-out infinite}@keyframes pu{50%{opacity:.55}}' +
    '.ov{position:fixed;inset:0;background:rgba(13,27,42,.62);display:flex;align-items:center;justify-content:center;z-index:2147483000;padding:12px}' +
    '.md{background:var(--bg);color:var(--fg);border-radius:30px;width:100%;max-width:460px;height:min(720px,94vh);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 30px 60px -20px rgba(0,0,0,.6)}' +
    '.mh{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:12px 16px}.mh b{display:flex;align-items:center;gap:8px;font-size:.95rem}' +
    '.x{border:0;background:var(--card);color:var(--fg);width:36px;height:36px;border-radius:50%;box-shadow:var(--soft);font-size:1.3rem;line-height:1;cursor:pointer}' +
    '.fr{position:relative;flex:1;min-height:0}.fr .ld{position:absolute;inset:0;display:grid;place-items:center;color:var(--muted);gap:8px;align-content:center;justify-items:center}' +
    'iframe{position:absolute;inset:0;border:0;width:100%;height:100%;background:var(--bg)}' +
    '@media(max-width:520px){.slide{flex-direction:column-reverse;align-items:flex-start;gap:6px}.slide .sart{width:46%;max-width:150px;align-self:center}.w{padding:14px;border-radius:24px}.ov{padding:0}.md{max-width:none;height:100%;border-radius:0}.how{gap:6px}.how li{flex-direction:column;text-align:center;gap:4px;padding:8px 4px;font-size:.74rem}.grid{grid-template-columns:1fr}}' +
    '@media(prefers-reduced-motion:no-preference){.card{transition:transform .15s}.card:hover{transform:translateY(-2px)}}@media(prefers-reduced-motion:reduce){.spin,.sk{animation:none}}';

  var ICONS = {
    lock: '<rect x="4" y="11" width="16" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    alert: '<path d="M12 4 3 20h18L12 4Z"/><path d="M12 10v4M12 17.2v.1"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="3"/><path d="M5 15V6a2 2 0 0 1 2-2h8"/>',
    share: '<path d="M12 3v12M8 7l4-4 4 4"/><path d="M5 12v6a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-6"/>',
    bell: '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    down: '<path d="M12 4v11M8 11l4 4 4-4"/><path d="M5 20h14"/>',
    out: '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
    phone: '<rect x="7" y="2.5" width="10" height="19" rx="3"/><path d="M11 18.5h2"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/>'
  };
  function icon(name) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('aria-hidden', 'true');
    s.innerHTML = ICONS[name] || ''; // static literals above only, never page or API data
    return s;
  }

  function mountPoint() {
    var sel = script.getAttribute('data-target');
    var n = (sel && document.querySelector(sel)) || document.getElementById('naara-widget');
    if (!n) { n = document.createElement('div'); n.id = 'naara-widget'; script.parentNode.insertBefore(n, script.nextSibling); }
    return n;
  }

  var STEPS = {
    esim: ['Pick a plan', 'Pay securely', 'Install from your email'],
    number: ['Pick service and country', 'Pay securely', 'Get your code here'],
    giftcard: ['Pick a card', 'Pay securely', 'Get it by email']
  };
  var FALLBACK = {
    esim: { title: 'Travel eSIM data', blurb: 'Mobile data for your trip. No SIM swap: scan a QR code and you are online.' },
    number: { title: 'Verification numbers', blurb: 'A temporary phone number to receive the SMS code for WhatsApp, Telegram, Google and more.' },
    giftcard: { title: 'Gift cards', blurb: 'Digital gift cards for top brands. The code is delivered by email.' }
  };
  var SORTS = [['rec', 'Recommended'], ['price', 'Price: low to high'], ['data', 'Most data'], ['days', 'Longest validity']];

  // Copy with a fallback for browsers or frames that block the async clipboard. Always the shopper's own click.
  function copyText(text, done) {
    function fallback() {
      try { var t = document.createElement('textarea'); t.value = text; t.setAttribute('readonly', ''); t.style.cssText = 'position:fixed;opacity:0;top:0'; document.body.appendChild(t); t.select(); var ok = document.execCommand('copy'); document.body.removeChild(t); done(!!ok); } catch (e) { done(false); }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(function () { done(true); }, fallback); } else { fallback(); }
  }
  function isPhone() { return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || ''); }

  function Widget(host) {
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host; // old browsers fall back to light DOM (still escaped)
    var st = el('style'); st.textContent = CSS; root.appendChild(st);
    var box = el('div', 'w ' + (ATTR_THEME === 'dark' ? 'dark' : ATTR_THEME === 'light' ? '' : 'auto'));
    root.appendChild(box);
    var data = {}, kindsMeta = [], lookups = {}, accountOn = false, active = KIND, overlay = null, frameOrigin = BASE, pending = null, brand = '', theme = ATTR_THEME;
    var view = { q: '', shown: PAGE, sort: 'rec' }, screen = 'shop', opener = null, prevOverflow = '', num = { service: '', country: '', q: '', quoteId: 0 };
    var live = null;   // the one live-code poller (a receipt on screen); cancelled whenever the screen changes

    function setBrand(c) { brand = c; box.style.setProperty('--brand', c); box.style.setProperty('--on-brand', ink(c)); }
    function stopLive() { if (live) { live.stop = true; live = null; } if (timer) { clearInterval(timer); timer = null; } }
    function meta(k) { for (var i = 0; i < kindsMeta.length; i++) { if (kindsMeta[i].kind === k) { return kindsMeta[i]; } } return FALLBACK[k] || { title: k, blurb: '' }; }
    function kindList() {
      var have = Object.keys(data).filter(function (k) { return data[k] && data[k].length; });
      var order = kindsMeta.length ? kindsMeta.map(function (m) { return m.kind; }) : ['esim', 'number', 'giftcard'];
      return order.filter(function (k) { return have.indexOf(k) >= 0; });
    }
    function token() {
      var raw = lsGet(TOKEN_STORE); if (!raw) { return null; }
      try { var t = JSON.parse(raw); if (t && t.token && t.expires_at && new Date(t.expires_at).getTime() > Date.now()) { return t; } } catch (e) {}
      lsDel(TOKEN_STORE); return null;
    }

    var ART = { esim: 'esim.svg', number: 'numbers.svg', giftcard: 'giftcard.svg' };
    var timer = null;
    function stopHero() { if (timer) { clearInterval(timer); timer = null; } }
    function hero(kinds) {
      var sec = el('section', 'hero'); sec.setAttribute('aria-roledescription', 'carousel'); sec.setAttribute('aria-label', 'What you can buy');
      var track = el('div', 'slides'), dots = el('div', 'dots'), idx = Math.max(0, kinds.indexOf(active)), slides = [], dotEls = [];
      function go(i, smooth) {
        idx = (i + kinds.length) % kinds.length;
        track.style.scrollBehavior = smooth ? 'smooth' : 'auto';
        track.scrollLeft = idx * track.clientWidth;
        dotEls.forEach(function (d, j) { d.setAttribute('aria-current', j === idx ? 'true' : 'false'); });
      }
      kinds.forEach(function (k, i) {
        var m = meta(k), sl = el('article', 'slide'), tx = el('div', 'tx');
        sl.setAttribute('role', 'group'); sl.setAttribute('aria-label', (i + 1) + ' of ' + kinds.length);
        tx.appendChild(el('h2', 'h', m.title)); tx.appendChild(el('p', 'sub', m.blurb));
        var how = el('ol', 'how');
        (STEPS[k] || STEPS.esim).forEach(function (t, n) { var li = el('li'); li.appendChild(el('b', null, String(n + 1))); li.appendChild(document.createTextNode(t)); how.appendChild(li); });
        tx.appendChild(how);
        if (k !== active) { var c = el('button', 'cta2', 'Shop ' + m.title); c.type = 'button'; c.onclick = function () { active = k; view = { q: '', shown: PAGE, sort: 'rec' }; paint(); }; tx.appendChild(c); }
        sl.appendChild(tx);
        if (ART[k] && isHttps(BASE)) { var im = el('img', 'sart'); im.alt = ''; im.src = BASE + '/images/illustrations/' + ART[k]; im.onerror = function () { im.style.display = 'none'; }; sl.appendChild(im); }
        track.appendChild(sl); slides.push(sl);
        var d = el('button'); d.type = 'button'; d.setAttribute('aria-label', 'Show ' + m.title); d.onclick = function () { stopHero(); go(i, true); }; dots.appendChild(d); dotEls.push(d);
      });
      sec.appendChild(track);
      if (kinds.length > 1) {
        sec.appendChild(dots);
        track.addEventListener('scroll', function () { var w = track.clientWidth || 1, i = Math.round(track.scrollLeft / w); if (i !== idx && i >= 0 && i < kinds.length) { idx = i; dotEls.forEach(function (d, j) { d.setAttribute('aria-current', j === idx ? 'true' : 'false'); }); } });
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        ['pointerdown', 'focusin', 'mouseenter', 'touchstart'].forEach(function (ev) { sec.addEventListener(ev, stopHero); });
        if (!reduce) { timer = setInterval(function () { go(idx + 1, true); }, 6000); }
      }
      dotEls.forEach(function (d, j) { d.setAttribute('aria-current', j === idx ? 'true' : 'false'); });
      requestAnimationFrame(function () { go(idx, false); });
      return sec;
    }

    function paint(extra) {
      stopLive(); box.textContent = '';
      if (screen === 'account') { paintAccount(extra); return; }
      var kinds = kindList();
      if (!kinds.length) { if (extra) { box.appendChild(extra); } else { box.appendChild(notice('Nothing is available to buy right now. Please check back soon.', false)); } if (accountOn) { box.appendChild(accountBtn()); } box.appendChild(by()); return; }
      if (!active || kinds.indexOf(active) < 0) { active = kinds[0]; }
      if (extra) { box.appendChild(extra); }
      var top = el('div', 'bar'); top.appendChild(el('span')); if (accountOn) { top.appendChild(accountBtn()); } box.appendChild(top);
      box.appendChild(hero(kinds));
      if (kinds.length > 1) {
        var tabs = el('div', 'kinds'); tabs.setAttribute('role', 'tablist');
        kinds.forEach(function (k) {
          var m = meta(k), t = el('button', 'kind'); t.type = 'button'; t.setAttribute('role', 'tab'); t.setAttribute('aria-selected', k === active ? 'true' : 'false');
          t.appendChild(el('b', null, m.title)); t.appendChild(el('span', null, m.count ? m.count + ' ' + ({ number: ['service', 'services'], esim: ['plan', 'plans'], giftcard: ['brand', 'brands'] }[k] || ['item', 'items'])[m.count === 1 ? 0 : 1] : ''));
          t.onclick = function () { active = k; view = { q: '', shown: PAGE, sort: 'rec' }; paint(); }; tabs.appendChild(t);
        });
        box.appendChild(tabs);
      }
      if (active === 'number') { paintNumbers(); } else { paintList(); }
      box.appendChild(by());
    }

    function accountBtn() {
      var b = el('button', 'acct'); b.type = 'button'; b.appendChild(icon('user')); b.appendChild(document.createTextNode(token() ? 'My orders' : 'Find my order'));
      b.onclick = function () { screen = 'account'; paint(); }; return b;
    }

    function sorted(list) {
      var l = list.slice(), s = view.sort;
      if (active !== 'esim' || s === 'rec') { return l; }
      var key = { price: function (p) { return Number(p.price_usd) || 0; }, data: function (p) { return -(Number(p.data_mb) || 0); }, days: function (p) { return -(Number(p.validity_days) || 0); } }[s];
      return l.sort(function (a, b) { return key(a) - key(b); });
    }
    function paintList() {
      var list = data[active];
      var grid = el('div', 'grid'), more = el('button', 'more', 'Show more'); more.type = 'button'; more.hidden = true;
      if (active === 'esim' && list.length > 1) {
        var bar = el('div', 'bar'), sel = el('select', 'sort'); sel.setAttribute('aria-label', 'Sort plans');
        SORTS.forEach(function (o) { var op = el('option', null, o[1]); op.value = o[0]; if (o[0] === view.sort) { op.selected = true; } sel.appendChild(op); });
        sel.onchange = function () { view.sort = sel.value; view.shown = PAGE; fill(); };
        bar.appendChild(el('span', 'meta', list.length + ' plans')); bar.appendChild(sel); box.appendChild(bar);
      }
      if (list.length >= SEARCH_FROM) {
        var f = el('label', 'find'); f.appendChild(icon('search'));
        var inp = el('input'); inp.type = 'search'; inp.placeholder = active === 'esim' ? 'Search a country or plan, e.g. Ghana' : 'Search a brand'; inp.setAttribute('aria-label', 'Search'); inp.value = view.q;
        inp.oninput = function () { view.q = inp.value.trim().toLowerCase(); view.shown = PAGE; fill(); };
        f.appendChild(inp); box.appendChild(f);
      }
      more.onclick = function () { view.shown += PAGE; fill(); };
      function match(p) {
        if (!view.q) { return true; }
        var hay = active === 'esim' ? [p.name].concat((p.countries || []).map(function (c) { return c + ' ' + country(c); })).join(' ') : p.brand + ' ' + (p.category || '');
        return hay.toLowerCase().indexOf(view.q) >= 0;
      }
      function fill() {
        grid.textContent = ''; var hits = sorted(list.filter(match));
        hits.slice(0, view.shown).forEach(function (p) { grid.appendChild(active === 'esim' ? esimCard(p) : giftCard(p)); });
        if (!hits.length) { var m = el('div', 'msg'); m.appendChild(el('div', 't', 'No match for "' + view.q + '". Try a country name or clear the search.')); grid.appendChild(m); }
        more.hidden = hits.length <= view.shown;
        if (!more.hidden) { more.textContent = 'Show more (' + (hits.length - view.shown) + ' left)'; }
      }
      fill(); box.appendChild(grid); box.appendChild(more);
    }

    // Verification numbers: service -> country -> live price -> buy. The price is asked for the moment both are chosen.
    function paintNumbers() {
      var services = data.number || [], countries = lookups.number_countries || [];
      var wrap = el('div', 'pick');
      if (!num.service && services.length) { num.service = services[0].id; }
      if (!num.country && countries.length) {
        var g = guessCountry(), want = g && names ? String(names.of(g) || '').toLowerCase() : '';
        for (var i = 0; i < countries.length; i++) { if (want && countries[i].name.toLowerCase() === want) { num.country = countries[i].code; break; } }
        if (!num.country) { num.country = countries[0].code; }
      }
      var l1 = el('div'); l1.appendChild(el('div', 'meta', '1. What do you need the code for?'));
      var chips = el('div', 'svc'); chips.setAttribute('role', 'group');
      var chipBtns = services.map(function (s) {
        var b = el('button', 'den', s.name); b.type = 'button'; b.setAttribute('aria-pressed', s.id === num.service ? 'true' : 'false');
        b.onclick = function () { num.service = s.id; chipBtns.forEach(function (x, j) { x.setAttribute('aria-pressed', services[j].id === num.service ? 'true' : 'false'); }); quote(); };
        chips.appendChild(b); return b;
      });
      l1.appendChild(chips); wrap.appendChild(l1);

      var l2 = el('label'); l2.appendChild(el('span', null, '2. Which country should the number be from?'));
      var find = el('input', 'amt'); find.type = 'search'; find.placeholder = 'Type to filter countries'; find.setAttribute('aria-label', 'Filter countries'); find.value = num.q;
      var sel = el('select'); sel.setAttribute('aria-label', 'Country');
      function fillCountries() {
        sel.textContent = ''; var q = num.q.toLowerCase(), shown = 0;
        countries.forEach(function (c) {
          if (q && c.name.toLowerCase().indexOf(q) < 0 && c.code !== num.country) { return; }
          var o = el('option', null, c.name + (c.dial ? '  (' + (String(c.dial).charAt(0) === '+' ? '' : '+') + c.dial + ')' : '')); o.value = c.code; if (c.code === num.country) { o.selected = true; } sel.appendChild(o); shown++;
        });
      }
      find.oninput = function () { num.q = find.value.trim(); fillCountries(); };
      sel.onchange = function () { num.country = sel.value; quote(); };
      fillCountries(); l2.appendChild(find); l2.appendChild(sel); wrap.appendChild(l2);

      var q = el('div', 'quote'), price = el('div', 'price', '\u2026'), note = el('div', 'meta'), buy;
      var qleft = el('div'); qleft.appendChild(note); q.appendChild(qleft); q.appendChild(price); wrap.appendChild(q);
      buy = buyBtn('Buy this number', function (done) { if (!num.service || !num.country) { done(); return; } start('number', null, null, done, { service: num.service, country: num.country }); });
      buy.classList.add('off'); wrap.appendChild(buy);
      wrap.appendChild(el('p', 'how2', 'You get a temporary number and the SMS code shows up on this page. If no code arrives within ' + (lookups.number_refund_minutes || 15) + ' minutes, your payment is returned automatically.'));
      box.appendChild(wrap);

      var tm;
      function quote() {
        clearTimeout(tm); var id = ++num.quoteId; buy.classList.add('off'); price.textContent = '\u2026'; note.textContent = 'Checking today\u2019s price\u2026';
        if (!num.service || !num.country) { return; }
        tm = setTimeout(function () {
          api('/number-quote?service=' + encodeURIComponent(num.service) + '&country=' + encodeURIComponent(num.country)).then(function (r) {
            if (id !== num.quoteId) { return; }
            if (r.available) { price.textContent = '$' + Number(r.price_usd).toFixed(2); note.textContent = r.service + ' number, ' + r.country; buy.classList.remove('off'); }
            else { price.textContent = 'Not available'; note.textContent = r.message || 'Try another country.'; }
          }).catch(function (e) { if (id === num.quoteId) { price.textContent = 'Not available'; note.textContent = e.message || 'Try again in a moment.'; } });
        }, 200);
      }
      quote();
    }

    function by() { var d = el('div', 'by'); d.appendChild(document.createTextNode('Powered by ')); var a = el('a', null, 'Naara'); a.href = BASE; a.target = '_blank'; a.rel = 'noopener'; d.appendChild(a); d.appendChild(document.createTextNode(' \u00b7 Naara handles payment and support')); return d; }
    function mb(n) { return n >= 1024 ? (n / 1024).toFixed(n % 1024 ? 1 : 0) + ' GB' : n + ' MB'; }
    function buyBtn(label, fn) {
      var b = el('button', 'buy'); b.type = 'button'; var t = el('span', null, label); b.appendChild(t);
      b.onclick = function () {
        if (b.classList.contains('off')) { return; }
        opener = b; b.disabled = true; t.textContent = 'Opening checkout\u2026'; b.insertBefore(el('span', 'spin'), t);
        fn(function () { b.disabled = false; t.textContent = label; var s = b.querySelector('.spin'); if (s) { b.removeChild(s); } });
      };
      b.setLabel = function (l) { label = l; t.textContent = l; };
      return b;
    }
    function mini(label, ic, fn, cls) {
      var b = el('button', 'mini' + (cls ? ' ' + cls : '')); b.type = 'button'; if (ic) { b.appendChild(icon(ic)); } var t = el('span', null, label); b.appendChild(t);
      b.onclick = function () { fn(b, function (txt) { t.textContent = txt; setTimeout(function () { t.textContent = label; }, 1800); }); }; return b;
    }
    function copyBtn(text, label) { return mini(label || 'Copy', 'copy', function (b, flash) { copyText(text, function (ok) { flash(ok ? 'Copied' : 'Press and hold to copy'); }); }); }
    function shareBtn(title, text, url) {
      if (!navigator.share) { return null; }
      return mini('Share', 'share', function () { var p = { title: title, text: text }; if (url) { p.url = url; } navigator.share(p).catch(function () {}); });
    }

    function esimCard(p) {
      var c = el('div', 'card'), top = el('div', 'top');
      top.appendChild(el('div', 'big', p.data_mb ? mb(p.data_mb) : (p.name || 'eSIM')));
      if (p.validity_days) { var v = el('span', 'pill'); v.appendChild(icon('clock')); v.appendChild(document.createTextNode(p.validity_days + ' days')); top.appendChild(v); }
      c.appendChild(top);
      if (p.data_mb) { c.appendChild(el('div', 'name', p.name)); }
      var cs = (p.countries || []).filter(function (x) { return typeof x === 'string'; });
      if (cs.length) { var g = el('div', 'meta', cs.length > 3 ? 'Works in ' + cs.length + ' countries' : 'Works in ' + cs.map(country).join(', ')); c.appendChild(g); }
      if (p.has_voice) { c.appendChild(el('div', 'meta', 'Includes calls')); }
      c.appendChild(el('div', 'sp')); c.appendChild(el('div', 'price', '$' + Number(p.price_usd).toFixed(2)));
      c.appendChild(buyBtn('Get this eSIM', function (done) { start('esim', p.id, null, done); }));
      return c;
    }
    function giftCard(p) {
      var c = el('div', 'card');
      if (p.logo_url && isHttps(p.logo_url)) { var im = el('img', 'logo'); im.src = p.logo_url; im.alt = ''; im.loading = 'lazy'; im.referrerPolicy = 'no-referrer'; c.appendChild(im); }
      c.appendChild(el('div', 'name', p.brand));
      var d = p.denominations || {}, amount = null, price = el('div', 'price'), buy;
      if (d.type === 'FIXED' && d.options && d.options.length) {
        var wrap = el('div', 'dens'), btns = [];
        var pick = function (i) { var o = d.options[i]; amount = o.face; price.textContent = '$' + Number(o.price_usd).toFixed(2); btns.forEach(function (b, j) { b.setAttribute('aria-pressed', j === i ? 'true' : 'false'); }); };
        d.options.forEach(function (o, i) { var b = el('button', 'den', o.face + ' ' + (p.face_currency || '')); b.type = 'button'; b.onclick = function () { pick(i); }; btns.push(b); wrap.appendChild(b); });
        c.appendChild(el('div', 'meta', 'Choose a value')); c.appendChild(wrap); pick(0);
      } else if (d.type === 'RANGE') {
        var inp = el('input', 'amt'); inp.type = 'number'; inp.min = d.min; inp.max = d.max; inp.inputMode = 'decimal'; inp.setAttribute('aria-label', 'Amount in ' + (p.face_currency || ''));
        inp.placeholder = 'Amount (' + d.min + ' \u2013 ' + d.max + ' ' + (p.face_currency || '') + ')'; amount = inp;
        c.appendChild(inp); price.textContent = 'Enter an amount'; var tm;
        inp.oninput = function () {
          clearTimeout(tm); var v = Number(inp.value);
          if (!v || v < d.min || v > d.max) { price.textContent = inp.value ? 'Between ' + d.min + ' and ' + d.max : 'Enter an amount'; return; }
          price.textContent = '\u2026';
          tm = setTimeout(function () { api('/giftcards/' + p.id + '/quote', { method: 'POST', body: { face_value: v } }).then(function (q) { if (Number(inp.value) === v) { price.textContent = '$' + Number(q.price_usd).toFixed(2); } }).catch(function (e) { price.textContent = e.message; }); }, 350);
        };
      }
      c.appendChild(el('div', 'sp')); c.appendChild(price);
      buy = buyBtn('Buy gift card', function (done) {
        var face = amount && amount.tagName === 'INPUT' ? Number(amount.value) : amount;
        if (!face) { done(); return; }
        start('giftcard', p.id, face, done);
      });
      c.appendChild(buy); return c;
    }

    function themeParam() { return theme === 'dark' || theme === 'light' ? theme : ''; }
    function start(kind, id, face, done, extra) {
      var body = { kind: kind, idempotency_key: uuid() };
      if (id != null) { body.product_id = id; }
      if (face) { body.face_value = face; }
      if (extra) { Object.keys(extra).forEach(function (k) { body[k] = extra[k]; }); }
      beacon('checkout_opened', { kind: kind, product_id: id });
      api('/checkout/sessions', { method: 'POST', body: body }).then(function (s) {
        if (!s.checkout_url || !isHttps(s.checkout_url)) { throw new Error('Checkout is unavailable.'); }
        pending = s.reference; try { sessionStorage.setItem(STORE, s.reference); } catch (e) {}
        var u = new URL(s.checkout_url); if (themeParam()) { u.searchParams.set('theme', themeParam()); } if (brand) { u.searchParams.set('color', brand); }
        if (MODE === 'redirect') { window.location.assign(u.toString()); return; }
        done(); frameOrigin = u.origin; openModal(u.toString());
      }).catch(function (e) { done(); paint(retry(e.message || 'We could not start checkout.')); });
    }
    function retry(text) {
      var m = notice(text + ' You have not been charged.', true); var b = el('button', 'lnk', 'Dismiss'); b.type = 'button'; b.onclick = function () { paint(); }; m.lastChild.appendChild(b); return m;
    }
    function notice(text, bad, title) {
      var m = el('div', 'msg ' + (bad ? 'err' : 'ok')); m.setAttribute('role', bad ? 'alert' : 'status'); m.appendChild(icon(bad ? 'alert' : 'check'));
      var d = el('div'); if (title) { d.appendChild(el('b', null, title)); } d.appendChild(el('span', 't', text)); m.appendChild(d); return m;
    }

    function openModal(url) {
      closeModal(); overlay = el('div', 'ov'); var m = el('div', 'md'); m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true'); m.setAttribute('aria-label', 'Secure checkout');
      var h = el('div', 'mh'), t = el('b'); t.appendChild(icon('lock')); t.appendChild(document.createTextNode('Secure checkout')); h.appendChild(t);
      var x = el('button', 'x', '\u00d7'); x.type = 'button'; x.setAttribute('aria-label', 'Close checkout'); x.onclick = function () { beacon('checkout_abandoned'); closeModal(); }; h.appendChild(x);
      var fr = el('div', 'fr'), ld = el('div', 'ld'); ld.appendChild(el('span', 'spin')); ld.appendChild(el('span', null, 'Loading secure checkout\u2026')); fr.appendChild(ld);
      var f = document.createElement('iframe'); f.src = url; f.title = 'Secure checkout'; f.setAttribute('allow', 'payment'); f.onload = function () { if (ld.parentNode) { ld.parentNode.removeChild(ld); } };
      fr.appendChild(f); m.appendChild(h); m.appendChild(fr); overlay.appendChild(m); box.appendChild(overlay); x.focus();
      prevOverflow = document.documentElement.style.overflow; document.documentElement.style.overflow = 'hidden';
      overlay.addEventListener('keydown', function (e) { if (e.key === 'Escape') { x.onclick(); } });
      overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { x.onclick(); } });
    }
    function closeModal() {
      if (overlay && overlay.parentNode) { overlay.parentNode.removeChild(overlay); document.documentElement.style.overflow = prevOverflow; if (opener && opener.focus) { try { opener.focus(); } catch (e) {} } }
      overlay = null;
    }

    // postMessage contract (blueprint S.8). Trust only the checkout origin and the 'naara' source tag; never act on anything else.
    window.addEventListener('message', function (e) {
      if (e.origin !== frameOrigin || !e.data || e.data.source !== 'naara') { return; }
      if (e.data.type === 'naara:checkout:redirect' && isHttps(e.data.url)) { window.location.assign(e.data.url); }
      else if (e.data.type === 'naara:checkout:complete') { closeModal(); screen = 'shop'; poll(pending, 0); }
      else if (e.data.type === 'naara:checkout:error') { /* the page shows its own error; keep the modal open for a retry */ }
      else if (e.data.type === 'naara:checkout:closed' && !pending) { closeModal(); }
    });

    function waiting() { var m = notice('Please keep this page open. This only takes a moment.', false, 'Confirming your payment'); m.replaceChild(el('span', 'spin'), m.firstChild); return m; }
    function forget() { try { sessionStorage.removeItem(STORE); } catch (e) {} }
    function poll(ref, n) {
      if (!ref) { return; }
      if (n === 0) { paint(waiting()); }
      api('/orders/' + encodeURIComponent(ref) + '/status').then(function (s) {
        if (s.status === 'completed') {
          if (s.kind === 'number') { paint(numberReceipt(s)); watchNumber(s); } // the pending ref stays until the code arrives, so a reload resumes
          else { forget(); paint(receipt(s)); }
        }
        else if (s.status === 'failed' || s.status === 'refunded') { forget(); paint(notice('If you were charged, it will be returned to you automatically. Nothing else to do.', true, 'We could not complete this order')); }
        else if (n < 40) { setTimeout(function () { poll(ref, n + 1); }, Math.min(1500 + n * 500, 6000)); }
        else { paint(notice('Your payment is still being confirmed. Your details will be emailed to you as soon as it is done, so you can close this page.', false, 'Still working on it')); }
      }).catch(function () { if (n < 5) { setTimeout(function () { poll(ref, n + 1); }, 3000); } else { paint(notice('We could not check your order just now. If you paid, your details will be emailed to you.', true)); } });
    }
    function trackHint(d) {
      if (!accountOn) { return; }
      var b = el('button', 'lnk', 'Track this order any time under My orders'); b.type = 'button'; b.onclick = function () { screen = 'account'; paint(); }; d.appendChild(b);
    }
    function receipt(s) {
      var m = notice('We have also emailed these details to you. Check spam if it does not show up in a minute.', false, 'All done. Your order is complete.'), r = s.result || {}, d = m.lastChild;
      Object.keys(r).forEach(function (k) {
        if (typeof r[k] === 'string' && k !== 'qr_code_data' && k !== 'install_link' && k !== 'kind') {
          var row = el('div', 'res'); row.appendChild(el('span', null, k.replace(/_/g, ' ') + ': ' + r[k])); d.appendChild(row);
        }
      });
      var acts = el('div', 'row');
      if (r.install_link && isHttps(r.install_link) && isPhone()) { var a = el('a', 'mini', 'Install on this phone'); a.href = r.install_link; a.target = '_blank'; a.rel = 'noopener'; acts.insertBefore(a, acts.firstChild); }
      if (r.lpa) { acts.appendChild(copyBtn(String(r.lpa), 'Copy activation code')); }
      acts.appendChild(el('span')); d.appendChild(acts);
      trackHint(d);
      var b = el('button', 'lnk', 'Buy another'); b.type = 'button'; b.onclick = function () { paint(); }; d.appendChild(b);
      return m;
    }

    // A number receipt that updates itself: the code appears the moment it arrives, with the refund window counting down.
    function numberReceipt(s) {
      var r = s.result || {}, m = notice('Use this number to request your code. The code will appear here.', false, 'Your number is ready'), d = m.lastChild;
      m.id = 'num-receipt';
      var row = el('div', 'res'); var n = el('span', 'num', String(r.number || '')); row.appendChild(n); d.appendChild(row);
      var codeBox = el('div', 'res'); codeBox.id = 'num-code'; d.appendChild(codeBox);
      var acts = el('div', 'row'); acts.id = 'num-acts';
      if (r.number) { acts.appendChild(copyBtn(String(r.number), 'Copy number')); }
      d.appendChild(acts);
      var timer = el('div', 'fine'); timer.id = 'num-timer'; d.appendChild(timer);
      var tail = el('div'); tail.id = 'num-tail'; d.appendChild(tail);
      trackHint(tail);
      return m;
    }
    function watchNumber(first) {
      var me = { stop: false }; live = me;
      var codeBox = box.querySelector('#num-code'), acts = box.querySelector('#num-acts'), timer = box.querySelector('#num-timer'), tail = box.querySelector('#num-tail'), head = box.querySelector('#num-receipt');
      if (!codeBox) { return; }
      var ref = pending, expires = null, asked = false, notifyBtn = null, ticker = null;
      function renderWaiting(r) {
        codeBox.textContent = ''; codeBox.appendChild(el('span', 'spin')); codeBox.appendChild(el('span', null, 'Waiting for your code\u2026'));
        if (r.expires_at) { expires = new Date(r.expires_at).getTime(); }
        if (!notifyBtn && 'Notification' in window && Notification.permission === 'default') {
          notifyBtn = mini('Tell me when it arrives', 'bell', function (b, flash) { try { Notification.requestPermission().then(function (p) { flash(p === 'granted' ? 'We will notify you' : 'Notifications are off'); }); } catch (e) {} });
          acts.appendChild(notifyBtn);
        }
      }
      function tick() {
        if (me.stop || !expires) { return; }
        var left = Math.max(0, Math.round((expires - Date.now()) / 1000));
        timer.textContent = left > 0 ? 'Refunded automatically if no code arrives in ' + Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2) : '';
      }
      function gotCode(r) {
        forget(); clearInterval(ticker); codeBox.textContent = ''; codeBox.className = 'res'; codeBox.appendChild(el('span', 'code', String(r.code)));
        timer.textContent = ''; head.querySelector('b').textContent = 'Your code has arrived'; head.querySelector('.t').textContent = 'Enter it where you were asked for it.'; if (notifyBtn && notifyBtn.parentNode) { notifyBtn.parentNode.removeChild(notifyBtn); }
        acts.appendChild(copyBtn(String(r.code), 'Copy code'));
        // Only if the shopper opted in, and never with the code itself in the notification text.
        if ('Notification' in window && Notification.permission === 'granted' && document.hidden) { try { new Notification('Your verification code has arrived', { body: 'Open the page to see it.', tag: 'naara-code' }); } catch (e) {} }
        var again = el('button', 'lnk', 'Buy another'); again.type = 'button'; again.onclick = function () { paint(); }; tail.appendChild(again);
      }
      function failed() { forget(); clearInterval(ticker); paint(notice('No code arrived, so your payment has been returned to you. You were not charged for this number.', true, 'Refunded')); }
      function expired() { forget(); clearInterval(ticker); codeBox.textContent = 'No code yet. If it does not arrive your payment is returned automatically.'; }
      function step(n) {
        if (me.stop) { return; }
        if (navigator.onLine === false) { return later(n, 5000); }
        api('/orders/' + encodeURIComponent(ref) + '/status').then(function (s) {
          if (me.stop) { return; }
          if (s.status === 'refunded' || s.status === 'failed') { return failed(); }
          var r = s.result || {};
          if (r.code) { return gotCode(r); }
          renderWaiting(r);
          if (expires && Date.now() > expires + 60000) { return expired(); }
          later(n, 0);
        }).catch(function () { later(n, 6000); });
      }
      // Quick while the tab is in front, gentle when it is in the background, and never more than a request every few seconds.
      function later(n, min) { if (me.stop) { return; } var base = document.hidden ? 15000 : Math.min(3000 + n * 500, 8000); setTimeout(function () { step(n + 1); }, Math.max(base, min)); }
      renderWaiting(first.result || {}); ticker = setInterval(function () { if (me.stop) { clearInterval(ticker); } else { tick(); } }, 1000); tick();
      if ((first.result || {}).code) { gotCode(first.result); } else { later(0, 0); }
    }

    // ---- "My orders": sign in with an emailed code, then see, copy and control your own data. ----
    function paintAccount(extra) {
      var back = mini('Back to shop', null, function () { screen = 'shop'; paint(); });
      var bar = el('div', 'bar'); bar.appendChild(el('h2', 'h', 'My orders')); bar.appendChild(back); box.appendChild(bar);
      if (extra) { box.appendChild(extra); }
      var t = token();
      if (!t) { paintSignIn(); } else { paintOrders(t); }
      box.appendChild(by());
    }
    function paintSignIn(email, note) {
      var wrap = el('div', 'frm');
      box.appendChild(el('p', 'sub', 'Enter the email you used when you bought. We will send you a 6-digit code. No password, and nothing to remember.'));
      var emailIn = el('input'); emailIn.type = 'email'; emailIn.autocomplete = 'email'; emailIn.placeholder = 'you@example.com'; emailIn.setAttribute('aria-label', 'Email address'); emailIn.value = email || '';
      var go = buyBtn('Email me a code', function (done) {
        var v = emailIn.value.trim(); if (!v) { done(); return; }
        api('/account/code', { method: 'POST', body: { email: v } }).then(function (r) { done(); step2(v, r.minutes || 10); }).catch(function (e) { done(); msg.textContent = e.message; });
      });
      var msg = el('p', 'fine err'); msg.setAttribute('role', 'alert'); if (note) { msg.textContent = note; }
      wrap.appendChild(emailIn); wrap.appendChild(go); wrap.appendChild(msg);
      wrap.appendChild(el('p', 'fine', 'We only show orders placed on this website. Nobody can find out whether an address has orders from this form.'));
      box.appendChild(wrap);
      function step2(v, minutes) {
        wrap.textContent = '';
        wrap.appendChild(notice('If that address has orders here, a code is on its way. It works for ' + minutes + ' minutes.', false, 'Check your email'));
        var codeIn = el('input', 'otp'); codeIn.type = 'text'; codeIn.inputMode = 'numeric'; codeIn.autocomplete = 'one-time-code'; codeIn.maxLength = 6; codeIn.placeholder = '000000'; codeIn.setAttribute('aria-label', '6-digit code');
        var m2 = el('p', 'fine err'); m2.setAttribute('role', 'alert');
        var ok = buyBtn('Show my orders', function (done) {
          api('/account/verify', { method: 'POST', body: { email: v, code: codeIn.value.trim() } }).then(function (r) {
            lsSet(TOKEN_STORE, JSON.stringify({ token: r.token, expires_at: r.expires_at, email: v })); screen = 'account'; paint();
          }).catch(function (e) { done(); m2.textContent = e.message; });
        });
        var redo = el('button', 'lnk', 'Use a different email or send a new code'); redo.type = 'button'; redo.onclick = function () { box.textContent = ''; paintAccount(); };
        wrap.appendChild(codeIn); wrap.appendChild(ok); wrap.appendChild(m2); wrap.appendChild(redo); codeIn.focus();
      }
    }
    function expiredSession() { lsDel(TOKEN_STORE); box.textContent = ''; screen = 'account'; paintAccount(notice('For your safety you were signed out. Please sign in again.', true)); }
    function paintOrders(t) {
      var list = el('div'); list.appendChild(el('div', 'meta', 'Loading your orders\u2026')); box.appendChild(list);
      api('/account/orders', { token: t.token }).then(function (r) {
        list.textContent = '';
        list.appendChild(el('p', 'sub', 'Signed in as ' + (r.email || t.email) + '. Everything here was bought on this website.'));
        if (!(r.orders || []).length) { list.appendChild(el('div', 'meta', 'No orders yet.')); }
        (r.orders || []).forEach(function (o) { list.appendChild(orderRow(o, t)); });
        list.appendChild(dataControls(t));
      }).catch(function (e) { if (e.status === 401) { expiredSession(); } else { list.textContent = ''; list.appendChild(notice(e.message || 'We could not load your orders.', true)); } });
    }
    function orderRow(o, t) {
      var c = el('div', 'ord'), top = el('div', 'top');
      top.appendChild(el('div', 'name', o.title)); var bd = el('span', 'badge ' + (o.status === 'completed' ? 'ok' : (o.status === 'failed' || o.status === 'refunded') ? 'bad' : ''), o.status === 'refunded' ? 'refunded' : o.status); top.appendChild(bd); c.appendChild(top);
      c.appendChild(el('div', 'meta', when(o.created_at) + ' \u00b7 $' + Number(o.price_usd).toFixed(2)));
      var r = o.result || {}, acts = el('div', 'row');
      if (o.note) { c.appendChild(el('div', 'meta err', o.note)); }
      if (o.kind === 'esim' && o.esim) {
        var e = o.esim;
        if (e.qr_code_url && isHttps(e.qr_code_url)) { var im = el('img', 'logo'); im.src = e.qr_code_url; im.alt = 'eSIM QR code'; im.style.height = '120px'; im.style.maxWidth = '120px'; im.referrerPolicy = 'no-referrer'; c.appendChild(im); }
        var bits = []; if (e.status) { bits.push('Status: ' + e.status); } if (e.data_remaining_mb != null) { bits.push(mb(Math.max(0, Number(e.data_remaining_mb))) + ' left'); } if (e.expires_at) { bits.push('expires ' + when(e.expires_at)); }
        if (bits.length) { c.appendChild(el('div', 'meta', bits.join(' \u00b7 '))); }
        if (e.install_link && isHttps(e.install_link)) { var a = el('a', 'mini', 'Install on this phone'); a.href = e.install_link; a.target = '_blank'; a.rel = 'noopener'; acts.appendChild(a); }
        if (e.lpa) { acts.appendChild(copyBtn(String(e.lpa), 'Copy activation code')); }
        var sh = shareBtn('My eSIM', 'My eSIM activation details', e.install_link && isHttps(e.install_link) ? e.install_link : ''); if (sh) { acts.appendChild(sh); }
      } else if (o.kind === 'number') {
        if (r.number) { c.appendChild(el('div', 'num', String(r.number))); acts.appendChild(copyBtn(String(r.number), 'Copy number')); }
        if (r.code) { c.appendChild(el('div', 'code', String(r.code))); acts.appendChild(copyBtn(String(r.code), 'Copy code')); }
        else if (o.status === 'completed') {
          c.appendChild(el('div', 'meta', 'Waiting for the code. If none arrives, you are refunded automatically.'));
          acts.appendChild(mini('Check again', null, function () { box.textContent = ''; paintAccount(); }));
        }
      } else if (o.kind === 'giftcard' && r.receipt && typeof r.receipt === 'object') {
        Object.keys(r.receipt).forEach(function (k) { var v = r.receipt[k]; if (typeof v === 'string' && v) { var row = el('div', 'res'); row.appendChild(el('span', null, k.replace(/_/g, ' ') + ': ' + v)); c.appendChild(row); acts.appendChild(copyBtn(v, 'Copy ' + k.replace(/_/g, ' '))); } });
      }
      if (acts.childNodes.length) { c.appendChild(acts); }
      return c;
    }
    // Control over their own data: a full download, a deletion request, and a sign-out that really revokes the session.
    function dataControls(t) {
      var d = el('div', 'ord'); d.appendChild(el('div', 'name', 'Your data'));
      d.appendChild(el('p', 'fine', 'Download everything we hold about you for this website, ask us to delete it, or sign out of this browser.'));
      var acts = el('div', 'row'), msg = el('p', 'fine'); msg.setAttribute('role', 'status');
      acts.appendChild(mini('Download my data', 'down', function (b, flash) {
        api('/account/export', { token: t.token }).then(function (j) {
          var blob = new Blob([JSON.stringify(j, null, 2)], { type: 'application/json' }), a = document.createElement('a');
          a.href = URL.createObjectURL(blob); a.download = 'my-orders.json'; document.body.appendChild(a); a.click(); document.body.removeChild(a); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000); flash('Downloaded');
        }).catch(function (e) { if (e.status === 401) { expiredSession(); } else { msg.textContent = e.message; } });
      }));
      var del = mini('Ask us to delete my data', null, function () {
        if (!del.armed) { del.armed = true; del.querySelector('span').textContent = 'Tap again to confirm'; setTimeout(function () { del.armed = false; del.querySelector('span').textContent = 'Ask us to delete my data'; }, 5000); return; }
        api('/account/delete-request', { method: 'POST', token: t.token, body: {} }).then(function (j) { msg.textContent = j.message || 'We have your request.'; del.armed = false; del.querySelector('span').textContent = 'Request sent'; }).catch(function (e) { if (e.status === 401) { expiredSession(); } else { msg.textContent = e.message; } });
      }, 'bad');
      acts.appendChild(del);
      acts.appendChild(mini('Sign out', 'out', function () { api('/account/logout', { method: 'POST', token: t.token, body: {} }).catch(function () {}).then(function () { lsDel(TOKEN_STORE); box.textContent = ''; paintAccount(); }); }));
      d.appendChild(acts); d.appendChild(msg); return d;
    }

    this.init = function () {
      beacon('impression');
      var sk = el('div', 'grid'); for (var i = 0; i < 3; i++) { sk.appendChild(el('div', 'sk')); } sk.setAttribute('aria-hidden', 'true'); box.appendChild(sk);
      api('/catalogue' + (KIND ? '?kind=' + encodeURIComponent(KIND) : '')).then(function (j) {
        data = j.data || {}; kindsMeta = j.kinds || []; lookups = j.lookups || {}; accountOn = !!j.account && ACCOUNT_ATTR;
        var th = j.theme || {}, color = safeColor(ATTR_COLOR) || safeColor(th.primary_color) || safeColor(th.color);
        if (ATTR_THEME === 'auto' && (th.mode === 'dark' || th.mode === 'light')) { box.className = 'w ' + (th.mode === 'dark' ? 'dark' : ''); theme = th.mode; }
        if (color) { setBrand(color); }
        if (START === 'account' && accountOn) { screen = 'account'; }
        beacon('catalogue_view', { kind: KIND || 'all' }); paint();
        var back; try { back = sessionStorage.getItem(STORE); } catch (e) {} // returning from the payment provider on a full-page redirect
        if (back) { pending = back; poll(back, 0); }
      }).catch(function () { box.textContent = ''; box.appendChild(notice('This widget is unavailable on this site.', true)); });
    };
  }

  function boot() { new Widget(mountPoint()).init(); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
