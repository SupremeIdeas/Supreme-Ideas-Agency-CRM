// N2N VOICE call screen smoke test (MASTER-ONLY): the redesigned voice UI over a REAL WebRTC call between two browser contexts (fake microphones).
// Same setup as call-smoke.mjs. Run: BASE=http://127.0.0.1:8099 node tests/visual/n2n/voice-call-ui-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--disable-features=WebRtcHideLocalIpsWithMdns', '--autoplay-policy=no-user-gesture-required'] });
let fails = 0; const ok = (n, c, extra = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); if (!c) fails++; };
async function login(email) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 780 }, permissions: ['microphone'], hasTouch: true });
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
const A = await login('frank@n2n.test'); const B = await login('ada@n2n.test');
await B.page.goto(BASE + '/n2n/chat', { waitUntil: 'networkidle' }); await A.page.goto(BASE + '/u/ada', { waitUntil: 'networkidle' }); await B.page.waitForTimeout(1500);
await A.page.click('[data-n2n-call]');
await A.page.waitForSelector('#n2n-call.is-voice[data-phase="calling"]', { timeout: 10000 });
ok('caller sees the voice screen with the big avatar while calling', await A.page.isVisible('[data-call-hero]') && (await A.page.textContent('[data-call-vclock]')).includes('Calling'));
await B.page.waitForSelector('#n2n-call.is-voice[data-phase="ringing"]', { timeout: 15000 });
ok('callee sees the ringing screen with Decline and Answer', await B.page.isVisible('[data-call-decline]') && await B.page.isVisible('[data-call-answer]'));
await B.page.screenshot({ path: 'test-results/n2n-voice-ringing.png' });
await B.page.click('[data-call-answer]');
await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 }), B.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 })]);
await A.page.waitForTimeout(4000);
const sa = await A.page.evaluate(() => window.N2NCall.stats()); ok('audio flows to the caller', sa.audioBytes > 0, `(${sa.audioBytes} B)`);
const g = await A.page.evaluate(() => { const r = (s) => document.querySelector(s)?.getBoundingClientRect(); return { av: r('[data-call-avatar]'), title: r('.ncv__title'), card: r('.nc-call__row'), vw: innerWidth, btns: [...document.querySelectorAll('.nc-call__row .nc-call__btn')].filter((b) => getComputedStyle(b).display !== 'none').map((b) => ({ t: b.querySelector('span').textContent.trim(), y: Math.round(b.getBoundingClientRect().top), x: b.getBoundingClientRect().left })).sort((a, b) => a.y - b.y || a.x - b.x).map((b) => b.t) }; });
ok('a big round avatar is centred', g.av.width >= 180 && Math.abs((g.av.left + g.av.right) / 2 - g.vw / 2) < 4);
ok('name and timer are centred at the top', Math.abs((g.title.left + g.title.right) / 2 - g.vw / 2) < 12 && g.title.top < 100);
ok('the rounded control card shows Speaker, Mute, More, End with labels', JSON.stringify(g.btns) === JSON.stringify(['Speaker', 'Mute', 'More', 'End']), JSON.stringify(g.btns));
await A.page.screenshot({ path: 'test-results/n2n-voice-active.png' });
await A.page.click('[data-call-more]');
ok('More shows reactions and Send message, with quality and lock on top', await A.page.isVisible('[data-more-sheet]') && await A.page.isVisible('[data-call-msg]') && await A.page.isHidden('[data-call-share]') && await A.page.isVisible('[data-call-lock]') && await A.page.isVisible('[data-call-qicon]'));
await A.page.screenshot({ path: 'test-results/n2n-voice-more.png' });
await A.page.click('[data-react="🙏"]');
await B.page.waitForSelector('[data-call-float] span', { timeout: 8000 }); ok('the other person sees the reaction', true);
await A.page.click('[data-call-out]'); ok('speaker toggles', (await A.page.locator('[data-call-out].is-on').count()) === 0); await A.page.click('[data-call-out]');
await A.page.click('[data-call-mute]'); ok('mute is pressed', (await A.page.getAttribute('[data-call-mute]', 'aria-pressed')) === 'true'); await A.page.click('[data-call-mute]');
await A.page.click('[data-call-minimize]');
const mini = await A.page.evaluate(() => document.getElementById('n2n-call').getBoundingClientRect().toJSON()); ok('minimise shrinks the call to a small window', mini.width < 140);
await A.page.screenshot({ path: 'test-results/n2n-voice-minimised.png' });
await A.page.click('#n2n-call', { position: { x: 40, y: 60 } }); await A.page.waitForTimeout(400);
await A.page.click('[data-call-hangup]');
await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 }), B.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 })]);
ok('hang-up ends the call on both sides', true);
// After the call: the rating sheet (when the admin has it on): stars -> Submit -> an invitation to Trustpilot offered whatever the rating was.
await A.page.waitForTimeout(4500);
const sheet = await A.page.locator('[data-call-review]').isVisible();
if (process.env.REVIEW === '1') {
  ok('the rating sheet opens after the call', sheet);
  ok('Submit is disabled until a star is chosen', await A.page.locator('[data-review-submit]').isDisabled());
  await A.page.screenshot({ path: 'test-results/n2n-call-review-rate.png' });
  await A.page.click('[data-star="2"]'); ok('Submit enables once a star is chosen', await A.page.locator('[data-review-submit]').isEnabled());
  await A.page.click('[data-review-submit]');
  await A.page.waitForSelector('[data-review-step="thanks"]:not([hidden])', { timeout: 8000 });
  ok('even a 2-star answer is invited to Trustpilot', (await A.page.getAttribute('[data-review-link]', 'href')).includes('trustpilot.com/evaluate/'));
  ok('a low rating also gets a support link', await A.page.locator('[data-review-support]').isVisible());
  await A.page.screenshot({ path: 'test-results/n2n-call-review-thanks.png' });
} else { ok('the rating sheet stays hidden while it is switched off', !sheet); }
await A.ctx.close(); await B.ctx.close(); await browser.close(); process.exit(fails ? 1 : 0);
