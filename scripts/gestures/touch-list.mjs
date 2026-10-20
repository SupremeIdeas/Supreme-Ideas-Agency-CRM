// Touch-emulated check of the conversation-list swipe (Gestures G3): swipe a chat row to pin and to archive, each with Undo; a short swipe and a vertical scroll do nothing; the row still opens on a plain tap.
// Usage: BASE=http://127.0.0.1:8099 EMAIL=... PASS=... OUT=/path node scripts/gestures/touch-list.mjs   (needs playwright + chromium and an account with at least one Naara chat)
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099'; const OUT = process.env.OUT || '/tmp';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, colorScheme: process.env.SCHEME || 'light' });
const p = await ctx.newPage(); const errors = [];
p.on('pageerror', (e) => errors.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await p.goto(BASE + '/login'); await p.waitForTimeout(1200);
const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
await p.fill('input[name=email]', process.env.EMAIL); await p.fill('input[name=password]', process.env.PASS);
await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
await p.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' }); await p.waitForTimeout(3000);
const cdp = await ctx.newCDPSession(p);
const touch = (type, x, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x, y, id: 1 }] });
const row = () => p.locator('.nc__rows > li[data-sel]').first();
const box = async () => { await row().scrollIntoViewIfNeeded(); await p.waitForTimeout(150); const r = await row().boundingBox(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; };
const swipe = async (dx, dy = 0, steps = 8) => { const c = await box(); await touch('touchStart', c.x, c.y); for (let i = 1; i <= steps; i++) { await touch('touchMove', c.x + dx * i / steps, c.y + dy * i / steps); await p.waitForTimeout(16); } await touch('touchEnd'); await p.waitForTimeout(900); };
const state = async () => p.evaluate(() => { const li = document.querySelector('.nc__rows > li[data-sel]'); const d = JSON.parse(li.dataset.ctx); return { pinned: !!d.pinned, archived: !!d.archived, open: !!document.querySelector('.nc__thread, [data-thread]') }; });
const snack = async () => p.evaluate(() => { const s = document.getElementById('ng-undo'); return s && s.classList.contains('is-on') ? s.querySelector('[data-ng-undo-text]').textContent : ''; });
const res = {};
const s0 = await state();
await swipe(110); res.pin_text = await snack(); await p.screenshot({ path: `${OUT}/list-pin-${process.env.SCHEME || 'light'}.png` });
res.pin_state = (await state()).pinned !== s0.pinned;
await p.locator('[data-ng-undo-btn]').click(); await p.waitForTimeout(900); res.undo_pin_state = (await state()).pinned === s0.pinned;
await swipe(30); res.short_swipe_snack = await snack();
await swipe(0, 120); res.vertical_snack = await snack();
if (!(await state()).archived) { await swipe(-110); res.archive_text = await snack(); await p.waitForTimeout(300); }
await p.locator('[data-ng-undo-btn]').click().catch(() => {}); await p.waitForTimeout(1000);
console.log(JSON.stringify({ s0, res, errors }, null, 1));
const bad = [];
if (!/pinned|unpinned/.test(res.pin_text)) bad.push('pin snackbar'); if (!res.pin_state) bad.push('pin did not change'); if (!res.undo_pin_state) bad.push('undo did not restore');
if (res.short_swipe_snack) bad.push('short swipe fired'); if (res.vertical_snack) bad.push('vertical scroll fired'); if (!/archived|unarchived/.test(res.archive_text || '')) bad.push('archive snackbar');
console.log(bad.length || errors.length ? 'FAIL: ' + bad.join(', ') : 'PASS'); await b.close(); process.exit(bad.length || errors.length ? 1 : 0);
