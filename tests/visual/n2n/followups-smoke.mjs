// Planner Phase C end to end: add a follow-up from the chat menu, see it on /n2n/planner, mark done, delete.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
try {
  for (const scheme of ['dark', 'light']) {
    const ctx = await browser.newContext({ viewport: { width: 420, height: 860 }, colorScheme: scheme });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'ada@n2n.test'); await p.fill('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
    await p.goto(BASE + '/n2n/chat?c=1', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__msgs', { timeout: 20000 }); await p.waitForTimeout(2000);
    const title = `Ask about invoice ${scheme} ${Math.floor(Math.random() * 9000 + 1000)}`;
    await p.click('button[aria-label="Chat options"]'); await p.click('.nc__menu >> text=Remind me to follow up'); await p.waitForSelector('.nc__panel input[type=text]');
    await p.fill('.nc__panel input[type=text]', title); await p.screenshot({ path: `test-results/followup-panel-${scheme}.png` });
    await p.click('.nc__panel >> text=Tomorrow morning'); await p.waitForTimeout(1800);
    ok(scheme + ': panel closes after saving', (await p.locator('.nc__panel').count()) === 0);
    await p.goto(BASE + '/n2n/planner', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('[data-n2n-planner]');
    await p.click('[role=tab]:has-text("Follow-ups")'); await p.waitForSelector(`li:has-text("${title}")`, { timeout: 8000 });
    ok(scheme + ': follow-up listed with its time in the member clock', /\d{2}:\d{2}/.test(await p.textContent(`li:has-text("${title}") time`)));
    await p.screenshot({ path: `test-results/planner-page-${scheme}.png` });
    await p.click(`li:has-text("${title}") button[aria-label="Mark as done"]`); await p.waitForTimeout(1200);
    ok(scheme + ': marking done strikes it through', (await p.getAttribute(`li:has-text("${title}") b`, 'class')).includes('line-through'));
    p.on('dialog', (d) => d.accept());
    await p.click(`li:has-text("${title}") button[aria-label="Delete follow-up"]`); await p.waitForTimeout(1200);
    ok(scheme + ': delete removes it', (await p.locator(`li:has-text("${title}")`).count()) === 0);
    await ctx.close();
  }
} finally { await browser.close(); }
