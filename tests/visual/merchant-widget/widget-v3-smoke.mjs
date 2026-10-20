// Naara Widgets SDK v3 (kind tabs, verification-number flow, live code, My orders + data controls). MASTER-ONLY.
// Self-contained: serves the SDK from public/ on a throwaway port and mocks the widget API in the browser, so it needs no database.
// Run: node tests/visual/merchant-widget/widget-v3-smoke.mjs   (screenshots land in test-results/)
import fs from 'node:fs';
import http from 'node:http';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const sdk = fs.readFileSync('public/widgets/merchant-widget.js');
const srv = http.createServer((req, res) => {
  if (req.url.startsWith('/widgets/merchant-widget.js')) { res.setHeader('Content-Type', 'text/javascript'); return res.end(sdk); }
  res.setHeader('Content-Type', 'text/html'); res.end('<!doctype html><meta name=viewport content="width=device-width,initial-scale=1"><body style="margin:0;padding:16px"><script src="/widgets/merchant-widget.js" data-naara-key="wk_live_test" defer></script>');
}).listen(0);
const BASE = 'http://localhost:' + srv.address().port;

const catalogue = {
  data: {
    esim: [
      { id: 1, name: 'Europe 5GB', type: 'regional', has_voice: false, data_mb: 5120, validity_days: 30, countries: ['FR', 'DE', 'ES'], price_usd: 14.5, currency: 'USD' },
      { id: 2, name: 'Ghana 1GB', type: 'local', has_voice: false, data_mb: 1024, validity_days: 7, countries: ['GH'], price_usd: 3.2, currency: 'USD' },
      { id: 3, name: 'World 20GB', type: 'global', has_voice: true, data_mb: 20480, validity_days: 60, countries: Array(40).fill('US'), price_usd: 49, currency: 'USD' },
    ],
    number: [{ id: 'whatsapp', name: 'WhatsApp' }, { id: 'telegram', name: 'Telegram' }, { id: 'google', name: 'Google' }],
    giftcard: [{ id: 9, brand: 'Spotify', logo_url: null, category: 'Music', face_currency: 'USD', denominations: { type: 'FIXED', options: [{ face: 10, price_usd: 10.4 }] }, currency: 'USD' }],
  },
  kinds: [
    { kind: 'esim', title: 'Travel eSIM data', blurb: 'Mobile data for your trip. No SIM swap.', count: 3 },
    { kind: 'number', title: 'Verification numbers', blurb: 'A temporary phone number to receive an SMS code.', count: 3 },
    { kind: 'giftcard', title: 'Gift cards', blurb: 'Digital gift cards for top brands.', count: 1 },
  ],
  lookups: { number_countries: [{ code: 'nigeria', name: 'Nigeria', dial: '234' }, { code: 'ghana', name: 'Ghana', dial: '233' }, { code: 'usa', name: 'United States', dial: '1' }], number_refund_minutes: 15 },
  account: true, merchant: { name: 'Demo Shop' }, theme: {},
};

async function mock(page, state) {
  await page.route('**/api/v1/merchant-widget/**', async (route) => {
    const u = new URL(route.request().url()), p = u.pathname.replace('/api/v1/merchant-widget', ''), m = route.request().method();
    const json = (b, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(b) });
    if (p === '/catalogue') return json(catalogue);
    if (p === '/events') return json({});
    if (p === '/number-quote') return json(u.searchParams.get('country') === 'usa' ? { available: false, message: 'No number is available for that service in that country right now. Try another country.' } : { available: true, price_usd: 0.92, currency: 'USD', service: 'WhatsApp', country: 'Nigeria', refund_minutes: 15 });
    if (p === '/account/code') { state.codeAsked = true; return json({ sent: true, minutes: 10 }); }
    if (p === '/account/verify') { const b = JSON.parse(route.request().postData()); return b.code === '123456' ? json({ ok: true, token: 'x'.repeat(56), expires_at: new Date(Date.now() + 864e5).toISOString() }) : json({ ok: false, message: 'That code is not right, or it has expired. Ask for a new one.' }, 422); }
    if (p === '/account/orders') { state.sawToken = route.request().headers()['x-naara-shopper']; return json({ email: 'ada@example.com', orders: [
      { reference: 'wo_1', kind: 'esim', status: 'completed', title: 'Europe 5GB', price_usd: 14.5, created_at: new Date().toISOString(), result: {}, esim: { status: 'active', data_remaining_mb: 4096, expires_at: new Date(Date.now() + 864e6).toISOString(), qr_code_url: null, lpa: 'LPA:1$smdp.io$ABC', install_link: 'https://esimsetup.apple.com/esim_qrcode_provisioning?carddata=LPA:1$smdp.io$ABC' } },
      { reference: 'wo_2', kind: 'number', status: 'completed', title: 'WhatsApp number (Nigeria)', price_usd: 0.92, created_at: new Date().toISOString(), result: { number: '+2348012345678', code: '482913', waiting: false } },
      { reference: 'wo_3', kind: 'number', status: 'refunded', title: 'Telegram number (Ghana)', price_usd: 0.8, created_at: new Date().toISOString(), result: null, note: 'This order did not complete. Anything you paid has been returned to your Naara wallet balance.' },
    ] }); }
    if (p === '/account/export') { state.exported = true; return json({ email: 'ada@example.com', orders: [] }); }
    if (p === '/account/delete-request') { state.deleted = true; return json({ requested: true, status: 'open', message: 'We have your request. Our team will review it and email you when it is done.' }); }
    if (p === '/account/logout') { state.loggedOut = true; return json({ ok: true }); }
    if (p === '/checkout/sessions') { state.session = JSON.parse(route.request().postData()); return json({ reference: 'wo_live', checkout_url: BASE + '/widget/checkout/tok' }); }
    if (p === '/orders/wo_live/status') { state.polls = (state.polls || 0) + 1; return json({ reference: 'wo_live', status: 'completed', kind: 'number', result: { kind: 'number', number: '+2348099999999', service: 'WhatsApp', country: 'Nigeria', code: state.polls > 2 ? '771204' : null, waiting: state.polls <= 2, expires_at: new Date(Date.now() + 14 * 6e4).toISOString(), refund_minutes: 15 } }); }
    return json({}, 404);
  });
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
fs.mkdirSync('test-results', { recursive: true });
for (const [scheme, vp, tag] of [['light', { width: 390, height: 900 }, '390-light'], ['dark', { width: 390, height: 900 }, '390-dark'], ['light', { width: 1100, height: 900 }, '1100-light'], ['dark', { width: 1100, height: 900 }, '1100-dark']]) {
  const ctx = await browser.newContext({ viewport: vp, colorScheme: scheme, locale: 'en-NG' });
  await ctx.grantPermissions([]);
  const page = await ctx.newPage(); const state = {};
  await mock(page, state);
  const w = (sel) => page.locator('#naara-widget ' + sel);   // shadow DOM pierces with css locators in playwright
  await page.goto(BASE + '/'); await page.waitForSelector('#naara-widget .kind');
  const first = tag === '390-light';
  if (first) {
    ok('kind tabs show plain-language titles in fixed order', (await page.locator('#naara-widget .kind b').allTextContents()).join('|') === 'Travel eSIM data|Verification numbers|Gift cards');
    ok('active kind states what you are buying', (await page.locator('#naara-widget .sub').textContent()).includes('Mobile data'));
    await page.locator('#naara-widget .sort').selectOption('price');
    ok('eSIM sort by price puts the cheapest first', (await page.locator('#naara-widget .card .price').first().textContent()) === '$3.20');
    await page.locator('#naara-widget .sort').selectOption('data');
    ok('eSIM sort by data puts the biggest first', (await page.locator('#naara-widget .card .big').first().textContent()).includes('20 GB'));
  }
  await page.screenshot({ path: `test-results/widget-v3-esim-${tag}.png`, fullPage: true });
  await page.locator('#naara-widget .kind', { hasText: 'Verification numbers' }).click();
  await page.waitForFunction(() => { const r = document.querySelector('#naara-widget').shadowRoot; const p = r && r.querySelector('.quote .price'); return p && p.textContent !== '\u2026'; });
  if (first) {
    ok('number tab explains the product and the 3 steps', (await page.locator('#naara-widget .how').textContent()).includes('Get your code here'));
    ok('browser locale pre-selects the shopper country', (await page.locator('#naara-widget select[aria-label=Country]').inputValue()) === 'nigeria');
    console.log('DBG', await page.locator('#naara-widget .quote .price').textContent(), await page.locator('#naara-widget .buy').getAttribute('class'));
    ok('live price appears and buy is enabled', (await page.locator('#naara-widget .quote .price').textContent()) === '$0.92' && !(await page.locator('#naara-widget .buy').getAttribute('class')).includes('off'));
    await page.locator('#naara-widget select[aria-label=Country]').selectOption('usa');
    await page.waitForSelector('#naara-widget .quote .price:text("Not available")');
    ok('unavailable pick says so and blocks buying', (await page.locator('#naara-widget .buy').getAttribute('class')).includes('off'));
    await page.locator('#naara-widget select[aria-label=Country]').selectOption('nigeria');
    await page.waitForFunction(() => document.querySelector('#naara-widget').shadowRoot.querySelector('.quote .price').textContent === '$0.92');
  }
  await page.screenshot({ path: `test-results/widget-v3-number-${tag}.png`, fullPage: true });
  if (first) {
    await page.locator('#naara-widget .buy').click();
    await page.waitForFunction(() => true); await page.waitForTimeout(400);
    ok('number checkout posts service + country, no product id', state.session && state.session.kind === 'number' && state.session.service === 'whatsapp' && state.session.country === 'nigeria' && state.session.product_id === undefined);
    // the hosted page finishes; simulate the postMessage the checkout frame sends
    await page.evaluate((origin) => { window.postMessage({ source: 'naara', type: 'naara:checkout:complete' }, '*'); }, BASE);
    // message origin must be the checkout origin; the page origin equals BASE so this is accepted
    await page.waitForSelector('#naara-widget #num-receipt', { timeout: 8000 });
    ok('number receipt shows the number and a waiting state', (await page.locator('#naara-widget .num').textContent()).includes('+234') && (await page.locator('#naara-widget #num-code').textContent()).includes('Waiting'));
    await page.screenshot({ path: `test-results/widget-v3-waiting-${tag}.png`, fullPage: true });
    await page.waitForSelector('#naara-widget .code', { timeout: 20000 });
    ok('the code appears by itself when it arrives', (await page.locator('#naara-widget .code').textContent()) === '771204');
    ok('copy buttons offered for number and code', (await page.locator('#naara-widget #num-acts .mini').allTextContents()).join('|').includes('Copy code'));
    await page.screenshot({ path: `test-results/widget-v3-code-${tag}.png`, fullPage: true });
  }
  // My orders
  await page.reload(); await page.waitForSelector('#naara-widget .acct');
  await page.locator('#naara-widget .acct').click();
  await page.waitForSelector('#naara-widget .frm input[type=email]');
  if (first) { ok('sign-in explains no password and is private', (await page.locator('#naara-widget .frm .fine').last().textContent()).includes('Nobody can find out')); }
  await page.fill('#naara-widget .frm input[type=email]', 'ada@example.com'); await page.locator('#naara-widget .frm .buy').click();
  await page.waitForSelector('#naara-widget input.otp');
  await page.fill('#naara-widget input.otp', '000000'); await page.locator('#naara-widget .frm .buy').click();
  await page.waitForSelector('#naara-widget .frm p.err:not(:empty)');
  if (first) ok('wrong code is explained and keeps you on the form', (await page.locator('#naara-widget .frm p.err').textContent()).includes('not right'));
  await page.screenshot({ path: `test-results/widget-v3-signin-${tag}.png`, fullPage: true });
  await page.fill('#naara-widget input.otp', '123456'); await page.locator('#naara-widget .frm .buy').click();
  await page.waitForSelector('#naara-widget .ord');
  if (first) {
    ok('shopper token travels in the header only', state.sawToken && state.sawToken.length === 56);
    const t = await page.evaluate(() => document.querySelector('#naara-widget').shadowRoot.textContent);
    ok('orders list shows eSIM, number with code, and the refunded note', t.includes('Europe 5GB') && t.includes('482913') && t.includes('returned to your Naara wallet'));
    ok('data controls: download, delete, sign out', ['Download my data', 'Ask us to delete my data', 'Sign out'].every((s) => t.includes(s)));
    const [dl] = await Promise.all([page.waitForEvent('download'), page.locator('#naara-widget .mini', { hasText: 'Download my data' }).click()]);
    ok('data export downloads a JSON file', dl.suggestedFilename() === 'my-orders.json' && state.exported);
    const del = page.locator('#naara-widget .mini.bad');
    await del.click(); ok('deletion needs a second tap', !state.deleted);
    await del.click(); await page.waitForTimeout(300); ok('deletion request is sent after confirming', state.deleted);
    ok('no horizontal scroll on a phone', !(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)));
  }
  await page.screenshot({ path: `test-results/widget-v3-orders-${tag}.png`, fullPage: true });
  if (first) {
    await page.locator('#naara-widget .mini', { hasText: 'Sign out' }).click(); await page.waitForSelector('#naara-widget .frm input[type=email]');
    ok('sign out revokes server-side and returns to sign-in', state.loggedOut);
    ok('token is gone from the browser', await page.evaluate(() => !Object.keys(localStorage).some((k) => k.startsWith('naara_widget_shopper'))));
  }
  await ctx.close();
}
await browser.close(); srv.close();
process.exit(results.some((r) => !r[1]) ? 1 : 0);
