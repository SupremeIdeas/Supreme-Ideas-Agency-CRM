#!/usr/bin/env python3
"""Background-removal + trim + WebP for the Naara wordmark. Sources sit on a flat background (black for the dark-surface mark, white for the
light-surface mark), so a distance-from-background alpha ramp with edge unpremultiply gives a clean cutout (no ML model needed).
usage: process_logo.py SRC OUT bg(black|white) [width]
"""
import sys
from PIL import Image
src, out, bg, width = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4]) if len(sys.argv) > 4 else 900
im = Image.open(src).convert('RGB')
W, H = im.size
px = im.load()
base = (0, 0, 0) if bg == 'black' else (255, 255, 255)
# estimate the actual flat background from the corners
corner = [px[2, 2], px[W - 3, 2], px[2, H - 3], px[W - 3, H - 3]]
base = tuple(sum(c[i] for c in corner) // 4 for i in range(3))
LO, HI = (10, 64) if bg == 'black' else (8, 56)
res = Image.new('RGBA', (W, H))
rp = res.load()
for y in range(H):
    for x in range(W):
        r, g, b = px[x, y]
        d = max(abs(r - base[0]), abs(g - base[1]), abs(b - base[2]))
        if d <= LO:
            rp[x, y] = (r, g, b, 0)
        elif d >= HI:
            rp[x, y] = (r, g, b, 255)
        else:
            a = (d - LO) / (HI - LO)
            # remove the background contribution from the edge pixel
            c = tuple(max(0, min(255, int(round((v - (1 - a) * bv) / a)))) for v, bv in zip((r, g, b), base))
            rp[x, y] = (c[0], c[1], c[2], int(round(a * 255)))
box = res.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox()
pad = int(max(box[2] - box[0], box[3] - box[1]) * 0.012)
box = (max(0, box[0] - pad), max(0, box[1] - pad), min(W, box[2] + pad), min(H, box[3] + pad))
res = res.crop(box)
w, h = res.size
res = res.resize((width, round(h * width / w)), Image.LANCZOS)
res.save(out, 'WEBP', quality=88, method=6)
print(out, res.size, 'bbox', box)
