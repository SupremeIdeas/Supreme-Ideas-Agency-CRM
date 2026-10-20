# App Export: build the Naara mobile apps for free, on any machine

The platform's mobile apps are Capacitor shells that open the live site (remote-URL mode). Admin > App Builder holds the settings; these scripts turn them into real builds.
Nothing here needs paid CI: `build-android-local.sh` runs on any Linux/macOS/Windows-WSL machine, and the CI workflows call the same scripts.

| File | What it does |
|---|---|
| `build-android-local.sh` | Builds the signed **AAB** (Google Play) and **APK** (direct download), verifies the signature, SDK level and permissions, writes SHA-256 sums. |
| `build-ios.sh` | On a Mac: builds the iOS app. `--simulator` needs no Apple account; without it you get an archive and `.ipa` for TestFlight/App Store (needs your Apple Team ID). |
| `scripts/prepare-capacitor.mjs` | Admin config -> `capacitor.config.json` (start page, offline page, splash, allowed hosts). Rejects an invalid package id. |
| `scripts/patch-android.mjs` | Permissions, verified app links, no data backup, real signing, version. |
| `scripts/patch-ios.mjs` | Permission usage strings (a missing one is an instant App Store rejection), export compliance, associated domains. |
| `shell/` | Bundled offline screen the app shows when the site cannot be reached. |
| `defaults/` | Icon (adaptive on Android) and the splash artwork (the Naara preloader, with "from" and the Supreme Ideas logo below). The admin's own icon and splash replace these. |

## What the user sees

1. **First launch:** the native splash (Naara preloader on white or the dark Naara navy, "from" in italic Times New Roman with the Supreme Ideas logo below), then the site's own animated Naara preloader while the first page loads.
2. **Slides:** the app opens `/get-started`: the onboarding slides (Admin > App Builder > Onboarding; four on-brand slides ship by default). Next, Next, Create account: a new person lands on **register** (with "I already have an account" for sign-in). Later launches skip the slides and go to **login**.
3. **Signed-in people** go straight to their dashboard; the marketing homepage is never the start page.
4. **No connection:** the bundled offline screen (reconnects by itself).

## Build

```
# Android (JDK 21, Android SDK platform 36 + build-tools 36, Node 20+)
app-export/build-android-local.sh --version 1.0.0 --code 1
#   store-ready: export NAARA_KEYSTORE_FILE NAARA_STORE_PASSWORD NAARA_KEY_ALIAS NAARA_KEY_PASSWORD first
#   without them you get *-TEST-SIGNED files: installable for testing, NOT uploadable to Play

# iOS (Mac with Xcode 16+)
app-export/build-ios.sh --simulator                       # free test build
app-export/build-ios.sh --team ABCDE12345 --version 1.0.0 # App Store build
```

Package id: set it once in App Builder (`com.supremeideas.naara` by default) BEFORE the first Play upload; it can never change afterwards.

## Store readiness

Verified deep links need two things you set in Admin > App Builder > Deep-link domain: the **Android signing fingerprint** (Play Console > App integrity) and your **Apple Team ID**. The site then serves `/.well-known/assetlinks.json` and `/.well-known/apple-app-site-association` (a 404 until set, so nothing half-configured is published). Make sure your web server lets `/.well-known/` through.

| Requirement | Status |
|---|---|
| Play: target API 35+ (build targets 36), AAB, 64-bit, signed | Built and verified here (test-signed) |
| Play: Data safety form, privacy policy URL, content rating, store listing | You, in Play Console (App Builder shows the checklist) |
| Play: verified app links | Needs the fingerprint above |
| Play/Apple: an app that is more than a website | Has: onboarding, offline screen, native splash, permissions-based camera/mic/location, deep links. **Add before submitting** (reviewers look for it): native push notifications (Capacitor push plugin + FCM/APNs), share sheet, pull to refresh. Not built yet. |
| Apple: Sign in with Apple when offering third-party login (guideline 4.8) | Required if Google/Facebook login is shown inside the iOS app: add Sign in with Apple or hide social login in the iOS build |
| Apple: in-app account deletion (5.1.1) | The platform has self-deactivate and a deletion request; confirm the request is reachable from the app |
| Apple: payments (3.1.1) | See `docs/APP-STORE-PAYMENTS-COMPLIANCE.md`: wallet top-up, Wizard fee and merchant upgrade are already restricted in the native iOS build |
| Apple: build, signing, TestFlight, review | Needs a Mac (or Codemagic), your Apple Developer account |

## What was tested

Android: compiled, signed, signature/SDK/permissions inspected (`aapt2`, `apksigner`), bundled offline screen and splash resources confirmed inside the APK. **Not tested on a device or emulator**: this build machine has no hardware virtualization. The manual `android-build.yml` workflow includes an emulator job (install, launch, screenshot, then offline) that runs on GitHub's free Linux runners. iOS: scripts and patching written and the patch step run against a generated project; **not built** (needs macOS).
