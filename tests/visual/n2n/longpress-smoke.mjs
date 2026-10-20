// N2N long-press / right-click menus on messages and chat rows, with reactions, forward, edit, info and the row actions. Needs frank@n2n.test with chats to ada and bola.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function open(touch, scheme = 'dark') {
  const ctx = await browser.newContext(touch ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 } : { viewport: { width: 1280, height: 760 } });
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
  { // ---- phone: long-press a chat row
    const { ctx, p } = await open(true);
    await p.waitForTimeout(3500);
    await hold(p, '.nc__rows li[data-ctx]');
    await p.waitForSelector('.n2nx__menu', { state: 'visible', timeout: 4000 });
    const items = await p.$$eval('.n2nx__menu [role=menuitem]:visible, .n2nx__chips button:visible', (e) => e.map((x) => x.textContent.trim()));
    ok('row: a long press opens the chat menu with pin, mute, unread, archive, block', ['Pin chat', 'Mark as unread', 'Archive', 'Block'].every((t) => items.some((i) => i.includes(t))) && (items.includes('8 hours') || items.includes('Unmute')), items.join(' | '));
    ok('row: the press did not also open the chat', (await p.$eval('.nc__thread', (e) => e.classList.contains('is-hidden'))));
    await p.screenshot({ path: 'test-results/longpress-row-390.png' });
    await p.click('.n2nx__menu >> text=Pin chat'); await p.waitForTimeout(900);
    ok('row: Pin chat pins it and closes the menu', (await p.$('.n2nx__menu:visible')) === null && (await p.$$eval('.nc__rows li', (e) => e[0].textContent)).length > 0);
    await hold(p, '.nc__rows li[data-ctx]'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    ok('row: it now offers Unpin chat', (await p.$$eval('.n2nx__menu [role=menuitem]:visible', (e) => e.map((x) => x.textContent.trim()))).some((t) => t.includes('Unpin chat')));
    await p.click('.n2nx__menu >> text=Unpin chat'); await p.waitForTimeout(700);
    await ctx.close(); }
  { // ---- phone: long-press a message
    const { ctx, p } = await open(true);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(1400);
    await hold(p, '.ncb--out:not(:has(.ncb__quote))');
    await p.waitForSelector('.n2nx__menu', { state: 'visible', timeout: 4000 });
    ok('message: the reaction bar and the actions are shown', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 6 && (await p.$$eval('.n2nx__menu [role=menuitem]:visible', (e) => e.map((x) => x.textContent.trim()))).some((t) => t === 'Forward'));
    ok('message: a lifted copy of the bubble sits above the dimmed screen', (await p.$('.n2nx__preview .ncb')) !== null);
    await p.screenshot({ path: 'test-results/longpress-msg-390.png' });
    await p.click('.n2nx__react button:nth-child(2)'); await p.waitForSelector('.ncb__react', { timeout: 6000 });
    ok('message: reacting shows a pill under the bubble', (await p.$$('.ncb__react')).length >= 1);
    // info
    await hold(p, '.ncb--out:not(:has(.ncb__quote))'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    await p.click('.n2nx__menu >> text=Info'); await p.waitForSelector('.nc__pinfo', { timeout: 6000 });
    ok('message: Info shows sent / delivered / read', /Sent/.test(await p.textContent('.nc__pinfo')) && /Delivered/.test(await p.textContent('.nc__pinfo')));
    await p.click('.nc__phead button'); await p.waitForTimeout(500);
    // forward
    await hold(p, '.ncb--out:not(:has(.ncb__quote))'); await p.waitForSelector('.n2nx__menu', { state: 'visible' });
    await p.click('.n2nx__menu >> text=Forward'); await p.waitForSelector('.nc__fw', { timeout: 6000 });
    ok('message: Forward lists the other chats with a cost line', /credit/.test(await p.textContent('.nc__pfoot')));
    await p.screenshot({ path: 'test-results/longpress-forward-390.png' });
    await p.click('.nc__fw >> nth=0'); await p.click('.nc__pfoot button.ns-cta'); await p.waitForTimeout(1500);
    ok('message: forwarding closes the panel', (await p.$('.nc__panel')) === null);
    await ctx.close(); }
  { // ---- desktop: right-click
    const { ctx, p } = await open(false, 'light');
    await p.waitForTimeout(3500);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(900);
    await p.click('.ncb--in', { button: 'right' });
    await p.waitForSelector('.n2nx__menu', { state: 'visible', timeout: 4000 });
    ok('desktop: right-click opens the same menu', (await p.$$('.n2nx__react button:not(.n2nx__plus)')).length === 6);
    await p.screenshot({ path: 'test-results/longpress-desktop-1280.png' });
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    ok('desktop: Escape closes it', (await p.$('.n2nx__menu:visible')) === null);
    await ctx.close(); }
} finally { await browser.close(); }
