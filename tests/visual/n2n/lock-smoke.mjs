// N2N chat lock end to end on a phone: lock a chat from the row menu (sets a PIN), see it vanish, open it from the Locked filter with the PIN, then restore.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function hold(p, sel, ms = 650) {
  const el = (await p.$$(sel))[0]; await el.scrollIntoViewIfNeeded(); const b = await el.boundingBox();
  const c = await p.context().newCDPSession(p);
  await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: b.x + b.width / 2, y: b.y + Math.min(b.height / 2, 14) }] }); await p.waitForTimeout(ms);
  await c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
}
try {
  for (const scheme of ['dark', 'light']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
    await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button'); await p.waitForTimeout(3200);
    await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
    await hold(p, '.nc__rows li[data-ctx]'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    await p.click('.n2nx__menu >> text=Lock chat'); await p.waitForSelector('.nc__sheet', { state: 'visible' });
    ok(scheme + ': locking with no PIN opens the set-PIN sheet', (await p.textContent('.nc__sheet h3')).includes('Set a chat PIN'));
    await p.screenshot({ path: `test-results/lock-sheet-${scheme}-390.png` });
    const inputs = await p.$$('.nc__sheet input'); await inputs[0].fill('4096'); await inputs[1].fill('4096'); await p.click('.nc__sheet button[type=submit]'); await p.waitForTimeout(1800);
    ok(scheme + ': the chat disappears from the list', (await p.$$('.nc__rows li[data-sel]')).length === 0 && (await p.$('.nc__chips button:has-text("Locked")')) !== null);
    await p.click('.nc__chips button:has-text("Locked")'); await p.waitForTimeout(1200);
    ok(scheme + ': setting the PIN opens a short session, so the Locked folder shows the chat', (await p.$$('.nc__rows li[data-sel]')).length === 1);
    await p.click('.nc__lockbar >> text=Lock now'); await p.waitForTimeout(1200);
    await p.click('.nc__chips button:has-text("Locked")'); await p.waitForSelector('.nc__sheet', { state: 'visible' });
    const bad = await p.$('.nc__sheet input'); await bad.fill('0000'); await p.click('.nc__sheet button[type=submit]'); await p.waitForTimeout(1200);
    ok(scheme + ': a wrong PIN is refused', (await p.textContent('.nc__sheeterr')).includes('not right'));
    await (await p.$('.nc__sheet input')).fill('4096'); await p.click('.nc__sheet button[type=submit]'); await p.waitForTimeout(1800);
    ok(scheme + ': the right PIN shows the locked chat', (await p.$$('.nc__rows li[data-sel]')).length === 1);
    await p.screenshot({ path: `test-results/lock-list-${scheme}-390.png` });
    // restore: unlock the chat, then remove the PIN
    await hold(p, '.nc__rows li[data-ctx]'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    await p.click('.n2nx__menu >> text=Unlock chat'); await p.waitForTimeout(1500);
    await p.click('.nc__chips button:has-text("Locked")').catch(() => {}); await p.waitForTimeout(800);
    await p.evaluate(() => window.Livewire && document.querySelector('[wire\\:id]') && window.Livewire.find(document.querySelector('[data-n2n-chat]').closest('[wire\\:id]').getAttribute('wire:id')).call('openLockSheet', 'remove'));
    await p.waitForSelector('.nc__sheet', { state: 'visible' }); await (await p.$('.nc__sheet input')).fill('4096'); await p.click('.nc__sheet button[type=submit]'); await p.waitForTimeout(1500);
    ok(scheme + ': the PIN can be removed again and the chat is back', (await p.$$('.nc__rows li[data-sel]')).length >= 1);
    await ctx.close();
  }
} finally { await browser.close(); }
