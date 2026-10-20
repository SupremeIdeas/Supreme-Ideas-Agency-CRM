// Read-only browse of the Zendit-backed storefronts as a customer: gift cards (pick a brand and an amount up to the confirm step) and eSIM plans. NEVER presses a final pay/buy button. Usage: BASE=... USER=cat@example.com PASS=... OUT=... node scripts/funnel/zendit-browse.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://localhost:8141'; const OUT = process.env.OUT || '/tmp'; const res = {}; const errors = [];
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
for (const scheme of ['light', 'dark']) {
  const c = await b.newContext({ viewport: { width: 390, height: 844 }, colorScheme: scheme }); const p = await c.newPage();
  p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text()); });
  await p.goto(BASE + '/login'); const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) await rej.first().click().catch(() => {});
  await p.fill('input[name=email]', process.env.USER_EMAIL || 'cat@example.com'); await p.fill('input[name=password]', process.env.PASS || 'Passw0rd!x');
  await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login')), p.click('button[type=submit]')]);
  await p.goto(BASE + '/gift-cards', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
  res[scheme + '_gift_cards'] = await p.locator('[wire\\:click^="select("]').count(); await p.screenshot({ path: `${OUT}/zendit-gift-list-${scheme}.png` });
  await p.locator('[wire\\:click^="select("]').filter({ hasText: /Netflix USA/ }).first().click(); await p.waitForTimeout(1500);
  const body = await p.locator('body').innerText(); res[scheme + '_detail_has_amounts'] = /\$\s?\d/.test(body); res[scheme + '_cost_leak'] = /cost[_ ]price|net[_ ]price|margin/i.test(body);
  res[scheme + '_overflow'] = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1); await p.screenshot({ path: `${OUT}/zendit-gift-detail-${scheme}.png`, fullPage: true });
  if (scheme === 'light') console.log('DETAIL>', body.replace(/\n+/g, ' | ').slice(0, 700), await p.locator('button:visible').evaluateAll((e) => e.map((x) => x.innerText.trim().slice(0, 28) + '#' + (x.getAttribute('wire:click') || '')).slice(0, 20)));
  await p.goto(BASE + '/catalogue', { waitUntil: 'networkidle' }); await p.waitForTimeout(1000); res[scheme + '_esim_overflow'] = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1); await p.screenshot({ path: `${OUT}/zendit-esim-${scheme}.png` });
  await c.close();
}
console.log(JSON.stringify({ res, errors }, null, 1)); await b.close();
