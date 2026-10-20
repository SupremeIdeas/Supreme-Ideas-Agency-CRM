// NaaraBrain shell check (MASTER-ONLY): empty state, sideways sheet (drag + button + scrim), sidebar, thinking state, desktop dock; light + dark.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
let fails = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) fails++; };
const sheetX = (page) => page.evaluate(() => new DOMMatrix(getComputedStyle(document.querySelector('.brn__sheet')).transform).m41);
// Real touch drag via CDP so pointer events carry pointerType=touch.
async function drag(page, x0, y0, x1, y1, steps = 12) {
  const c = await page.context().newCDPSession(page);
  await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x0, y: y0 }] });
  for (let i = 1; i <= steps; i++) { await c.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x0 + ((x1 - x0) * i) / steps, y: y0 + ((y1 - y0) * i) / steps }] }); await page.waitForTimeout(16); }
  await c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await page.waitForTimeout(500);
}
for (const dark of [false, true]) {
  const tag = dark ? 'dark' : 'light';
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: dark ? 'dark' : 'light', hasTouch: true, isMobile: true });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login');
  await page.fill('input[name=email]', 'frank@n2n.test'); await page.fill('input[name=password]', 'password');
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
  await page.evaluate((d) => { try { localStorage.setItem('theme', d ? 'dark' : 'light'); } catch (e) {} document.documentElement.classList.toggle('dark', d); }, dark);
  // Hold the splash image briefly so the preloader stays up long enough to look at (the page's load event waits for it).
  await page.route('**/build/assets/shell-*.js', async (r) => { await new Promise((res) => setTimeout(res, 2600)); await r.continue(); });
  await page.goto(BASE + '/brain', { waitUntil: 'commit' });
  await page.waitForSelector('.nx-pre-brain img', { state: 'attached', timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(1200);
  await page.screenshot({ path: `test-results/brain-splash-${tag}.png` });
  ok(`${tag}: brain splash shown`, await page.locator('#nx-preloader .nx-pre-brain img[src*="naarabrain-splash"]').count() > 0);
  await page.waitForSelector('#nx-preloader', { state: 'detached', timeout: 9000 }).catch(() => {});
  await page.waitForSelector('.brn', { timeout: 10000 });
  await page.waitForTimeout(600);
  await page.screenshot({ path: `test-results/brain-empty-${tag}.png` });
  ok(`${tag}: empty chat shows chat logo + NaaraBrain name`, await page.locator('.brn__lockup img[src*="naarabrain-chat"]').isVisible() && (await page.locator('.brn__lockname').innerText()) === 'NaaraBrain');
  ok(`${tag}: exact Naara composer present`, await page.locator('naara-composer').count() === 1);
  const box = await page.locator('.brn__lockup').boundingBox();
  ok(`${tag}: lockup is centred horizontally`, Math.abs(box.x + box.width / 2 - 195) < 20);
  ok(`${tag}: sidebar closed at rest`, (await sheetX(page)) === 0);

  // drag the composer area right -> sidebar opens
  await drag(page, 40, 760, 300, 765);
  const x1 = await sheetX(page);
  ok(`${tag}: dragging from the composer area opens the sidebar (sheet x=${Math.round(x1)})`, x1 > 250);
  await page.screenshot({ path: `test-results/brain-open-${tag}.png` });
  ok(`${tag}: history groups visible`, await page.locator('.brn__glabel', { hasText: 'Pinned' }).isVisible() && await page.locator('.brn__ttl', { hasText: 'Kenya eSIM' }).isVisible());
  // drag back left closes
  await drag(page, 330, 400, 60, 405);
  ok(`${tag}: dragging back closes it`, (await sheetX(page)) === 0);
  // burger opens, scrim closes
  await page.click('.brn__burger'); await page.waitForTimeout(450);
  ok(`${tag}: menu button opens`, (await sheetX(page)) > 250);
  await page.mouse.click(370, 500); await page.waitForTimeout(450);
  ok(`${tag}: tapping the dimmed sheet closes`, (await sheetX(page)) === 0);
  // a vertical drag must NOT open it
  await drag(page, 100, 600, 110, 300);
  ok(`${tag}: vertical drag leaves it closed`, (await sheetX(page)) === 0);

  // open a conversation from the sidebar
  await page.click('.brn__burger'); await page.waitForTimeout(450);
  await page.fill('.brn__search input', 'virtual'); await page.waitForTimeout(1200);
  ok(`${tag}: search filters history`, (await page.locator('.brn__ttl').count()) === 1);
  await page.screenshot({ path: `test-results/brain-search-${tag}.png` });
  await page.fill('.brn__search input', ''); await page.waitForTimeout(700);
  await page.locator('.brn__ttl', { hasText: 'Reply to a customer' }).click(); await page.waitForTimeout(1200);
  ok(`${tag}: sidebar closes after picking a chat`, (await sheetX(page)) === 0);
  await page.screenshot({ path: `test-results/brain-thread-${tag}.png` });
  ok(`${tag}: messages render with markdown list`, await page.locator('.brn__prose li').count() >= 2);
  // controls + actions
  await page.click('.brn__burger'); await page.waitForTimeout(450);
  await page.locator('.brn__seg button', { hasText: 'Deep' }).click(); await page.waitForTimeout(700);
  await page.screenshot({ path: `test-results/brain-controls-${tag}.png` });
  ok(`${tag}: depth control applies`, await page.locator('.brn__seg button.is-on', { hasText: 'Deep' }).count() === 1);
  await ctx.close();
}
// desktop: docked
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
await page.goto(BASE + '/login');
await page.fill('input[name=email]', 'frank@n2n.test'); await page.fill('input[name=password]', 'password');
await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
await page.goto(BASE + '/brain'); await page.waitForSelector('.brn', { timeout: 10000 }); await page.waitForTimeout(1500);
await page.screenshot({ path: 'test-results/brain-desktop.png' });
ok('desktop: sidebar docked and visible beside the sheet', await page.locator('.brn__side').isVisible() && (await page.locator('.brn__sheet').boundingBox()).x >= 300);
ok('desktop: no burger', !(await page.locator('.brn__burger').isVisible()));
await browser.close(); process.exit(fails ? 1 : 0);
