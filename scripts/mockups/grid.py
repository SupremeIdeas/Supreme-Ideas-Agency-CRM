"""Draw a labelled coordinate grid over a library mockup so a screen quad can be read off by eye.
usage: python3 scripts/mockups/grid.py resources/mockups/library/04-phone-hand-white.jpg /tmp/grid.png [step]"""
import sys
from PIL import Image, ImageDraw
src, out = sys.argv[1], sys.argv[2]
step = int(sys.argv[3]) if len(sys.argv) > 3 else 50
im = Image.open(src).convert('RGB'); S = 2
im = im.resize((im.width * S, im.height * S), Image.LANCZOS)
d = ImageDraw.Draw(im, 'RGBA')
for x in range(0, im.width // S, step):
    d.line([(x * S, 0), (x * S, im.height)], fill=(255, 0, 80, 120 if x % 100 else 200), width=1)
    d.text((x * S + 2, 2), str(x), fill=(255, 255, 0, 255))
for y in range(0, im.height // S, step):
    d.line([(0, y * S), (im.width, y * S)], fill=(0, 160, 255, 120 if y % 100 else 200), width=1)
    d.text((2, y * S + 2), str(y), fill=(0, 255, 255, 255))
im.save(out)
