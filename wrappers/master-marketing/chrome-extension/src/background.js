import { APP_URL, startPage, clampBounds } from './core.js';

const KEY_WIN = 'appWindowId';
const KEY_FIRST = 'firstRunDone';
const KEY_BOUNDS = 'bounds';

async function reachable() {
  try {
    const c = new AbortController();
    const t = setTimeout(() => c.abort(), 4000);
    await fetch(APP_URL, { method: 'HEAD', cache: 'no-store', signal: c.signal });
    clearTimeout(t);
    return true;
  } catch {
    return false;
  }
}

async function openApp({ inTab = false } = {}) {
  const store = await chrome.storage.local.get([KEY_WIN, KEY_FIRST, KEY_BOUNDS]);

  // one app window: focus it if it is still open
  if (!inTab && store[KEY_WIN]) {
    try {
      const w = await chrome.windows.get(store[KEY_WIN]);
      if (w) { await chrome.windows.update(w.id, { focused: true, drawAttention: true }); return; }
    } catch { /* closed */ }
  }

  const url = startPage({ firstRun: !store[KEY_FIRST], reachable: await reachable(), extUrl: (p) => chrome.runtime.getURL(p) });
  await chrome.storage.local.set({ [KEY_FIRST]: true });

  if (inTab) { await chrome.tabs.create({ url }); return; }

  const display = (await chrome.system?.display?.getInfo?.())?.[0]?.workArea;
  const b = clampBounds(store[KEY_BOUNDS], display ? { width: display.width, height: display.height } : undefined);
  const w = await chrome.windows.create({ url, type: 'popup', focused: true, ...b });
  await chrome.storage.local.set({ [KEY_WIN]: w.id });
}

chrome.action.onClicked.addListener(() => openApp());

chrome.runtime.onInstalled.addListener(({ reason }) => {
  chrome.contextMenus.create({ id: 'open-window', title: 'Open Naara in an app window', contexts: ['action'] });
  chrome.contextMenus.create({ id: 'open-tab', title: 'Open Naara in a tab', contexts: ['action'] });
  if (reason === 'install') openApp();   // first launch plays the splash
});

chrome.contextMenus.onClicked.addListener((info) => openApp({ inTab: info.menuItemId === 'open-tab' }));

chrome.windows.onRemoved.addListener(async (id) => {
  const s = await chrome.storage.local.get(KEY_WIN);
  if (s[KEY_WIN] === id) await chrome.storage.local.remove(KEY_WIN);
});

chrome.windows.onBoundsChanged?.addListener(async (w) => {
  const s = await chrome.storage.local.get(KEY_WIN);
  if (s[KEY_WIN] === w.id && w.state === 'normal') await chrome.storage.local.set({ [KEY_BOUNDS]: { width: w.width, height: w.height, left: w.left, top: w.top } });
});
