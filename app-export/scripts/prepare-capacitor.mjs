#!/usr/bin/env node
// Turns the admin's App Studio config (Median-shaped JSON, as TriggerAppBuildJob sends it) into a complete capacitor.config.json, and bakes the live site address
// into the bundled offline screen. One script, used by the local build and by CI, so they can never drift apart.
//   node prepare-capacitor.mjs --config app-config.json --out capacitor.config.json --shell ../shell [--url https://www.naara.app]
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { parseArgs } from 'node:util';

const { values: a } = parseArgs({ options: { start: { type: 'string' }, config: { type: 'string' }, out: { type: 'string' }, shell: { type: 'string' }, url: { type: 'string' }, version: { type: 'string' } } });
const cfg = a.config && existsSync(a.config) ? JSON.parse(readFileSync(a.config, 'utf8')) : (process.env.NAARA_APP_CONFIG ? JSON.parse(process.env.NAARA_APP_CONFIG) : {});
const g = cfg.general || {}, st = cfg.styling || {}, nav = cfg.navigation || {};

const origin = (u) => { try { const x = new URL(u); return x.protocol === 'https:' ? x.origin : null; } catch { return null; } };
const url = origin(a.url || g.initialUrl) || 'https://www.naara.app';
const host = new URL(url).hostname;
const startPath = (a.start || g.startPath || '/get-started').trim();
if (!/^\/[A-Za-z0-9\-._~\/]*$/.test(startPath)) throw new Error('Start path "' + startPath + '" must be a plain path such as /get-started.');
const startUrl = url + startPath;
const appId = (g.androidPackageName || 'com.supremeideas.naara').trim();
if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/.test(appId)) throw new Error('Package id "' + appId + '" is not a valid Android/iOS id (e.g. com.company.app).');
const bg = /^#[0-9a-f]{6}$/i.test(st.androidBackgroundColor || '') ? st.androidBackgroundColor : '#0a172c';   // the Naara preloader's dark colour
const pay = ['paystack.com', 'paystack.co', 'flutterwave.com', 'stripe.com', 'paypal.com', 'checkout.com'];

// The app tells the website it is the app (hides "Get the app" links and social buttons that cannot work inside it, see app.css .nx-in-app).
const appVersion = /^[0-9][0-9A-Za-z.\-]{0,20}$/.test(a.version || '') ? a.version : '1.0.0';
const out = {
  appId,
  appendUserAgent: 'NaaraApp/' + appVersion,
  appName: (g.appName || 'Naara').trim(),
  webDir: a.shell || 'shell',
  server: { url: startUrl, cleartext: false, androidScheme: 'https', errorPath: 'offline.html', allowNavigation: [host, '*.' + host, ...pay.flatMap((h) => [h, '*.' + h])] },
  android: { allowMixedContent: false, webContentsDebuggingEnabled: false, backgroundColor: bg },
  ios: { contentInset: 'always', limitsNavigationsToAppBoundDomains: false, backgroundColor: bg, scheme: 'Naara' },
  plugins: {
    SplashScreen: { launchShowDuration: 2800, launchAutoHide: true, launchFadeOutDuration: 400, backgroundColor: bg, androidSplashResourceName: 'splash', androidScaleType: 'CENTER_CROP', showSpinner: false, splashFullScreen: true, splashImmersive: true },
  },
};
writeFileSync(a.out || 'capacitor.config.json', JSON.stringify(out, null, 2));

// the bundled offline screen reconnects to THIS address (Capacitor's errorPath cannot take a query string)
if (a.shell) {
  const p = a.shell + '/offline.html';
  const html = readFileSync(p, 'utf8').replace(/'https:\/\/www\.naara\.app\/get-started'/g, "'" + startUrl + "'").replace(/'https:\/\/www\.naara\.app\/'/g, "'" + startUrl + "'");
  writeFileSync(p, html);
}
console.log('capacitor.config.json ready:', out.appId, out.server.url);
