#!/usr/bin/env node
// One command keeps every wrapper on the same version:  node set-version.mjs 1.0.2
// Writes config.json (Chrome zip name), desktop/package.json (Electron file names) and chrome-extension/src/manifest.json (what Chrome shows).
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const v = process.argv[2];
if (!/^\d+\.\d+\.\d+$/.test(v || '')) { console.error('usage: node set-version.mjs 1.2.3'); process.exit(1); }
const here = dirname(fileURLToPath(import.meta.url));
for (const f of ['config.json', 'desktop/package.json', 'chrome-extension/src/manifest.json']) {
  const p = join(here, f);
  const j = JSON.parse(readFileSync(p, 'utf8'));
  j.version = v;
  writeFileSync(p, JSON.stringify(j, null, 2) + '\n');
  console.log(f, '->', v);
}
