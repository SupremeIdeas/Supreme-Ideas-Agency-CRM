// Touch-emulated check of the Naara chat gesture engine on the settings screen's practice bubble (Gestures blueprint A.7).
// Usage: BASE=http://127.0.0.1:8097 EMAIL=... PASS=... OUT=/path node scripts/gestures/touch-practice.mjs   (needs playwright + chromium)
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8097'; const OUT = process.env.OUT || '/tmp';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, colorScheme: process.env.SCHEME || 'light' });
const p = await ctx.newPage(); const errors = [];
p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await p.goto(BASE + '/login'); await p.fill('input[name=email]', process.env.EMAIL); await p.fill('input[name=password]', process.env.PASS);
await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
await p.goto(BASE + '/account/chat-gestures', { waitUntil: 'networkidle' }); await p.waitForTimeout(2500);
// Start from the platform defaults (an earlier run may have saved "Buttons only"), then bring the sample bubble into view: touches outside the viewport miss it.
await p.locator('button:has-text("Back to defaults")').click(); await p.waitForTimeout(1200);
await p.evaluate(() => document.querySelector('.ng-practice .ncb').scrollIntoView({ block: 'center' })); await p.waitForTimeout(400);
const cdp = await ctx.newCDPSession(p);
const touch = (type, x, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x, y, id: 1 }] });
const box = async () => { await p.evaluate(() => document.querySelector('.ng-practice .ncb').scrollIntoView({ block: 'center' })); await p.waitForTimeout(150); const r = await p.locator('.ng-practice .ncb').boundingBox(); if (process.env.DEBUG) console.log('box', Math.round(r.x), Math.round(r.y)); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; };
const log = async () => (await p.evaluate(() => { const l = document.getElementById('ng-practice-log'); return l ? (l.dataset.last || '') : '__missing__'; }));
const reset = async () => p.evaluate(() => { const l = document.getElementById('ng-practice-log'); l.dataset.last = ''; l.textContent = 'Nothing yet.'; });
const res = {}; const where = (l) => console.log(l, new URL(p.url()).pathname);
const swipe = async (dx, dy = 0, steps = 8) => { const c = await box(); await touch('touchStart', c.x, c.y); for (let i = 1; i <= steps; i++) { await touch('touchMove', c.x + dx * i / steps, c.y + dy * i / steps); await p.waitForTimeout(16); } await touch('touchEnd'); await p.waitForTimeout(350); };

await reset(); { const c = await box(); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(110); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(300); } res.double_tap = await log(); where('double_tap');
await reset(); { const c = await box(); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(600); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(300); } res.slow_taps_are_not_a_double = await log(); where('slow_taps_are_not_a_double');
await reset(); await swipe(110); res.swipe_end = await log(); where('swipe_end');
await reset(); await swipe(-110); res.swipe_start = await log(); where('swipe_start');
await reset(); await swipe(30); res.short_swipe_does_nothing = await log(); where('short_swipe_does_nothing');
await reset(); await swipe(0, 120); res.vertical_scroll_does_nothing = await log(); where('vertical_scroll_does_nothing');
await reset(); { const c = await box(); await touch('touchStart', c.x, c.y); await p.waitForTimeout(700); await touch('touchEnd'); await p.waitForTimeout(300); } res.long_press = await log(); where('long_press');
await p.screenshot({ path: `${OUT}/gestures-${process.env.SCHEME || 'light'}.png`, fullPage: false });
// Buttons only: gestures off, a tap on a normal bubble is the menu (here the practice bubble reports it).
await p.evaluate(() => document.querySelector('form[data-tour="chat.gestures"]').scrollIntoView({ block: 'center' }));
await p.locator('form[data-tour="chat.gestures"] input[type=checkbox]').first().check(); await p.waitForTimeout(800);
await p.locator('button:has-text("Save")').first().click(); await p.waitForTimeout(1200);
await reset(); await swipe(110); res.buttons_only_swipe = await log();
await reset(); { const c = await box(); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(110); await touch('touchStart', c.x, c.y); await touch('touchEnd'); await p.waitForTimeout(300); } res.buttons_only_double_tap = await log();
console.log(JSON.stringify({ res, errors }, null, 1));
const expect = { double_tap: 'react', slow_taps_are_not_a_double: '', swipe_end: 'reply', swipe_start: 'info', short_swipe_does_nothing: '', vertical_scroll_does_nothing: '', long_press: 'menu', buttons_only_swipe: '', buttons_only_double_tap: 'menu' };
const bad = Object.keys(expect).filter((k) => res[k] !== expect[k]);
console.log(bad.length ? 'FAIL: ' + bad.join(', ') : 'PASS'); await b.close(); process.exit(bad.length || errors.length ? 1 : 0);
