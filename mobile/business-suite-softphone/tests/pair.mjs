import { chromium } from 'playwright';
import fs from 'fs';
const base = process.env.NAARA_URL || 'http://127.0.0.1:8152';
const b = await chromium.launch(); const p = await (await b.newContext()).newPage();
await p.goto(base + '/login');
await p.fill('input[name=email]', process.env.AGENT_EMAIL); await p.fill('input[name=password]', process.env.AGENT_PASSWORD);
await Promise.all([p.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), p.click('button[type=submit]')]);
console.log('after login url:', p.url());
await p.goto(base + '/dashboard').catch(() => {});
console.log('page url:', p.url());
// the phone shell's bridge, stubbed to record what the page hands over
await p.evaluate(() => {
  window.__saved = null;
  window.NaaraNative = { deviceId: () => 'android-contracttest0001', platform: () => 'android', appVersion: () => '1.0.0', osVersion: () => '14', isPaired: () => false, savePairingToken: t => { window.__saved = t; }, unpair: () => {} };
});
await p.evaluate(fs.readFileSync(process.env.PAIRSCRIPT || 'pairscript.js', 'utf8'));
await p.waitForFunction(() => window.__saved, null, { timeout: 15000 }).catch(() => {});
const tok = await p.evaluate(() => window.__saved);
console.log('token handed to native:', tok ? tok.slice(0, 6) + '...(' + tok.length + ' chars)' : null);
if (tok) fs.writeFileSync('pairing.token', tok);
await b.close();
