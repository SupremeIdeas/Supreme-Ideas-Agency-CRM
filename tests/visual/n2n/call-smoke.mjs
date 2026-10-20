// N2N voice call smoke test (MASTER-ONLY): REAL WebRTC between two browser contexts with fake microphones, signalled through the real server.
// Needs: a seeded app (frank@n2n.test / ada@n2n.test, connected, funded), calls enabled in dev mode, and coturn on 127.0.0.1:3478 with
// N2N_TURN_URLS / N2N_TURN_SECRET set on the server for the forced-relay run. Run: BASE=http://127.0.0.1:8765 node tests/visual/n2n/call-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--disable-features=WebRtcHideLocalIpsWithMdns', '--autoplay-policy=no-user-gesture-required'] });
let fails = 0; const ok = (n, c, extra = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); if (!c) fails++; };

async function login(email) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, permissions: ['microphone'] });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => console.log('pageerror', email, String(e).slice(0, 160)));
  if (process.env.DEBUG) { page.on('console', (m) => console.log('console', email, m.text().slice(0, 200))); page.on('response', (r) => { if (r.url().includes('/n2n/api/calls') || r.url().includes('/n2n/realtime/sync')) { r.text().then((t) => console.log('resp', email, r.status(), r.url().replace(BASE, ''), t.slice(0, 220))).catch(() => {}); } }); }
  await page.goto(BASE + '/login');
  await page.fill('input[name=email]', email); await page.fill('input[name=password]', 'password');
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
  await page.evaluate(() => { try { localStorage.setItem('nx_preloader_seen:n2n:' + new Date().toISOString().slice(0, 10), '99'); } catch (e) {} });
  return { ctx, page };
}

async function scenario(name, qs) {
  const A = await login('frank@n2n.test'); const B = await login('ada@n2n.test');
  await B.page.goto(BASE + '/n2n/chat' + qs, { waitUntil: 'networkidle' });
  await A.page.goto(BASE + '/u/ada' + qs, { waitUntil: 'networkidle' });
  await B.page.waitForTimeout(1500);                                   // let B's realtime connect
  await A.page.click('[data-n2n-call]');
  await A.page.waitForSelector('#n2n-call[data-phase="calling"]', { timeout: 10000 });
  ok(`${name}: caller sees Calling...`, true);
  await B.page.waitForSelector('#n2n-call[data-phase="ringing"]', { timeout: 15000 });
  ok(`${name}: callee's screen rings`, true);
  await B.page.screenshot({ path: `test-results/n2n-call-ringing-${name}.png` });
  await B.page.click('[data-call-answer]');
  await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 }), B.page.waitForSelector('#n2n-call[data-phase="active"]', { timeout: 30000 })]);
  ok(`${name}: both sides reach the active phase (ICE connected)`, true);
  await A.page.waitForTimeout(6000);                                   // media flows; heartbeats run
  const sa = await A.page.evaluate(() => window.N2NCall.stats()); const sb = await B.page.evaluate(() => window.N2NCall.stats());
  ok(`${name}: audio bytes received by caller > 0`, sa && sa.audioBytes > 0, `(${sa && sa.audioBytes} B)`);
  ok(`${name}: audio bytes received by callee > 0`, sb && sb.audioBytes > 0, `(${sb && sb.audioBytes} B)`);
  const relay = qs.includes('relay=1');
  ok(`${name}: relay ${relay ? 'USED (forced)' : 'not required (direct path)'}`, relay ? (sa.relay && sb.relay) : true, `caller relay=${sa.relay} callee relay=${sb.relay}`);
  await A.page.screenshot({ path: `test-results/n2n-call-active-${name}.png` });
  // mute toggles the local track
  await A.page.click('[data-call-mute]');
  ok(`${name}: mute pressed state`, (await A.page.getAttribute('[data-call-mute]', 'aria-pressed')) === 'true');
  await A.page.click('[data-call-hangup]');
  await Promise.all([A.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 }), B.page.waitForSelector('#n2n-call[data-phase="ended"], #n2n-call[hidden]', { timeout: 15000 })]);
  ok(`${name}: hang-up ends the call on both sides`, true);
  await A.ctx.close(); await B.ctx.close();
}

await scenario('direct', '');
await scenario('relay', '?relay=1');
await browser.close();
process.exit(fails ? 1 : 0);
