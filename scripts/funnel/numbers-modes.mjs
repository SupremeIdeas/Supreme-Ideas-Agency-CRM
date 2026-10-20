// Probe: Admin > Numbers cards display mode (Modal vs Dedicated page) for Naara Verify / Rent / Line, and what the customer actually gets in each mode.
// Usage: BASE=... ADMIN_PATH=adminheavens OUT=... node scripts/funnel/numbers-modes.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://localhost:8141'; const AP = process.env.ADMIN_PATH || 'adminmaster'; const OUT = process.env.OUT || '/tmp'; const PASS = 'Passw0rd!x'; const res = {}; const errors = [];
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
async function login(email, vp = { width: 1200, height: 900 }) { const c = await b.newContext({ viewport: vp }); const p = await c.newPage(); p.on('pageerror', (e) => errors.push(String(e)));
  await p.goto(BASE + '/login'); const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) await rej.first().click().catch(() => {});
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS); await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login')), p.click('button[type=submit]')]); return p; }
const a = await login('menuadmin@example.com');
await a.goto(`${BASE}/${AP}/numbers-cards`, { waitUntil: 'networkidle' }); await a.waitForTimeout(800); await a.screenshot({ path: `${OUT}/nm-admin.png`, fullPage: true });
const sel = a.locator('select[wire\\:model*="display_mode"]'); res.adminSelects = await sel.count();
res.adminModesBefore = await sel.evaluateAll((e) => e.map((x) => x.getAttribute('wire:model') + '=' + x.value));
for (const mode of ['page', 'modal']) {
  for (let i = 0; i < await sel.count(); i++) await sel.nth(i).selectOption(mode);
  for (const k of ['verify', 'rent', 'line']) { await a.locator(`[wire\\:click*="save('${k}')"], form[wire\\:submit*="save('${k}')"] button[type=submit]`).first().click(); await a.waitForTimeout(1200); }
  res[mode + '_saved'] = await a.locator('select[wire\\:model*="display_mode"]').evaluateAll((e) => e.map((x) => x.value));
  const u = await login('cat@example.com', { width: 390, height: 844 });
  await u.goto(BASE + '/numbers', { waitUntil: 'networkidle' }); await u.waitForTimeout(1000);
  const cards = await u.locator('a[href*="numbers"], [data-bento-card], [wire\\:click*="modal"], [x-on\\:click*="modal"]').evaluateAll((e) => e.map((x) => (x.innerText || '').trim().slice(0, 18) + '|' + (x.getAttribute('href') || x.getAttribute('wire:click') || '')).slice(0, 14));
  res[mode + '_cards'] = cards; await u.screenshot({ path: `${OUT}/nm-user-${mode}.png` });
  for (const key of ['verify', 'rent', 'line']) {
    await u.goto(BASE + '/numbers', { waitUntil: 'networkidle' }); const link = u.locator(`a[href*="modal=${key}"]:visible`).first();
    if (await link.count()) { await link.click(); await u.waitForTimeout(1500); } else { const t = { verify: /Verify/, rent: /Rent/, line: /Line/ }[key]; await u.getByText(t).first().click().catch(() => {}); await u.waitForTimeout(1500); }
    res[`${mode}_${key}`] = { url: u.url().replace(BASE, ''), dialog: await u.locator('[role=dialog], .nx-sheet, [data-modal]').count(), h: (await u.locator('h1,h2').first().innerText().catch(() => '')).slice(0, 40), overflow: await u.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1) };
    await u.screenshot({ path: `${OUT}/nm-${mode}-${key}.png` });
  }
  await u.context().close();
}
console.log(JSON.stringify({ res, errors }, null, 1)); await b.close();
