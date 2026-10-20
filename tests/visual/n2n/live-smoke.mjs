// N2N live location end to end: Ada starts a 15-minute live share from the composer, Frank sees a live card with Open in Maps, Ada stops, Frank sees it ended.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function login(ctx, email) {
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat?c=1', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__msgs', { timeout: 20000 }); await p.waitForTimeout(2500);
  return p;
}
try {
  for (const scheme of ['dark', 'light']) {
    const geo = { latitude: 6.4541, longitude: 3.3947 };
    const ca = await browser.newContext({ viewport: { width: 1280, height: 860 }, permissions: ['geolocation'], geolocation: geo, colorScheme: scheme });
    const cf = await browser.newContext({ viewport: { width: 1280, height: 860 }, colorScheme: scheme });
    const ada = await login(ca, 'ada@n2n.test'); const frank = await login(cf, 'frank@n2n.test');
    for (const p of [ada, frank]) { await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme); }
    // open the attach menu, pick Location, choose a live duration
    await ada.click('button[aria-label*="ttach" i]'); await ada.waitForTimeout(500);
    await ada.click('.cc-panel >> text=Location'); await ada.waitForTimeout(500);
    const labels = await ada.$$eval('.cc-dlg button, .cc-actions button', (bs) => bs.map((b) => b.textContent.trim()));
    ok(scheme + ': the location choice offers 15 minutes, 1 hour and 8 hours', ['Live for 15 minutes', 'Live for 1 hour', 'Live for 8 hours'].every((l) => labels.includes(l)), JSON.stringify(labels));
    await ada.click('.cc-actions >> text=Live for 15 minutes'); await ada.waitForTimeout(800);
    await ada.waitForSelector('.nc__dock .cc-att', { timeout: 8000 }).catch(() => {}); await ada.screenshot({ path: 'test-results/live-debug.png' }); await ada.click('.nc__dock .cc-send:not([disabled])'); const before = await ada.$$eval('.nlive', (e) => e.length); await ada.waitForFunction((n) => document.querySelectorAll('.nlive').length > n, before, { timeout: 15000 }); await ada.waitForTimeout(2500);
    ok(scheme + ': the sharer sees a live card with Stop sharing', (await ada.isVisible('.nlive >> nth=-1 >> [data-live-stop]')) && (await ada.textContent('.nlive >> nth=-1 >> [data-live-status]')).includes('Sharing with them'));
    ok(scheme + ': no coordinates are written in the message text', !(await ada.content()).includes('6.4541'));
    await frank.waitForFunction((n) => document.querySelectorAll('.nlive').length > n, before, { timeout: 30000 }); await frank.waitForTimeout(4000);
    const href = await frank.getAttribute('.nlive >> nth=-1 >> [data-live-open]', 'href');
    ok(scheme + ': the other member sees Open in Maps with the point', !!href && href.includes('query=6.4541%2C3.3947'), href);
    ok(scheme + ': the other member sees recency and time left', /Updated .* left/.test(await frank.textContent('.nlive >> nth=-1 >> [data-live-status]')));
    await ada.screenshot({ path: `test-results/live-sharer-${scheme}.png` }); await frank.screenshot({ path: `test-results/live-viewer-${scheme}.png` });
    await ada.click('.nlive >> nth=-1 >> [data-live-stop]'); await ada.waitForTimeout(800);
    ok(scheme + ': stopping ends the card for the sharer', (await ada.textContent('.nlive >> nth=-1 >> [data-live-status]')).includes('ended'));
    await frank.waitForFunction(() => [...document.querySelectorAll('.nlive')].pop()?.querySelector('[data-live-status]')?.textContent.includes('ended'), null, { timeout: 25000 }).catch(() => {});
    ok(scheme + ': and for the other member within one poll', (await frank.textContent('.nlive >> nth=-1 >> [data-live-status]')).includes('ended') && !(await frank.isVisible('.nlive >> nth=-1 >> [data-live-open]')));
    await frank.screenshot({ path: `test-results/live-ended-${scheme}.png` });
    await ca.close(); await cf.close();
  }
} finally { await browser.close(); }
