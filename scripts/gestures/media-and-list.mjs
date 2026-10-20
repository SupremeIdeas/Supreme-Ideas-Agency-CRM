// Touch-emulated check of the remaining chat gestures: photo viewer (pinch, double-tap, swipe sideways, swipe down), voice-note double-tap skip, peek card on a chat row,
// pull-down search, and hold-then-drag select. Real touch events through CDP at phone size.
// Usage: BASE=http://127.0.0.1:8124 EMAIL=ada.tester@example.com PASS=password OUT=/path node scripts/gestures/media-and-list.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8124'; const OUT = process.env.OUT || '/tmp'; const SCHEME = process.env.SCHEME || 'light';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--autoplay-policy=no-user-gesture-required'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, colorScheme: SCHEME });
const p = await ctx.newPage(); const errors = [];
p.on('pageerror', (e) => errors.push(String(e)));
await p.goto(BASE + '/login'); await p.waitForTimeout(1200);
const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
await p.fill('input[name=email]', process.env.EMAIL); await p.fill('input[name=password]', process.env.PASS);
await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
await p.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' }); await p.waitForTimeout(3500);
const cdp = await ctx.newCDPSession(p);
const tp = (pts) => pts.map((q, i) => ({ x: q[0], y: q[1], id: i + 1 }));
const ts = (pts) => cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: tp(pts) });
const tm = (pts) => cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: tp(pts) });
const te = () => cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
const wait = (ms) => p.waitForTimeout(ms);
const tap = async (x, y) => { await ts([[x, y]]); await wait(40); await te(); };
const drag = async (x0, y0, x1, y1, steps = 10) => { await ts([[x0, y0]]); for (let i = 1; i <= steps; i++) { await tm([[x0 + (x1 - x0) * i / steps, y0 + (y1 - y0) * i / steps]]); await wait(16); } await te(); };
const res = {}; const bad = [];
process.on('uncaughtException', async (e) => { console.log(JSON.stringify(res, null, 1)); console.log('BAD so far: ' + bad.join(', ')); console.log('CRASH: ' + String(e).slice(0, 200)); try { await p.screenshot({ path: `${OUT}/g-crash-${SCHEME}.png` }); } catch (_) {} process.exit(1); });
const check = (name, ok, extra = '') => { res[name] = ok; if (!ok) bad.push(name + (extra ? ` (${extra})` : '')); };

// ---------------------------------------------------------------- the list: pull-down search, peek, drag-select
const collapsed = await p.evaluate(() => document.documentElement.classList.contains('nc-search-collapsed'));
check('search_collapsed_on_phone', collapsed);
check('search_chip_visible', await p.locator('[data-pull-search]').isVisible());
const rows = p.locator('.nc__rows > li[data-sel]');
const r0 = await rows.first().boundingBox();
await drag(195, r0.y + 10, 195, r0.y + 160, 14); await wait(500);
check('pull_down_reveals_search', await p.evaluate(() => !document.documentElement.classList.contains('nc-search-collapsed') && document.activeElement && document.activeElement.matches('.nc__search input')));
await p.screenshot({ path: `${OUT}/g-pull-search-${SCHEME}.png` });
await p.evaluate(() => document.activeElement && document.activeElement.blur()); await wait(500);
check('search_tucks_away_again', await p.evaluate(() => document.documentElement.classList.contains('nc-search-collapsed')));
// a short pull does nothing
await drag(195, r0.y + 10, 195, r0.y + 30, 6); await wait(300);
check('short_pull_does_nothing', await p.evaluate(() => document.documentElement.classList.contains('nc-search-collapsed')));
// the chip is the button alternative
await p.locator('[data-pull-search]').tap(); await wait(300);
check('search_chip_reveals', await p.evaluate(() => !document.documentElement.classList.contains('nc-search-collapsed')));
await p.evaluate(() => document.activeElement && document.activeElement.blur()); await wait(500);

// peek: hold a row until the menu opens, the last messages appear in a card
const row1 = await rows.nth(1).boundingBox();
await ts([[195, row1.y + row1.height / 2]]); await wait(900);
check('peek_card_shows_messages', await p.evaluate(async () => { for (let i = 0; i < 20; i++) { const li = document.querySelectorAll('[data-peek] li'); if (li.length) return true; await new Promise((r) => setTimeout(r, 100)); } return false; }));
await p.screenshot({ path: `${OUT}/g-peek-${SCHEME}.png` });
await te(); await wait(300);
await p.keyboard.press('Escape'); await wait(300);

// drag-select: Select, then hold on the 2nd row and drag to the 5th
await p.locator('.nc__chip--sel').tap(); await wait(400);
const a = await rows.nth(1).boundingBox(); const z = await rows.nth(4).boundingBox();
await ts([[195, a.y + a.height / 2]]); await wait(450);
for (let i = 1; i <= 12; i++) { await tm([[195, a.y + a.height / 2 + (z.y + z.height / 2 - a.y - a.height / 2) * i / 12]]); await wait(20); }
await te(); await wait(300);
const picked = await p.evaluate(() => { const d = window.Alpine.$data(document.querySelector('.nc__list')); return d.ids.length; });
check('drag_select_range', picked === 4, `picked ${picked}`);
await p.screenshot({ path: `${OUT}/g-dragselect-${SCHEME}.png` });
// dragging back over the same rows un-picks them
const a2 = await rows.nth(2).boundingBox();
await ts([[195, a2.y + a2.height / 2]]); await wait(450);
for (let i = 1; i <= 8; i++) { await tm([[195, a2.y + a2.height / 2 + (z.y + z.height / 2 - a2.y - a2.height / 2) * i / 8]]); await wait(20); }
await te(); await wait(300);
const after = await p.evaluate(() => window.Alpine.$data(document.querySelector('.nc__list')).ids.length);
check('drag_select_deselects', after === 1, `left ${after}`);
await p.evaluate(() => window.Alpine.$data(document.querySelector('.nc__list')).stop());
// a quick vertical swipe still scrolls and selects nothing
await p.locator('.nc__chip--sel').tap(); await wait(300);
await drag(195, a.y + 40, 195, a.y - 60, 8); await wait(200);
check('quick_swipe_selects_nothing', await p.evaluate(() => window.Alpine.$data(document.querySelector('.nc__list')).ids.length === 0));
await p.evaluate(() => window.Alpine.$data(document.querySelector('.nc__list')).stop());

// ---------------------------------------------------------------- open the chat with photos
const chatRow = rows.first(); await chatRow.locator('button').first().tap(); await wait(1500);
const img = p.locator('a[data-nlb]').first(); await img.evaluate((n) => n.scrollIntoView({ block: 'center' })); await wait(400);
const ib = await img.boundingBox(); await tap(ib.x + ib.width / 2, ib.y + ib.height / 3); await wait(700);
check('lightbox_opens', await p.locator('.nlb').count() === 1);
const scale = () => p.evaluate(() => { const m = /scale\(([\d.]+)\)/.exec(document.querySelector('.nlb__img').style.transform || ''); return m ? Number(m[1]) : 1; });
// pinch out
let c = [195, 422];
await ts([[c[0] - 30, c[1]], [c[0] + 30, c[1]]]);
for (let i = 1; i <= 10; i++) { await tm([[c[0] - 30 - i * 8, c[1]], [c[0] + 30 + i * 8, c[1]]]); await wait(16); }
await te(); await wait(300);
const zoomed = await scale(); check('pinch_zooms_in', zoomed > 1.8, `scale ${zoomed}`);
await p.screenshot({ path: `${OUT}/g-lightbox-zoom-${SCHEME}.png` });
// pan while zoomed does not change photo or close
const countBefore = await p.locator('.nlb__count').textContent();
await drag(260, 420, 200, 440, 6); await wait(250);
check('pan_keeps_photo', (await p.locator('.nlb__count').textContent()) === countBefore && await p.locator('.nlb').count() === 1);
// double-tap zooms back out
await tap(195, 420); await wait(120); await tap(195, 420); await wait(400);
check('double_tap_zooms_out', (await scale()) === 1, `scale ${await scale()}`);
// double-tap zooms in
await tap(195, 420); await wait(120); await tap(195, 420); await wait(400);
check('double_tap_zooms_in', (await scale()) > 2, `scale ${await scale()}`);
await tap(195, 420); await wait(120); await tap(195, 420); await wait(400);
// swipe sideways changes photo
const c0 = await p.locator('.nlb__count').textContent();
await drag(320, 420, 60, 425, 8); await wait(400);
const c1 = await p.locator('.nlb__count').textContent();
check('swipe_changes_photo', c0 !== c1 && c1 !== '', `${c0} -> ${c1}`);
// a short swipe down springs back, a long one closes
await drag(195, 300, 195, 340, 5); await wait(400);
check('short_down_swipe_stays', await p.locator('.nlb').count() === 1);
await drag(195, 250, 195, 520, 10); await wait(500);
check('swipe_down_closes', await p.locator('.nlb').count() === 0);

// ---------------------------------------------------------------- voice note: double-tap skips 5 seconds
const vn = p.locator('.nvp[data-ready]').first(); await vn.evaluate((n) => n.scrollIntoView({ block: 'center' })); await wait(400);
const wv = await vn.locator('.nvp__wave').boundingBox();
res.vn_probe = await p.evaluate(([x, y]) => { const e = document.elementFromPoint(x, y); return e ? e.className.toString().slice(0, 40) + '|' + e.tagName : 'none'; }, [wv.x + wv.width * 0.8, wv.y + wv.height / 2]); res.vn_box = JSON.stringify(wv);
const frac = () => p.evaluate(() => document.querySelector('.nvp[data-ready]').__nvp.frac);
// load the note first (a seek needs loaded audio), then pause it
await tap(wv.x - 30 + 12, wv.y + wv.height / 2); await wait(900); await tap(wv.x - 30 + 12, wv.y + wv.height / 2); await wait(300);
await p.evaluate(() => { const n = document.querySelector('.nvp[data-ready]').__nvp; n.seekTo(0); }); await wait(200);
const f0 = await frac();
await tap(wv.x + wv.width * 0.8, wv.y + wv.height / 2); await wait(120); await tap(wv.x + wv.width * 0.8, wv.y + wv.height / 2); await wait(400);
const f1 = await frac(); check('vn_double_tap_right_skips_5s', Math.abs(f1 - (f0 + 5 / 8)) < 0.05, `${f0} -> ${f1}`);
await wait(500);
await tap(wv.x + wv.width * 0.2, wv.y + wv.height / 2); await wait(120); await tap(wv.x + wv.width * 0.2, wv.y + wv.height / 2); await wait(400);
const f2 = await frac(); check('vn_double_tap_left_skips_back', Math.abs(f2 - Math.max(0, f1 - 5 / 8)) < 0.05, `${f1} -> ${f2}`);
await wait(500);
await tap(wv.x + wv.width * 0.5, wv.y + wv.height / 2); await wait(500);
res.vn_dbg = await p.evaluate(() => { const n = document.querySelector('.nvp[data-ready]').__nvp; return JSON.stringify({ frac: n.frac, ct: n.a && n.a.currentTime, rs: n.a && n.a.readyState, err: n.a && n.a.error && n.a.error.code, dur: n.a && n.a.duration, d2: n.dur, scrub: n.scrub, src: n.src }); });
const f3 = await frac(); check('vn_single_tap_still_seeks', Math.abs(f3 - 0.5) < 0.06, `${f3}`);
await p.screenshot({ path: `${OUT}/g-voice-${SCHEME}.png` });

check('no_js_errors', errors.length === 0, errors.join(' | ').slice(0, 300));
console.log(JSON.stringify(res, null, 1)); console.log(bad.length ? 'FAIL: ' + bad.join(', ') : 'PASS');
await b.close(); process.exit(bad.length ? 1 : 0);
