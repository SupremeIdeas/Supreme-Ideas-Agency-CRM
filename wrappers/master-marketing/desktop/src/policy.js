// Pure rules (no Electron imports) so they are unit-tested in Node.
const APP_HOST = 'naara.app';
const PAY_HOSTS = ['paystack.com', 'paystack.co', 'flutterwave.com', 'stripe.com', 'paypal.com', 'checkout.com'];

const hostIs = (host, base) => host === base || host.endsWith('.' + base);

export function parse(url) { try { return new URL(url); } catch { return null; } }

/** Inside the app window: only https on naara.app (and the bundled screens). */
export function isAppUrl(url) {
  const u = parse(url);
  return !!u && ((u.protocol === 'https:' && hostIs(u.hostname, APP_HOST)) || u.protocol === 'file:');
}

/** What to do with a link the page tries to open. */
export function linkAction(url) {
  const u = parse(url);
  if (!u) return 'deny';
  if (u.protocol === 'https:' && hostIs(u.hostname, APP_HOST)) return 'app';
  if (u.protocol === 'https:' && PAY_HOSTS.some((h) => hostIs(u.hostname, h))) return 'child';   // card checkout and 3-D Secure need an in-app window to return to us
  if (['https:', 'http:', 'mailto:', 'tel:'].includes(u.protocol)) return 'external';
  return 'deny';
}

/** Only the app's own origin may use the microphone, camera, notifications and location (calls, QR, live location). */
export function permissionAllowed(permission, requestingUrl) {
  const u = parse(requestingUrl);
  if (!u || u.protocol !== 'https:' || !hostIs(u.hostname, APP_HOST)) return false;
  return ['media', 'notifications', 'geolocation', 'clipboard-sanitized-write', 'fullscreen', 'speaker-selection'].includes(permission);
}

/** The OS-level device classes a 'media' request needs (macOS asks the person once per class). */
export function mediaKinds(details) {
  const t = (details && details.mediaTypes) || [];
  return ['audio', 'video'].filter((k) => t.includes(k));
}

/** naara://open/esim -> https://www.naara.app/esim. Anything that is not a plain path is ignored. */
export function deepLinkToUrl(link, base = 'https://www.naara.app/') {
  const u = parse(link);
  if (!u || u.protocol !== 'naara:') return null;
  const path = (u.hostname === 'open' ? u.pathname : '/' + u.hostname + u.pathname).replace(/\/{2,}/g, '/');
  if (!/^\/[A-Za-z0-9\-._~/%?=&]*$/.test(path + u.search) || /\.\.|%2e|%2f|%5c|%00/i.test(path)) return null;
  return new URL(path + u.search, base).toString();
}

export function offlineUrl(offlineFileUrl, retry) { return offlineFileUrl + '?retry=' + encodeURIComponent(retry); }
