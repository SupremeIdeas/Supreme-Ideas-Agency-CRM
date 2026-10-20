// Browser check of Naara Chat status mentions and the in-app camera (fake camera device): tag in a text status, take a photo with a caption that tags, record a short video, then the tagged member sees "Mentioned you".
// Usage: BASE=http://127.0.0.1:8099 OWNER=frank@n2n.test VIEWER=ada@n2n.test PASS=password OUT=/path node scripts/gestures/status-camera.mjs
import { chromium } from '/opt/node-tools/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099'; const OUT = process.env.OUT || '/tmp'; const PASS = process.env.PASS;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] });
const errors = []; const res = {};
async function login(ctx, email) {
  const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(String(e))); p.on('requestfailed', (r) => { if (!/favicon|realtime\/sync/.test(r.url())) errors.push('requestfailed ' + r.url().slice(0, 80)); }); p.on('console', (m) => { if (m.type() === 'error' && !/ERR_FILE_NOT_FOUND|favicon/.test(m.text())) errors.push(m.text()); });
  await p.goto(BASE + '/login'); await p.waitForTimeout(800);
  const rej = p.getByRole('button', { name: /reject all/i }); if (await rej.count()) { await rej.first().click().catch(() => {}); }
  await p.fill('input[name=email]', email); await p.fill('input[name=password]', PASS);
  await Promise.all([p.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), p.click('button[type=submit]')]);
  return p;
}
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, permissions: ['camera', 'microphone'], colorScheme: process.env.SCHEME || 'dark' });
const p = await login(ctx, process.env.OWNER);
await p.goto(BASE + '/n2n/updates', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
if (!process.env.VIDEO) {
await p.getByRole('button', { name: /add status/i }).click(); await p.waitForTimeout(500);
// text status with a mention
const ta = p.locator('textarea[aria-label="Status text"]'); await ta.click(); await ta.type('Coffee with @Ad', { delay: 40 }); await p.waitForTimeout(500);
res.mention_list = await p.locator('[data-status-mention] [role=option]').count(); await p.screenshot({ path: `${OUT}/st-mention.png` });
await p.locator('[data-status-mention] [role=option]').first().click(); await p.waitForTimeout(400);
res.text_after_pick = await ta.inputValue();
await p.getByRole('button', { name: /^post$/i }).first().click(); await p.waitForTimeout(2500);
// photo from the in-app camera
await p.getByRole('button', { name: /add status/i }).click(); await p.waitForTimeout(400);
await p.getByRole('tab', { name: 'Photo' }).click(); await p.waitForTimeout(2500);
res.cam_ready = await p.locator('[data-status-camera] video[autoplay]').first().evaluate((v) => v.videoWidth > 0).catch(() => false);
await p.screenshot({ path: `${OUT}/st-camera.png` });
await p.getByRole('button', { name: 'Take photo' }).click(); await p.waitForTimeout(700);
res.review_img = await p.locator('[data-status-camera] img[alt="Your photo"]').isVisible();
const cap = p.locator('#st-caption'); await cap.click(); await cap.type('Sunset with @Ad', { delay: 40 }); await p.waitForTimeout(400);
res.caption_list = await p.locator('[data-status-camera] [role=option]').count(); await p.screenshot({ path: `${OUT}/st-review.png` });
await p.locator('[data-status-camera] [role=option]').first().click(); await p.waitForTimeout(300); res.caption_value = await cap.inputValue();
await p.locator('[data-status-camera]').getByRole('button', { name: /^post$/i }).click(); await p.waitForTimeout(4000);
}
// video from the in-app camera (needs cloud storage to be offered; VIDEO=1 runs this part against a server configured for it and stops at the review step)
if (process.env.VIDEO) {
// video from the in-app camera
await p.getByRole('button', { name: /add status/i }).click(); await p.waitForTimeout(400);
await p.getByRole('tab', { name: 'Video' }).click(); await p.waitForTimeout(2500);
await p.getByRole('button', { name: 'Record video' }).click(); await p.waitForTimeout(2500); await p.screenshot({ path: `${OUT}/st-recording.png` });
await p.getByRole('button', { name: 'Stop recording' }).click(); await p.waitForTimeout(900);
res.review_video = await p.locator('[data-status-camera] video[controls]').isVisible();
}
if (process.env.VIDEO) { console.log(JSON.stringify({ res, errors }, null, 1)); console.log(res.review_video && !errors.length ? 'PASS (video record and review)' : 'FAIL video'); await b.close(); process.exit(res.review_video && !errors.length ? 0 : 1); }
res.my_posts = await p.locator('ul[aria-label="My active status updates"] > li').count(); await p.screenshot({ path: `${OUT}/st-mine.png`, fullPage: true });
// the tagged member
const actx = await b.newContext({ viewport: { width: 390, height: 844 } }); const a = await login(actx, process.env.VIEWER);
await a.goto(BASE + '/n2n/updates', { waitUntil: 'networkidle' }); await a.waitForTimeout(1200);
const ring = a.locator('button:has-text("Frank"), [data-ring]').first(); await ring.click({ timeout: 8000 }).catch(() => {}); await a.waitForTimeout(1500);
res.viewer_badge = await a.locator('[data-status-mentioned]').count(); await a.screenshot({ path: `${OUT}/st-viewer.png` });
console.log(JSON.stringify({ res, errors }, null, 1));
const bad = []; if (!res.mention_list) bad.push('mention list'); if (!/@Ada Tester/.test(res.text_after_pick || '')) bad.push('pick'); if (!res.cam_ready) bad.push('camera'); if (!res.review_img) bad.push('review'); if (!/@Ada Tester/.test(res.caption_value || '')) bad.push('caption pick'); if (!process.env.VIDEO && res.my_posts < 2) bad.push('posts ' + res.my_posts); if (process.env.VIDEO && !res.review_video) bad.push('video review'); if (!res.viewer_badge) bad.push('viewer badge');
console.log(bad.length || errors.length ? 'FAIL ' + bad.join(', ') : 'PASS'); await b.close(); process.exit(bad.length || errors.length ? 1 : 0);
