// NaaraBrain ideas + plan UI: rotating centre line, idea chips, idea browser, plan meter, capability list. 390px.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true }); const p = await ctx.newPage();
let fails = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) fails++; };
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
const lines = new Set();
for (let i = 0; i < 6; i++) { await p.goto('http://127.0.0.1:8765/brain'); await p.waitForSelector('.brn__tag'); lines.add((await p.locator('.brn__tag').innerText()).trim()); }
ok(`centre line rotates between visits (${lines.size} different in 6 visits)`, lines.size >= 3);
await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForTimeout(600);
const chips = await p.locator('.brn__chip:not(.brn__chip--more)').allInnerTexts();
ok(`4 quick questions + More ideas (${chips.length})`, chips.length === 4 && await p.locator('.brn__chip--more').isVisible());
await p.screenshot({ path: 'test-results/brain-ideas-empty.png' });
await p.click('.brn__chip--more'); await p.waitForSelector('.brn__ideas'); await p.waitForTimeout(400);
ok('idea browser lists many areas', (await p.locator('.brn__ideas-pack').count()) >= 12);
await p.screenshot({ path: 'test-results/brain-ideas-browser.png' });
await p.click('.brn__ideas-head .brn__iconbtn'); await p.waitForTimeout(300);
ok('browser closes', await p.locator('.brn__ideas').count() === 0);
await p.click('.brn__burger'); await p.waitForTimeout(500);
ok('plan meter visible in the sidebar', await p.locator('.brn__plan').isVisible());
await p.locator('.brn__scroll').evaluate((e) => { e.scrollTop = e.scrollHeight; }); await p.waitForTimeout(300);
await p.screenshot({ path: 'test-results/brain-ideas-sidebar.png' });
await b.close(); process.exit(fails ? 1 : 0);
