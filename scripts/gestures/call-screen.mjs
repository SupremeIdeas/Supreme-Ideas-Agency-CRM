// Touch-emulated check of the call-screen gestures on a REAL video call between two browser contexts (fake camera and microphone): swipe down to shrink the call to the small
// window, drag the small window to a corner (it snaps), tap it to bring the call back. Short or sideways swipes do nothing.
// Usage: BASE=http://127.0.0.1:8124 CALLER=frank@n2n.test CALLEE=ada.tester@example.com CALLEE_HANDLE=ada PASS=password OUT=/path node scripts/gestures/call-screen.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8124'; const OUT = process.env.OUT || '/tmp'; const PASS = process.env.PASS || 'password';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
const res = {}; const bad = []; const errors = [];
const check = (n, ok, extra = '') => { res[n] = ok; if (!ok) bad.push(n + (extra ? ` (${extra})` : '')); };
async function login(email) {
  const ctx = await b.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true, permissions: ['microphone', 'camera'] });
  const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e).slice(0, 160)));
  await p.goto(BASE + '/login'); await p.waitForTimeout(1200);
  const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]', { force: true })]);
  await p.evaluate(() => { try { localStorage.setItem('nx_preloader_seen:n2n:' + new Date().toISOString().slice(0, 10), '99'); } catch (e) {} });
  return { ctx, p };
}
const A = await login(process.env.CALLER || 'frank@n2n.test'); const B = await login(process.env.CALLEE || 'ada.tester@example.com');
await B.p.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' });
await A.p.goto(BASE + '/u/' + (process.env.CALLEE_HANDLE || 'ada'), { waitUntil: 'networkidle' });
await B.p.waitForTimeout(1500);
await A.p.click('[data-n2n-video-call]');
await B.p.waitForSelector('#n2n-call[data-phase="ringing"]', { timeout: 20000 });
await B.p.click('[data-call-answer]');
await Promise.all([A.p.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 }), B.p.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 })]);
await A.p.waitForTimeout(2500);
const cdp = await A.ctx.newCDPSession(A.p);
const t = (type, x, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x, y, id: 1 }] });
const drag = async (x0, y0, x1, y1, steps = 8) => { await t('touchStart', x0, y0); for (let i = 1; i <= steps; i++) { await t('touchMove', x0 + (x1 - x0) * i / steps, y0 + (y1 - y0) * i / steps); await A.p.waitForTimeout(16); } await t('touchEnd'); };
const isMin = () => A.p.evaluate(() => document.querySelector('#n2n-call').classList.contains('is-min'));
const wake = () => A.p.mouse.move(200, 300);
await wake();
check('call_is_full_screen', !(await isMin()));
await A.p.screenshot({ path: `${OUT}/g-call-full.png` });
// a short swipe, and a sideways one, do nothing
await drag(195, 260, 195, 320); await A.p.waitForTimeout(300); check('short_swipe_does_nothing', !(await isMin()));
await drag(60, 300, 330, 330); await A.p.waitForTimeout(300); check('sideways_swipe_does_nothing', !(await isMin()));
// a long swipe down shrinks it
await drag(195, 220, 195, 520, 10); await A.p.waitForTimeout(500);
check('swipe_down_shrinks_to_small_window', await isMin());
await A.p.screenshot({ path: `${OUT}/g-call-mini.png` });
// drag the small window to the top-left corner: it snaps there
let r = await A.p.evaluate(() => { const e = document.querySelector('#n2n-call').getBoundingClientRect(); return { x: e.left + e.width / 2, y: e.top + e.height / 2, w: e.width, h: e.height }; });
await drag(r.x, r.y, 70, 200, 10); await A.p.waitForTimeout(500);
const pos = await A.p.evaluate(() => { const e = document.querySelector('#n2n-call'); const q = e.getBoundingClientRect(); return { left: q.left, top: q.top, corner: e.dataset.miniCorner }; });
check('small_window_snaps_to_top_left', pos.corner === 'tl' && pos.left < 20, JSON.stringify(pos));
check('still_small_after_drag', await isMin());
await A.p.screenshot({ path: `${OUT}/g-call-mini-corner.png` });
// a tap brings the call back
r = await A.p.evaluate(() => { const e = document.querySelector('#n2n-call').getBoundingClientRect(); return { x: e.left + e.width / 2, y: e.top + e.height / 2 }; });
await t('touchStart', r.x, r.y); await A.p.waitForTimeout(50); await t('touchEnd'); await A.p.waitForTimeout(500);
check('tap_restores_the_call', !(await isMin()));
check('call_still_connected', (await A.p.evaluate(() => window.N2NCall.state().phase)) === 'active');
check('no_js_errors', errors.length === 0, errors.join(' | '));
console.log(JSON.stringify(res, null, 1)); console.log(bad.length ? 'FAIL: ' + bad.join(', ') : 'PASS');
await A.p.evaluate(() => document.querySelector('[data-call-hangup]')?.click()).catch(() => {});
await b.close(); process.exit(bad.length ? 1 : 0);
