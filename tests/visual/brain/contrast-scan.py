"""Samples the rendered background around each captured NaaraBrain text box and reports WCAG contrast (needs >= 4.5, or >= 3 for large text)."""
import json, re, sys
from PIL import Image
OUT = sys.argv[1] if len(sys.argv) > 1 else '/tmp/cs'
def lin(c):
    c /= 255
    return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
def lum(rgb): return 0.2126 * lin(rgb[0]) + 0.7152 * lin(rgb[1]) + 0.0722 * lin(rgb[2])
def ratio(a, b):
    la, lb = lum(a), lum(b)
    if la < lb: la, lb = lb, la
    return (la + 0.05) / (lb + 0.05)
def parse(c):
    n = [float(x) for x in re.findall(r'-?[\d.]+(?:e-?\d+)?', c.split('/')[0] if 'color(' in c else c)]
    if c.startswith('color(srgb'):
        n = n[:3]; a = float(c.split('/')[1].strip(' )')) if '/' in c else 1.0
        return [v * 255 for v in n], a
    return n[:3], (n[3] if len(n) > 3 else 1.0)
bad = {}; n = 0
for m in json.load(open(f'{OUT}/manifest.json')):
    im = Image.open(m['f']).convert('RGB'); W, H = im.size
    for b in m['boxes']:
        fg, a = parse(b['color'])
        # background: pixels just above and below the text box (inside the same surface), averaged
        pts = []
        padded = 'bubble' in b['q'] or 'chip' in b['q']
        ym = int(b['y'] + b['h'] / 2)
        # Padded boxes (bubbles, cards): sample their own fill inside the left/right padding. Bare text: sample the surface just outside the box, at mid height.
        xs = (int(b['x']) + 7, int(b['x'] + b['w']) - 8) if padded else (int(b['x']) - 8, int(b['x'] + b['w']) + 8)
        if 'showcase' in b['q'] or 'ns-gc' in b['q']: xs = (int(b['x']) - 8,)   # the right side of these boxes can sit on artwork: judge the card surface on the left
        for yy in ((ym - 6, ym, ym + 6) if padded else (ym,)):
            for xx in xs:
                if 0 <= xx < W and 0 <= yy < H: pts.append(im.getpixel((xx, yy)))
        if not pts: continue
        bg = [sum(p[i] for p in pts) / len(pts) for i in range(3)]
        eff = [fg[i] * a + bg[i] * (1 - a) for i in range(3)]
        large = b['size'] >= 24 or (b['size'] >= 18.66 and int(b['weight']) >= 700)
        need = 3.0 if large else 4.5
        r = min(ratio(eff, p) for p in pts)
        n += 1
        if r < need - 0.05:
            bad.setdefault((m['s'], 'dark' if m['dark'] else 'light'), []).append((m['label'], b['q'], round(r, 2), need))
print(f'{n} text boxes checked')
for k, v in sorted(bad.items()):
    print(k, v[:4])
print('FAILING skin/mode combos:', len(bad))
