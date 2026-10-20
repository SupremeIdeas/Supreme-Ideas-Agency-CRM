// Naara Planner end to end: Ada plans an event from the chat menu, Frank sees the card, answers Going, Ada sees the answer; calendar file downloads; Ada cancels.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
async function login(ctx, email) {
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/n2n/chat?c=1', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__msgs', { timeout: 20000 }); await p.waitForTimeout(2500);
  return p;
}
const iso = (d) => d.toISOString().slice(0, 10);
try {
  for (const scheme of ['dark', 'light']) {
    const ca = await browser.newContext({ viewport: { width: 1280, height: 860 }, colorScheme: scheme, timezoneId: 'Europe/London' });
    const cf = await browser.newContext({ viewport: { width: 1280, height: 860 }, colorScheme: scheme, timezoneId: 'Africa/Lagos' });
    const ada = await login(ca, 'ada@n2n.test'); const frank = await login(cf, 'frank@n2n.test');
    for (const p of [ada, frank]) { await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme); }
    const day = new Date(Date.now() + 3 * 864e5); const title = `Contract call ${scheme} ${Math.floor(Math.random() * 9000 + 1000)}`;
    await ada.click('button[aria-label="Chat options"]'); await ada.click('.nc__menu >> text=Plan an event'); await ada.waitForSelector('#ev-title');
    await ada.fill('#ev-title', title); await ada.fill('.nc__panel input[type=date]', iso(day)); await ada.fill('.nc__panel input[type=time]', '09:00');
    await ada.fill('#ev-desc', 'Bring the figures'); await ada.screenshot({ path: `test-results/planner-form-${scheme}.png` });
    await ada.click('.nc__panel button[type=submit]'); await ada.waitForSelector(`.nev:has-text("${title}")`, { timeout: 15000 });
    ok(scheme + ': the planner shows the event card to its creator, going by default', (await ada.textContent(`.nev:has-text("${title}") .nev__r`)).includes('You: Going'));
    await frank.waitForSelector(`.nev:has-text("${title}")`, { timeout: 30000 }); await frank.waitForTimeout(800);
    const when = await frank.textContent(`.nev:has-text("${title}") time`);
    ok(scheme + ': the other member sees it in their own timezone (09:00 London is 10:00 or 09:00 Lagos)', /(09|10):00/.test(when), when);
    await frank.click(`.nev:has-text("${title}") button:has-text("Going")`); await frank.waitForTimeout(1500);
    ok(scheme + ': answering Going is reflected on the card', (await frank.textContent(`.nev:has-text("${title}") .nev__r`)).includes('You: Going'));
    await ada.waitForFunction((t) => [...document.querySelectorAll('.nev')].find((e) => e.textContent.includes(t))?.querySelector('.nev__r')?.textContent.includes('Going') && /Ada|Frank/.test([...document.querySelectorAll('.nev')].find((e) => e.textContent.includes(t)).querySelector('.nev__r').textContent), title, { timeout: 25000 }).catch(() => {});
    ok(scheme + ': the creator sees the answer', /Frank Tester: Going/.test(await ada.textContent(`.nev:has-text("${title}") .nev__r`)), await ada.textContent(`.nev:has-text("${title}") .nev__r`));
    const href = await frank.getAttribute(`.nev:has-text("${title}") a:has-text("Add to calendar")`, 'href');
    const ics = await frank.evaluate(async (h) => { const r = await fetch(h, { credentials: 'same-origin' }); return { type: r.headers.get('content-type'), body: await r.text() }; }, href);
    ok(scheme + ': the .ics downloads with the right type and fields', ics.type.startsWith('text/calendar') && ics.body.includes('BEGIN:VEVENT') && ics.body.includes('SUMMARY:' + title.replace(/,/g, '\\,')) && ics.body.includes('DTSTART:'), ics.type);
    await frank.screenshot({ path: `test-results/planner-card-${scheme}.png` });
    await ada.click('button[aria-label="Chat options"]'); await ada.click('.nc__menu >> text=Upcoming events'); await ada.waitForSelector('.nc__panel li', { timeout: 8000 });
    ok(scheme + ': the agenda lists the event', (await ada.textContent('.nc__panel')).includes(title));
    await ada.screenshot({ path: `test-results/planner-agenda-${scheme}.png` }); await ada.click('.nc__panel button[aria-label="Close"]');
    ada.once('dialog', (d) => d.accept());
    await ada.click(`.nev:has-text("${title}") button:has-text("Cancel event")`); await ada.waitForTimeout(1500);
    ok(scheme + ': cancelling marks the card cancelled and removes the answer buttons', (await ada.textContent(`.nev:has-text("${title}") .nev__flag`)).includes('Cancelled') && (await ada.$$(`.nev:has-text("${title}") button:has-text("Going")`)).length === 0);
    await frank.waitForFunction((t) => [...document.querySelectorAll('.nev')].find((e) => e.textContent.includes(t))?.querySelector('.nev__flag')?.textContent.includes('Cancelled'), title, { timeout: 25000 }).catch(() => {});
    ok(scheme + ': the other member sees it cancelled', (await frank.textContent(`.nev:has-text("${title}") .nev__flag`)).includes('Cancelled'));
    await ca.close(); await cf.close();
  }
} finally { await browser.close(); }
