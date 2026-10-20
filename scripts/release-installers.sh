#!/usr/bin/env bash
# Builds EVERY installer for one version, from one command, so nothing is built from a stale folder or named by hand:
#   Chrome extension zip, desktop (Linux AppImage + .deb, Windows zip, macOS Apple silicon + Intel zips) and the Android APK + AAB.
# Output: <out>/v<version>/ with the files, SHA256SUMS*.txt, join.sh and release-manifest.json. Drop that whole folder into Admin > Installers:
# the manifest tells the admin which file belongs in which slot, its checksum and its version, and the admin warns about anything missing or foreign.
#
#   scripts/release-installers.sh 1.0.2 [--out /path] [--code 10002] [--skip-android] [--skip-desktop]
# Windows .exe and macOS .dmg cannot be built on Linux: run .github/workflows/master-marketing-wrappers.yml for those (or build on that OS), then add them to the folder.
set -euo pipefail

VERSION="${1:-}"; shift || true
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "usage: $0 <x.y.z> [--out dir] [--code n] [--skip-android] [--skip-desktop]"; exit 2; }
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_ROOT="$ROOT/release-out"; SKIP_ANDROID=0; SKIP_DESKTOP=0
IFS=. read -r MA MI PA <<<"$VERSION"; CODE=$((MA * 10000 + MI * 100 + PA))
while [ $# -gt 0 ]; do case "$1" in
  --out) OUT_ROOT="$2"; shift 2;; --code) CODE="$2"; shift 2;; --skip-android) SKIP_ANDROID=1; shift;; --skip-desktop) SKIP_DESKTOP=1; shift;;
  *) echo "unknown option $1"; exit 2;; esac; done
OUT="$OUT_ROOT/v$VERSION"; rm -rf "$OUT"; mkdir -p "$OUT"
W="$ROOT/wrappers/master-marketing"

echo "== 1/6 one version everywhere ($VERSION, Android versionCode $CODE)"
node "$W/set-version.mjs" "$VERSION"

echo "== 2/6 tests"
node --test "$W/tests/core.test.mjs" >/dev/null
( cd "$W/desktop" && { [ -d node_modules ] || npm ci --no-audit --no-fund --silent; } && npm test --silent >/dev/null )

echo "== 3/6 Chrome extension"
node "$W/build.mjs" chrome >/dev/null
cp "$W/dist/naara-chrome-extension-$VERSION.zip" "$OUT/"

if [ "$SKIP_DESKTOP" = 0 ]; then
  echo "== 4/6 desktop (Linux, Windows zip, macOS zips)"
  cd "$W/desktop"; rm -rf dist; npm run prebuild --silent
  for target in "--linux AppImage deb --x64" "--win zip --x64" "--mac zip --x64 --arm64"; do
    echo "   electron-builder $target"
    # shellcheck disable=SC2086
    npx electron-builder $target --publish never > "$OUT/.eb.log" 2>&1 || { tail -30 "$OUT/.eb.log"; echo "electron-builder failed for: $target"; exit 1; }
  done
  rm -f "$OUT/.eb.log"; cd "$ROOT"
  for f in "$W"/desktop/dist/Naara-"$VERSION"-*; do case "$f" in *.blockmap|*.yml) ;; *) [ -f "$f" ] && cp "$f" "$OUT/";; esac; done
fi

if [ "$SKIP_ANDROID" = 0 ]; then
  echo "== 5/6 Android (APK for the download page, AAB for Google Play)"
  "$ROOT/app-export/build-android-local.sh" --version "$VERSION" --code "$CODE" --out "$OUT/android"
fi

echo "== 6/6 checksums, split files over 95 MB, manifest"
cd "$OUT"
for f in *.zip *.AppImage *.deb; do [ -f "$f" ] || continue
  if [ "$(stat -c %s "$f")" -gt 99614720 ]; then
    sha256sum "$f" >> SHA256SUMS-split-files.txt
    split -b 90M -d -a 2 "$f" "$f.part"; rm "$f"
  else sha256sum "$f" >> SHA256SUMS.txt; fi
done
cat > join.sh <<'JOIN'
#!/usr/bin/env bash
# Joins numbered parts back into the original file (you rarely need this: the Admin > Installers drop zone joins and checks them for you).
set -euo pipefail
for first in *.part00; do [ -e "$first" ] || continue; base="${first%.part00}"; cat "$base".part?? > "$base"; echo "joined $base"; done
[ -f SHA256SUMS-split-files.txt ] && sha256sum -c SHA256SUMS-split-files.txt
JOIN
chmod +x join.sh

node - "$VERSION" "$CODE" "$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo unknown)" <<'NODE'
const fs = require('fs'), cp = require('child_process'), [ , , version, code, commit ] = process.argv;
const slot = (n) => { const l = n.toLowerCase(); const ext = l.split('.').pop();
  if (ext === 'apk' || ext === 'aab') return 'android'; if (l.includes('chrome')) return 'chrome';
  if (/(^|[-_.])mac/.test(l)) return /arm64|aarch64/.test(l) ? 'macos' : 'macos_intel';
  if (/(^|[-_.])win/.test(l) || ext === 'exe') return 'windows'; if (ext === 'deb' || ext === 'rpm') return 'linux_deb'; if (l.includes('linux') || ext === 'appimage') return 'linux'; return null; };
const sums = {}; for (const f of ['SHA256SUMS.txt', 'SHA256SUMS-split-files.txt']) if (fs.existsSync(f)) for (const line of fs.readFileSync(f, 'utf8').split('\n')) { const m = line.match(/^([a-f0-9]{64})\s+\*?(.+)$/); if (m) sums[m[2]] = m[1]; }
const files = [];
const add = (name, sha, parts) => { const p = slot(name); if (!p) return; const size = parts ? parts.reduce((n, x) => n + fs.statSync(x).size, 0) : fs.statSync(name).size;
  files.push({ name, platform: p, version, sha256: sha, size, ...(parts ? { parts } : {}), ...(/TEST-SIGNED/.test(name) ? { signing: 'test' } : {}), ...(p === 'android' && name.endsWith('.aab') ? { playOnly: true } : {}) }); };
for (const [name, sha] of Object.entries(sums)) { const parts = fs.readdirSync('.').filter((x) => x.startsWith(name + '.part')).sort(); add(name, sha, parts.length ? parts : null); }
if (fs.existsSync('android')) { const asums = {}; for (const f of fs.readdirSync('android')) if (/^SHA256SUMS/.test(f)) for (const line of fs.readFileSync('android/' + f, 'utf8').split('\n')) { const m = line.match(/^([a-f0-9]{64})\s+\*?(.+)$/); if (m) asums[m[2]] = m[1]; }
  for (const f of fs.readdirSync('android')) {
    if (f.endsWith('.apk')) { fs.copyFileSync('android/' + f, f); add(f, asums[f] || cp.execSync('sha256sum android/' + f).toString().split(' ')[0], null); }
    // the Play bundle is for Play Console only: kept apart so it can never be dropped on the download page by mistake
    if (f.endsWith('.aab')) { fs.mkdirSync('play-store', { recursive: true }); fs.copyFileSync('android/' + f, 'play-store/' + f); }
  } }
for (const f of fs.readdirSync('.')) if (/^naara-chrome-extension/.test(f)) { add(f, cp.execSync('sha256sum ' + f).toString().split(' ')[0], null); }
fs.writeFileSync('release-manifest.json', JSON.stringify({ release: version, androidVersionCode: Number(code), commit, builtAt: new Date().toISOString(), files }, null, 2) + '\n');
console.log(files.map((f) => `  ${f.platform.padEnd(12)} ${f.name}${f.parts ? ' (' + f.parts.length + ' parts)' : ''}${f.signing ? '  [TEST-SIGNED]' : ''}`).join('\n'));
NODE
rm -rf "$OUT/android"
echo; echo "Done: $OUT"; ls -la "$OUT" | sed 's/^/  /'
