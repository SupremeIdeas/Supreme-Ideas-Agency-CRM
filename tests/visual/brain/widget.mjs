// NaaraBrain floating widget + admin page check (MASTER-ONLY).
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
let fails = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) fails++; };
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
const page = await ctx.newPage();
await page.goto(BASE + '/login');
await page.fill('input[name=email]', 'frank@n2n.test'); await page.fill('input[name=password]', 'password');
await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
const fab = () => page.locator('[data-brain-widget]');
const settle = async () => { await page.waitForSelector('#nx-preloader', { state: 'detached', timeout: 9000 }).catch(() => {}); await page.waitForTimeout(700); };
await page.goto(BASE + '/dashboard'); await settle();
ok('dashboard shows the widget', await fab().isVisible());
const box = await page.locator('.brn-fab__btn').boundingBox();
ok(`left side, ~96px above the bottom (x=${Math.round(box.x)}, bottom gap=${Math.round(844 - box.y - box.height)})`, box.x < 40 && Math.abs(844 - box.y - box.height - 96) < 6);
await page.screenshot({ path: 'test-results/brain-widget-left.png' });
await page.goto(BASE + '/catalogue'); await settle();
ok('eSIM section shows the widget', await fab().isVisible());
await page.screenshot({ path: 'test-results/brain-widget-esim.png' });
await page.goto(BASE + '/security'); await settle();
ok('an unticked section does not show it', (await fab().count()) === 0);
// dismiss
await page.goto(BASE + '/dashboard'); await settle();
await page.click('.brn-fab__x'); await page.waitForTimeout(300);
ok('the member can hide it', !(await fab().isVisible()));
await page.evaluate(() => localStorage.removeItem('brain_fab_hidden_until'));
// tap opens Brain
await page.goto(BASE + '/dashboard'); await settle();
await page.click('.brn-fab__btn'); await page.waitForURL(/\/brain/, { timeout: 10000 });
ok('tapping it opens NaaraBrain', true);
// admin page
await page.goto(BASE + '/adminmaster/brain'); await page.waitForTimeout(1200);
const adminOk = (await page.locator('h1', { hasText: 'NaaraBrain' }).count()) > 0;
ok('admin page loads', adminOk);
if (adminOk) {
  await page.screenshot({ path: 'test-results/brain-admin-left.png', fullPage: true });
  await page.locator('label', { hasText: 'Right' }).click(); await page.waitForTimeout(500);
  await page.locator('label', { hasText: 'Wallet and receipts' }).locator('input').check().catch(() => {});
  await page.getByRole('button', { name: 'Save' }).click(); await page.waitForTimeout(1200);
  await page.screenshot({ path: 'test-results/brain-admin-right.png', fullPage: true });
  await page.goto(BASE + '/dashboard'); await settle();
  const b2 = await page.locator('.brn-fab__btn').boundingBox();
  ok(`widget moved to the right and above the stack (x=${Math.round(b2.x)}, y=${Math.round(b2.y)})`, b2.x > 300 && b2.y < 844 - 96 - 150);
  await page.screenshot({ path: 'test-results/brain-widget-right.png' });
}
await browser.close(); process.exit(fails ? 1 : 0);
