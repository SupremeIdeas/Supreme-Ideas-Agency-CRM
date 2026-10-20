// Naara chat x EVERY skin x mode x accent: measures real text contrast (WCAG) on every text element in the chat shell (rail, header, list, thread, cards, composer, bottom bar).
// Usage: node tests/visual/n2n/skin-contrast.mjs [--skins=a,b] [--accents=teal,rose] [--mode=dark|light] [--panel=event|agenda|none]
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const arg = (k, d) => (process.argv.find((a) => a.startsWith('--' + k + '=')) || '').split('=')[1] || d;
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const allSkins = fs.readdirSync('resources/css/nx-skins').filter((f) => !f.startsWith('_')).map((f) => f.replace('.css', ''));
const skins = arg('skins', allSkins.join(',')).split(',');
const accents = arg('accents', 'teal,emerald,graphite,ocean,rose,violet').split(',');
const modes = arg('mode', 'dark,light').split(',');
const sceneArg = arg('scene', 'thread');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 1280, height: 900 } });
const p = await ctx.newPage();
await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
const U = BASE + '/n2n/chat?c=1';
const openMenu = async (item) => { await p.click('button[aria-label="Chat options"]'); await p.click(`.nc__menu >> text=${item}`); await p.waitForTimeout(900); };
const rightClickBubble = async () => { const bs = await p.$$('.ncb--in'); await bs[bs.length - 1].click({ button: 'right' }); await p.waitForTimeout(700); };
const page = (u, wait) => async () => { await p.goto(BASE + u, { waitUntil: 'load' }); if (wait) await p.waitForSelector(wait, { timeout: 30000 }); await p.waitForTimeout(3500); };
const thread = async () => { await p.goto(U, { waitUntil: 'load' }); await p.waitForSelector('.nev', { timeout: 30000 }); await p.waitForTimeout(3000); };
const scenes = {
  thread,
  'panel-search': async () => { await thread(); await openMenu('Search in chat'); await p.fill('.nc__panel input[type=search]', 'Lunch'); await p.waitForTimeout(1200); },
  'panel-starred': async () => { await thread(); await openMenu('Starred messages'); },
  'panel-media': async () => { await thread(); await openMenu('Media, voice and links'); },
  'panel-theme': async () => { await thread(); await openMenu('Chat theme'); },
  'panel-storage': async () => { await thread(); await openMenu('Manage storage'); },
  'panel-scheduled': async () => { await thread(); await openMenu('Scheduled messages'); },
  'panel-disappear': async () => { await thread(); await openMenu('Disappearing messages'); },
  'panel-event': async () => { await thread(); await openMenu('Plan an event'); },
  'panel-agenda': async () => { await thread(); await openMenu('Upcoming events'); },
  menu: async () => { await thread(); await p.click('button[aria-label="Chat options"]'); await p.waitForTimeout(600); },
  ctx: async () => { await thread(); await rightClickBubble(); },
  'ctx-forward': async () => { await thread(); const bs = await p.$$('.ncb--in'); let opened = false; for (const b of bs) { await b.click({ button: 'right' }); await p.waitForTimeout(600); const f = await p.$('.n2nx__menu >> text=Forward'); if (f && await f.isVisible()) { await f.click(); await p.waitForSelector('.nc__panel', { timeout: 5000 }).catch(() => {}); await p.waitForTimeout(900); opened = true; break; } await p.keyboard.press('Escape'); } if (!opened) console.log('forward panel did not open'); },
  select: async () => { await thread(); await p.click('.nc__chips >> text=Select'); await p.waitForTimeout(700); const li = await p.$('.nc__rows li'); if (li) await li.click(); await p.waitForTimeout(500); },
  'list-menu': async () => { await thread(); const li = await p.$('.nc__rows li'); await li.click({ button: 'right' }); await p.waitForTimeout(700); },
  request: page('/n2n/chat?c=2', '.nc__msgs'),
  newchat: page('/n2n/chat?new=1', '.nn__main'),
  more: async () => { await thread(); await p.click('.nn__rail button[aria-label="More"]'); await p.waitForTimeout(900); },
  updates: page('/n2n/updates', '.nn__main'),
  calls: page('/n2n/calls', '.nn__main'),
  people: page('/n2n/people', '.nn__main'),
  credits: page('/credits/n2n', '.nn__main'),
  profile: page('/account/naara-profile', '.nn__main'),
  naaraid: page('/account/naara-id', '.nn__main'),
};
const sceneNames = sceneArg === 'all' ? Object.keys(scenes) : sceneArg.split(',');
const audit = () => {
  const parse = (c) => { const m = c.match(/-?[\d.]+(?:e-?\d+)?/g).map(Number); if (/^color\(/.test(c)) { const sl = c.split('/'); const al = sl[1] ? parseFloat(sl[1]) : 1; const sp = /srgb/.test(c) ? 1 : 1; return { r: m[0] * 255 * sp, g: m[1] * 255 * sp, b: m[2] * 255 * sp, a: al }; } return { r: m[0], g: m[1], b: m[2], a: m[3] === undefined ? 1 : m[3] }; };
  const lin = (v) => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; };
  const L = (c) => .2126 * lin(c.r) + .7152 * lin(c.g) + .0722 * lin(c.b);
  const cr = (a, b2) => { const x = L(a), y = L(b2); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  const over = (top, under) => ({ r: top.r * top.a + under.r * (1 - top.a), g: top.g * top.a + under.g * (1 - top.a), b: top.b * top.a + under.b * (1 - top.a), a: 1 });
  const stops = (bi) => (bi.match(/(?:rgba?|color)\([^)]*\)/g) || []).map(parse);
  const bgOptions = (el) => {
    const layers = []; let e = el;
    while (e && e.nodeType === 1) { const cs = getComputedStyle(e); const c = parse(cs.backgroundColor); if (cs.backgroundImage !== 'none') { const parts = []; let depth = 0, cur = ''; for (const ch of cs.backgroundImage) { if (ch === '(') depth++; if (ch === ')') depth--; if (ch === ',' && depth === 0) { parts.push(cur); cur = ''; } else cur += ch; } parts.push(cur); const sizes = cs.backgroundSize.split(/,\s*(?![^(]*\))/); const pools = []; for (const [pi, part] of parts.entries()) { const sz = (sizes[pi % sizes.length] || '').trim(); const m = sz.match(/^([\d.]+)px 100%$|^100% ([\d.]+)px$/); if (m && parseFloat(m[1] || m[2]) <= 8) continue; /* an edge strip (<= 8px, outside the text padding) is never behind a letter */ const g = stops(part).filter((s) => s.a > .05); if (!g.length) continue; if (/^\s*(repeating-)?radial-gradient/.test(part)) pools.push(...g); else layers.push({ opts: g.slice(0, 4) }); } if (pools.length) layers.push({ opts: pools.slice(0, 8) }); } if (c.a > 0) layers.push({ opts: [c] }); if (c.a >= 1) break; e = e.parentElement; }
    let combos = [{ r: 255, g: 255, b: 255, a: 1 }]; if (document.documentElement.classList.contains('dark')) combos = [{ r: 0, g: 0, b: 0, a: 1 }];
    for (let i = layers.length - 1; i >= 0; i--) { const next = []; for (const u of combos) for (const o of layers[i].opts) next.push(over(o, u)); combos = next.slice(0, 24); }
    return combos;
  };
  const opacityOf = (el) => { let o = 1; for (let e = el; e && e.nodeType === 1; e = e.parentElement) o *= Number(getComputedStyle(e).opacity); return o; };
  const out = []; const root = document.body;
  const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  for (let n = w.nextNode(); n; n = w.nextNode()) {
    const t = n.nodeValue.trim(); if (!t || /^[\p{Extended_Pictographic}\u200d\ufe0f\s]+$/u.test(t)) continue; const el = n.parentElement; if (!el || seen.has(el)) continue; seen.add(el);
    if (el.closest('script,style,.sr-only,[hidden],option,svg,template')) continue;
    const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect(); if (r.width < 2 || r.height < 2 || r.bottom < 0 || r.top > innerHeight + 400) continue;
    if (el.closest('[data-nx-skip-contrast], :disabled, [aria-disabled=true]')) continue;
    const op = opacityOf(el); if (op < .05) continue;   // hover-only / not yet shown
    const fg0 = parse(cs.color);
    let worst = 99; let bgs = bgOptions(el);
    for (const bg of bgs) { const fg = over({ ...fg0, a: fg0.a * op }, bg); worst = Math.min(worst, cr(fg, bg)); }
    const size = parseFloat(cs.fontSize); const bold = Number(cs.fontWeight) >= 700; const need = size >= 24 || (size >= 18.66 && bold) ? 4.5 : size < 14 ? 7 : 5.5;
    if (worst < need) { const cl = (el.className && el.className.baseVal === undefined ? String(el.className) : '').split(/\s+/).filter((c) => /^(n|cc-|ns-)/.test(c))[0] || el.tagName.toLowerCase(); const par = (el.closest('[class*="nc__"],[class*="nn__"],[class*="ncb"],[class*="cc-"]')?.className || '').toString().split(/\s+/)[0]; out.push({ el: cl, par, text: t.slice(0, 28), ratio: Math.round(worst * 100) / 100, need, fg: cs.color, size }); }
  }
  // ---- Non-text contrast (WCAG 1.4.11, 3:1): icons, and the edge of a form field whose fill does not already separate it from what is behind it ----
  const vis = (el) => { const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden' || opacityOf(el) < .05) return false; const r = el.getBoundingClientRect(); return r.width >= 6 && r.height >= 6 && r.bottom > 0 && r.top < innerHeight + 400 && !el.closest('script,style,.sr-only,[hidden],template,:disabled,[aria-disabled=true],[data-nx-skip-contrast]'); };
  const nm = (el) => (el.closest('[class*="nc__"],[class*="nn__"],[class*="ncb"],[class*="cc-"]')?.className || '').toString().split(/\s+/)[0] || el.tagName.toLowerCase();
  for (const svg of document.querySelectorAll('svg')) {
    if (!svg.querySelector('use,path,circle,rect,line,polyline') || !vis(svg)) continue; if (svg.closest('symbol,defs,[data-nx-skip-contrast],[role=img]')) continue; if ([...svg.querySelectorAll('[fill]')].some((n) => { const f = n.getAttribute('fill'); return f && f !== 'none' && f !== 'currentColor' && !f.startsWith('url('); })) continue;   // multi-colour brand artwork (payment logos, flags)
    const cs = getComputedStyle(svg); const fg0 = parse(cs.color); const op = opacityOf(svg); let worst = 99;
    for (const bg of bgOptions(svg)) { const fg = over({ ...fg0, a: fg0.a * op }, bg); worst = Math.min(worst, cr(fg, bg)); }
    if (worst < 3) out.push({ el: 'icon', par: nm(svg), text: '[icon] ' + (svg.querySelector('use')?.getAttribute('href') || svg.querySelector('use')?.getAttribute('xlink:href') || '').slice(0, 22), ratio: Math.round(worst * 100) / 100, need: 3, fg: cs.color, size: 0 });
  }
  for (const f of document.querySelectorAll('input:not([type=hidden]):not([type=color]):not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]),textarea,select,.cc-box,.ns-search,.nc__psearch,[data-nx-field]')) {
    if (!vis(f) || (f.matches('.cc-text') && f.closest('.cc-box'))) continue; const cs = getComputedStyle(f); const par = f.parentElement; if (!par) continue;
    if (f.matches('input,textarea,select') && f.parentElement.closest('.ns-search,.nc__psearch,.cc-box,[data-nx-field],[class*=linkcard]') && parse(cs.backgroundColor).a < .05 && !(parseFloat(cs.borderTopWidth) >= 1 && cs.borderTopStyle !== 'none')) continue;   // the wrapper is the field and is audited itself
    const fills = bgOptions(f), backs = bgOptions(par); let sep = 99; for (const a of fills) for (const b2 of backs) sep = Math.min(sep, cr(a, b2));
    if (sep >= 3) continue;
    let edge = null; const bw = parseFloat(cs.borderTopWidth); if (bw >= 1 && cs.borderTopStyle !== 'none') { const c = parse(cs.borderTopColor); if (c.a > .05) edge = c; }
    if (!edge) { let best = null; for (const m of cs.boxShadow.matchAll(/(rgba?\([^)]*\)|color\([^)]*\))\s+0px\s+0px\s+(?:0px\s+)?(\d+(?:\.\d+)?)px(?:\s+inset)?/g)) { if (parseFloat(m[2]) >= 1) { const c = parse(m[1]); if (c.a > .05 && (!best || c.a >= best.a)) best = c; } } if (best) edge = best; }
    let worst = 99; if (edge) { for (const b2 of backs) { const e2 = over(edge, b2); worst = Math.min(worst, cr(e2, b2)); } } else worst = sep;
    if (worst < 3) out.push({ el: 'border', par: nm(f), text: '[field edge] ' + (f.placeholder || f.name || f.className.toString().split(' ')[0] || f.tagName).toString().slice(0, 18), ratio: Math.round(worst * 100) / 100, need: 3, fg: edge ? 'edge' : 'none', size: 0 });
  }
  return out;
};
await p.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' }).catch(() => {});
const results = []; let total = 0; const perScene = {};
for (const sc of sceneNames) {
  try { await scenes[sc](); } catch (e) { console.log('scene setup failed:', sc, e.message.split('\n')[0]); continue; }
  await p.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' }).catch(() => {});
  const before = results.length;
  for (const mode of modes) for (const s of skins) for (const a of accents) {
    await p.evaluate(([m, s, a]) => { const d = document.documentElement; d.dataset.nxMode = m; d.classList.toggle('dark', m === 'dark'); d.dataset.nxSkin = s; d.dataset.nxAccent = a; }, [mode, s, a]);
    await p.waitForTimeout(100);
    const f = await p.evaluate(audit); total++;
    for (const x of f) results.push({ scene: sc, mode, skin: s, accent: a, ...x });
  }
  perScene[sc] = results.length - before;
}
fs.writeFileSync('test-results/skin-contrast.json', JSON.stringify(results));
const byEl = {}; for (const r of results) { const k = r.scene + ' | ' + r.par + ' > ' + r.el; (byEl[k] ||= { n: 0, min: 99, skins: new Set(), ex: r.text }).n++; byEl[k].min = Math.min(byEl[k].min, r.ratio); byEl[k].skins.add(r.skin); }
console.log(`combinations checked: ${total}   failing text elements: ${results.length}`);
console.log('per scene:', JSON.stringify(perScene));
for (const [k, v] of Object.entries(byEl).sort((a, b2) => b2[1].n - a[1].n).slice(0, 60)) console.log(String(v.n).padStart(5), k.padEnd(56), 'min', v.min, 'skins', v.skins.size, '"' + v.ex + '"');
await b.close();
