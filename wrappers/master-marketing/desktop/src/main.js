'use strict';
const { app, BrowserWindow, Menu, Tray, shell, session, nativeImage, ipcMain, systemPreferences, clipboard } = require('electron');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { isAppUrl, linkAction, permissionAllowed, deepLinkToUrl, offlineUrl, mediaKinds } = require('./policy');

const APP_URL = 'https://www.naara.app/';
// Opens the first-run slides, which skip themselves once seen, send signed-out people to the login page and signed-in people to their dashboard. Never the marketing homepage.
const START_URL = APP_URL + 'get-started';
const UA_SUFFIX = ' NaaraDesktop/' + app.getVersion();
const stateFile = () => path.join(app.getPath('userData'), 'window-state.json');
const shared = (f) => path.join(__dirname, '..', 'shared', f);

let win = null;
let tray = null;
let quitting = false;

function readState() {
  try { return JSON.parse(fs.readFileSync(stateFile(), 'utf8')); } catch { return {}; }
}
function saveState() {
  if (!win || win.isDestroyed()) return;
  const b = win.isMaximized() ? readState().bounds : win.getBounds();
  try { fs.writeFileSync(stateFile(), JSON.stringify({ bounds: b, maximized: win.isMaximized(), firstRunDone: true })); } catch { /* read-only profile: not fatal */ }
}

function startUrl(target = START_URL) {
  const first = !readState().firstRunDone;
  return first ? pathToFileURL(shared('splash.html')).toString() + '?min=2800&next=' + encodeURIComponent(target) : target;
}

function createWindow(target) {
  const st = readState();
  win = new BrowserWindow({
    width: st.bounds?.width || 1280, height: st.bounds?.height || 840, x: st.bounds?.x, y: st.bounds?.y, minWidth: 420, minHeight: 600,
    show: false, backgroundColor: '#0D1B2A', title: 'Naara', icon: path.join(__dirname, '..', 'build', 'icon.png'),
    webPreferences: { contextIsolation: true, sandbox: true, nodeIntegration: false, webSecurity: true, spellcheck: true, preload: path.join(__dirname, 'preload.js') },
  });
  win.webContents.setUserAgent(win.webContents.getUserAgent().replace(/\sElectron\/\S+/, '') + UA_SUFFIX);
  if (st.maximized) win.maximize();
  win.once('ready-to-show', () => win.show());

  win.webContents.setWindowOpenHandler(({ url }) => {
    const a = linkAction(url);
    if (a === 'app') { win.loadURL(url); return { action: 'deny' }; }
    if (a === 'child') return { action: 'allow', overrideBrowserWindowOptions: { width: 520, height: 720, parent: win, modal: false, webPreferences: { sandbox: true, contextIsolation: true } } };
    if (a === 'external') shell.openExternal(url);
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', (e, url) => {
    if (isAppUrl(url)) return;
    e.preventDefault();
    if (linkAction(url) === 'external') shell.openExternal(url);
  });
  // no connection (or the service is down): our own screen, which reconnects by itself. -3 is a navigation the page cancelled, not a failure.
  win.webContents.on('did-fail-load', (_e, code, _d, failedUrl, isMain) => {
    if (!isMain || code === -3 || failedUrl.startsWith('file:')) return;
    win.loadURL(offlineUrl(pathToFileURL(shared('offline.html')).toString(), START_URL));
  });
  win.webContents.on('render-process-gone', () => { if (!quitting) win.reload(); });
  // right-click: copy, paste, spelling suggestions, and open a link in the browser (Electron has no context menu of its own)
  win.webContents.on('context-menu', (_e, p) => {
    const items = [];
    for (const word of p.dictionarySuggestions || []) items.push({ label: word, click: () => win.webContents.replaceMisspelling(word) });
    if (p.misspelledWord) items.push({ type: 'separator' });
    if (p.linkURL && linkAction(p.linkURL) === 'external') items.push({ label: 'Open link in browser', click: () => shell.openExternal(p.linkURL) }, { label: 'Copy link', click: () => clipboard.writeText(p.linkURL) }, { type: 'separator' });
    if (p.isEditable) items.push({ role: 'undo' }, { role: 'redo' }, { type: 'separator' }, { role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { role: 'selectAll' });
    else if (p.selectionText) items.push({ role: 'copy' });
    if (items.length) Menu.buildFromTemplate(items).popup({ window: win });
  });
  win.on('resize', saveState);
  win.on('move', saveState);
  win.on('close', (e) => {
    saveState();
    if (!quitting && process.platform === 'darwin') { e.preventDefault(); win.hide(); }
  });
  win.on('closed', () => { win = null; });
  win.loadURL(startUrl(target));
}

function buildMenu() {
  const mac = process.platform === 'darwin';
  const tpl = [
    ...(mac ? [{ role: 'appMenu' }] : []),
    { label: 'File', submenu: [{ label: 'Open Naara in my browser', click: () => shell.openExternal(APP_URL) }, mac ? { role: 'close' } : { role: 'quit' }] },
    { role: 'editMenu' },
    { label: 'View', submenu: [{ role: 'reload' }, { role: 'forceReload' }, { type: 'separator' }, { role: 'resetZoom' }, { role: 'zoomIn' }, { role: 'zoomOut' }, { type: 'separator' }, { role: 'togglefullscreen' }] },
    { role: 'windowMenu' },
    { label: 'Help', submenu: [{ label: 'Help Center', click: () => shell.openExternal(APP_URL + 'help') }, { label: 'Privacy', click: () => shell.openExternal(APP_URL + 'privacy') }] },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(tpl));
}

function buildTray() {
  const img = nativeImage.createFromPath(path.join(__dirname, '..', 'build', 'icon.png')).resize({ width: 18, height: 18 });
  tray = new Tray(img);
  tray.setToolTip('Naara');
  tray.setContextMenu(Menu.buildFromTemplate([{ label: 'Open Naara', click: show }, { type: 'separator' }, { label: 'Quit', click: () => { quitting = true; app.quit(); } }]));
  tray.on('click', show);
}

function show() {
  if (!win) { createWindow(); return; }
  if (win.isMinimized()) win.restore();
  win.show();
  win.focus();
}

function handleLink(link) {
  const url = deepLinkToUrl(link);
  if (!url) return;
  if (!win) createWindow(url); else { show(); win.loadURL(url); }
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', (_e, argv) => { const link = argv.find((a) => a.startsWith('naara://')); if (link) handleLink(link); else show(); });
  app.on('open-url', (e, link) => { e.preventDefault(); handleLink(link); });
  app.setAsDefaultProtocolClient('naara');

  app.whenReady().then(() => {
    // On macOS the OS asks the person for the microphone/camera once; without this the page's request fails silently. Windows and Linux have no per-app prompt.
    const askOs = async (details) => {
      if (process.platform !== 'darwin') return true;
      for (const kind of mediaKinds(details)) {
        const type = kind === 'audio' ? 'microphone' : 'camera';
        if (systemPreferences.getMediaAccessStatus(type) === 'granted') continue;
        if (!(await systemPreferences.askForMediaAccess(type))) return false;
      }
      return true;
    };
    session.defaultSession.setPermissionRequestHandler(async (wc, permission, cb, details) => {
      if (!permissionAllowed(permission, wc.getURL())) return cb(false);
      cb(permission === 'media' ? await askOs(details).catch(() => false) : true);
    });
    session.defaultSession.setPermissionCheckHandler((wc, permission, origin) => permissionAllowed(permission, origin));
    buildMenu();
    buildTray();
    createWindow();
    app.on('activate', show);
  });
  app.on('before-quit', () => { quitting = true; });
  app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
}

// Only our own pages (the preload runs for them alone) can ask; the window is hidden from capture on Windows and macOS while a protected video plays.
ipcMain.on('naara:content-protection', (e, on) => { const w = BrowserWindow.fromWebContents(e.sender); if (w && !w.isDestroyed() && isAppUrl(e.sender.getURL())) w.setContentProtection(on === true); });
ipcMain.handle('naara:info', () => ({ version: app.getVersion(), platform: process.platform }));
