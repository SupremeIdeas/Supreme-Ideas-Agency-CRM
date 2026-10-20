# Naara Phone: the native softphone apps (Business Suite Prompt 19, master only)

Read `docs/business-suite/NATIVE-SOFTPHONE.md` first: the decision, the server API, the pairing flow, what was proven and how, and the 15-row physical-device test table.

- `android/`: Java and Gradle. Web shell plus FCM push, full-screen call screen, Twilio Voice Android SDK. **Compiled, unit-tested and contract-tested here.**
- `ios/`: Swift and XcodeGen. Web shell plus PushKit, CallKit, Twilio Voice iOS SDK. **Complete source, not yet compiled (needs a Mac).**
- `tests/pair.mjs`: runs the app's real pairing script in headless Chromium against a real Naara server and writes the token it receives. Feed that token to the Android `ServerContractTest` (`NAARA_CONTRACT_BASE_URL`, `NAARA_CONTRACT_PAIRING_TOKEN`).

Android: `cd android && ./gradlew testDebugUnitTest assembleDebug -PnaaraBaseUrl=https://your-domain` (needs JDK 17+ and the Android SDK, platform 34). Add your Firebase `app/google-services.json` first or the banner will say the phone cannot ring while closed.
iOS: on a Mac, `brew install xcodegen && cd ios && xcodegen generate`, set `NAARA_BASE_URL`, enable Push Notifications and Background Modes (Voice over IP, Audio), sign.
Both can be built from the manual workflow `.github/workflows/mobile-softphone.yml`.

Use the Twilio **sandbox** push credential for debug builds and the production one for store builds. A call has not been placed to a physical phone yet.
