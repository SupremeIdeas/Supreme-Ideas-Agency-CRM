// Browser check of the composer extras (Gestures G4): send menu (silent), slash menu, colon emoji menu, shortcut sheet, expand toggle.
// Usage: BASE=http://127.0.0.1:8099 EMAIL=... PASS=... OUT=/path node scripts/gestures/composer-extras.mjs   (needs an account with a Naara chat; Pro for voice)
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099'; const OUT = process.env.OUT || '/tmp';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] });
const ctx = await b.newContext({ viewport: { width: 1100, height: 800 }, colorScheme: process.env.SCHEME || 'light', permissions: ['microphone'] });
const p = await ctx.newPage(); const errors = []; const sends = [];
p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
p.on('request', (r) => { if (r.method() === 'POST' && /\/n2n\/.*(send|messages)/.test(r.url())) sends.push({ url: r.url(), body: r.postData() }); });
await p.goto(BASE + '/login'); await p.waitForTimeout(1200);
const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
await p.fill('input[name=email]', process.env.EMAIL); await p.fill('input[name=password]', process.env.PASS);
await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
await p.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' }); await p.waitForTimeout(2500);
await p.locator('.nc__rows > li[data-sel]').first().click(); await p.waitForTimeout(2000);
const ta = p.locator('textarea.cc-text').first(); const res = {};
await ta.click();
await ta.fill('/'); await p.waitForTimeout(400); res.slash_open = await p.locator('.cc-suggest:not([hidden])').count();
await p.screenshot({ path: `${OUT}/cx-slash.png` });
await p.keyboard.press('Escape'); await ta.fill('');
await ta.fill(':smi'); await p.waitForTimeout(400); res.colon_open = await p.locator('.cc-suggest:not([hidden])').count();
await p.screenshot({ path: `${OUT}/cx-colon.png` });
await p.keyboard.press('Enter'); await p.waitForTimeout(300); res.colon_value = await ta.inputValue(); await ta.fill('');
await ta.fill('silent hello ' + Date.now()); await p.waitForTimeout(300);
await p.locator('.cc-send').first().click({ button: 'right' }); await p.waitForTimeout(400);
res.menu_open = await p.locator('.cc-sendmenu:not([hidden])').count();
await p.screenshot({ path: `${OUT}/cx-sendmenu.png` });
await p.locator('.cc-sendmenu .cc-smi.cc-needs').first().click(); await p.waitForTimeout(3500);
res.silent_sent = sends.some((s) => /"silent":true/.test(s.body || ''));
await ta.fill(''); await ta.click(); await p.keyboard.type('?'); await p.waitForTimeout(400);
res.keys_open = await p.locator('#cc-keys').isVisible().catch(() => false); await p.screenshot({ path: `${OUT}/cx-keys.png` });
await p.keyboard.press('Escape'); await p.locator('#cc-keys').evaluate((e) => e.remove()).catch(() => {});
const ex = p.locator('.cc-expand').first(); res.expand_btn = await ex.count();
if (res.expand_btn) { await ex.click(); await p.waitForTimeout(500); res.expanded = await p.locator('.cc-expanded').count(); await p.screenshot({ path: `${OUT}/cx-expanded.png` }); await ex.click(); await p.waitForTimeout(300); res.collapsed = (await p.locator('.cc-expanded').count()) === 0; }
console.log(JSON.stringify({ res, sends: sends.map((s) => s.body?.slice(0, 200)), errors }, null, 1));
const bad = [];
if (!res.slash_open) bad.push('slash menu'); if (!res.colon_open) bad.push('colon menu'); if (!/\p{Extended_Pictographic}/u.test(res.colon_value || '')) bad.push('colon pick'); if (!res.menu_open) bad.push('send menu');
if (!res.silent_sent) bad.push('silent send'); if (!res.keys_open) bad.push('shortcut sheet'); if (!res.expanded || !res.collapsed) bad.push('expand');
console.log(bad.length || errors.length ? 'FAIL: ' + bad.join(', ') : 'PASS'); await b.close(); process.exit(bad.length || errors.length ? 1 : 0);
