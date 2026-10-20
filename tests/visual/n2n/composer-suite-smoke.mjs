// N2N composer suite in a real browser: free member sees locked Pro tools with an upgrade prompt, Pro member uses them; formatting, GIF maker, schedule, contact card.
// Needs the dev app with frank@n2n.test (free) and ada@n2n.test (Naara Pro) connected. Run: node tests/visual/n2n/composer-suite-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function open(email, w = 390, h = 844) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, hasTouch: w < 700, isMobile: w < 700, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button');
  const name = email.startsWith('frank') ? 'Ada' : 'Frank';
  await p.locator('.nc__rows button', { hasText: name }).first().click(); await p.waitForSelector('.cc'); await p.waitForTimeout(1200);
  return { ctx, p };
}
try {
  // ---- free member
  { const { ctx, p } = await open('frank@n2n.test');
    const present = await p.$$eval('.cc-controls [data-r]', (els) => els.map((e) => e.dataset.r));
    ok('free: every tool is visible (attach, format, emoji, gif, schedule, mic)', ['attachBtn', 'fmtBtn', 'emojiBtn', 'gifBtn', 'schedBtn', 'micBtn'].every((n) => present.includes(n)), present.join(','));
    ok('free: mic and gif carry a padlock', (await p.$$('.cc-controls .cc-locked')).length === 2);
    await p.click('[data-r=micBtn]');
    ok('free: tapping the mic explains Naara Pro with a button', /Naara Pro/.test(await p.textContent('.cc-status')) && (await p.$('.cc-status button')) !== null);
    await p.screenshot({ path: 'test-results/suite-free-upsell-390.png' });
    await p.click('[data-r=attachBtn]'); await p.waitForSelector('.cc-sheet');
    const kinds = await p.$$eval('.cc-sheet-item', (els) => els.map((e) => [e.dataset.kind, e.classList.contains('cc-locked')]));
    ok('free: gallery/camera/video are locked, contact/location are free', kinds.filter((k) => k[1]).map((k) => k[0]).sort().join() === 'camera,media,video' && kinds.some((k) => k[0] === 'contact' && !k[1]) && kinds.some((k) => k[0] === 'location' && !k[1]), JSON.stringify(kinds));
    await p.click('.cc-sheet-item[data-kind=media]');
    ok('free: tapping Gallery shows the upsell instead of a file picker', /Naara Pro/.test(await p.textContent('.cc-status')));
    // contact card becomes a formatted banner
    await p.click('[data-r=attachBtn]'); await p.click('.cc-sheet-item[data-kind=contact]');
    await p.fill('[data-r=contactName]', 'Tunde'); await p.fill('[data-r=contactPhone]', '+2348012345678'); await p.click('[data-r=contactAdd]');
    await p.click('[data-r=sendBtn]'); await p.waitForSelector('.ncf-banner--blue', { timeout: 12000 });
    ok('free: a contact card is delivered as a blue banner', /Contact\s*Tunde/.test(await p.textContent('.ncf-banner--blue')));
    // GIF maker is usable, sending is the Pro part
    await p.click('[data-r=gifBtn]'); await p.waitForSelector('.cc-gtools');
    ok('free: the GIF maker opens (making a GIF is free)', (await p.$('[data-r=gImg]')) !== null);
    await ctx.close(); }
  // ---- schedule (free)
  { const { ctx, p } = await open('frank@n2n.test');
    await p.click('[data-r=schedBtn]'); await p.waitForSelector('[data-r=schedInput]');
    const when = new Date(Date.now() + 3600e3); const pad = (n) => String(n).padStart(2, '0');
    await p.fill('[data-r=schedInput]', `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}T${pad(when.getHours())}:${pad(when.getMinutes())}`);
    await p.click('[data-r=schedSet]'); await p.fill('.cc-text', 'Scheduled hello from the smoke test'); await p.click('[data-r=sendBtn]');
    await p.waitForFunction(() => /Scheduled/.test(document.querySelector('.cc-status')?.textContent || ''), null, { timeout: 8000 });
    ok('schedule: the composer confirms it was queued', true);
    ok('schedule: it is NOT in the chat yet', !/Scheduled hello from the smoke test/.test(await p.textContent('.nc__msgs')));
    await p.click('.nc__thead [aria-label*="menu" i], .nc__thead button[aria-haspopup]').catch(() => {});
    await ctx.close(); }
  // ---- Pro member
  { const { ctx, p } = await open('ada@n2n.test');
    ok('pro: no padlocks', (await p.$$('.cc-controls .cc-locked')).length === 0);
    await p.click('[data-r=attachBtn]'); await p.waitForSelector('.cc-sheet');
    ok('pro: gallery, camera, video, contact and location are all unlocked', (await p.$$('.cc-sheet-item.cc-locked')).length === 0 && (await p.$$('.cc-sheet-item')).length === 5);
    await p.screenshot({ path: 'test-results/suite-pro-attach-390.png' });
    await p.click('[data-r=gifBtn]'); await p.waitForSelector('.cc-gtools');
    ok('pro: GIF panel shows the maker tools', (await p.$$('.cc-gtools button')).length === 2);
    await p.screenshot({ path: 'test-results/suite-pro-gif-390.png' });
    await ctx.close(); }
} finally { await browser.close(); }
