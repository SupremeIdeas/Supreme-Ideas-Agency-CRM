import test from 'node:test';
import assert from 'node:assert/strict';
import { startPage, clampBounds, APP_URL, START_URL } from '../chrome-extension/src/core.js';

const extUrl = (p) => 'chrome-extension://abc/' + p;

test('first launch plays the splash, then hands over to the app', () => {
  const u = startPage({ firstRun: true, reachable: true, extUrl });
  assert.match(u, /^chrome-extension:\/\/abc\/splash\.html\?min=2800&next=/);
  assert.equal(decodeURIComponent(u.split('next=')[1]), START_URL);
});
test('later launches go straight to the app', () => assert.equal(startPage({ firstRun: false, reachable: true, extUrl }), START_URL));
test('no connection shows the offline screen with a way back, even on first launch', () => {
  for (const firstRun of [true, false]) {
    const u = startPage({ firstRun, reachable: false, extUrl });
    assert.match(u, /offline\.html\?retry=/);
    assert.equal(decodeURIComponent(u.split('retry=')[1]), START_URL);
  }
});
test('saved bounds are clamped so the window can never open off-screen', () => {
  const b = clampBounds({ width: 5000, height: 10, left: 9000, top: -50 }, { width: 1366, height: 768 });
  assert.ok(b.width <= 1366 && b.height >= 560 && b.height <= 768);
  assert.ok(b.left >= 0 && b.left + b.width <= 1366 && b.top >= 0);
});
test('defaults centre a 1180x800 window', () => {
  const b = clampBounds(undefined, { width: 1920, height: 1080 });
  assert.deepEqual(b, { width: 1180, height: 800, left: 370, top: 140 });
});

test('the start page is the onboarding route on the Naara site, not the homepage', () => { assert.equal(START_URL, APP_URL + 'get-started'); });
