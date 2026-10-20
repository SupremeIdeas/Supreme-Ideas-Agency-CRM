# Naara desktop app and Chrome extension (master only)

Premium wrappers that open https://www.naara.app/ in their own window, with a first-launch splash (animated Naara logo and illustration) and a real offline screen that reconnects by itself. Android and iOS come from App Builder (App Export) and the phone apps in `mobile/business-suite-softphone/`; all of them are managed from Admin > Installers.

| Folder | What | Built here |
|---|---|---|
| `shared/` | `splash.html`, `offline.html` and the logo assets. One copy, used by every wrapper. Dark/light and reduced-motion aware, no network needed. | yes |
| `chrome-extension/` | Manifest V3 extension for Chrome, Edge, Brave: toolbar click opens Naara in an app window (or a tab from the menu), remembers window size, plays the splash on first run, shows the offline screen when unreachable. | yes: `node build.mjs chrome` |
| `desktop/` | Electron app for Windows, macOS, Linux. Single instance, window memory, tray, menu, `naara://` links, permissions only for naara.app, links to other sites open in the browser, card checkout opens in an in-app window. | Linux AppImage and .deb; Windows and macOS as .zip. The Windows .exe installer and macOS .dmg need their own OS: run the workflow. |

## Build

```
node --test wrappers/master-marketing/tests/core.test.mjs        # extension rules
node wrappers/master-marketing/build.mjs chrome                  # dist/naara-chrome-extension-1.0.0.zip
cd wrappers/master-marketing/desktop && npm install && npm test  # security rules
npm run dist:linux     # AppImage + deb (any OS with Linux tooling)
npm run dist:win       # Windows installer (run on Windows)
npm run dist:mac       # dmg + zip (run on a Mac)
```

The manual workflow `.github/workflows/master-marketing-wrappers.yml` builds all of them on the right runners (Actions tab > Run workflow). Change the address in `config.json`, `chrome-extension/src/core.js`, `desktop/src/main.js` and `desktop/src/policy.js` if the app ever moves off www.naara.app.

## Putting them in front of users

1. Download the files from the `installers` branch (heavy files live there, not in main) or from the workflow run.
2. Admin > Installers: upload each file (or paste a link), set the version, tick Published. They appear on `/download` with size and SHA-256.

## Signing and stores (you need to do these; they need your accounts)

- **Windows**: an unsigned .exe triggers SmartScreen. Buy a code-signing certificate and set `CSC_LINK` / `CSC_KEY_PASSWORD` in the workflow. Microsoft Store needs an MSIX target and a Partner Center account.
- **macOS**: the .zip/.dmg must be signed with a Developer ID and notarized (`CSC_LINK`, `APPLE_ID`, `APPLE_APP_SPECIFIC_PASSWORD`, `APPLE_TEAM_ID`) or Gatekeeper blocks it. Mac App Store needs a different target and review.
- **Linux**: AppImage and .deb run unsigned.
- **Chrome Web Store**: one-time developer fee, upload `naara-chrome-extension-1.0.0.zip`, add the store screenshots and privacy policy URL. Google rejects extensions that are only a link to a site with no added value, so the store listing should lead with what the extension does (app window, offline screen, shortcuts). Add real features (notifications, quick actions) before submitting if the review pushes back.
- **Google social login** blocks embedded browsers, so "Continue with Google" inside the desktop app opens the system browser and cannot hand the session back. Email login and passkeys work. Add a desktop-specific sign-in code flow before promoting Google login there.
- **Auto-update** is not wired: it needs a release feed you host. Until then each new version is a new upload in Admin > Installers.
