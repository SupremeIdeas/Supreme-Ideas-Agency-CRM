#!/usr/bin/env python3
"""Put real dashboard screenshots inside the device mockups from resources/mockups/library.json.

  python3 scripts/mockups/compose.py --mock 04 --shot /tmp/mk/shots/step1.png --out public/images/marketing/mockups/home/step-1.webp
  python3 scripts/mockups/compose.py --mock 12 --shot a.png --shot b.png --shot c.png --out ...   # one --shot per screen, in screen order

What it does, in order, per screen: fit the screenshot to the device's physical aspect (cover, top aligned), add the status bar +
island + home indicator for phones, round the corners, warp it into the measured screen quad (true perspective, 3x supersampled),
put back any pixels that sit IN FRONT of the screen (another phone, a finger) from the original photo, and add a faint glass sheen so
the screen sits in the bezel instead of floating on it. Output is WebP, tens of KB, never a hotlink.
"""
import argparse, json, math, os, sys
import numpy as np
from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LIB = os.path.join(ROOT, 'resources', 'mockups')
FONT_B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'


def persp_coeffs(out_quad, in_rect):
    """Coefficients for PIL Image.transform(PERSPECTIVE): maps OUTPUT (x, y) -> INPUT (u, v)."""
    A, B = [], []
    for (x, y), (u, v) in zip(out_quad, in_rect):
        A.append([x, y, 1, 0, 0, 0, -u * x, -u * y]); B.append(u)
        A.append([0, 0, 0, x, y, 1, -v * x, -v * y]); B.append(v)
    return np.linalg.solve(np.array(A, float), np.array(B, float)).tolist()


def luminance(img):
    a = np.asarray(img.convert('RGB').resize((16, 16)), float)
    return float((0.2126 * a[..., 0] + 0.7152 * a[..., 1] + 0.0722 * a[..., 2]).mean() / 255)


def phone_chrome(shot, W, H, scr):
    """Status bar, dynamic island and home indicator drawn onto the screenshot (already W x H)."""
    top = int(H * 0.058)
    base = shot.convert('RGB')
    # Keep the app's own header colour behind the status bar by sampling its first row.
    row = np.asarray(base.crop((0, 0, W, 6))).reshape(-1, 3).mean(0)
    canvas = Image.new('RGB', (W, H), tuple(int(c) for c in row))
    canvas.paste(base.resize((W, H - top), Image.LANCZOS), (0, top))
    d = ImageDraw.Draw(canvas, 'RGBA')
    ink = (255, 255, 255) if luminance(canvas.crop((0, 0, W, top))) < 0.45 else (18, 20, 24)
    if scr.get('island', True):
        iw, ih = int(W * 0.30), int(H * 0.0425)
        d.rounded_rectangle([(W - iw) // 2, int(H * 0.0135), (W + iw) // 2, int(H * 0.0135) + ih], radius=ih // 2, fill=(6, 6, 8, 255))
    f = ImageFont.truetype(FONT_B, int(H * 0.0215))
    d.text((int(W * 0.115), int(H * 0.0135) + int(H * 0.0425) // 2), '9:41', font=f, fill=ink + (255,), anchor='lm')
    # signal bars, wifi dot-arc, battery
    cy = int(H * 0.0135) + int(H * 0.0425) // 2; x0 = int(W * 0.70); bw = max(2, int(W * 0.011))
    for i in range(4):
        h = int(H * (0.008 + 0.0042 * i)); x = x0 + i * int(bw * 1.6)
        d.rounded_rectangle([x, cy + int(H * 0.0095) - h, x + bw, cy + int(H * 0.0095)], radius=1, fill=ink + (255,))
    bx = int(W * 0.855); bwid, bh = int(W * 0.065), int(H * 0.0135)
    d.rounded_rectangle([bx, cy - bh // 2, bx + bwid, cy + bh // 2], radius=bh // 3, outline=ink + (150,), width=max(1, int(W * 0.004)))
    d.rounded_rectangle([bx + 2, cy - bh // 2 + 2, bx + int(bwid * 0.78), cy + bh // 2 - 2], radius=max(1, bh // 5), fill=ink + (255,))
    if scr.get('homebar', True):
        lum = luminance(canvas.crop((int(W * .3), H - int(H * .05), int(W * .7), H)))
        hb = (20, 22, 26, 200) if lum > 0.5 else (255, 255, 255, 215)
        bw2, bh2 = int(W * 0.34), max(3, int(H * 0.0058))
        d.rounded_rectangle([(W - bw2) // 2, H - int(H * 0.017) - bh2, (W + bw2) // 2, H - int(H * 0.017)], radius=bh2 // 2, fill=hb)
    return canvas


def fit_cover(img, w, h, align_top=True):
    s = max(w / img.width, h / img.height)
    r = img.resize((max(w, round(img.width * s)), max(h, round(img.height * s))), Image.LANCZOS)
    x = (r.width - w) // 2; y = 0 if align_top else (r.height - h) // 2
    return r.crop((x, y, x + w, y + h))


def rounded_mask(W, H, r):
    m = Image.new('L', (W * 2, H * 2), 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, W * 2 - 1, H * 2 - 1], radius=int(r * 2), fill=255)
    return m.resize((W, H), Image.LANCZOS)


def poly_mask(size, pts, feather=0.0):
    m = Image.new('L', size, 0)
    ImageDraw.Draw(m).polygon([tuple(p) for p in pts], fill=255)
    return m.filter(ImageFilter.GaussianBlur(feather)) if feather else m


def rrect_mask(size, box, radius, ss):
    m = Image.new('L', size, 0)
    ImageDraw.Draw(m).rounded_rectangle([c * ss for c in box], radius=radius * ss, fill=255)
    return m


def heal(img, box, ss):
    """Paint over a small blemish (e.g. the artist's watermark) by blending the four surrounding edges across the box."""
    x0, y0, x1, y1 = [int(round(c * ss)) for c in box]
    a = np.asarray(img, float)
    L, R = a[y0:y1, max(0, x0 - 3):x0].mean(1), a[y0:y1, x1:x1 + 3].mean(1)
    T, B = a[max(0, y0 - 3):y0, x0:x1].mean(0), a[y1:y1 + 3, x0:x1].mean(0)
    w, h = x1 - x0, y1 - y0
    tx = np.linspace(0, 1, w)[None, :, None]; ty = np.linspace(0, 1, h)[:, None, None]
    horiz = L[:, None, :] * (1 - tx) + R[:, None, :] * tx
    vert = T[None, :, :] * (1 - ty) + B[None, :, :] * ty
    a[y0:y1, x0:x1] = (horiz + vert) / 2
    out = Image.fromarray(a.clip(0, 255).astype('uint8'))
    soft = out.filter(ImageFilter.GaussianBlur(ss * 1.2)).crop((x0, y0, x1, y1))
    img.paste(soft, (x0, y0))
    return img


def compose(entry, shots, scale, ss_extra=1):
    base0 = Image.open(os.path.join(LIB, entry['file'])).convert('RGB')
    ss = scale * 2 * ss_extra                     # work at 2x the output, then LANCZOS down
    size = (round(base0.width * ss), round(base0.height * ss))
    base = base0.resize(size, Image.BICUBIC)
    for c in entry.get('clean', []):
        heal(base, c['box'], ss)
    orig = base.copy()
    vp = entry.get('viewport', {'w': 390, 'h': 844})
    for scr, shot_path in zip(entry['screens'], shots):
        quad = [(x * ss, y * ss) for x, y in scr['quad']]
        W = 1170 if entry['kind'] == 'phone' else 1600
        H = round(W * vp['h'] / vp['w'])
        shot = fit_cover(Image.open(shot_path).convert('RGB'), W, H)
        if entry['kind'] == 'phone':
            shot = phone_chrome(shot, W, H, scr)
        mask = rounded_mask(W, H, scr.get('radius', 0.0) * W) if scr.get('radius') else Image.new('L', (W, H), 255)
        coeffs = persp_coeffs(quad, [(0, 0), (W, 0), (W, H), (0, H)])
        warped = shot.transform(size, Image.PERSPECTIVE, coeffs, Image.BICUBIC)
        wmask = mask.transform(size, Image.PERSPECTIVE, coeffs, Image.BICUBIC)
        # Glass sheen: a faint diagonal highlight + a hairline inner shadow so the glass reads as sitting under the bezel.
        sheen = Image.new('L', (W, H), 0); sd = ImageDraw.Draw(sheen)
        for i in range(H):
            sd.line([(0, i), (W, i)], fill=int(34 * max(0, 1 - i / (H * 0.55))))
        wsheen = sheen.transform(size, Image.PERSPECTIVE, coeffs, Image.BICUBIC)
        white = Image.new('RGB', size, (255, 255, 255))
        warped = Image.composite(white, warped, ImageChops.multiply(wsheen, wmask))
        edge = wmask.filter(ImageFilter.GaussianBlur(ss * 0.8))
        inner = ImageChops.subtract(wmask, ImageChops.multiply(edge, wmask).point(lambda v: v)).point(lambda v: min(255, v * 2))
        warped = Image.composite(Image.new('RGB', size, (0, 0, 0)), warped, inner.point(lambda v: int(v * 0.10)))
        base = Image.composite(warped, base, wmask)
        for occ in scr.get('occlude', []):
            if 'rrect' in occ:
                om = rrect_mask(size, occ['rrect'][:4], occ['rrect'][4], ss)
            else:
                om = poly_mask(size, [(x * ss, y * ss) for x, y in occ['poly']])
            om = om.filter(ImageFilter.GaussianBlur(ss * occ.get('feather', 0.5)))
            base = Image.composite(orig, base, ImageChops.multiply(om, wmask.point(lambda v: 255 if v else 0)) if occ.get('only_over_screen', True) else om)
    return base


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--mock', required=True); ap.add_argument('--shot', action='append', required=True)
    ap.add_argument('--out', required=True); ap.add_argument('--scale', type=float, default=1.5)
    ap.add_argument('--q', type=int, default=80); ap.add_argument('--png', action='store_true')
    ap.add_argument('--crop', help='x0,y0,x1,y1 in SOURCE pixels: crop the finished image to this box (e.g. frame a monitor tighter)')
    a = ap.parse_args()
    lib = json.load(open(os.path.join(LIB, 'library.json')))
    entry = next((m for m in lib['mockups'] if m['id'] == a.mock or m['id'].startswith(a.mock + '-')), None)
    if not entry: sys.exit(f'unknown mockup {a.mock}')
    if not entry.get('calibrated'): sys.exit(f"mockup {entry['id']} is catalogued but not calibrated yet")
    if len(a.shot) != len(entry['screens']): sys.exit(f"{entry['id']} has {len(entry['screens'])} screen(s), got {len(a.shot)} shot(s)")
    img = compose(entry, a.shot, a.scale)
    if a.crop:
        x0, y0, x1, y1 = [float(v) for v in a.crop.split(',')]
        k = img.width / Image.open(os.path.join(LIB, entry['file'])).width
        img = img.crop((round(x0 * k), round(y0 * k), round(x1 * k), round(y1 * k)))
    out_w = round(Image.open(os.path.join(LIB, entry['file'])).width * a.scale) if not a.crop else round((x1 - x0) * a.scale)
    img = img.resize((out_w, round(img.height * out_w / img.width)), Image.LANCZOS)
    os.makedirs(os.path.dirname(os.path.abspath(a.out)), exist_ok=True)
    if a.png or a.out.endswith('.png'): img.save(a.out)
    else: img.save(a.out, 'WEBP', quality=a.q, method=4)
    print(f"{a.out}  {img.width}x{img.height}  {os.path.getsize(a.out)//1024} KB")


if __name__ == '__main__':
    main()
