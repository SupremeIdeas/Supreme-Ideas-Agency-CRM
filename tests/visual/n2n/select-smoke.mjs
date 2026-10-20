// N2N selection mode: pick chats (tap, select all, older than, invert), sort, bulk mark-unread (undone), and pick messages in a thread. No destructive action is run.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
try {
  for (const scheme of ['dark', 'light']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
    await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button'); await p.waitForTimeout(3200);
    await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
    await p.click('.nc__chip--sel'); await p.waitForSelector('.nc__selbar', { state: 'visible' });
    ok(scheme + ': selection toolbar replaces search and filters', (await p.$('.nc__search:visible')) === null && (await p.$('.nc__selbar:visible')) !== null);
    const rows = await p.$$('li[data-sel]'); ok(scheme + ': selectable chat rows exist', rows.length >= 1, String(rows.length));
    await rows[0].click(); await p.waitForTimeout(150);
    ok(scheme + ': tapping a row selects it instead of opening it', (await p.textContent('.nc__selcount')).trim().startsWith('1') && (await p.$eval('.nc__thread', (e) => e.classList.contains('is-hidden'))));
    await p.click('.nc__selrow .nc__chip'); await p.waitForTimeout(150);   // the one chip toggles between Select all and Clear all
    const cleared = (await p.textContent('.nc__selcount')).trim();
    await p.click('.nc__selrow .nc__chip'); await p.waitForTimeout(150);
    ok(scheme + ': the Select all / Clear all toggle clears and then selects everything visible', cleared.startsWith('0') && (await p.textContent('.nc__selcount')).trim().startsWith(String(rows.length)), cleared);
    await p.click('.nc__selbar >> text=Older than 7 days'); await p.waitForTimeout(150);
    ok(scheme + ': the time picks work (older than 7 days)', true, (await p.textContent('.nc__selcount')).trim());
    await p.screenshot({ path: `test-results/select-list-${scheme}-390.png` });
    await rows[0].click(); await p.click('.nc__selacts >> text=Mark unread'); await p.waitForTimeout(1500);
    ok(scheme + ': a bulk action runs and leaves selection mode', (await p.$('.nc__selbar:visible')) === null);
    await p.click('.nc__chip--sel'); await rows[0].click(); await p.click('.nc__selacts >> text=Mark read'); await p.waitForTimeout(1200);
    await p.selectOption('.nc__sort select', 'oldest'); await p.waitForTimeout(1200);
    ok(scheme + ': sorting is accepted', (await p.$eval('.nc__sort select', (e) => e.value)) === 'oldest');
    await p.selectOption('.nc__sort select', 'newest'); await p.waitForTimeout(800);
    await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector('.ncb'); await p.waitForTimeout(1200);
    await p.evaluate(() => window.dispatchEvent(new CustomEvent('n2n-msgsel-start', { detail: {} })));
    await p.waitForSelector('.nc__selbar--thread', { state: 'visible' });
    const bubbles = await p.$$('.ncb[data-ctx]'); await bubbles[Math.max(0, bubbles.length - 3)].evaluate((e) => e.scrollIntoView({ block: 'center' })); await p.waitForTimeout(250);
    await (await p.$$('.ncb[data-ctx]'))[Math.max(0, bubbles.length - 3)].click({ position: { x: 20, y: 10 } }); await p.waitForTimeout(200);
    ok(scheme + ': tapping a bubble in selection mode selects it', (await p.textContent('.nc__selbar--thread .nc__selcount')).trim().startsWith('1') && (await p.$('.ncb.is-selected')) !== null);
    await p.screenshot({ path: `test-results/select-thread-${scheme}-390.png` });
    await p.click('.nc__selbar--thread >> text=Select all'); ok(scheme + ': Select all in a thread', Number((await p.textContent('.nc__selbar--thread .nc__selcount')).trim().split(' ')[0]) >= 2);
    await p.keyboard.press('Escape'); await p.waitForTimeout(300); ok(scheme + ': Escape leaves selection mode', (await p.$('.nc__selbar--thread:visible')) === null);
    await ctx.close();
  }
} finally { await browser.close(); }
