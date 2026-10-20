// Naara Widgets SDK smoke test (MASTER-ONLY). Serves the real public/widgets/merchant-widget.js on a fake merchant page and mocks the API.
// Run: node tests/visual/merchant-widget/widget-smoke.mjs
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');

const js = fs.readFileSync(new URL('../../../public/widgets/merchant-widget.js', import.meta.url), 'utf8');
const BASE = 'https://naara.test';
const catalogue = { data: {
  esim: [{ id: 1, name: 'Roam 5GB', data_mb: 5120, validity_days: 30, countries: ['NG', 'GH'], price_usd: 13 }].concat(['FR', 'DE', 'IT', 'ES', 'PT', 'KE', 'ZA', 'EG', 'MA', 'TN'].map((c, i) => ({ id: 20 + i, name: 'Local ' + c, data_mb: 1024 * (i + 1), validity_days: 7 + i, countries: [c], price_usd: 4 + i }))),
  giftcard: [{ id: 7, brand: 'Spotify', face_currency: 'USD', denominations: { type: 'FIXED', options: [{ face: 10, price_usd: 10.4 }, { face: 25, price_usd: 25.9 }] } }],
}, merchant: { name: 'Sahara' }, theme: { primary_color: '#B45309' } };

const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
const ctx = await browser.newContext({ viewport: { width: 390, height: 800 } });
const page = await ctx.newPage();
const calls = { headers: [], beacons: [], session: null, polls: 0 };

await page.route(BASE + '/widgets/merchant-widget.js', r => r.fulfill({ contentType: 'application/javascript', body: js }));
await page.route(BASE + '/api/v1/merchant-widget/**', async route => {
  const req = route.request(), u = new URL(req.url()), cors = { 'access-control-allow-origin': 'https://mystore.com', 'access-control-allow-headers': '*' };
  if (req.method() === 'OPTIONS') return route.fulfill({ status: 204, headers: cors });
  if (u.pathname.endsWith('/events')) { calls.beaconKeys = (calls.beaconKeys || []).concat(JSON.parse(req.postData() || '{}').key); calls.beacons.push(JSON.parse(req.postData() || '{}').type); return route.fulfill({ status: 204, headers: cors }); }
  calls.headers.push(req.headers()['x-naara-widget-key']);
  if (u.pathname.endsWith('/catalogue')) return route.fulfill({ json: catalogue, headers: cors });
  if (u.pathname.endsWith('/checkout/sessions')) { calls.session = JSON.parse(req.postData()); return route.fulfill({ status: 201, json: { reference: 'ref-1', status: 'pending_payment', checkout_url: BASE + '/widget/checkout/tok' }, headers: cors }); }
  if (u.pathname.includes('/orders/')) { calls.polls++; return route.fulfill({ json: { status: calls.polls > 1 ? 'completed' : 'fulfilling', result: { lpa_string: 'LPA:1$x', qr_code_data: 'ignored' } }, headers: cors }); }
  return route.fulfill({ status: 404, headers: cors });
});
await page.route(BASE + '/widget/checkout/tok', r => r.fulfill({ contentType: 'text/html', body: '<p id=f>checkout</p>' }));
await page.route('https://mystore.com/', r => r.fulfill({ contentType: 'text/html', body:
  `<!doctype html><title>shop</title><h1>My shop</h1><div id="naara-widget"></div><script src="${BASE}/widgets/merchant-widget.js" data-naara-key="wk_live_abc123" data-mode="modal"></script>` }));

await page.goto('https://mystore.com/');
await page.waitForSelector('#naara-widget >> .card');
const root = page.locator('#naara-widget');
ok('renders esim card into shadow DOM', (await root.locator('.name').first().textContent()) === 'Roam 5GB');
ok('uses the merchant theme colour', (await root.locator('.w').evaluate(n => getComputedStyle(n).getPropertyValue('--brand').trim())) === '#B45309');
ok('public key sent in header on every call (in body for beacons)', calls.headers.length > 0 && calls.headers.every(h => h === 'wk_live_abc123') && calls.beaconKeys.every(k => k === 'wk_live_abc123'));
ok('impression + catalogue_view beacons fired', calls.beacons.includes('impression') && calls.beacons.includes('catalogue_view'));
ok('non-removable Powered by Naara footer', (await root.locator('.by a').textContent()) === 'Naara');
await root.locator('.tab', { hasText: 'Gift cards' }).click();
ok('gift card tab shows denominations as buttons', (await root.locator('.den').count()) === 2);
await root.locator('.den').nth(1).click();
ok('picking a value updates the price', (await root.locator('.card .price').first().textContent()) === '$25.90');
await root.locator('.tab', { hasText: 'eSIM' }).click();
ok('plain-language how-it-works strip is shown', (await root.locator('.how li').count()) === 3);
ok('shows a first page of plans with Show more', (await root.locator('.grid .card').count()) === 9 && (await root.locator('.more').isVisible()));
await root.locator('.find input').fill('ghana');
ok('country search finds a plan by country NAME, not just code', (await root.locator('.grid .card').count()) === 1 && (await root.locator('.grid .name').first().textContent()) === 'Roam 5GB');
await root.locator('.find input').fill('zzzz');
ok('no-match state explains itself', (await root.locator('.grid .msg').textContent()).includes('No match'));
await root.locator('.find input').fill('');
await page.screenshot({ path: 'test-results/merchant-widget-390-clay.png' });
await root.locator('.buy').first().click();
await page.waitForSelector('#naara-widget >> iframe');
ok('session body is kind/product/idempotency', calls.session.kind === 'esim' && calls.session.product_id === 1 && /^[A-Za-z0-9_-]{8,64}$/.test(calls.session.idempotency_key));
const src = new URL(await root.locator('iframe').getAttribute('src'));
ok('checkout opened in a modal iframe on the Naara origin, carrying the accent colour', src.origin === BASE && src.pathname === '/widget/checkout/tok' && src.searchParams.get('color') === '#B45309');
ok('modal says it is a secure checkout', (await root.locator('.mh').textContent()).includes('Secure checkout'));
// messages from a foreign origin are ignored; ones from the checkout origin act
await page.evaluate(() => window.postMessage({ source: 'naara', type: 'naara:checkout:complete' }, '*'));
await page.waitForTimeout(300);
ok('postMessage from a foreign origin is ignored', (await root.locator('iframe').count()) === 1);
await page.evaluate(([b]) => { const e = new MessageEvent('message', { data: { source: 'naara', type: 'naara:checkout:complete' }, origin: b }); window.dispatchEvent(e); }, [BASE]);
await page.waitForSelector('#naara-widget >> .msg.ok', { timeout: 15000 });
ok('complete closes modal and shows receipt', (await root.locator('iframe').count()) === 0 && (await root.locator('.res').first().textContent()).includes('LPA:1$x'));
ok('qr payload not dumped as text', !(await root.locator('.w').textContent()).includes('ignored'));
await page.screenshot({ path: 'test-results/merchant-widget-390.png' });
ok('receipt says the order is complete', (await root.locator('.msg.ok b').textContent()).includes('complete'));
await page.emulateMedia({ colorScheme: 'dark' }); await page.waitForTimeout(150);
ok('auto theme follows a dark device', (await root.locator('.w').evaluate(n => getComputedStyle(n).getPropertyValue('--bg').trim())) === '#0b1624');
await page.screenshot({ path: 'test-results/merchant-widget-390-dark.png' });
await browser.close();
const bad = results.filter(r => !r[1]);
process.exit(bad.length ? 1 : 0);
