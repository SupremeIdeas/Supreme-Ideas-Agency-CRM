// Pure helpers (no chrome.* calls) so they can be unit-tested in Node.
export const APP_URL = 'https://www.naara.app/';
/** The app opens the first-run slides (they skip themselves once seen; signed-in people land on their dashboard, signed-out on login), never the marketing homepage. */
export const START_URL = APP_URL + 'get-started';

/** Which page the app window should open first. Splash only on the very first launch; offline page whenever the service cannot be reached. */
export function startPage({ firstRun, reachable, appUrl = START_URL, extUrl }) {
  if (!reachable) return extUrl('offline.html') + '?retry=' + encodeURIComponent(appUrl);
  if (firstRun) return extUrl('splash.html') + '?min=2800&next=' + encodeURIComponent(appUrl);
  return appUrl;
}

/** Window bounds that always fit the screen, whatever was saved last time. */
export function clampBounds(saved, screen = { width: 1920, height: 1080 }) {
  const w = Math.min(Math.max(saved?.width || 1180, 420), screen.width);
  const h = Math.min(Math.max(saved?.height || 800, 560), screen.height);
  const left = Math.min(Math.max(saved?.left ?? Math.round((screen.width - w) / 2), 0), Math.max(screen.width - w, 0));
  const top = Math.min(Math.max(saved?.top ?? Math.round((screen.height - h) / 2), 0), Math.max(screen.height - h, 0));
  return { width: w, height: h, left, top };
}
