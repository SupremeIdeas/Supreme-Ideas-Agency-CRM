const test = require('node:test');
const assert = require('node:assert/strict');
const { isAppUrl, linkAction, permissionAllowed, deepLinkToUrl, offlineUrl, mediaKinds } = require('../src/policy');

test('only https naara.app (and our own bundled screens) load inside the app window', () => {
  assert.ok(isAppUrl('https://www.naara.app/esim'));
  assert.ok(isAppUrl('https://naara.app/'));
  assert.ok(isAppUrl('file:///opt/Naara/resources/app/shared/offline.html'));
  for (const bad of ['http://www.naara.app/', 'https://naara.app.evil.com/', 'https://evilnaara.app/', 'https://example.com/', 'javascript:alert(1)', 'not a url']) assert.equal(isAppUrl(bad), false, bad);
});
test('links: our site in-app, card checkout in a child window, the rest in the system browser, junk denied', () => {
  assert.equal(linkAction('https://www.naara.app/wallet'), 'app');
  assert.equal(linkAction('https://checkout.paystack.com/abc'), 'child');
  assert.equal(linkAction('https://example.com/x'), 'external');
  assert.equal(linkAction('mailto:hello@naara.app'), 'external');
  for (const bad of ['file:///etc/passwd', 'javascript:alert(1)', 'data:text/html,hi', 'nope']) assert.equal(linkAction(bad), 'deny', bad);
  assert.equal(linkAction('https://paystack.com.evil.io/'), 'external');
});
test('only the Naara origin may use the microphone, camera, notifications and location', () => {
  assert.ok(permissionAllowed('media', 'https://www.naara.app/dial'));
  assert.equal(permissionAllowed('media', 'https://evil.com/'), false);
  assert.equal(permissionAllowed('media', 'http://www.naara.app/'), false);
  assert.equal(permissionAllowed('openExternal', 'https://www.naara.app/'), false);
  assert.equal(permissionAllowed('midi', 'https://www.naara.app/'), false);
});
test('naara:// deep links map to site paths and nothing else', () => {
  assert.equal(deepLinkToUrl('naara://open/esim'), 'https://www.naara.app/esim');
  assert.equal(deepLinkToUrl('naara://wallet'), 'https://www.naara.app/wallet');
  assert.equal(deepLinkToUrl('naara://open/dial?to=1'), 'https://www.naara.app/dial?to=1');
  for (const bad of ['https://evil.com/', 'naara://open/..%2f..%2fx<script>', 'junk']) assert.equal(deepLinkToUrl(bad), null, bad);
});
test('offline url carries the retry target', () => assert.equal(offlineUrl('file:///a/offline.html', 'https://www.naara.app/'), 'file:///a/offline.html?retry=https%3A%2F%2Fwww.naara.app%2F'));
test('a media request is split into the OS device classes it needs', () => {
  assert.deepEqual(mediaKinds({ mediaTypes: ['video', 'audio'] }), ['audio', 'video']);
  assert.deepEqual(mediaKinds({ mediaTypes: ['audio'] }), ['audio']);
  assert.deepEqual(mediaKinds({}), []);
  assert.deepEqual(mediaKinds(undefined), []);
});
test('follow links to social platforms open outside the app window and never replace it', () => {
  for (const u of ['https://www.instagram.com/naara', 'https://x.com/NaaraFamily', 'https://www.tiktok.com/@naara', 'https://www.youtube.com/@naara', 'https://www.facebook.com/naara', 'https://t.me/naara', 'https://wa.me/2348000000000', 'https://www.linkedin.com/company/naara']) {
    assert.equal(linkAction(u), 'external', u);
    assert.equal(isAppUrl(u), false, u);
  }
  assert.equal(linkAction('https://naara.app.evil.com/x'), 'external');   // a lookalike host is external, never "app"
});
