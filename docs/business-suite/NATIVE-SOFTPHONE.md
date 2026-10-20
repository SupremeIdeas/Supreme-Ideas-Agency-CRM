# Naara native softphone (Business Suite Prompt 19), master only

Status in one line: **the server, the Android app and the iOS app now exist in this repo. Android is compiled, unit-tested, contract-tested against the real server and rendered to screenshots here; iOS is complete source with a macOS CI workflow that has not run yet. No call has been placed to a physical phone.** Nothing here may be called "native calling" in marketing until section 6 has passed on physical devices. Section 7 lists exactly what was proven and how.

## 1. The written decision (acceptance item 1)

| Who rings | Needs the native app? | Why |
|---|---|---|
| A member of a business workspace on a **ring-group seat** with "ring in the browser" on | **Yes** | A sales or support person must not miss an inbound lead because their phone was locked. A browser tab cannot ring a locked phone. |
| A member answering **call-flow** queues / forwards on a workspace number | **Yes** (same identity) | Same reason. |
| A person's **personal Naara Line** calls and the consumer **browser dialer** | **No** | The browser dialer is fine for placing calls; nobody depends on a locked-screen ring for a personal travel line. Stays on the web view exactly as today. |
| Account, wallet, eSIM, messaging, everything else | **No** | Stays on the existing `AppStudio` web-view shell. |

Decision: **hybrid**. Keep the web-view app for everything. Add one native module that owns only the calling screen and incoming-call handling.

## 2. What is built on the server (all tested)

- Switch: Admin > Business Suite > Modules > **Native softphone** (off by default). Off means the endpoints answer a plain 404.
- Eligibility: only an **active member of a business workspace** (owner, admin, agent) while Twilio is active. Removed members lose access at once.
- `POST /softphone/native/pair` (signed-in web view, session and CSRF): body `device_id` (8 to 64 of `A-Z a-z 0-9 . _ -`, stable per install), `platform` (`ios` or `android`), optional `app_version`, `os_version`. Returns a **one-purpose Sanctum token** (ability `softphone`, 30 days, bound to that phone). Pairing the same phone again replaces its token. Ten attempts a minute.
- `POST /api/softphone/token` (Bearer): body `device_id`, `platform`. Returns `{token, identity, ttl, push}`: a Twilio Voice access token (1 hour) for identity `naara_user_{id}` with `incoming.allow = true`, the outbound TwiML app, and, when set, the platform's **push credential SID**. `push: false` means the phone will only ring while the app is open; the app must say so. Thirty a minute.
- `POST /api/softphone/status` and `POST /api/softphone/unpair` (Bearer, same body): state and removal (revokes the token).
- Device list: table `business_softphone_devices` (no push token, no secret). A phone that has not asked for a token in 14 days is stale.
- Readiness check: `php artisan naara:verify softphone` (also in Admin > System Verify). Fails only when the module is on and something is missing.
- **Ring routing needs no change.** Ring groups already dial `<Client>naara_user_{id}</Client>` for seats with "ring in the browser". A phone registered under that identity answers to it, so one switch (now labelled "browser dialer and, when paired, the Naara phone app") rings both.
- Keys: Admin > API Keys > **Native softphone** group: the two Twilio Push Credential SIDs (IDs, not secrets).

## 3. Setting up the push credentials (you, once)

1. **iOS.** Apple Developer > Certificates > create a **VoIP Services Certificate** (or an APNs key). Export the `.p12`. Twilio Console > Voice > Manage > **Push credentials** > Create: type **APN**, upload the certificate and key, tick **Sandbox** for debug/TestFlight builds. Copy the SID (`CR...`) into Admin > API Keys > Twilio push credential (iOS). Use a second, non-sandbox credential for the App Store build.
2. **Android.** Firebase Console > Project settings > Service accounts > **Generate new private key** (a JSON file). Google shut the legacy FCM server key down in 2024, so Twilio now needs this FCM HTTP v1 service-account JSON. Twilio Console > Voice > Manage > Push credentials > Create: type **FCM**, paste the JSON. Copy the SID (`CR...`) into the Android field. Also download `google-services.json` for the app from Firebase (Project settings > Your apps) and put it at `mobile/business-suite-softphone/android/app/google-services.json` before building; without it the app builds but cannot receive pushes and its banner says so.
3. Twilio re-checks which key to use from the access token, so a token minted with the sandbox SID rings a debug build and one with the production SID rings the store build. Keep one environment per install of the server.
4. Run `php artisan naara:verify softphone`.

## 4. How a phone gets its credentials (the pairing flow)

```
web view (signed in)  --POST /softphone/native/pair-->  server   (session cookie + CSRF)
        |  token (ability softphone)
        v  JS bridge (Median / WKScriptMessageHandler / addJavascriptInterface)
native module  --POST /api/softphone/token (Bearer)-->  server   -> Twilio access token
native module  --Twilio Voice SDK register(token, pushToken)-->  Twilio
Twilio  --VoIP push (PushKit / FCM data)-->  phone   when a call dials <Client>naara_user_N</Client>
```

The web view never sees Twilio keys. The native module never sees the user's password or session. Logging out in the web view must call `unpair` first.

Bridge snippet for the web view page (runs after sign-in):

```js
const csrf = document.querySelector('meta[name=csrf-token]').content;
const r = await fetch('/softphone/native/pair', { method: 'POST', credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
  body: JSON.stringify({ device_id: NaaraNative.deviceId(), platform: NaaraNative.platform(), app_version: NaaraNative.appVersion(), os_version: NaaraNative.osVersion() }) });
if (r.ok) NaaraNative.savePairingToken((await r.json()).token);   // stored in Keychain / Keystore, never in web storage
```

## 5. The apps (in `mobile/business-suite-softphone/`)

Hybrid, as decided in section 1: a thin shell that shows the Naara web app, a one-line banner saying whether this phone will ring, and a native calling module. Placing calls stays in the web dialer; the apps **receive** business calls and let you answer, decline, mute, use the speaker and end.

| | Android (`android/`) | iOS (`ios/`) |
|---|---|---|
| Language / build | Java, Gradle, AGP 8.12, minSdk 28 | Swift 5.9, XcodeGen `project.yml`, iOS 15+ |
| Call SDK | `com.twilio:voice-android` 6.10 | `twilio-voice-ios` 6.13.6 (Swift Package) |
| Wake when closed | FCM high-priority data push (`NaaraMessagingService`) | PushKit VoIP push, reported to CallKit at once (`CallManager`) |
| Call screen | Full-screen notification and `CallActivity` over the lock screen | System CallKit UI |
| Secrets storage | Keystore-backed `EncryptedSharedPreferences` | Keychain (after first unlock) |
| Pairing | web view injects `PairScript`; token goes to native storage, never web storage | same script, same bridge name (`NaaraNative`) |
| Logout | logout form triggers `unpair` before the session ends | same |

Build: Android `cd mobile/business-suite-softphone/android && ./gradlew assembleDebug -PnaaraBaseUrl=https://your-domain`. iOS: on a Mac `brew install xcodegen && cd mobile/business-suite-softphone/ios && xcodegen generate`, set `NAARA_BASE_URL`, enable Push Notifications and Background Modes, sign. Both build from the manual-only workflow `.github/workflows/mobile-softphone.yml` (Actions tab, Run workflow).

Honest behaviours the apps show on screen: no push credential on the server means the banner says "this phone will not ring" (a native app cannot ring without push, so the old "rings only while open" idea was wrong and is removed); no Firebase file means the same; a revoked token says to sign in again.

Screens rendered on the JVM from the real layouts are in `docs/business-suite/softphone-screens/` (the web area of the main screen is a real mobile screenshot of the Naara dashboard composited under the real banner, because a web view does not render without a phone).

## 6. What must be proven on physical phones before this is marketed (acceptance items 3 and 4)

Use a debug build with the sandbox credential and a real second phone to place the calls. Record result, device, OS version and date for each row.

| # | Scenario | Expected |
|---|---|---|
| 1 | App open in the foreground | Rings with the call UI, answers, two-way audio |
| 2 | App backgrounded | Rings within 2 seconds |
| 3 | App force-quit (swiped away) | iOS: still rings (PushKit). Android: still rings (FCM high priority) |
| 4 | Phone locked, screen off | System lock-screen call UI, answer from the lock screen |
| 5 | Do Not Disturb / Focus on | Rings through if the product intent is "like a cellular call"; set the Focus allow-list or call category accordingly and record the decision |
| 6 | After a phone restart, app never opened | Rings (token re-registration on boot) |
| 7 | App not opened for 15 days | Rings, or the stale device appears in the admin list and the app re-pairs on next open |
| 8 | Wi-Fi to cellular switch during a call | Call survives or ends cleanly, no hung call UI |
| 9 | Two phones paired to one seat | Both ring; answering on one stops the other |
| 10 | Answered on the browser dialer while the phone rings | Phone stops ringing, no missed-call ghost |
| 11 | Member removed from the workspace | Next call does not ring that phone; `token` request answers 403 |
| 12 | Log out in the web view | `unpair` called, token revoked, phone no longer rings |
| 13 | Push credential missing | Banner says calls are not set up and the phone will not ring (`push: false`) |
| 14 | Low-power / battery saver (Android) | Rings (high-priority data message) or the app explains the vendor battery setting |
| 15 | Bluetooth / car audio | Audio routes correctly |

iOS rule: every VoIP push **must** be reported to CallKit immediately or the system will stop delivering pushes to the app. Android: the foreground notification for the ringing call must be posted promptly.

## 7. What was proven, and how (2026-10-19)

| Claim | Proof level | Evidence |
|---|---|---|
| Android app compiles, debug APKs for arm64, armv7, x86_64 and a universal build | **Proven here** | `./gradlew assembleDebug` succeeds; manifest inspected with `aapt2` (package, permissions, launcher) |
| Android toolchain and SDK versions work | **Proven here** | Twilio's own MIT quickstart built first with the same toolchain |
| Server client behaves (bearer, device id, 404/403/401/429/500 mapping, unpair forgets even offline, junk tokens refused) | **Proven here** | 9 JVM tests against a real local HTTP server |
| Pairing script works in a real browser against the real server | **Proven here** | Headless Chromium logged in as a workspace member, ran the exact script the app injects with a recording bridge, received a real Sanctum token (`tests/pair.mjs`) |
| Phone client against the real Laravel server | **Proven here** | `ServerContractTest`: status, token (JWT identity `naara_user_N`, incoming allowed, Android push credential SID present, no secret in the payload), unpair, revoked token refused |
| Call screen and banner render correctly | **Proven here** | Robolectric native-graphics screenshots of the real layouts (5 states) |
| iOS app compiles and its tests pass | **Not proven** | Source complete and written against Twilio's Swift quickstart API names; needs a Mac. Run the `ios` job of the manual workflow |
| A call rings a locked, closed phone | **Not proven** | Needs a physical phone, a Firebase project and Twilio push credentials. Section 6 rows 1 to 15 |
| Two-way audio, Bluetooth, network handover | **Not proven** | Physical devices |

The contract and browser checks used a sandbox with fake Twilio keys, so the JWT was minted and inspected, not presented to Twilio.
