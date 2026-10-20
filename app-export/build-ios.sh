#!/usr/bin/env bash
# Builds the Naara iOS app on a Mac (Xcode 16+, CocoaPods, Node 20+). iOS cannot be built on Linux or Windows.
#   app-export/build-ios.sh [--config app-config.json] [--version 1.0.0] [--code 1] [--url https://www.naara.app] [--team ABCDE12345] [--out ./app-export-out] [--simulator]
# --simulator builds an unsigned simulator app (to test the install and the screens, free, no Apple account).
# --prepare-only generates and patches the Xcode project and stops (Codemagic then signs and archives it).
# Without it an archive + .ipa is produced; that needs your Apple team and signing set up in Xcode or via Codemagic (see docs/APP-EXPORT.md).
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
CONFIG=""; VERSION="1.0.0"; CODE="1"; URL=""; TEAM=""; OUT="$PWD/app-export-out"; SIM=0; PREPARE_ONLY=0
while [ $# -gt 0 ]; do case "$1" in
  --config) CONFIG="$2"; shift 2;; --version) VERSION="$2"; shift 2;; --code) CODE="$2"; shift 2;; --url) URL="$2"; shift 2;;
  --team) TEAM="$2"; shift 2;; --out) OUT="$2"; shift 2;; --simulator) SIM=1; shift;; --prepare-only) PREPARE_ONLY=1; shift;; *) echo "unknown option $1"; exit 2;; esac; done
[ "$(uname)" = "Darwin" ] || { echo "iOS builds need macOS."; exit 1; }

WORK="${NAARA_WORK:-$(mktemp -d /tmp/naara-ios-export.XXXXXX)}"; mkdir -p "$WORK" "$OUT"
rm -rf "$WORK/shell" "$WORK/assets"; cp -r "$HERE/shell" "$WORK/shell"; cp -r "$HERE/defaults" "$WORK/assets"
cat > "$WORK/package.json" <<'JSON'
{ "name": "naara-app-export", "private": true, "version": "1.0.0",
  "dependencies": { "@capacitor/ios": "^8.5.2", "@capacitor/core": "^8.5.2", "@capacitor/splash-screen": "^8.0.2" },
  "devDependencies": { "@capacitor/cli": "^8.5.2", "@capacitor/assets": "^3.0.5" } }
JSON
cd "$WORK"
PREP=(--out capacitor.config.json --shell shell); [ -n "$CONFIG" ] && PREP+=(--config "$CONFIG"); [ -n "$URL" ] && PREP+=(--url "$URL"); PREP+=(--version "$VERSION")
node "$HERE/scripts/prepare-capacitor.mjs" "${PREP[@]}"
ORIGIN="$(node -e "console.log(new URL(require('./capacitor.config.json').server.url).origin)")"
NAME="$(node -e "console.log(require('./capacitor.config.json').appName)")"
BUNDLE="$(node -e "console.log(require('./capacitor.config.json').appId)")"

npm install --no-audit --no-fund --silent
rm -rf ios; npx cap add ios >/dev/null
npx capacitor-assets generate --ios --assetPath assets --iconBackgroundColor '#0D1B2A' --splashBackgroundColor '#FFFFFF' --splashBackgroundColorDark '#0A172C' >/dev/null
node "$HERE/scripts/patch-ios.mjs" ios/App/App "$ORIGIN" "$NAME"
npx cap sync ios >/dev/null
( cd ios/App && pod install >/dev/null && agvtool new-marketing-version "$VERSION" >/dev/null && agvtool new-version -all "$CODE" >/dev/null )

if [ "$PREPARE_ONLY" = 1 ]; then
  echo "prepared: $WORK/ios/App/App.xcworkspace   (entitlements: App.entitlements)"
  exit 0
fi

if [ "$SIM" = 1 ]; then
  xcodebuild -workspace ios/App/App.xcworkspace -scheme App -configuration Debug -sdk iphonesimulator -derivedDataPath "$WORK/dd" CODE_SIGNING_ALLOWED=NO build | tail -5
  APP="$(find "$WORK/dd" -name 'App.app' -path '*iphonesimulator*' | head -1)"
  ditto -c -k --keepParent "$APP" "$OUT/Naara-$VERSION-ios-simulator.zip"
  echo "simulator app: $OUT/Naara-$VERSION-ios-simulator.zip   (xcrun simctl install booted App.app)"
else
  [ -n "$TEAM" ] || { echo "--team <Apple Team ID> is required for a device/App Store build"; exit 1; }
  xcodebuild -workspace ios/App/App.xcworkspace -scheme App -configuration Release -archivePath "$WORK/App.xcarchive" \
    DEVELOPMENT_TEAM="$TEAM" PRODUCT_BUNDLE_IDENTIFIER="$BUNDLE" CODE_SIGN_ENTITLEMENTS="App.entitlements" -allowProvisioningUpdates archive | tail -5
  cat > "$WORK/export.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict><key>method</key><string>app-store-connect</string><key>teamID</key><string>$TEAM</string></dict></plist>
PLIST
  xcodebuild -exportArchive -archivePath "$WORK/App.xcarchive" -exportOptionsPlist "$WORK/export.plist" -exportPath "$OUT" -allowProvisioningUpdates
fi
ls -la "$OUT"
