#!/usr/bin/env node
// Responsive audit (engineering blueprint 0.6). For each route x viewport x colour scheme it asserts:
//   - no horizontal page overflow (scrollWidth <= innerWidth)
//   - no visible element sticking out past the right edge
//   - tap targets in <main> are at least 44x44 CSS px (warnings, listed, not failures: many are inline links in prose)
// and saves a screenshot. Exit code 1 if any hard assertion fails.
//
//   node scripts/responsive/audit.mjs --base http://127.0.0.1:8000 --routes /login,/register --out test-results/responsive
//   Optional: --cookie "laravel_session=...; XSRF-TOKEN=..." to audit signed-in pages.
// Playwright is resolved from PLAYWRIGHT_MODULE, then ./node_modules, then /opt/node-tools (the cloud image).
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > -1 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8000').replace(/\/$/, '');
const routes = arg('routes', '/login').split(',').map((r) => r.trim()).filter(Boolean);
const out = arg('out', 'test-results/responsive');
const cookie = arg('cookie', '');
const VIEWPORTS = [['360x740', 360, 740], ['390x844', 390, 844], ['768x1024', 768, 1024], ['1280x800', 1280, 800]];
const SCHEMES = ['light', 'dark'];

const require = createRequire(import.meta.url);
let pw;
for (const p of [process.env.PLAYWRIGHT_MODULE, 'playwright', path.resolve('node_modules/playwright'), '/opt/node-tools/node_modules/playwright'].filter(Boolean)) {
    try { pw = require(p); break; } catch { /* try next */ }
}
if (!pw) { console.error('Playwright not found. Set PLAYWRIGHT_MODULE or npm i -D playwright.'); process.exit(2); }

fs.mkdirSync(out, { recursive: true });
const browser = await pw.chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM || (fs.existsSync('/opt/pw-browsers/chromium') ? '/opt/pw-browsers/chromium' : undefined) });
let failures = 0;
for (const route of routes) {
    for (const [label, w, h] of VIEWPORTS) {
        for (const scheme of SCHEMES) {
            const ctx = await browser.newContext({ viewport: { width: w, height: h }, colorScheme: scheme, extraHTTPHeaders: cookie ? { cookie } : {} });
            const page = await ctx.newPage();
            const errors = [];
            page.on('pageerror', (e) => errors.push(String(e.message).slice(0, 160)));
            try {
                await page.goto(base + route, { waitUntil: 'networkidle', timeout: 30000 });
                await page.waitForTimeout(600);
                const r = await page.evaluate(() => {
                    const iw = window.innerWidth;
                    const overflow = document.documentElement.scrollWidth - iw;
                    const out = [];
                    document.querySelectorAll('body *').forEach((el) => {
                        const cs = getComputedStyle(el);
                        if (cs.visibility === 'hidden' || cs.display === 'none' || cs.position === 'fixed') return;
                        const b = el.getBoundingClientRect();
                        if (b.width > 0 && b.height > 0 && b.right > iw + 1 && !el.closest('[data-allow-overflow], .overflow-x-auto, .overflow-hidden, svg, canvas, video')) out.push((el.tagName + '.' + String(el.className).split(' ')[0]).slice(0, 60));
                    });
                    const small = [];
                    document.querySelectorAll('main a, main button, main [role=button]').forEach((el) => {
                        const b = el.getBoundingClientRect();
                        if (b.width > 0 && b.height > 0 && (b.width < 44 || b.height < 44) && getComputedStyle(el).display !== 'inline') small.push((el.textContent || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 30));
                    });
                    return { overflow, sticking: [...new Set(out)].slice(0, 8), small: [...new Set(small)].slice(0, 8) };
                });
                const file = path.join(out, `${route.replace(/[^a-z0-9]+/gi, '_') || 'root'}-${label}-${scheme}.png`);
                await page.screenshot({ path: file, fullPage: true });
                const bad = r.overflow > 1 || r.sticking.length > 0 || errors.length > 0;
                if (bad) failures++;
                console.log(`${bad ? 'FAIL' : 'ok  '} ${route} ${label} ${scheme}` + (r.overflow > 1 ? ` overflow=${r.overflow}px` : '') + (r.sticking.length ? ` sticking=${r.sticking.join('|')}` : '') + (errors.length ? ` js=${errors.join('|')}` : '') + (r.small.length ? ` (small taps: ${r.small.join(', ')})` : ''));
            } catch (e) {
                failures++;
                console.log(`FAIL ${route} ${label} ${scheme} ${String(e.message).slice(0, 120)}`);
            }
            await ctx.close();
        }
    }
}
await browser.close();
process.exit(failures ? 1 : 0);
