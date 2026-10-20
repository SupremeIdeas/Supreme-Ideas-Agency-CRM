// A-Z skin scan of real pages: every URL x skin x accent x mode, with the DOM audit (text, icons, field edges).
//   ROLE=admin|member  URLS=/a,/b  SKINS=neo,glass  ACCENTS=teal  node tests/visual/skins/dom-pages.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import { audit } from './dom-audit.mjs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const role = process.env.ROLE || 'member';
const cred = role === 'admin' ? ['supremeideasz@gmail.com', 'password'] : [process.env.NX_EMAIL || 'frank@n2n.test', 'password'];
const urls = (process.env.URLS || '/dashboard').split(',');
const allSkins = fs.readdirSync('resources/css/nx-skins').filter((f) => !f.startsWith('_')).map((f) => f.replace('.css', ''));
const skins = (process.env.SKINS || allSkins.join(',')).split(',');
const accents = (process.env.ACCENTS || 'teal').split(',');
const modes = (process.env.MODES || 'dark,light').split(',');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await (await b.newContext({ viewport: { width: 430, height: 932 } })).newPage();
await p.goto(BASE + (role === 'admin' ? '/adminmaster/login' : '/login'));
await p.fill('input[name=email]', cred[0]); await p.fill('input[name=password]', cred[1]);
await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
const results = []; const notes = [];
for (const url of urls) {
  try { await p.goto(BASE + url, { waitUntil: 'load', timeout: 40000 }); } catch (e) { notes.push(url + ': load failed'); continue; }
  await p.waitForTimeout(1800);
  if (!p.url().includes(url.split('?')[0].replace(/\/$/, '')) && !url.includes('{')) { notes.push(url + ': redirected to ' + p.url().replace(BASE, '')); }
  const st = await p.evaluate(() => document.title + ' | ' + document.body.innerText.slice(0, 60).replace(/\s+/g, ' ')); const status = /404|Not Found|Server Error|Whoops/i.test(st) ? 'ERROR PAGE' : 'ok';
  if (status !== 'ok') { notes.push(url + ': ' + st); continue; }
  await p.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' }).catch(() => {});
  for (const mode of modes) for (const s of skins) for (const a of accents) {
    await p.evaluate(([m, s2, a2]) => { const d = document.documentElement; d.dataset.nxMode = m; d.classList.toggle('dark', m === 'dark'); d.dataset.nxSkin = s2; d.dataset.nxAccent = a2; }, [mode, s, a]);
    await p.waitForTimeout(60);
    for (const x of await p.evaluate(audit)) results.push({ url, mode, skin: s, accent: a, ...x });
  }
}
fs.mkdirSync('test-results', { recursive: true });
const tag = process.env.TAG || role;
fs.writeFileSync(`test-results/dom-pages-${tag}.json`, JSON.stringify({ results, notes }));
const by = {}; for (const r of results) { const k = r.url + ' | ' + r.par + ' > ' + r.el; (by[k] ||= { n: 0, min: 99, skins: new Set(), ex: r.text }).n++; by[k].min = Math.min(by[k].min, r.ratio); by[k].skins.add(r.skin); }
console.log(`pages ${urls.length} skins ${skins.length} accents ${accents.length}: failing ${results.length}`); for (const n of notes) console.log('NOTE', n);
for (const [k, v] of Object.entries(by).sort((a, b2) => b2[1].n - a[1].n).slice(0, 40)) console.log(String(v.n).padStart(5), k.padEnd(70), 'min', v.min, 'skins', v.skins.size, '"' + v.ex + '"');
await b.close();
