#!/usr/bin/env bash
# Capture the real dashboard in each skin and turn it into the committed WebP screens used by the master marketing pages
# (images/marketing/skins/{skin}/{home,esim,wallet,numbers,chat}.webp). Needs the demo app running (see README) with seed-demo.php and
# seed-chat-demo.php applied. Usage: BASE=http://127.0.0.1:8766 scripts/mockups/build-skins.sh [skin ...]
set -euo pipefail
cd "$(dirname "$0")/../.."
BASE="${BASE:-http://127.0.0.1:8766}"
export DB_CONNECTION=sqlite DB_DATABASE="${DEMO_DB:-/tmp/mockdemo.sqlite}" CACHE_STORE=file
# skin:accent:mode — each skin is shown in the accent and mode that shows it off best (members choose their own).
SKINS=("$@"); [ ${#SKINS[@]} -eq 0 ] && SKINS=(surface:teal:light noir:teal:dark glass:ocean:light paper:rose:light neo:violet:light aurora:violet:dark ankara:emerald:light vault:teal:dark)
OUT=public/images/marketing/skins
RAW=/tmp/mk/skins
mkdir -p "$RAW"
for spec in "${SKINS[@]}"; do
  IFS=: read -r s accent mode <<< "$spec"; accent="${accent:-teal}"; mode="${mode:-light}"
  php scripts/mockups/set-appearance.php "$s" "$accent" "$mode"
  python3 - "$s" "$mode" <<'PY'
import json, sys
s, mode = sys.argv[1], sys.argv[2]
hide = ['[data-n2n-bar]']
shots = [
  {'id': f'{s}-home', 'path': '/dashboard', 'w': 390, 'h': 844, 'dpr': 2, 'mode': mode},
  {'id': f'{s}-esim', 'path': '/catalogue', 'w': 390, 'h': 844, 'dpr': 2, 'mode': mode},
  {'id': f'{s}-wallet', 'path': '/wallet', 'w': 390, 'h': 844, 'dpr': 2, 'mode': mode},
  {'id': f'{s}-numbers', 'path': '/numbers', 'w': 390, 'h': 844, 'dpr': 2, 'mode': mode},
  {'id': f'{s}-chat', 'path': '/n2n/chat', 'w': 390, 'h': 844, 'dpr': 2, 'mode': mode, 'click': ['text=Sophie Turner'], 'wait': 1500},
]
json.dump(shots, open(f'/tmp/mk/skins/shots-{s}.json', 'w'))
PY
  BASE="$BASE" node scripts/mockups/capture.mjs "$RAW/shots-$s.json" "$RAW"
  mkdir -p "$OUT/$s"
  for k in home esim wallet numbers chat; do
    python3 scripts/mockups/skinshot.py "$RAW/$s-$k.png" "$OUT/$s/$k.webp"
  done
done
