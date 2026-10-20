// Browser check of the White Label custom-order pages (Funnel Phase 8): buyer request form, buyer order page (quote, accept, pay), admin queue and quote form. Light and dark, phone and desktop.
// Usage: BASE=http://127.0.0.1:8099 BUYER=ada@n2n.test ADMIN=frank@n2n.test PASS=password REF=WLC-XXXXXX OUT=/path node scripts/funnel/custom-orders.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099'; const OUT = process.env.OUT || '/tmp'; const PASS = process.env.PASS; const REF = process.env.REF;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const errors = []; const res = {};
async function login(ctx, email) {
  const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await p.goto(BASE + '/login'); await p.waitForTimeout(800);
  const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
  return p;
}
for (const [name, vp] of [['desktop', { width: 1200, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
  for (const scheme of ['light', 'dark']) {
    const bctx = await b.newContext({ viewport: vp, colorScheme: scheme }); const p = await login(bctx, process.env.BUYER);
    await p.goto(BASE + '/account/white-label-custom', { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
    res[`${name}-${scheme}-form`] = await p.locator('[data-wlco-form]').count();
    await p.screenshot({ path: `${OUT}/wlco-form-${name}-${scheme}.png`, fullPage: true });
    await p.goto(BASE + '/account/white-label-custom/' + REF, { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
    res[`${name}-${scheme}-quote`] = (await p.locator('[data-wlco-total]').textContent().catch(() => '')).trim();
    res[`${name}-${scheme}-overflow`] = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    await p.screenshot({ path: `${OUT}/wlco-order-${name}-${scheme}.png`, fullPage: true });
    await bctx.close();
    const actx = await b.newContext({ viewport: vp, colorScheme: scheme }); const a = await login(actx, process.env.ADMIN);
    await a.goto(BASE + '/adminmaster/white-label/custom-orders', { waitUntil: 'networkidle' }); await a.waitForTimeout(600);
    await a.locator('[data-wlco-queue] li button').nth(Number(process.env.NTH || 0)).click(); await a.waitForTimeout(900);
    res[`${name}-${scheme}-admin-detail`] = await a.locator('[data-wlco-quote-form], [data-wlco-offline], [data-wlco-thread]').count();
    res[`${name}-${scheme}-admin-overflow`] = await a.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    await a.screenshot({ path: `${OUT}/wlco-admin-${name}-${scheme}.png`, fullPage: true }); await actx.close();
  }
}
// the live path: accept, then pay from the wallet as the buyer (SKIPPAY=1 to only take the screenshots)
if (process.env.SKIPPAY) { console.log(JSON.stringify({ res, errors }, null, 1)); await b.close(); process.exit(0); }
const bctx = await b.newContext({ viewport: { width: 1200, height: 900 } }); const p = await login(bctx, process.env.BUYER);
await p.goto(BASE + '/account/white-label-custom/' + REF, { waitUntil: 'networkidle' });
const acc = p.getByRole('button', { name: /accept this quote/i }); if (await acc.count()) { await acc.click(); } await p.waitForSelector('[data-wlco-pay]', { timeout: 10000 });
p.once('dialog', (d) => d.accept()); await p.getByRole('button', { name: /from wallet/i }).click(); await p.waitForSelector('text=Paid from your wallet', { timeout: 10000 });
res.paid_status = (await p.locator('[data-wlco-status]').textContent()).trim(); await p.screenshot({ path: `${OUT}/wlco-paid.png`, fullPage: true });
console.log(JSON.stringify({ res, errors }, null, 1));
const bad = Object.entries(res).filter(([k, v]) => (/overflow/.test(k) && v) || (/form|detail/.test(k) && !v)); if (res.paid_status !== 'Paid') bad.push(['paid_status', res.paid_status]);
console.log(bad.length || errors.length ? 'FAIL ' + JSON.stringify(bad) : 'PASS'); await b.close(); process.exit(bad.length || errors.length ? 1 : 0);
