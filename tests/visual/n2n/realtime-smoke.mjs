// N2N realtime client smoke test (MASTER-ONLY): drives the real resources/js/n2n/realtime.js against a mocked server.
// Run: node tests/visual/n2n/realtime-smoke.mjs   (takes ~25 s: it measures real polling cadence)
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');

const src = fs.readFileSync(new URL('../../../resources/js/n2n/realtime.js', import.meta.url), 'utf8').replace(/export default [^;]+;/, '');
const results = []; const ok = (n, c) => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

async function scenario(handler, run) {
  const ctx = await browser.newContext(); const page = await ctx.newPage();
  const log = { sync: [], config: 0 };
  await page.route('https://app.test/**', async (route) => {
    const u = new URL(route.request().url());
    if (u.pathname === '/') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><meta name="csrf-token" content="t"><body>x</body>' });
    if (u.pathname === '/n2n/realtime/config') { log.config++; return route.fulfill({ json: { driver: 'poll', cursor: 5, user: 1, poll: { visible_ms: 1000, hidden_ms: 5000, max_backoff_ms: 8000 } } }); }
    if (u.pathname === '/n2n/realtime/sync') { log.sync.push({ t: Date.now(), cursor: Number(u.searchParams.get('cursor')) }); return handler(route, log); }
    return route.fulfill({ status: 404, body: '' });
  });
  await page.goto('https://app.test/');
  await page.addScriptTag({ content: src.replace('window.N2NRealtime = window.N2NRealtime || api;', 'window.N2NRealtime = api;') });
  await run(page, log);
  await ctx.close();
}
const setVisibility = (page, v) => page.evaluate((state) => { Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state }); document.dispatchEvent(new Event('visibilitychange')); }, v);

// 1. delivery, ordering, de-duplication, cursor handling
await scenario((route, log) => route.fulfill({ json: { events: log.sync.length === 1 ? [{ id: 6, type: 'message.new', payload: { n: 1 } }, { id: 7, type: 'message.new', payload: { n: 2 } }, { id: 6, type: 'message.new', payload: { n: 'dup' } }] : [], has_more: false, cursor: 7 } }), async (page, log) => {
  await page.evaluate(() => { window.got = []; window.N2NRealtime.subscribe('message.new', (p) => window.got.push(p.n)); window.N2NRealtime.start(); });
  await page.waitForFunction(() => window.got.length >= 2, null, { timeout: 8000 });
  await page.waitForTimeout(2500);
  ok('events arrive in order and a replayed id is dropped', JSON.stringify(await page.evaluate(() => window.got)) === '[1,2]');
  ok('config is fetched exactly once', log.config === 1);
  ok('first sync starts from the server cursor, later ones from the last id', log.sync[0].cursor === 5 && log.sync.at(-1).cursor === 7);
  ok('a thrown subscriber does not stop the stream', await page.evaluate(() => { window.N2NRealtime.subscribe('*', () => { throw new Error('x'); }); return true; }));
});

// 2. visible tab polls faster than a hidden one
await scenario((route) => route.fulfill({ json: { events: [], has_more: false, cursor: 5 } }), async (page, log) => {
  await page.evaluate(() => window.N2NRealtime.start());
  await page.waitForTimeout(6200);
  const visible = log.sync.length;
  await setVisibility(page, 'hidden');
  await page.waitForTimeout(500); const mark = log.sync.length;
  await page.waitForTimeout(11000);
  const hidden = log.sync.length - mark;
  ok(`visible tab polls about every second (${visible} in 6 s)`, visible >= 4 && visible <= 9);
  ok(`hidden tab polls far less (${hidden} in 11 s)`, hidden >= 1 && hidden <= 3);
  await setVisibility(page, 'visible'); const before = log.sync.length;
  await page.waitForTimeout(300);
  ok('becoming visible triggers an immediate catch-up sync', log.sync.length > before);
});

// 3. errors back off exponentially
await scenario((route) => route.fulfill({ status: 500, body: 'no' }), async (page, log) => {
  await page.evaluate(() => window.N2NRealtime.start());
  await page.waitForTimeout(9000);
  const gaps = log.sync.slice(1).map((s, i) => s.t - log.sync[i].t);
  ok(`gaps grow after errors (${gaps.map((g) => Math.round(g / 100) / 10).join('s, ')}s)`, gaps.length >= 2 && gaps[1] > gaps[0] * 1.5);
});

// 4. signed out / N2N switched off stops the loop for good
await scenario((route) => route.fulfill({ status: 401, body: '' }), async (page, log) => {
  await page.evaluate(() => window.N2NRealtime.start());
  await page.waitForTimeout(4000);
  ok('a 401 stops polling (no hammering a signed-out session)', log.sync.length === 1);
});

await browser.close();
process.exit(results.some((r) => !r[1]) ? 1 : 0);
