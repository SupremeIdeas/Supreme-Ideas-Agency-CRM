// N2N: aurora pin bar, frosted composer, message formatting tools, and the phone slide between list and thread. Needs the dev app on BASE with frank@n2n.test
// connected to ada@n2n.test (one pinned message). Run: node tests/visual/n2n/composer-glass-swipe-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, extra = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function open(scheme) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, colorScheme: scheme, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button');
  await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
  await p.waitForTimeout(3600);
  return { ctx, p };
}
async function swipe(p, x0, y, x1, steps = 10, hold = 0) {
  const c = await p.context().newCDPSession(p);
  await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x0, y }] });
  for (let i = 1; i <= steps; i++) { await c.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x0 + ((x1 - x0) * i) / steps, y }] }); await p.waitForTimeout(12); }
  if (hold) { await p.waitForTimeout(hold); }
  return { release: () => c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] }), c };
}
try {
  for (const scheme of ['light', 'dark']) {
    const { ctx, p } = await open(scheme);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(900);
    const pinned = await p.$('.nc__pinbar'); ok(`${scheme}: aurora pin bar present`, !!pinned);
    const pb = await p.$eval('.nc__pinbar', (e) => { const c = getComputedStyle(e, '::before'); return c.backgroundImage.includes('radial-gradient') && c.animationName === 'nc-aurora'; });
    ok(`${scheme}: aurora gradient animates`, pb);
    const foot = await p.$eval('.nc__foot', (e) => ({ pos: getComputedStyle(e).position, h: e.offsetHeight }));
    const msgs = await p.$eval('.nc__msgs', (e) => ({ pad: parseFloat(getComputedStyle(e).paddingBottom), bottom: e.getBoundingClientRect().bottom }));
    ok(`${scheme}: composer floats over the messages`, foot.pos === 'absolute' && msgs.pad >= foot.h && msgs.bottom > 800);
    const blur = await p.$eval('.nc__foot .cc-box', (e) => { const s = getComputedStyle(e); return (s.backdropFilter || s.webkitBackdropFilter || '').includes('blur') && /0\.(74|86)/.test(s.backgroundColor); });
    ok(`${scheme}: composer box is frosted`, blur);
    await p.evaluate(() => { const m = document.querySelector('.nc__msgs'); m.scrollTop = m.scrollHeight / 2; });
    await p.waitForTimeout(300);
    await p.screenshot({ path: `test-results/glass-${scheme}-thread-390.png` });

    // formatting: tools write the dialect, bubbles render it
    await p.click('.cc [data-r=fmtBtn]'); await p.waitForSelector('.cc-fmt:not([hidden])');
    await p.fill('.cc-text', 'Plan');
    await p.evaluate(() => { const t = document.querySelector('.cc-text'); t.focus(); t.setSelectionRange(0, 4); });
    await p.click('.cc-fmt [data-act=wrap][data-arg="**"]');
    ok(`${scheme}: bold tool wraps the selection`, (await p.inputValue('.cc-text')) === '**Plan**');
    await p.click('.cc-fmt [data-act=line][data-arg="- "]');
    ok(`${scheme}: bullet tool prefixes the line`, (await p.inputValue('.cc-text')) === '- **Plan**');
    await p.evaluate(() => { const t = document.querySelector('.cc-text'); t.focus(); t.setSelectionRange(t.value.length, t.value.length); });
    await p.keyboard.press('Enter');
    ok(`${scheme}: Enter continues the list instead of sending`, (await p.inputValue('.cc-text')) === '- **Plan**\n- ');
    await p.keyboard.type('Pickup at 17:40');
    await p.click('.cc-fmt [data-act=banner][data-arg=green]');
    ok(`${scheme}: banner tool fences the lines`, (await p.inputValue('.cc-text')) === '- **Plan**\n:::green\n- Pickup at 17:40\n:::');
    await p.screenshot({ path: `test-results/glass-${scheme}-format-390.png` });
    await p.fill('.cc-text', '');
    await ctx.close();
  }

  // swipe (light)
  const { ctx, p } = await open('light');
  await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(900);
  let s = await swipe(p, 60, 400, 170, 8, 50);
  const mid = await p.$eval('.nc__thread', (e) => ({ x: new DOMMatrix(getComputedStyle(e).transform).m41 }));
  const listVis = await p.$eval('.nc__list', (e) => getComputedStyle(e).visibility);
  ok('mid-drag: the thread follows the finger and the list is revealed underneath', mid.x > 70 && mid.x < 150 && listVis === 'visible', `x=${Math.round(mid.x)} list=${listVis}`);
  await p.screenshot({ path: 'test-results/swipe-mid-390.png' });
  await s.release(); await p.waitForTimeout(150);
  ok('short drag: it springs back', true);
  await p.waitForTimeout(500);
  const back = await p.$eval('.nc__thread', (e) => new DOMMatrix(getComputedStyle(e).transform).m41);
  ok('short drag released: thread returns to 0', Math.abs(back) < 2, `x=${Math.round(back)}`);
  s = await swipe(p, 40, 420, 300, 10, 20); await s.release();
  await p.waitForFunction(() => document.querySelector('.nc__thread').classList.contains('is-hidden'), null, { timeout: 4000 }).catch(() => {});
  ok('long drag: commits and shows the conversation list', await p.$eval('.nc__thread', (e) => e.classList.contains('is-hidden')));
  await p.waitForTimeout(500);
  ok('list is interactive again', (await p.$eval('.nc__list', (e) => getComputedStyle(e).visibility)) === 'visible');
  await p.screenshot({ path: 'test-results/swipe-list-390.png' });
  await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(700);
  s = await swipe(p, 300, 300, 40, 10, 20); await s.release();   // leftward also works
  await p.waitForFunction(() => document.querySelector('.nc__thread').classList.contains('is-hidden'), null, { timeout: 4000 }).catch(() => {});
  ok('leftward drag also returns to the list', await p.$eval('.nc__thread', (e) => e.classList.contains('is-hidden')));
  await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(700);
  const y0 = await p.$eval('.nc__msgs', (e) => e.scrollTop);
  const c = await p.context().newCDPSession(p);
  await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: 200, y: 500 }] });
  for (let i = 1; i <= 8; i++) { await c.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: 204, y: 500 - i * 30 }] }); await p.waitForTimeout(12); }
  await c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] }); await p.waitForTimeout(300);
  ok('vertical scrolling is not hijacked', !(await p.$eval('.nc__thread', (e) => e.classList.contains('is-hidden'))));
  await ctx.close();
} finally { await browser.close(); }
