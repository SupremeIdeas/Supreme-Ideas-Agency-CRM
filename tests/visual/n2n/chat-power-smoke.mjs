// N2N Batch 12 (Tier 1) real-browser check: menu, reply, star, pin, search, theme (colour + wallpaper), storage, mute/archive, in light + dark, phone + desktop.
// Needs the dev app on BASE with frank@n2n.test / ada@n2n.test connected and one conversation (see PROGRESS.md). Run: node tests/visual/n2n/chat-power-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

async function login(ctx, email) {
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  return p;
}
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: 'light' });
const p = await login(ctx, 'frank@n2n.test');
await p.goto(BASE + '/n2n/chat'); await p.waitForSelector('.nc__rows button');
await p.locator('.nc__rows button').first().click();
await p.waitForSelector('.ncb');
ok('thread shows a reply with its quote', (await p.locator('.ncb__quote').count()) >= 1);
await p.screenshot({ path: 'test-results/chat-power-1-thread-390.png' });

// message actions: star + pin
await p.locator('.ncb--in').first().hover();
await p.locator('.ncb__dots').first().click({ force: true });
await p.waitForSelector('.ncb__pop', { state: 'visible' });
ok('message menu opens with Reply/Star/Pin', (await p.locator('.ncb__pop:visible button').count()) >= 3);
await p.locator('.ncb__pop:visible button', { hasText: 'Pin for me' }).click();
await p.waitForSelector('.nc__pinbar');
ok('pinned message bar appears', (await p.locator('.nc__pinbar').textContent()).includes('Pinned:'));
await p.locator('.ncb--in').first().hover();
await p.locator('.ncb__dots').first().click({ force: true });
await p.locator('.ncb__pop:visible button', { hasText: 'Star' }).click();
await p.waitForSelector('.ncb__meta svg');
ok('star shows on the message', (await p.locator('.ncb__meta svg').count()) >= 1);

// reply
await p.locator('.ncb--in').last().hover();
await p.locator('.ncb__dots').last().click({ force: true });
await p.locator('.ncb__pop:visible button', { hasText: 'Reply' }).click();
await p.waitForSelector('.nc__replybar');
ok('reply bar shows what is being replied to', (await p.locator('.nc__replybar').textContent()).includes('Replying to'));
await p.screenshot({ path: 'test-results/chat-power-2-reply-390.png' });
await p.locator('.nc__replybar button').click();

// header menu
await p.locator('button[aria-label="Chat options"]').click();
const menuText = await p.locator('.nc__menu').innerText();
ok('options menu lists the power features', ['Search in chat', 'Starred messages', 'Media, voice and links', 'Chat theme', 'Mute notifications', 'Pin chat', 'Archive chat', 'Manage storage', 'Export chat (text)'].every(t => menuText.includes(t)));
await p.screenshot({ path: 'test-results/chat-power-3-menu-390.png' });

// search
await p.locator('.nc__menu button', { hasText: 'Search in chat' }).click();
await p.waitForSelector('.nc__panel');
await p.fill('.nc__psearch input', 'flight');
await p.waitForSelector('.nc__plist li');
ok('search finds the message', (await p.locator('.nc__plist li').count()) >= 1);
await p.screenshot({ path: 'test-results/chat-power-4-search-390.png' });
await p.locator('.nc__plist li button').first().click();
await p.waitForTimeout(500);
ok('result jumps back to the message and closes the sheet', (await p.locator('.nc__panel').count()) === 0);

// theme
await p.locator('button[aria-label="Chat options"]').click();
await p.locator('.nc__menu button', { hasText: 'Chat theme' }).click();
await p.waitForSelector('.ncp__swatches');
await p.locator('.ncp__swatches button[aria-label="Rose"]').click();
await p.locator('.ncw', { hasText: 'Dunes' }).click();
await p.screenshot({ path: 'test-results/chat-power-5-theme-390.png' });
await p.locator('button', { hasText: 'Save theme' }).click();
await p.waitForSelector('[data-nc-theme]');
await p.evaluate(() => document.documentElement.classList.remove('dark')); await p.waitForTimeout(200);
const vars = await p.locator('.nc__msgs').evaluate(n => ({ out: getComputedStyle(n).getPropertyValue('--nc-out-bg').trim(), fg: getComputedStyle(n).getPropertyValue('--nc-out-fg').trim(), inn: getComputedStyle(n).getPropertyValue('--nc-in-bg').trim() }));
ok('theme applies scoped variables to the thread', /^#[0-9a-f]{6}$/i.test(vars.out) && /^#[0-9a-f]{6}$/i.test(vars.fg));
await p.screenshot({ path: 'test-results/chat-power-6-themed-light-390.png' });
await p.evaluate(() => document.documentElement.classList.add('dark')); await p.waitForTimeout(200);
await p.screenshot({ path: 'test-results/chat-power-7-themed-dark-390.png' });
const dark = await p.locator('.nc__msgs').evaluate(n => getComputedStyle(n).getPropertyValue('--nc-in-bg').trim());
ok('dark mode swaps to the dark bubble colours', dark !== vars.inn && /^#[0-9a-f]{6}$/i.test(dark));

// the other person is unaffected
const ctx2 = await browser.newContext({ viewport: { width: 390, height: 844 } });
const q = await login(ctx2, 'ada@n2n.test');
await q.goto(BASE + '/n2n/chat'); await q.waitForSelector('.nc__rows button'); await q.locator('.nc__rows button').first().click(); await q.waitForSelector('.ncb');
ok("the other person's thread has no theme and no pinned bar", (await q.locator('[data-nc-theme]').count()) === 0 && (await q.locator('.nc__pinbar').count()) === 0);
await q.close(); await ctx2.close();

// mute + archive
await p.locator('button[aria-label="Chat options"]').click();
await p.locator('.nc__menu button', { hasText: 'Mute notifications' }).click();
await p.locator('.nc__menu button', { hasText: '1 week' }).click();
await p.waitForTimeout(400);
await p.locator('button[aria-label="Back to conversations"]').first().click();
await p.waitForSelector('.nc__rows button');
ok('muted chat shows the bell-off marker in the list', (await p.locator('.nc__rows svg[aria-label="Muted"]').count()) >= 1);
await p.screenshot({ path: 'test-results/chat-power-8-list-390.png' });

// desktop
await p.setViewportSize({ width: 1280, height: 800 });
await p.locator('.nc__rows button').first().click(); await p.waitForSelector('.ncb');
await p.screenshot({ path: 'test-results/chat-power-9-desktop-1280.png' });
await browser.close();
process.exit(results.some(r => !r[1]) ? 1 : 0);
