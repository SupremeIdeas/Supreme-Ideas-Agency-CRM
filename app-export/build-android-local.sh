#!/usr/bin/env bash
# Builds the Naara Android app (signed AAB for Google Play + APK for direct download) on THIS machine: no paid CI, no GitHub Actions minutes.
# Same steps as .github/workflows/android-build.yml, driven by the same scripts, so what you test here is what CI would build.
#
#   app-export/build-android-local.sh [--config app-config.json] [--version 1.0.0] [--code 1] [--url https://www.naara.app] [--out ./app-export-out]
#
# Needs: Node 20+, JDK 21, Android SDK (platform 36, build-tools 36) at $ANDROID_HOME. For a store-ready build set
#   NAARA_KEYSTORE_FILE, NAARA_STORE_PASSWORD, NAARA_KEY_ALIAS, NAARA_KEY_PASSWORD
# Without them a throw-away TEST keystore is generated and the files are named *-TEST-SIGNED (installable for testing, not uploadable to Play).
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
CONFIG=""; VERSION="1.0.0"; CODE="1"; URL=""; OUT="$PWD/app-export-out"
while [ $# -gt 0 ]; do case "$1" in
  --config) CONFIG="$2"; shift 2;; --version) VERSION="$2"; shift 2;; --code) CODE="$2"; shift 2;; --url) URL="$2"; shift 2;; --out) OUT="$2"; shift 2;;
  *) echo "unknown option $1"; exit 2;; esac; done

export ANDROID_HOME="${ANDROID_HOME:-/opt/android-sdk}"; export ANDROID_SDK_ROOT="$ANDROID_HOME"
WORK="${NAARA_WORK:-$(mktemp -d /tmp/naara-app-export.XXXXXX)}"
echo "work dir: $WORK"
mkdir -p "$WORK" "$OUT"
rm -rf "$WORK/shell" "$WORK/assets"
cp -r "$HERE/shell" "$WORK/shell"
cp -r "$HERE/defaults" "$WORK/assets"
cat > "$WORK/package.json" <<'JSON'
{ "name": "naara-app-export", "private": true, "version": "1.0.0",
  "dependencies": { "@capacitor/android": "^8.5.2", "@capacitor/core": "^8.5.2", "@capacitor/splash-screen": "^8.0.2" },
  "devDependencies": { "@capacitor/cli": "^8.5.2", "@capacitor/assets": "^3.0.5" } }
JSON
cd "$WORK"

PREP=(--out capacitor.config.json --shell shell)
[ -n "$CONFIG" ] && PREP+=(--config "$CONFIG")
[ -n "$URL" ] && PREP+=(--url "$URL"); PREP+=(--version "$VERSION")
node "$HERE/scripts/prepare-capacitor.mjs" "${PREP[@]}"
ORIGIN="$(node -e "console.log(new URL(require('./capacitor.config.json').server.url).origin)")"

npm install --no-audit --no-fund --silent
rm -rf android
npx cap add android >/dev/null
# the admin's own icon and splash win over the bundled defaults when App Studio has them (downloaded by the caller into ./assets)
npx capacitor-assets generate --android --assetPath assets --iconBackgroundColor '#0D1B2A' --splashBackgroundColor '#FFFFFF' --splashBackgroundColorDark '#0A172C' >/dev/null
node "$HERE/scripts/patch-android.mjs" android "$ORIGIN"
npx cap sync android >/dev/null

if [ -z "${NAARA_KEYSTORE_FILE:-}" ]; then
  echo "No release keystore supplied: generating a TEST keystore (not for the Play Store)."
  export NAARA_KEYSTORE_FILE="$WORK/test.keystore" NAARA_STORE_PASSWORD="testpass123" NAARA_KEY_ALIAS="naara-test" NAARA_KEY_PASSWORD="testpass123"
  rm -f "$NAARA_KEYSTORE_FILE"
  keytool -genkeypair -noprompt -keystore "$NAARA_KEYSTORE_FILE" -storepass "$NAARA_STORE_PASSWORD" -keypass "$NAARA_KEY_PASSWORD" -alias "$NAARA_KEY_ALIAS" \
    -keyalg RSA -keysize 2048 -validity 10000 -dname "CN=Naara TEST, O=Test, C=NG" >/dev/null 2>&1
  TAG="-TEST-SIGNED"
else TAG=""; fi

# Maven Central rate-limits shared networks (HTTP 429); dependencies already downloaded are kept, so retrying converges.
for attempt in 1 2 3 4 5 6 7 8; do
  if ( cd android && ./gradlew --no-daemon -q assembleRelease bundleRelease -PversionName="$VERSION" -PversionCode="$CODE" ); then break; fi
  [ "$attempt" = 8 ] && { echo "build failed after $attempt attempts"; exit 1; }
  echo "gradle attempt $attempt failed (usually a rate limit); retrying in 45s"; sleep 45
done

APK="$(ls android/app/build/outputs/apk/release/*.apk | head -1)"
AAB="$(ls android/app/build/outputs/bundle/release/*.aab | head -1)"
BT="$(ls -d "$ANDROID_HOME"/build-tools/* | sort -V | tail -1)"
echo "--- verify"
"$BT/apksigner" verify --print-certs "$APK" | head -3
"$BT/aapt2" dump badging "$APK" | grep -E "^package|^sdkVersion|^targetSdkVersion|^application-label:|^launchable" | head -6
echo "permissions:"; "$BT/aapt2" dump permissions "$APK" | sed 's/^/  /'
node -e "console.log(require('./capacitor.config.json').appId)" > "$OUT/package-id.txt"
cp "$APK" "$OUT/Naara-$VERSION-android$TAG.apk"; cp "$AAB" "$OUT/Naara-$VERSION-android$TAG.aab"
( cd "$OUT" && sha256sum Naara-$VERSION-android$TAG.* > SHA256SUMS-android.txt; ls -la )
