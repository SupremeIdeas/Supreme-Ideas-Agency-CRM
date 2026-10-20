// Branding check (MASTER-ONLY): chat logo in the shell header, the seeded intro animation, and the quick chat button.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
let fails = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) fails++; };
for (const [label, vp] of [['390', { width: 390, height: 844 }], ['1280', { width: 1280, height: 800 }]]) {
  for (const dark of [false, true]) {
    const tag = `${label}-${dark ? 'dark' : 'light'}`;
    const ctx = await browser.newContext({ viewport: vp, colorScheme: dark ? 'dark' : 'light' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/login');
    await page.fill('input[name=email]', 'frank@n2n.test'); await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
    await page.evaluate((d) => { try { localStorage.setItem('theme', d ? 'dark' : 'light'); localStorage.clear(); } catch (e) {} document.documentElement.classList.toggle('dark', d); }, dark);
    // intro animation: capture while the preloader is up
    await page.goto(BASE + '/n2n/chat', { waitUntil: 'commit' });
    await page.waitForSelector('#nx-preloader img', { timeout: 8000 }).catch(() => {});
    await page.waitForTimeout(900);
    await page.screenshot({ path: `test-results/n2n-brand-intro-${tag}.png` });
    ok(`${tag}: chat intro animation is shown`, await page.locator('#nx-preloader img[src*="naara-chat-intro"]').count() > 0);
    await page.waitForSelector('#nx-preloader', { state: 'detached', timeout: 8000 }).catch(() => {});
    await page.waitForTimeout(400);
    await page.screenshot({ path: `test-results/n2n-brand-header-${tag}.png` });
    ok(`${tag}: chat logo in the header/rail`, await page.locator('.nn__head img[src*="naara-chat-mark"], .nn__rail img[src*="naara-chat-mark"]').locator('visible=true').first().isVisible());
    // quick button: on numbers contacts, not on dashboard
    await page.goto(BASE + '/numbers/contacts', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.screenshot({ path: `test-results/n2n-brand-fab-${tag}.png` });
    ok(`${tag}: quick chat button on Contacts`, await page.locator('.nn-fab__btn').isVisible());
    await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
    ok(`${tag}: no quick chat button on the dashboard home`, await page.locator('.nn-fab').count() === 0);
    await ctx.close();
  }
}
await browser.close(); process.exit(fails ? 1 : 0);
