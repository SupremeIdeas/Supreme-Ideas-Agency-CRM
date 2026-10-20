#!/usr/bin/env python3
"""ins.py FILE "### Heading to insert before" TEXTFILE  -- insert text (read from TEXTFILE) before the first line equal to the heading. Fails loudly if absent."""
import sys
f, head, tf = sys.argv[1:4]
s = open(f).read(); t = open(tf).read().strip() + '\n\n'
i = s.find('\n' + head + '\n')
if i < 0: sys.exit('heading not found: ' + head)
s = s[:i + 1] + t + s[i + 1:]
open(f, 'w').write(s); print('inserted', len(t.split()), 'words before', head)
