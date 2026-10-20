// NaaraBusiness Suite marketing page (MASTER-ONLY): sections render, bento modal opens, A-Z is complete, pills mount the physics, FAQ tabs switch,
// no horizontal scroll, light + dark, phone + desktop. Needs a running app with the Suite switched on and plans seeded. node tests/visual/business-landing-smoke.mjs
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
fs.mkdirSync('test-results', { recursive: true });
for (const [scheme, vp, tag] of [['light', { width: 390, height: 900 }, '390-light'], ['dark', { width: 390, height: 900 }, '390-dark'], ['light', { width: 1280, height: 900 }, '1280-light'], ['dark', { width: 1280, height: 900 }, '1280-dark']]) {
  const ctx = await browser.newContext({ viewport: vp, colorScheme: scheme });
  const page = await ctx.newPage(); const errs = [];
  page.on('pageerror', (e) => errs.push(e.message));
  await page.goto(BASE + '/business', { waitUntil: 'networkidle' }); await page.waitForTimeout(4500);
  const first = tag === '390-light';
  if (first) {
    ok('hero headline and CTA', (await page.locator('h1').first().textContent()).includes('Get found') && await page.locator('a[href="#plans"]').first().isVisible());
    ok('26 A-Z cards', (await page.locator('#plans').count()) === 1 && (await page.locator('ol li span[aria-hidden=true]').filter({ hasText: /^[A-Z]$/ }).count()) === 26);
    ok('three live plans with prices', (await page.locator('#plans').textContent()).match(/\$19.*\$49.*\$99/s));
    ok('plan shows suite tools, skins and credits', (await page.locator('#plans').textContent()).includes('Shared inbox') && (await page.locator('#plans').textContent()).includes('Premium dashboard skins') && (await page.locator('#plans').textContent()).includes('NaaraCredits every month'));
    await page.locator('.nx-bento-cell[data-cell=p1]').scrollIntoViewIfNeeded();
    await page.locator('.nx-bento-cell[data-cell=p1]').click();
    await page.waitForTimeout(500);
    ok('problem card opens its modal with Suite copy', (await page.locator('[data-m-headline]').textContent()).includes('five places') && await page.locator('.nx-bento-modal.is-open').count() === 1);
    await page.keyboard.press('Escape');
    await page.locator('[data-pill-physics]').scrollIntoViewIfNeeded(); await page.waitForTimeout(1500);
    ok('pills run the Matter.js playground', await page.locator('[data-pill-physics].is-physics').count() === 1);
    await page.locator('button', { hasText: 'Plans and billing' }).click();
    ok('FAQ topic tabs switch', await page.getByText('How does billing work?').isVisible());
  }
  ok(`no horizontal scroll ${tag}`, !(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)));
  ok(`no page errors ${tag}`, errs.length === 0);
  for (const [name, sel] of [['hero', 'h1'], ['problem', '[data-bento-diagnosis]'], ['atoz', '#how'], ['plans', '#plans'], ['pills', '[data-pill-physics]'], ['faq', 'text=Questions, answered properly']]) {
    await page.locator(sel).first().scrollIntoViewIfNeeded(); await page.waitForTimeout(700);
    if (['390-light', '1280-dark'].includes(tag)) await page.screenshot({ path: `test-results/business-landing-${name}-${tag}.png` });
  }
  await page.screenshot({ path: `test-results/business-landing-full-${tag}.png`, fullPage: true });
  await ctx.close();
}
await browser.close();
process.exit(results.some((r) => !r[1]) ? 1 : 0);
