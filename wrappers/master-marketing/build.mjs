#!/usr/bin/env node
// Builds the Chrome extension zip: node build.mjs chrome   ->   dist/naara-chrome-extension-<version>.zip
// The splash and offline screens live once in shared/ and are copied in, so every wrapper shows the same ones.
import { cpSync, mkdirSync, rmSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const cfg = JSON.parse(readFileSync(join(here, 'config.json'), 'utf8'));
const target = process.argv[2] || 'chrome';
if (target !== 'chrome') { console.error('usage: node build.mjs chrome'); process.exit(1); }

const out = join(here, 'dist', 'chrome');
rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });
cpSync(join(here, 'chrome-extension', 'src'), out, { recursive: true });
cpSync(join(here, 'shared', 'splash.html'), join(out, 'splash.html'));
cpSync(join(here, 'shared', 'offline.html'), join(out, 'offline.html'));
cpSync(join(here, 'shared', 'assets'), join(out, 'assets'), { recursive: true });

const zip = join(here, 'dist', `naara-chrome-extension-${cfg.version}.zip`);
rmSync(zip, { force: true });
execFileSync('zip', ['-qr', zip, '.'], { cwd: out });
console.log('built', zip);
