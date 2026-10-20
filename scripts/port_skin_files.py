#!/usr/bin/env python3
"""Master-side helper for Prompt 22: port the SKIN hunks of shared files into a white-label fork.

For each file: theirs = master's blob at the newest commit with "skin" in its subject that touched the file; base = master just before the
engine (08885b2^). Fork file missing -> copied; fork file == base -> replaced by theirs; otherwise a 3-way merge (conflicts are left as
markers and reported, never silently resolved).
Usage: scripts/port_skin_files.py /home/user/NaaraSim-WhiteLabel path1 path2 ...   (run from the master repo)"""
import subprocess, sys, os, tempfile, hashlib

BASE = '08885b2^'

def sh(*a, cwd=None, check=False):
    r = subprocess.run(a, cwd=cwd, capture_output=True, text=True)
    return r.stdout

def blob(rev, path):
    r = subprocess.run(['git', 'show', f'{rev}:{path}'], capture_output=True)
    return r.stdout if r.returncode == 0 else None

def last_skin_commit(path):
    out = sh('git', 'log', '--format=%h', '-i', '--grep=skin', f'{BASE}..HEAD', '--', path).split()
    return out[0] if out else None

fork = sys.argv[1]
for path in sys.argv[2:]:
    c = last_skin_commit(path)
    theirs = blob(c, path) if c else None
    if theirs is None:
        print('SKIP (no skin commit)', path); continue
    base = blob(BASE, path)
    dst = os.path.join(fork, path)
    if not os.path.exists(dst):
        os.makedirs(os.path.dirname(dst), exist_ok=True); open(dst, 'wb').write(theirs); print('NEW      ', path, c); continue
    ours = open(dst, 'rb').read()
    if ours == theirs:
        print('SAME     ', path); continue
    if base is not None and ours == base:
        open(dst, 'wb').write(theirs); print('REPLACED ', path, c); continue
    if base is None:
        print('NO-BASE  ', path, '(hand merge)'); continue
    with tempfile.TemporaryDirectory() as t:
        for n, b in (('ours', ours), ('base', base), ('theirs', theirs)):
            open(os.path.join(t, n), 'wb').write(b)
        r = subprocess.run(['git', 'merge-file', '-p', '-L', 'fork', '-L', 'master-base', '-L', 'master-skin', os.path.join(t, 'ours'), os.path.join(t, 'base'), os.path.join(t, 'theirs')], capture_output=True)
        open(dst, 'wb').write(r.stdout)
        print('MERGED   ' if r.returncode == 0 else f'CONFLICT({r.returncode})', path, c)
