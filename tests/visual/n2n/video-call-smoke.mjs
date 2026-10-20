// N2N VIDEO call smoke test (MASTER-ONLY): REAL WebRTC between two browser contexts with fake camera + microphone, signalled through the real server.
// Same setup as call-smoke.mjs (seeded frank@n2n.test / ada@n2n.test, calls enabled in dev mode, caller funded). Run: BASE=http://127.0.0.1:8099 node tests/visual/n2n/video-call-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist', '--disable-features=WebRtcHideLocalIpsWithMdns', '--autoplay-policy=no-user-gesture-required'] });
let fails = 0; const ok = (n, c, extra = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); if (!c) fails++; };
async function login(email, vp = { width: 1280, height: 800 }) {
  const ctx = await browser.newContext({ viewport: vp, permissions: ['microphone', 'camera'], hasTouch: vp.width < 600 });
  const page = await ctx.newPage(); page.on('pageerror', (e) => console.log('pageerror', email, String(e).slice(0, 200)));
  await page.goto(BASE + '/login');
  // The cookie banner can sit over the Sign in button at phone width: choose Reject all first (when it shows).
  await page.locator('#naara-cookie button:has-text("Reject all")').first().click({ timeout: 9000 }).catch(() => {});
  await page.fill('input[name=email]', email); await page.fill('input[name=password]', 'password');
  for (let i = 0; i < 3 && new URL(page.url()).pathname.startsWith('/login'); i++) { await page.click('button[type=submit]', { force: true }); await page.waitForTimeout(3000); }
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 });
  await page.evaluate(() => { try { localStorage.setItem('nx_preloader_seen:n2n:' + new Date().toISOString().slice(0, 10), '99'); } catch (e) {} });
  return { ctx, page };
}
const A = await login('frank@n2n.test', { width: 390, height: 780 }); const B = await login('ada@n2n.test', { width: 390, height: 780 });
await B.page.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' });
await A.page.goto(BASE + '/u/ada', { waitUntil: 'networkidle' });
await B.page.waitForTimeout(1500);
ok('profile shows a Video button', (await A.page.locator('[data-n2n-video-call]').count()) === 1);
await A.page.click('[data-n2n-video-call]');
await A.page.waitForSelector('#n2n-call.is-video[data-phase="calling"]', { timeout: 10000 }); ok('caller sees the video stage while calling', await A.page.isVisible('[data-call-stage]'));
await B.page.waitForSelector('#n2n-call[data-phase="ringing"]', { timeout: 15000 });
ok('callee sees an incoming VIDEO call with Video and Audio-only answers', (await B.page.textContent('[data-call-status]')) === 'Incoming video call' && await B.page.isVisible('[data-call-answer-audio]') && await B.page.isVisible('[data-call-answer]'));
await B.page.screenshot({ path: 'test-results/n2n-video-ringing.png' });
// Before the callee answers, the caller picks a beauty look: the panel is hidden by default and the look is OFF.
ok('beauty panel is hidden by default', await A.page.locator('[data-beauty-sheet]').isHidden());
await A.page.click('[data-call-beauty]'); ok('beauty panel opens on request', await A.page.locator('[data-beauty-sheet]').isVisible());
ok('default look is "No filter"', (await A.page.getAttribute('[data-beauty-list] [aria-checked="true"]', 'title')) === 'Your camera as it is');
await A.page.click('[data-beauty-close]');
await B.page.click('[data-call-answer]');
await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 }), B.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 })]);
ok('both sides reach active', true);
await A.page.waitForTimeout(5000);
let sa = await A.page.evaluate(() => window.N2NCall.stats()); let sb = await B.page.evaluate(() => window.N2NCall.stats());
console.log('A video', JSON.stringify(await A.page.evaluate(() => window.N2NCall.video())), 'B video', JSON.stringify(await B.page.evaluate(() => window.N2NCall.video()))); ok('caller receives audio and VIDEO bytes', sa.audioBytes > 0 && sa.videoBytes > 0, `(${sa.audioBytes} / ${sa.videoBytes})`);
ok('callee receives audio and VIDEO bytes', sb.audioBytes > 0 && sb.videoBytes > 0, `(${sb.audioBytes} / ${sb.videoBytes})`);
// The controls fade away on an active call; any movement brings them back.
const wake = async (P) => { await P.page.mouse.move(200, 300); await P.page.waitForTimeout(250); };
await A.page.waitForTimeout(1500); await wake(A);
// Choose Glow with the wand: the processed track replaces the camera track and video keeps flowing.
await A.page.click('[data-call-beauty]');
await A.page.click('[data-beauty-list] button[title="Bright and luminous"]');
ok('picking a look shows the intensity slider', await A.page.locator('[data-beauty-slider]').isVisible());
await A.page.waitForTimeout(3500);
const before = sb.videoBytes; sb = await B.page.evaluate(() => window.N2NCall.stats());
ok('beauty is active on the sending side', (await A.page.evaluate(() => window.N2NCall.stats())).beauty === 'glow');
ok('video keeps flowing to the callee after the filter turns on', sb.videoBytes > before, `(${before} -> ${sb.videoBytes})`);
await A.page.click('[data-beauty-close]');
ok('the wand lights up (white) while a look is on', await A.page.locator('[data-call-beauty].is-on').count() === 1);
// Layout: minimise top-left, name + timer centred, flip + wand on the small self tile, the pill bar with 5 round buttons.
await wake(A);
const geo = await A.page.evaluate(() => { const r = (s) => document.querySelector(s)?.getBoundingClientRect(); const bar = r('.nc-call__row'); const btns = [...document.querySelectorAll('.nc-call__row .nc-call__btn')].filter((b) => !b.hidden).map((b) => b.getAttribute('aria-label') || b.textContent.trim()); return { cover: r('#n2n-call'), min: r('[data-call-minimize]'), title: r('.ncv__title'), pip: r('[data-tile="self"]'), tools: r('[data-tile="self"] .ncv__tools'), bar, btns, vw: innerWidth }; });
ok('the call covers the full screen width', geo.cover.width === geo.vw && geo.cover.left === 0, JSON.stringify(geo.cover));
ok('minimise button sits top-left', geo.min.left < 30 && geo.min.top < 90);
ok('name and timer are centred', Math.abs((geo.title.left + geo.title.right) / 2 - geo.vw / 2) < 12);
ok('self view is the small tile bottom-right', geo.pip.right > geo.vw - 30 && geo.pip.width < geo.vw * 0.5 && geo.pip.top > 250);
ok('flip and wand sit on the self tile', geo.tools.left >= geo.pip.left && geo.tools.right <= geo.pip.right + 1);
ok('the pill bar holds More, Camera, Speaker, Mic, End', JSON.stringify(geo.btns) === JSON.stringify(['More', 'Turn the camera off', 'Speaker', 'Microphone', 'End the call']), JSON.stringify(geo.btns));
await A.page.screenshot({ path: 'test-results/n2n-video-active-caller.png' });
// More menu: reactions, Share screen (desktop only) and Send message; the top shows quality and a lock.
await A.page.click('[data-call-more]');
ok('More opens the reactions and the menu, and hides the bar', await A.page.locator('[data-more-sheet]').isVisible() && await A.page.locator('.nc-call__row').isHidden());
ok('quality bars and the lock replace the top buttons', await A.page.locator('[data-call-qicon]').isVisible() && await A.page.locator('[data-call-lock]').isVisible() && await A.page.locator('[data-call-minimize]').isHidden());
ok('Send message is offered', await A.page.locator('[data-call-msg]').isVisible());
await A.page.screenshot({ path: 'test-results/n2n-video-more-menu.png' });
await A.page.click('[data-react="❤️"]');
await B.page.waitForSelector('[data-call-float] span', { timeout: 6000 }); ok('the other person sees the reaction float up', true);
await A.page.waitForTimeout(400);
// Speaker and mic toggles.
await wake(A);
await A.page.click('[data-call-out]'); ok('speaker toggles off', (await A.page.locator('[data-call-out].is-on').count()) === 0);
await A.page.click('[data-call-out]'); ok('speaker toggles back on', (await A.page.locator('[data-call-out].is-on').count()) === 1);
await A.page.click('[data-call-mute]'); ok('mic mutes', (await A.page.getAttribute('[data-call-mute]', 'aria-pressed')) === 'true');
await A.page.click('[data-call-mute]');
// Tap the small tile to swap: you become the big picture and your tools move to the right edge.
await A.page.click('[data-tile="self"]', { position: { x: 20, y: 120 } });
const swapped = await A.page.evaluate(() => ({ selfMain: document.querySelector('[data-tile="self"]').classList.contains('ncv__tile--main'), tools: document.querySelector('[data-tile="self"] .ncv__tools').getBoundingClientRect().toJSON(), vw: innerWidth }));
ok('tapping the small tile swaps the pictures', swapped.selfMain);
ok('your tools move to the right edge when you are the big picture', swapped.tools.right > swapped.vw - 30 && swapped.tools.top < 260);
await A.page.screenshot({ path: 'test-results/n2n-video-swapped.png' });
await A.page.click('[data-tile="remote"]', { position: { x: 20, y: 120 } });
// Minimise to a small live window and come back.
await wake(A);
await A.page.click('[data-call-minimize]');
const mini = await A.page.evaluate(() => document.getElementById('n2n-call').getBoundingClientRect().toJSON());
ok('minimise shrinks the call to a small window', mini.width < 140 && mini.height < 200);
await A.page.screenshot({ path: 'test-results/n2n-video-minimised.png' });
await A.page.click('#n2n-call', { position: { x: 40, y: 60 } });
ok('tapping the window brings the call back', (await A.page.evaluate(() => document.getElementById('n2n-call').getBoundingClientRect().width)) > 300);
// Callee turns the camera off: the caller sees the "camera is off" placeholder; on again restores.
await wake(B);
await B.page.click('[data-call-cam]');
ok('caller sees the away placeholder when the callee turns the camera off', await A.page.waitForSelector('[data-tile="remote"] [data-call-away]:not([hidden])', { timeout: 10000 }).then(() => true, () => false));
await wake(B); await B.page.click('[data-call-cam]');
ok('placeholder goes away when the camera comes back', await A.page.waitForSelector('[data-tile="remote"] [data-call-away][hidden]', { state: 'attached', timeout: 10000 }).then(() => true, () => false));
await B.page.screenshot({ path: 'test-results/n2n-video-active-callee-mobile.png' });
await wake(A);
await A.page.click('[data-call-hangup]');
await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 }), B.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 })]);
ok('hang-up ends the call on both sides', true);
// The look is remembered on this device only.
ok('the chosen look is remembered locally', (await A.page.evaluate(() => JSON.parse(localStorage.getItem('naara-beauty') || '{}').preset)) === 'glow');
await A.ctx.close(); await B.ctx.close(); await browser.close();
process.exit(fails ? 1 : 0);
