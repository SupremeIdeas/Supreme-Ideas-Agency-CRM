// Copies the one shared splash/offline screen set into this app before packaging.
import { cpSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const here = dirname(fileURLToPath(import.meta.url));
const from = join(here, '..', '..', 'shared');
const to = join(here, '..', 'shared');
rmSync(to, { recursive: true, force: true });
mkdirSync(to, { recursive: true });
for (const f of ['splash.html', 'offline.html']) cpSync(join(from, f), join(to, f));
cpSync(join(from, 'assets'), join(to, 'assets'), { recursive: true });
console.log('shared screens copied');
