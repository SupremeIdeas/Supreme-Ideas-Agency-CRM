// N2N long-press extras: aurora/grain chat list, the "+" reaction picker with a personal editable bar, and the Clear/Delete chat + Delete for me items.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function open(scheme) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button');
  await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
  return { ctx, p };
}
async function hold(p, sel, ms = 650) {
  const all = await p.$$(sel); const el = all[all.length - 1]; await el.evaluate((e) => e.scrollIntoView({ block: 'center' })); await p.waitForTimeout(250); const b = await el.boundingBox(); const x = b.x + b.width / 2; const y = b.y + Math.min(b.height / 2, 14);
  const c = await p.context().newCDPSession(p);
  await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] }); await p.waitForTimeout(ms);
  await c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
}
try {
  for (const scheme of ['dark', 'light']) {
    const { ctx, p } = await open(scheme); await p.waitForTimeout(3500);
    const bg = await p.$eval('.nc__list', (e) => ({ b: getComputedStyle(e, '::before').backgroundImage, a: getComputedStyle(e, '::after').backgroundImage }));
    ok(scheme + ': list has aurora + grain layers', bg.b.includes('radial-gradient') && bg.a.includes('svg'));
    await p.screenshot({ path: `test-results/chatlist-aurora-${scheme}.png` });
    await ctx.close();
  }
  { // row menu items
    const { ctx, p } = await open('dark'); await p.waitForTimeout(3500);
    await hold(p, '.nc__rows li[data-ctx]'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    const items = await p.$$eval('.n2nx__menu [role=menuitem]:visible', (e) => e.map((x) => x.textContent.trim()));
    ok('row menu: Clear chat, Delete chat and Block are listed', ['Clear chat', 'Delete chat', 'Block'].every((t) => items.includes(t)), items.join(' | '));
    await p.screenshot({ path: 'test-results/rowmenu-clear-390.png' });
    await ctx.close(); }
  for (const scheme of ['dark', 'light']) { // plus picker + customise bar
    const { ctx, p } = await open(scheme);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(1400);
    await hold(p, '.ncb--out:not(:has(.ncb__quote))'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    ok(scheme + ': bar shows 6 defaults plus a + button', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 6 && (await p.$('.n2nx__plus')) !== null);
    ok(scheme + ': Delete for me is offered', (await p.$$eval('.n2nx__menu [role=menuitem]:visible', (e) => e.map((x) => x.textContent.trim()))).includes('Delete for me'));
    await p.click('.n2nx__plus'); await p.waitForSelector('.n2nx__grid', { state: 'visible' });
    const n = (await p.$$('.n2nx__grid button')).length;
    ok(scheme + ': the picker offers the full catalogue', n >= 30, String(n));
    await p.click('.n2nx__phead button:has-text("Edit bar")');
    await p.click('.n2nx__grid button[aria-label=fire]'); await p.waitForTimeout(700);
    ok(scheme + ': tapping in edit mode adds it to the bar', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 7);
    await p.screenshot({ path: `test-results/reaction-picker-${scheme}-390.png` });
    if (scheme === 'dark') {
      await p.click('.n2nx__grid button[aria-label=pray]'); await p.waitForTimeout(500);
      ok('dark: tapping a bar emoji in edit mode removes it', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 6);
      await p.click('.n2nx__grid button[aria-label=pray]'); await p.waitForTimeout(500); // put it back so we test order persistence
    }
    await p.click('.n2nx__phead button:has-text("Done")'); await p.click('.n2nx__scrim', { position: { x: 10, y: 10 } }); await p.waitForTimeout(400);
    // reopen: the bar must come back from the server
    await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button'); await p.waitForTimeout(2500);
    await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(1600);
    await hold(p, '.ncb--out:not(:has(.ncb__quote))'); await p.waitForSelector('.n2nx__menu', { state: 'visible', timeout: 6000 }).catch(async () => { await p.screenshot({ path: 'test-results/extras-fail.png' }); throw new Error('menu did not open after reload'); });
    const codes = await p.$$eval('.n2nx__react button:not(.n2nx__plus)', (e) => e.map((x) => x.getAttribute('aria-label')));
    ok(scheme + ': the customised bar survives a reload', codes.includes('React fire'), codes.join(','));
    await p.screenshot({ path: `test-results/reaction-bar-${scheme}-390.png` });
    // reset
    await p.click('.n2nx__plus'); await p.click('.n2nx__phead button:has-text("Edit bar")'); await p.click('.n2nx__phead button:has-text("Reset")'); await p.waitForTimeout(700);
    ok(scheme + ': Reset restores the six defaults', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 6);
    await ctx.close();
  }
} finally { await browser.close(); }
