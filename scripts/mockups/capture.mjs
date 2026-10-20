// Snap real dashboard (or marketing) screens for the mockup library.
//   BASE=http://127.0.0.1:8766 node scripts/mockups/capture.mjs <shots.json> [outDir]
// shots.json = [{ "id": "home-step1", "path": "/catalogue", "w": 390, "h": 780, "dpr": 3, "mode": "light", "login": true,
//                 "scrollTo": "selector", "click": ["selector"], "hide": ["selector"], "wait": 600, "full": false }]
// Output: <outDir>/<id>.png at w*dpr by h*dpr. The window is sized to the target mockup's real screen aspect, so nothing is cropped later.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
let chromium;
try { ({ chromium } = require('playwright')); } catch { ({ chromium } = require(process.env.PW_MODULE || '/opt/node22/lib/node_modules/playwright')); }
const BASE = process.env.BASE || 'http://127.0.0.1:8766';
const CHROME = process.env.CHROME || '/opt/pw-browsers/chromium';
const EMAIL = process.env.MK_EMAIL || 'emily@naara.test', PASS = process.env.MK_PASSWORD || 'password';
const shots = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const outDir = process.argv[3] || '/tmp/mk/shots';
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({ executablePath: CHROME });
const sessions = new Map();
async function ctxFor(s) {
  const key = [s.w, s.h, s.dpr || 2, s.mode || 'light', s.login !== false].join('|');
  if (sessions.has(key)) return sessions.get(key);
  const ctx = await browser.newContext({ viewport: { width: s.w, height: s.h }, deviceScaleFactor: s.dpr || 2, colorScheme: s.mode || 'light', isMobile: s.w < 600, hasTouch: s.w < 600 });
  await ctx.addInitScript((m) => { try { localStorage.setItem('theme', m); } catch (e) {} }, s.mode || 'light');
  if (s.login !== false) {
    const p = await ctx.newPage();
    await p.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await p.fill('input[name="email"]', EMAIL); await p.fill('input[name="password"]', PASS);
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), p.click('button[type="submit"]')]);
    await p.close();
  }
  sessions.set(key, ctx); return ctx;
}
for (const s of shots) {
  const ctx = await ctxFor(s); const p = await ctx.newPage();
  p.on('pageerror', (e) => console.log('PAGEERROR', s.id, e.message));
  await p.goto(BASE + s.path, { waitUntil: 'networkidle' });
  if (s.mode === 'dark') await p.evaluate(() => document.documentElement.classList.add('dark'));
  // Floating helpers (wizard, journey, support bubble) and the splash never belong in a product shot.
  const base = ['#nx-preloader', '.nn-fab', '.fixed.right-4', '.fixed.left-4', '[x-data*="wizHidden"]', '[x-data*="jlHidden"]'];
  for (const sel of [...base, ...(s.hide || [])]) await p.addStyleTag({ content: `${sel}{display:none !important}` });
  for (const sel of s.click || []) { await p.click(sel); await p.waitForTimeout(400); }
  if (s.scrollTo) await p.evaluate(([sel, off]) => { const el = document.querySelector(sel); if (el) { el.scrollIntoView({ block: 'start', behavior: 'instant' }); window.scrollBy({ top: -(off || 0), behavior: 'instant' }); } }, [s.scrollTo, s.scrollOffset]);
  if (s.scrollY) await p.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), s.scrollY);
  await p.waitForTimeout(s.wait ?? 700);
  const file = path.join(outDir, s.id + '.png');
  await p.screenshot({ path: file, fullPage: !!s.full });
  console.log('shot', s.id, p.url().replace(BASE, ''));
  await p.close();
}
await browser.close();
