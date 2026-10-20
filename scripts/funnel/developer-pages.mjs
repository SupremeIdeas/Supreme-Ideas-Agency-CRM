// Browser check of the master-only developer pages: landing + docs, light/dark, desktop/phone. Usage: BASE=http://127.0.0.1:8123 OUT=dir node scripts/funnel/developer-pages.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8123'; const OUT = process.env.OUT || '/tmp';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const errors = []; const res = {};
for (const [name, vp] of [['desktop', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
  for (const scheme of ['light', 'dark']) {
    const ctx = await b.newContext({ viewport: vp, colorScheme: scheme }); const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e)));
    for (const [slug, path] of [['landing', '/developers'], ['docs-order', '/developers/docs/create-order'], ['docs-intro', '/developers/docs/introduction']]) {
      await p.goto(BASE + path, { waitUntil: 'networkidle' }); await p.waitForTimeout(3500);
      const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
      res[`${name}-${scheme}-${slug}-overflow`] = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
      await p.screenshot({ path: `${OUT}/dev-${slug}-${name}-${scheme}.png`, fullPage: slug === 'landing' });
    }
    if (name === 'desktop' && scheme === 'light') {
      await p.goto(BASE + '/developers/docs/create-order', { waitUntil: 'networkidle' });
      await p.locator('[data-dv-code]:visible').first().getByRole('tab', { name: 'Python' }).click(); await p.waitForTimeout(300);
      res.python_visible = await p.locator('[data-dv-code]:visible').first().locator('pre:visible').first().innerText().then((t) => t.includes('requests.post'));
      await p.locator('[data-dv-code]:visible').first().getByRole('button', { name: /copy/i }).click(); await p.waitForTimeout(300);
      res.filter = await (async () => { await p.fill('input[type=search]', 'idemp'); await p.waitForTimeout(200); return await p.locator('aside nav a:visible').allInnerTexts(); })();
    }
    await ctx.close();
  }
}
console.log(JSON.stringify({ res, errors }, null, 1)); await b.close();
