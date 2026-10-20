// N2N polls end to end: create from the composer, see the card, vote, watch the share bar move, close. Ada (Pro) asks, Frank votes.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const login = async (who, scheme) => {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', who + '@n2n.test'); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button');
  await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
  return { ctx, p };
};
try {
  for (const scheme of ['dark', 'light']) {
    const q = `Lunch ${scheme} ${Date.now() % 10000}?`;
    { const { ctx, p } = await login('ada', scheme);
      await p.locator('.nc__rows button', { hasText: 'Frank' }).first().click(); await p.waitForSelector('naara-composer .cc-text', { timeout: 15000 }); await p.waitForTimeout(1000);
      await p.click('naara-composer [data-r=attachBtn]'); await p.click('.cc-sheet-item[data-kind=poll]');
      await p.fill('[data-r=pollQuestion]', q); const opts = await p.$$('.cc-poll-opt'); await opts[0].fill('Pizza'); await opts[1].fill('Suya');
      ok(scheme + ': the composer offers a multiple-answers switch', (await p.$('[data-r=pollMulti]')) !== null);
      await p.click('[data-r=pollCreate]'); await p.waitForTimeout(300);
      await p.click('naara-composer .cc-send'); await p.waitForSelector(`.npoll:has-text("${q}")`, { timeout: 25000 });
      ok(scheme + ': the poll card appears in the thread', true);
      await ctx.close(); }
    { const { ctx, p } = await login('frank', scheme);
      await p.locator('.nc__rows button', { hasText: 'Ada' }).first().click(); await p.waitForSelector(`.npoll:has-text("${q}")`, { timeout: 20000 }); await p.waitForTimeout(800);
      const card = p.locator('.npoll', { hasText: q }); await card.scrollIntoViewIfNeeded();
      await card.locator('.npoll__opt', { hasText: 'Suya' }).click(); await p.waitForTimeout(1500);
      ok(scheme + ': voting marks my option and shows 100%', (await card.locator('.npoll__opt.is-mine').count()) === 1 && (await card.textContent()).includes('100%'));
      await p.screenshot({ path: `test-results/poll-${scheme}-390.png` });
      await card.locator('.npoll__opt', { hasText: 'Pizza' }).click(); await p.waitForTimeout(1500);
      ok(scheme + ': a second pick moves the vote (single choice)', (await card.locator('.npoll__opt.is-mine').count()) === 1 && (await card.locator('.npoll__opt.is-mine').textContent()).includes('Pizza'));
      await ctx.close(); }
  }
} finally { await browser.close(); }
