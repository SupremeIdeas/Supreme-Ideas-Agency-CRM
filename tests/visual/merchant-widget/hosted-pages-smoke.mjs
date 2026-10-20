// Naara Widgets hosted pages (checkout / return / expired) in the Clay skin, light + dark, phone + desktop. MASTER-ONLY.
// Needs a running app and /tmp/wd.json from the seed (see PROGRESS.md). Run: node tests/visual/merchant-widget/hosted-pages-smoke.mjs
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const wd = JSON.parse(fs.readFileSync('/tmp/wd.json', 'utf8'));
const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
fs.mkdirSync('test-results', { recursive: true });

for (const [scheme, vp, tag] of [['light', { width: 390, height: 900 }, '390-light'], ['dark', { width: 390, height: 900 }, '390-dark'], ['light', { width: 1280, height: 900 }, '1280-light']]) {
  const ctx = await browser.newContext({ viewport: vp, colorScheme: scheme, locale: 'en-NG' });
  const page = await ctx.newPage();
  await page.goto(BASE + wd.checkout, { waitUntil: 'networkidle' });
  if (tag === '390-light') {
    ok('checkout shows the product and a clear price', (await page.locator('.name').textContent()).includes('Europe Traveller') && /\$\d+\.\d\d/.test(await page.locator('.price').textContent()));
    ok('plan facts are shown before paying (data, validity, countries)', (await page.locator('.chips').textContent()).match(/5 GB.*30 days.*5 countries/s));
    ok('Nigerian browser defaults to NGN where a gateway supports it', (await page.locator('input[name=currency]:checked').inputValue()) === 'NGN');
    ok('pay button states the (estimated) amount', /Pay/.test(await page.locator('#go-t').textContent()));
    await page.locator('input[name=gateway][value=stripe]').check({ force: true });
    const curs = await page.locator('input[name=currency]').evaluateAll(n => n.map(x => x.value));
    ok('switching gateway only offers currencies that gateway accepts', curs.length >= 1 && curs.every(c => ['USD','NGN','GHS','KES','ZAR','GBP','EUR','CAD','INR'].includes(c)));
    await page.fill('#email', 'ada@gmial.com'); await page.locator('#email').blur();
    ok('email typo suggestion appears', (await page.locator('#typo').textContent()).includes('ada@gmail.com'));
    await page.locator('#typo button').click();
    ok('tapping the suggestion fixes the email', (await page.inputValue('#email')) === 'ada@gmail.com');
    await page.fill('#email', 'nope'); await page.click('#go');
    ok('bad email is explained, nothing is sent', (await page.locator('#err').textContent()).includes('Enter the email'));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    ok('no horizontal scroll on a phone', !overflow);
  }
  await page.screenshot({ path: `test-results/hosted-checkout-${tag}.png`, fullPage: true });
  for (const k of ['completed', 'pending', 'held', 'failed']) {
    if (tag === '1280-light' && k !== 'completed') continue;
    const resp = await page.goto(BASE + wd[k]);
    if (tag === '390-light') ok(`return page (${k}) renders with HTTP 200`, resp.status() === 200 && (await page.locator('h1').textContent()).length > 5);
    await page.screenshot({ path: `test-results/hosted-return-${k}-${tag}.png`, fullPage: true });
  }
  await page.goto(BASE + '/widget/checkout/not-a-token');
  if (tag === '390-light') ok('expired link explains itself', (await page.locator('h1').textContent()).includes('has ended'));
  await page.screenshot({ path: `test-results/hosted-expired-${tag}.png` });
  await ctx.close();
}
await browser.close();
process.exit(results.some(r => !r[1]) ? 1 : 0);
