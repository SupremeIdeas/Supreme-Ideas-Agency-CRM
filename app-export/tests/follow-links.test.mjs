// The Android/iOS shell must hand follow links (Instagram, X, TikTok ...) to the platform's app, so those hosts must NEVER be in allowNavigation (Capacitor keeps allowed hosts inside
// the app window and sends every other host to the system, which opens the installed app).  node --test app-export/tests/
import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

test('allowNavigation keeps only Naara and card checkout inside the app, so social links open their apps', () => {
  const out = join(mkdtempSync(join(tmpdir(), 'cap-')), 'capacitor.config.json');
  execFileSync('node', [join(here, '..', 'scripts', 'prepare-capacitor.mjs'), '--out', out, '--version', '1.2.3']);
  const cfg = JSON.parse(readFileSync(out, 'utf8'));
  const allowed = cfg.server.allowNavigation.join(' ');
  for (const social of ['instagram', 'x.com', 'twitter', 'tiktok', 'youtube', 'facebook', 't.me', 'wa.me', 'whatsapp', 'linkedin', 'snapchat']) assert.ok(!allowed.includes(social), social + ' must open its app, not inside ours');
  assert.ok(allowed.includes('naara.app') && allowed.includes('paystack.com'));
  assert.equal(cfg.appendUserAgent, 'NaaraApp/1.2.3');
});

test('the Android patch sends known social hosts to their package and falls back to the normal path', () => {
  const src = readFileSync(join(here, '..', 'scripts', 'patch-android.mjs'), 'utf8');
  for (const pair of ['instagram.com", "com.instagram.android', 'x.com", "com.twitter.android', 'tiktok.com", "com.zhiliaoapp.musically', 'youtube.com", "com.google.android.youtube', 't.me", "org.telegram.messenger', 'wa.me", "com.whatsapp']) assert.ok(src.includes(pair), pair);
  assert.ok(src.includes('i.setPackage(pkg)') && src.includes('return false;   // the app is not installed'));
});
