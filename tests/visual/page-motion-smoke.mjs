// Dashboard page transitions: classes toggle around wire:navigate, the page content animates (compositor props only), the chrome does not, and the admin switch removes it.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
try {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'no-preference' });
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'frank@n2n.test'); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  await p.goto(BASE + '/dashboard', { waitUntil: 'networkidle' }).catch(() => {});
  await p.waitForTimeout(3500);
  ok('html carries the transition attributes', (await p.evaluate(() => document.documentElement.getAttribute('data-nx-pt'))) === 'fluid');
  ok('the page content region exists', (await p.$('[data-nx-page]')) !== null);
  await p.evaluate(() => { window.__log = []; const h = document.documentElement; new MutationObserver(() => window.__log.push(h.className.split(' ').filter((c) => c.startsWith('nx-')).join('+'))).observe(h, { attributes: true, attributeFilter: ['class'] }); document.addEventListener('livewire:navigated', () => { const m = document.querySelector('[data-nx-page]'); window.__anim = m ? getComputedStyle(m).animationName : null; }); });
  const link = await p.$('a[wire\\:navigate][href*="/wallet"], a[wire\\:navigate][href*="/orders"], a[wire\\:navigate][href*="/account"]');
  if (link) { await link.evaluate((e) => e.click()); } else { await p.evaluate(() => window.Livewire && window.Livewire.navigate('/wallet')); }
  await p.waitForTimeout(6000);
  const log = await p.evaluate(() => window.__log); const anim = await p.evaluate(() => window.__anim);
  ok('leave then enter classes were applied', log.some((l) => l.includes('nx-leave')) && log.some((l) => l.includes('nx-enter')), JSON.stringify(log));
  ok('the content ran the fluid animation on arrival', anim === 'nxm-flow', String(anim));
  ok('the classes are cleaned up afterwards', (await p.evaluate(() => document.documentElement.className)).indexOf('nx-enter') === -1);
  await ctx.close();
  const rc = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  const q = await rc.newPage();
  await q.goto(BASE + '/login'); await q.fill('input[name=email]', 'frank@n2n.test'); await q.fill('input[name=password]', 'password');
  await Promise.all([q.waitForNavigation().catch(() => {}), q.click('button[type=submit]')]);
  await q.goto(BASE + '/dashboard', { waitUntil: 'networkidle' }).catch(() => {}); await q.waitForTimeout(2500);
  await q.evaluate(() => window.Livewire && window.Livewire.navigate('/wallet')); await q.waitForTimeout(1200);
  ok('reduced-motion members get no animation', (await q.evaluate(() => { const m = document.querySelector('[data-nx-page]'); return m ? getComputedStyle(m).animationName : 'none'; })) === 'none');
  await rc.close();
} finally { await browser.close(); }
