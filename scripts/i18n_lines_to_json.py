#!/usr/bin/env python3
"""Turn a translator's "N|text" lines (N = 1-based position in `i18n:export` key order) into the flat JSON `i18n:import` expects.

  php artisan i18n:export --out=source.json
  python3 scripts/i18n_lines_to_json.py source.json pt.lines.txt pt.json
  php artisan i18n:import pt pt.json
"""
import json, sys

src, lines, out = sys.argv[1:4]
keys = list(json.load(open(src)).keys())
res, bad = {}, 0
for raw in open(lines, encoding='utf-8'):
    raw = raw.rstrip('\n')
    if not raw.strip() or '|' not in raw:
        continue
    n, text = raw.split('|', 1)
    try:
        i = int(n.strip()) - 1
    except ValueError:
        bad += 1
        continue
    if 0 <= i < len(keys) and text.strip():
        res[keys[i]] = text.strip().replace('\\n', '\n')
    else:
        bad += 1
json.dump(res, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(f'{len(res)}/{len(keys)} translated, {bad} bad lines')
