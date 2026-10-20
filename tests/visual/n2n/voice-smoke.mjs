// N2N voice notes: the premium recorder (live waves, pause/resume, scrub) and the bubble player (waves dance while it plays). Needs ada@n2n.test (Pro) with a chat to frank.
// Uses Chromium's fake microphone, so a real recording is made without hardware.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const ok = (n, c, x = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  ' + x : '')); if (!c) { process.exitCode = 1; } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--autoplay-policy=no-user-gesture-required'] });
const sig = (p, sel) => p.$eval(sel, (c) => { const g = c.getContext('2d'); const d = g.getImageData(0, 0, c.width, c.height).data; let h = 0; for (let i = 3; i < d.length; i += 4) { h = (h * 31 + d[i]) >>> 0; } let ink = 0; for (let i = 3; i < d.length; i += 4) { if (d[i] > 0) { ink++; } } return { h, ink }; });
try {
  for (const scheme of ['dark', 'light']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2, permissions: ['microphone'] });
    await ctx.addInitScript(() => { try { localStorage.setItem('cc_mic_ok', 'true'); } catch (e) {} });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'ada@n2n.test'); await p.fill('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
    await p.goto(BASE + '/n2n/chat', { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.nc__rows button');
    await p.evaluate((s) => { const d = document.documentElement; d.dataset.nxMode = s; d.classList.toggle('dark', s === 'dark'); }, scheme);
    await p.locator('.nc__rows button', { hasText: 'Frank' }).first().click(); await p.waitForSelector('naara-composer .cc-text', { timeout: 15000 }); await p.waitForTimeout(1200);
    await p.click('naara-composer [data-r=micBtn]'); await p.waitForSelector('.cc-voice:not([hidden])', { timeout: 6000 });
    ok(scheme + ': recorder opens with a timer, a state label and a canvas stage', (await p.$('.cc-vcanvas')) !== null && (await p.textContent('.cc-vstate')) === 'Recording');
    await p.waitForTimeout(1200); const a = await sig(p, '.cc-vcanvas'); await p.waitForTimeout(700); const b = await sig(p, '.cc-vcanvas');
    ok(scheme + ': the live waves move while recording', a.ink > 0 && a.h !== b.h, `ink=${a.ink}`);
    await p.screenshot({ path: `test-results/voice-recording-${scheme}-390.png` });
    await p.click('.cc-pill'); await p.waitForTimeout(500);
    ok(scheme + ': Pause freezes it and offers Resume, play and speed', (await p.textContent('.cc-vstate')) === 'Paused' && (await p.textContent('.cc-pill')).includes('Resume') && (await p.$('.cc-vplay:not([hidden])')) !== null && (await p.$('.cc-speed:not([hidden])')) !== null);
    const f1 = await sig(p, '.cc-vcanvas'); await p.waitForTimeout(400); const f2 = await sig(p, '.cc-vcanvas');
    ok(scheme + ': paused waves stay still', f1.h === f2.h);
    await p.screenshot({ path: `test-results/voice-paused-${scheme}-390.png` });
    await p.click('.cc-vplay'); await p.waitForTimeout(500); const d1 = await sig(p, '.cc-vcanvas'); await p.waitForTimeout(450); const d2 = await sig(p, '.cc-vcanvas');
    ok(scheme + ': preview playback animates the waves', d1.h !== d2.h);
    await p.click('.cc-vplay');
    const t1 = await p.textContent('.cc-vtimer'); await p.click('.cc-pill'); await p.waitForTimeout(1600);
    const t2 = await p.textContent('.cc-vtimer');
    ok(scheme + ': Resume continues the same recording (timer keeps counting)', t2 !== '0:00' && t2 >= t1, `${t1} -> ${t2}`);
    await p.click('.cc-vsend'); await p.waitForSelector('.nvp', { timeout: 30000 });
    await p.waitForTimeout(1500);
    const meta = await p.$eval('.nvp', (e) => ({ peaks: (e.dataset.peaks || '').split(',').length, dur: e.dataset.dur }));
    ok(scheme + ': the sent note shows a player with its stored waveform', meta.peaks >= 8, JSON.stringify(meta));
    await p.screenshot({ path: `test-results/voice-bubble-${scheme}-390.png` });
    const last = p.locator('.nvp').last();
    const n0 = await p.evaluate(() => { const c = document.querySelectorAll('.nvp__wave'); return c[c.length - 1].toDataURL().length; });
    await last.locator('.nvp__btn').click(); await p.waitForTimeout(700);
    ok(scheme + ': play starts and the button turns to pause', (await last.evaluate((e) => e.classList.contains('is-playing'))) === true);
    const s1 = await p.evaluate(() => { const c = document.querySelectorAll('.nvp__wave'); return c[c.length - 1].toDataURL(); }); await p.waitForTimeout(260);
    const s2 = await p.evaluate(() => { const c = document.querySelectorAll('.nvp__wave'); return c[c.length - 1].toDataURL(); });
    ok(scheme + ': the bubble waves dance while it plays', s1 !== s2);
    await p.screenshot({ path: `test-results/voice-playing-${scheme}-390.png` });
    await last.locator('.nvp__btn').click(); await p.waitForTimeout(300);
    ok(scheme + ': pause stops it', (await last.evaluate((e) => e.classList.contains('is-playing'))) === false);
    await ctx.close();
  }
} finally { await browser.close(); }
