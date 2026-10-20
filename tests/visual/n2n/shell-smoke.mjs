// N2N shell visual check (MASTER-ONLY): logs in to a locally booted app and screenshots the chat list, an open thread and People.
// Run: BASE=http://127.0.0.1:8765 node tests/visual/n2n/shell-smoke.mjs   (needs a seeded user frank@n2n.test / password)
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const out = (n) => `test-results/n2n-shell-${n}.png`;
let fails = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) fails++; };

async function session(vp, dark) {
  const ctx = await browser.newContext({ viewport: vp, colorScheme: dark ? 'dark' : 'light' });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login');
  await page.fill('input[name=email]', 'frank@n2n.test');
  await page.fill('input[name=password]', 'password');
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
  await page.evaluate((d) => { try { localStorage.setItem('theme', d ? 'dark' : 'light'); } catch (e) {} document.documentElement.classList.toggle('dark', d); }, dark);
  return { ctx, page };
}

for (const [label, vp] of [['390', { width: 390, height: 844 }], ['1280', { width: 1280, height: 800 }]]) {
  for (const dark of [false, true]) {
    const tag = `${label}-${dark ? 'dark' : 'light'}`;
    const { ctx, page } = await session(vp, dark);
    await page.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    await page.screenshot({ path: out(`list-${tag}`) });
    const bar = await page.locator('.nn__bar').isVisible();
    const rail = await page.locator('.nn__rail').isVisible();
    ok(`${tag}: ${label === '390' ? 'bottom bar visible, rail hidden' : 'rail visible, bottom bar hidden'}`, label === '390' ? bar && !rail : rail && !bar);
    ok(`${tag}: header has the More button`, await page.locator('.nn__head button[aria-label=More]').isVisible());
    // open the first conversation
    await page.locator('.nc__rows button, .nc__rows a').first().click();
    await page.waitForTimeout(1200);
    await page.screenshot({ path: out(`thread-${tag}`) });
    if (label === '390') {
      ok(`${tag}: in a thread the bar and header step aside`, !(await page.locator('.nn__bar').isVisible()) && !(await page.locator('.nn__head').isVisible()));
      const box = await page.locator('.nc__dock').boundingBox();
      ok(`${tag}: composer is docked at the bottom edge (bottom=${box && Math.round(box.y + box.height)} of ${vp.height})`, box && Math.abs(box.y + box.height - vp.height) <= 2);
    }
    await page.goto(BASE + '/n2n/people', { waitUntil: 'networkidle' });
    await page.screenshot({ path: out(`people-${tag}`) });
    await ctx.close();
  }
}
await browser.close();
process.exit(fails ? 1 : 0);
