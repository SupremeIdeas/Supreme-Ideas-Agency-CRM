// End-to-end check of the White Label funnel, buyer to licence: wizard (7 steps) -> pay page (manual transfer, proof upload) -> admin approves -> buyer sees it paid.
// Setup: scratch DB + `php scripts/funnel/seed-e2e.php`, server running. Usage: BASE=http://localhost:8141 ADMIN_PATH=adminheavens OUT=/path node scripts/funnel/e2e.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const BASE = process.env.BASE || 'http://localhost:8141'; const AP = process.env.ADMIN_PATH || 'adminmaster'; const OUT = process.env.OUT || '/tmp'; const PASS = 'Passw0rd!x';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const errors = []; const res = {}; const shot = (p, n) => p.screenshot({ path: `${OUT}/wlfunnel-${n}.png`, fullPage: true });
async function login(ctx, email) {
  const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text()); });
  await p.goto(BASE + '/login'); const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) await rej.first().click().catch(() => {});
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]); return p;
}
const main = (p) => p.locator('main');
const next = async (p) => { await main(p).getByRole('button', { name: /^(next|continue|review)/i }).first().click(); await p.waitForTimeout(1100); };
const png = '/tmp/wl-proof.png'; fs.writeFileSync(png, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));
const buyerCtx = await b.newContext({ viewport: { width: 1200, height: 900 } }); const p = await login(buyerCtx, 'wlbuyer@example.com');
await p.goto(BASE + '/white-label/start?plan=basic'); await p.waitForTimeout(800);
await p.goto(BASE + '/account/white-label-purchase', { waitUntil: 'networkidle' });
await main(p).locator('input[type=text]').first().fill('Zenith Cloud'); await next(p); res.step2 = /Pick your colours/.test(await main(p).innerText());
await main(p).locator('input[value=naara-official]').check({ force: true }); await next(p);
await main(p).locator('input[value=supreme_ideas_server]').check({ force: true }); await next(p);
await main(p).locator('select').selectOption({ label: 'Email' }); await next(p);
const fillBilling = async () => { const m = main(p); await m.getByLabel(/Name or company/i).fill('Zenith Cloud Ltd'); await m.getByLabel(/Billing address/i).fill('1 Marina Road, Lagos'); await m.getByLabel(/Country/i).fill('NG'); await m.getByLabel(/Billing email/i).fill('billing@zenith.test'); };
await fillBilling(); await next(p);
await next(p); res.review = /everything/i.test(await main(p).innerText());
await main(p).getByText(/^Pay now$/).first().click(); await p.waitForURL(/white-label-pay/, { timeout: 15000 }); await p.waitForTimeout(1500); res.payPage = p.url();
await main(p).getByRole('button', { name: /^USD$/ }).click(); await p.waitForTimeout(1500);
res.quote = /1,500\.00 USD/.test(await main(p).innerText()); res.reference = (await main(p).innerText()).match(/WL-BS-[A-Z0-9*~$=]+-[A-Z0-9]+/)?.[0];
const pf = await buyerCtx.request.get(BASE + '/account/white-label-pay/proforma'); res.proforma = pf.status() + ' ' + (pf.headers()['content-type'] || '') + ' ' + (await pf.body()).subarray(0, 5).toString();
await shot(p, 'pay-quote');
await main(p).locator('input[type=file]').setInputFiles(png); await main(p).locator('input').nth(0).fill('Zenith Cloud Ltd');
await main(p).getByLabel(/Name on the paying account/i).fill('Zenith Cloud Ltd'); await main(p).getByRole('button', { name: /Send proof/ }).click(); await p.waitForTimeout(2500);
res.afterProof = (await main(p).innerText()).replace(/\n+/g, ' | ').slice(0, 500); await shot(p, 'after-proof');
// admin: review and approve
const actx = await b.newContext({ viewport: { width: 1200, height: 900 } }); const a = await login(actx, 'wladmin@example.com');
await a.goto(`${BASE}/${AP}/white-label/payment-review`, { waitUntil: 'networkidle' }); await a.waitForTimeout(800);
await a.locator('button[wire\\:click^="view("]').first().click(); await a.waitForTimeout(1200); await shot(a, 'admin-detail');
res.checksPassed = (await a.locator('body').innerText()).match(/Pass:/g)?.length || 0;
await a.getByLabel(/Amount that arrived/i).fill('1500'); a.on('dialog', (d) => d.accept()); await a.getByRole('button', { name: /^Approve$/ }).click(); await a.waitForTimeout(2500); await shot(a, 'admin-approved');
res.queueEmpty = /Waiting for review \(0\)|Nothing waiting|No payments/i.test(await a.locator('body').innerText()) || (await a.locator('button[wire\\:click^="view("]').count()) === 0;
// buyer: the purchase is paid and the setup (server details / next step) opens
await p.goto(BASE + '/account/white-label-pay', { waitUntil: 'networkidle' }); res.buyerPay = (await main(p).innerText()).replace(/\n+/g, ' | ').slice(0, 300); await shot(p, 'buyer-after-approval');
await p.goto(BASE + '/account/white-label-setup', { waitUntil: 'networkidle' }); res.buyerSetup = (await p.locator('body').innerText()).replace(/\n+/g, ' | ').slice(0, 400); res.setupStatus = (await p.goto(BASE + '/account/white-label-setup')).status(); await shot(p, 'buyer-setup');
const docs = await buyerCtx.request.get(BASE + '/account/white-label-balance'); res.balanceStatus = docs.status();
console.log('URL', p.url()); await shot(p, 'after-wizard');
console.log(JSON.stringify({ res, errors }, null, 1));
const bad = []; if (!res.quote) bad.push('quote'); if (!/^200 application\/pdf %PDF/.test(res.proforma)) bad.push('proforma'); if (res.checksPassed < 3) bad.push('review checks'); if (!/paid|approved|licen[cs]e|thank|setup/i.test(res.buyerPay + res.buyerSetup)) bad.push('buyer not paid'); if (errors.length) bad.push('console errors');
console.log(bad.length ? 'FAIL ' + bad.join(', ') : 'PASS'); await b.close(); process.exit(bad.length ? 1 : 0);
