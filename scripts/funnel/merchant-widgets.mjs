// Browser check of Naara Widgets: merchant analytics + paywall, admin pricing. Light/dark, desktop/phone.
// Usage: BASE=http://127.0.0.1:8123 PASS=password OUT=dir node scripts/funnel/merchant-widgets.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8123'; const OUT = process.env.OUT || '/tmp'; const PASS = process.env.PASS || 'password';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const errors = []; const res = {};
async function login(ctx, email) {
  const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e)));
  await p.goto(BASE + '/login'); await p.waitForTimeout(600);
  const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
  return p;
}
for (const [name, vp] of [['desktop', { width: 1280, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
  for (const scheme of ['light', 'dark']) {
    const ctx = await b.newContext({ viewport: vp, colorScheme: scheme }); const p = await login(ctx, 'mer@example.com');
    await p.goto(BASE + '/merchant/widgets', { waitUntil: 'networkidle' }); await p.waitForTimeout(700);
    res[`${name}-${scheme}-analytics`] = await p.locator('[data-mw-analytics]').count();
    res[`${name}-${scheme}-paywall`] = await p.locator('[data-mw-paywall]').count();
    res[`${name}-${scheme}-overflow`] = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    await p.locator('[data-mw-analytics]').scrollIntoViewIfNeeded().catch(() => {});
    await p.locator('[data-mw-analytics]').screenshot({ path: `${OUT}/mw-analytics-${name}-${scheme}.png` }).catch(() => {});
    if (name === 'desktop' && scheme === 'light') { await p.getByRole('button', { name: '7 days' }).click(); await p.waitForTimeout(800); res.range7 = await p.locator('.mw-an__seg button[aria-pressed=true]').textContent(); }
    await ctx.close();
  }
}
const actx = await b.newContext({ viewport: { width: 1280, height: 900 } }); const a = await login(actx, 'adm@example.com');
await a.goto(BASE + '/adminmaster/merchant-widgets', { waitUntil: 'networkidle' }); await a.waitForTimeout(600);
res.admin_pricing = await a.locator('[data-mw-pricing]').count();
await a.locator('[data-mw-pricing]').screenshot({ path: `${OUT}/mw-admin-pricing-free.png` });
await a.locator('[data-mw-pricing] input[type=checkbox]').check(); await a.fill('#mw-price', '25'); await a.getByRole('button', { name: 'Save settings' }).click(); await a.waitForTimeout(1000);
await a.locator('[data-mw-pricing]').screenshot({ path: `${OUT}/mw-admin-pricing-paid.png` });
await actx.close();
// merchant now sees the paywall
const mctx = await b.newContext({ viewport: { width: 1280, height: 900 } }); const m = await login(mctx, 'mer@example.com');
await m.goto(BASE + '/merchant/widgets', { waitUntil: 'networkidle' }); await m.waitForTimeout(600);
res.paywall_after_paid = await m.locator('[data-mw-paywall]').count();
await m.screenshot({ path: `${OUT}/mw-paywall.png`, fullPage: false });
console.log(JSON.stringify({ res, errors }, null, 1)); await b.close();
