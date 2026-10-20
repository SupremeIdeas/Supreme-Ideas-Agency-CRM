#!/usr/bin/env python3
"""Turn a 390x844 @2x dashboard capture into the small WebP phone screen the skin showcases use: python3 skinshot.py in.png out.webp"""
import os, sys
from PIL import Image
src, dst = sys.argv[1], sys.argv[2]
im = Image.open(src).convert('RGB')
im = im.resize((480, round(im.height * 480 / im.width)), Image.LANCZOS)
im.save(dst, 'WEBP', quality=74, method=6)
print(dst, im.size, os.path.getsize(dst) // 1024, 'KB')
