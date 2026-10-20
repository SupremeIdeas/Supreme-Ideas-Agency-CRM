# Original animated sticker pack (our own artwork, procedurally drawn): cartoon characters on rounded colour tiles, supersampled for clean edges.
import math, os, random
from PIL import Image, ImageDraw, ImageFont
S = 3; T = 240; N = 16; DUR = 70
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'public', 'images', 'gifs', 'stickers')
FB = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
def hx(h, a=255): h = h.lstrip('#'); return tuple(int(h[i:i+2], 16) for i in (0, 2, 4)) + (a,)
def P(v): return v * S
def tile(c1, c2):
    im = Image.new('RGBA', (T*S, T*S)); d = ImageDraw.Draw(im); a, b = hx(c1), hx(c2)
    for y in range(T*S):
        t = y / (T*S - 1); d.line([(0, y), (T*S, y)], fill=tuple(int(a[i] + (b[i]-a[i]) * t) for i in range(3)) + (255,))
    m = Image.new('L', (T*S, T*S), 0); ImageDraw.Draw(m).rounded_rectangle([0, 0, T*S-1, T*S-1], radius=P(34), fill=255)
    out = Image.new('RGBA', (T*S, T*S), (0, 0, 0, 0)); out.paste(im, (0, 0), m); return out
CUR = [None]
def soft(fn, fill):
    # ImageDraw writes alpha straight into an RGBA layer, so a translucent shape would punch a hole: draw it on a scratch layer and composite instead
    if isinstance(fill, tuple) and len(fill) == 4 and fill[3] < 255:
        tmp = Image.new('RGBA', CUR[0].size, (0, 0, 0, 0)); fn(ImageDraw.Draw(tmp)); CUR[0].alpha_composite(tmp); return True
    return False
def ell(d, cx, cy, rx, ry, fill, outline=None, w=0):
    f = lambda dd: dd.ellipse([P(cx-rx), P(cy-ry), P(cx+rx), P(cy+ry)], fill=fill, outline=outline, width=P(w) if w else 0)
    if not soft(f, fill): f(d)
def line(d, pts, fill, w):
    f = lambda dd: dd.line([(P(x), P(y)) for x, y in pts], fill=fill, width=P(w), joint='curve')
    if not soft(f, fill): f(d)
def arc(d, cx, cy, rx, ry, a0, a1, fill, w): d.arc([P(cx-rx), P(cy-ry), P(cx+rx), P(cy+ry)], a0, a1, fill=fill, width=P(w))
def poly(d, pts, fill):
    f = lambda dd: dd.polygon([(P(x), P(y)) for x, y in pts], fill=fill)
    if not soft(f, fill): f(d)
def heart(d, cx, cy, s, fill):
    pts = []
    for i in range(80):
        t = i / 80 * 2 * math.pi
        pts.append((cx + 16 * math.sin(t) ** 3 * s / 16, cy - (13 * math.cos(t) - 5 * math.cos(2*t) - 2 * math.cos(3*t) - math.cos(4*t)) * s / 16))
    poly(d, pts, fill)
def star(d, cx, cy, r, fill, rot=0):
    pts = []
    for i in range(10):
        rr = r if i % 2 == 0 else r * .45; a = math.pi * i / 5 - math.pi / 2 + rot; pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    poly(d, pts, fill)
INK = hx('#2b1a0e'); WHITE = hx('#ffffff')
def face(spec, t):
    """Draw the character on a transparent 240x240 layer (supersampled) with its own animation transform; returns the RGBA layer."""
    L = Image.new('RGBA', (T*S, T*S), (0, 0, 0, 0)); d = ImageDraw.Draw(L); CUR[0] = L
    cx, cy, r = 120, 128, 70; base = hx(spec.get('skin', '#ffcc33')); shade = hx(spec.get('shade', '#e89a1a'))
    shape = spec.get('shape', 'round'); blink = spec.get('blink') and (int(t * N) % 8 == 7)
    # body shape
    if shape == 'round':
        ell(d, cx, cy + 4, r, r, shade); ell(d, cx, cy, r, r, base)
        ell(d, cx - 22, cy - 30, 30, 18, hx('#ffffff', 70))
    elif shape == 'ghost':
        poly(d, [(cx - r, cy + 6), (cx - r, cy + r), (cx - r * .6, cy + r - 18), (cx - r * .2, cy + r), (cx + r * .2, cy + r - 18), (cx + r * .6, cy + r), (cx + r, cy + r - 6), (cx + r, cy + 6)], base)
        ell(d, cx, cy + 6, r, r, base)
    elif shape == 'robot':
        d.rounded_rectangle([P(cx-r), P(cy-r+8), P(cx+r), P(cy+r-8)], radius=P(22), fill=base, outline=shade, width=P(5))
        line(d, [(cx, cy - r + 8), (cx, cy - r - 12)], shade, 5); ell(d, cx, cy - r - 16, 7, 7, hx('#ff5a5f'))
    elif shape == 'cat':
        poly(d, [(cx - r + 4, cy - 30), (cx - r + 10, cy - r - 12), (cx - 20, cy - r + 8)], base); poly(d, [(cx + r - 4, cy - 30), (cx + r - 10, cy - r - 12), (cx + 20, cy - r + 8)], base)
        poly(d, [(cx - r + 16, cy - 36), (cx - r + 18, cy - r + 4), (cx - 28, cy - r + 14)], hx('#ffb3c1')); poly(d, [(cx + r - 16, cy - 36), (cx + r - 18, cy - r + 4), (cx + 28, cy - r + 14)], hx('#ffb3c1'))
        ell(d, cx, cy + 4, r, r - 6, shade); ell(d, cx, cy, r, r - 6, base)
    elif shape == 'alien':
        ell(d, cx, cy + 4, r, r - 4, shade); ell(d, cx, cy, r, r - 4, base)
        line(d, [(cx - 28, cy - r + 8), (cx - 40, cy - r - 14)], shade, 5); ell(d, cx - 41, cy - r - 16, 7, 7, base); line(d, [(cx + 28, cy - r + 8), (cx + 40, cy - r - 14)], shade, 5); ell(d, cx + 41, cy - r - 16, 7, 7, base)
    ey = cy - 12; ex = 28
    eyes = spec.get('eyes', 'open')
    def open_eye(x, big=1.0):
        if blink: line(d, [(x - 12, ey), (x + 12, ey)], INK, 6); return
        ell(d, x, ey, 15 * big, 19 * big, WHITE, INK, 3); ell(d, x + 2, ey + 2, 8 * big, 10 * big, INK); ell(d, x + 5, ey - 4, 3, 3, WHITE)
    if eyes == 'open': open_eye(cx - ex); open_eye(cx + ex)
    elif eyes == 'big': open_eye(cx - ex, 1.25); open_eye(cx + ex, 1.25)
    elif eyes == 'happy': arc(d, cx - ex, ey + 6, 14, 14, 200, 340, INK, 7); arc(d, cx + ex, ey + 6, 14, 14, 200, 340, INK, 7)
    elif eyes == 'closed': arc(d, cx - ex, ey - 2, 14, 12, 20, 160, INK, 7); arc(d, cx + ex, ey - 2, 14, 12, 20, 160, INK, 7)
    elif eyes == 'wink': open_eye(cx - ex); arc(d, cx + ex, ey + 6, 14, 14, 200, 340, INK, 7)
    elif eyes == 'hearts':
        k = 1 + .18 * math.sin(t * 6.28 * 2); heart(d, cx - ex, ey, 30 * k, hx('#ff2d55')); heart(d, cx + ex, ey, 30 * k, hx('#ff2d55'))
    elif eyes == 'dollar':
        f = ImageFont.truetype(FB, P(38))
        for x in (cx - ex, cx + ex): d.text((P(x), P(ey)), '$', font=f, fill=hx('#0a7a3d'), anchor='mm')
    elif eyes == 'stars': star(d, cx - ex, ey, 18, hx('#ffb300'), t * 6.2832 / 5); star(d, cx + ex, ey, 18, hx('#ffb300'), -t * 6.2832 / 5)
    elif eyes == 'dizzy':
        for x in (cx - ex, cx + ex):
            for k in range(3): arc(d, x, ey, 6 + k * 5, 6 + k * 5, (t * 720 + k * 120) % 360, (t * 720 + k * 120) % 360 + 250, INK, 3)
    elif eyes == 'angry':
        open_eye(cx - ex, .85); open_eye(cx + ex, .85); line(d, [(cx - ex - 16, ey - 28), (cx - ex + 14, ey - 16)], INK, 8); line(d, [(cx + ex + 16, ey - 28), (cx + ex - 14, ey - 16)], INK, 8)
    elif eyes == 'sad':
        open_eye(cx - ex, .9); open_eye(cx + ex, .9); line(d, [(cx - ex - 14, ey - 16), (cx - ex + 12, ey - 26)], INK, 6); line(d, [(cx + ex + 14, ey - 16), (cx + ex - 12, ey - 26)], INK, 6)
    elif eyes == 'side':
        for x in (cx - ex, cx + ex): ell(d, x, ey, 14, 18, WHITE, INK, 3); ell(d, x + 7 + 3 * math.sin(t * 6.28), ey - 2, 7, 9, INK)
    elif eyes == 'shades':
        d.rounded_rectangle([P(cx - 56), P(ey - 16), P(cx - 6), P(ey + 16)], radius=P(10), fill=INK); d.rounded_rectangle([P(cx + 6), P(ey - 16), P(cx + 56), P(ey + 16)], radius=P(10), fill=INK)
        line(d, [(cx - 6, ey - 6), (cx + 6, ey - 6)], INK, 5); ell(d, cx - 40, ey - 6, 5, 3, hx('#ffffff', 120)); ell(d, cx + 22, ey - 6, 5, 3, hx('#ffffff', 120))
    elif eyes == 'nerd':
        open_eye(cx - ex, .8); open_eye(cx + ex, .8)
        for x in (cx - ex, cx + ex): ell(d, x, ey, 22, 22, None, INK, 4)
        line(d, [(cx - ex + 22, ey), (cx + ex - 22, ey)], INK, 4)
    elif eyes == 'robot':
        for x in (cx - ex, cx + ex): d.rounded_rectangle([P(x - 14), P(ey - 12), P(x + 14), P(ey + 12)], radius=P(5), fill=hx('#7cf5ff') if int(t * N) % 6 else hx('#ffffff'), outline=INK, width=P(3))
    # mouth
    my = cy + 36; m = spec.get('mouth', 'smile')
    if m == 'smile': arc(d, cx, my - 14, 30, 24, 20, 160, INK, 7)
    elif m == 'grin': d.chord([P(cx-34), P(my-28), P(cx+34), P(my+22)], 0, 180, fill=INK); d.chord([P(cx-26), P(my-12), P(cx+26), P(my+4)], 0, 180, fill=WHITE)
    elif m == 'laugh':
        open_amt = 6 + 8 * abs(math.sin(t * 6.28 * 2)); d.chord([P(cx-36), P(my-30), P(cx+36), P(my+24+open_amt)], 0, 180, fill=INK); ell(d, cx, my + 14 + open_amt / 2, 18, 8, hx('#ff6b81'))
    elif m == 'o': o = 14 + 4 * math.sin(t * 6.28 * 3); ell(d, cx, my + 4, o, o + 4, INK)
    elif m == 'tongue': arc(d, cx, my - 14, 30, 24, 20, 160, INK, 7); ell(d, cx + 4, my + 14 + 4 * math.sin(t * 6.28 * 2), 12, 16, hx('#ff6b81'), INK, 3)
    elif m == 'kiss': ell(d, cx, my + 2, 10, 8, hx('#e0245e'))
    elif m == 'flat': line(d, [(cx - 22, my + 4), (cx + 22, my + 4)], INK, 7)
    elif m == 'frown': arc(d, cx, my + 24, 28, 22, 200, 340, INK, 7)
    elif m == 'smirk': arc(d, cx + 6, my - 10, 28, 20, 20, 150, INK, 7)
    elif m == 'sleep': ell(d, cx, my + 6, 9, 7, INK)
    elif m == 'teeth': d.rounded_rectangle([P(cx-30), P(my-8), P(cx+30), P(my+16)], radius=P(8), fill=WHITE, outline=INK, width=P(4)); [line(d, [(cx - 30 + i * 10, my - 6), (cx - 30 + i * 10, my + 14)], INK, 2) for i in range(1, 6)]
    elif m == 'robot': d.rectangle([P(cx - 30), P(my - 6), P(cx + 30), P(my + 14)], fill=INK); [d.rectangle([P(cx - 28 + i * 10), P(my - 4), P(cx - 22 + i * 10), P(my + 12)], fill=hx('#7cf5ff')) for i in range(6)]
    if shape == 'cat': [line(d, [(cx + s * 30, my - 6 + k * 6), (cx + s * 62, my - 12 + k * 12)], INK, 3) for s in (-1, 1) for k in (0, 1)]; poly(d, [(cx - 6, my - 24), (cx + 6, my - 24), (cx, my - 16)], hx('#ff7a90'))
    if spec.get('blush'): ell(d, cx - 50, cy + 22, 14, 8, hx('#ff6b81', 140)); ell(d, cx + 50, cy + 22, 14, 8, hx('#ff6b81', 140))
    if spec.get('hat'):
        poly(d, [(cx - 30, cy - r + 14), (cx + 30, cy - r + 14), (cx + 4, cy - r - 52)], hx('#ff4d8d')); ell(d, cx + 4, cy - r - 54, 8, 8, hx('#ffd166'))
        for k in range(4): d.line([(P(cx - 24 + k * 4), P(cy - r + 4 - k * 14)), (P(cx + 20 - k * 4), P(cy - r + 4 - k * 14))], fill=WHITE, width=P(3))
    if spec.get('tears'):
        for k in (-1, 1):
            for j in range(2):
                ph = (t * 2 + j * .5) % 1; ell(d, cx + k * 44 + k * ph * 6, ey + 18 + ph * 54, 7, 10, hx('#6ec6ff'))
    if spec.get('sweat'): ph = (t * 1.5) % 1; ell(d, cx + 64, cy - 40 + ph * 40, 8, 12, hx('#6ec6ff'))
    return L
def objects(kind, t):
    L = Image.new('RGBA', (T*S, T*S), (0, 0, 0, 0)); d = ImageDraw.Draw(L); CUR[0] = L; cx, cy = 120, 128
    if kind == 'thumbs':
        d.rounded_rectangle([P(cx - 62), P(cy - 6), P(cx - 26), P(cy + 62)], radius=P(8), fill=hx('#2d6cdf'))
        d.rounded_rectangle([P(cx - 20), P(cy - 6), P(cx + 62), P(cy + 62)], radius=P(16), fill=hx('#ffcc33'), outline=hx('#e89a1a'), width=P(4))
        d.rounded_rectangle([P(cx - 14), P(cy - 58), P(cx + 18), P(cy + 4)], radius=P(14), fill=hx('#ffcc33'), outline=hx('#e89a1a'), width=P(4))
        for k in range(3): line(d, [(cx + 4, cy + 14 + k * 14), (cx + 54, cy + 14 + k * 14)], hx('#e89a1a'), 3)
    elif kind == 'heart': k = 1 + .12 * math.sin(t * 6.28 * 2); heart(d, cx, cy + 6, 128 * k, hx('#ff2d55')); ell(d, cx - 28, cy - 26, 14, 8, hx('#ffffff', 140))
    elif kind == 'star': star(d, cx, cy + 4, 84 + 6 * math.sin(t * 6.28 * 2), hx('#ffc400'), t * 6.2832 / 5); ell(d, cx, cy + 4, 5, 5, hx('#ffffff', 0))
    elif kind == 'trophy':
        poly(d, [(cx - 44, cy - 62), (cx + 44, cy - 62), (cx + 34, cy + 6), (cx - 34, cy + 6)], hx('#ffc400')); line(d, [(cx - 44, cy - 50), (cx - 62, cy - 50), (cx - 56, cy - 12), (cx - 38, cy - 8)], hx('#e89a1a'), 7); line(d, [(cx + 44, cy - 50), (cx + 62, cy - 50), (cx + 56, cy - 12), (cx + 38, cy - 8)], hx('#e89a1a'), 7)
        d.rectangle([P(cx - 8), P(cy + 6), P(cx + 8), P(cy + 40)], fill=hx('#e89a1a')); d.rounded_rectangle([P(cx - 36), P(cy + 40), P(cx + 36), P(cy + 62)], radius=P(6), fill=hx('#b8860b')); star(d, cx, cy - 26, 20, hx('#ffffff'))
    elif kind == 'rocket':
        poly(d, [(cx, cy - 78), (cx + 28, cy - 20), (cx + 28, cy + 40), (cx - 28, cy + 40), (cx - 28, cy - 20)], hx('#f2f4f8')); poly(d, [(cx, cy - 78), (cx + 22, cy - 34), (cx - 22, cy - 34)], hx('#ff4d4f'))
        ell(d, cx, cy - 4, 13, 13, hx('#4da3ff'), INK, 3); poly(d, [(cx - 28, cy + 10), (cx - 52, cy + 50), (cx - 28, cy + 40)], hx('#ff4d4f')); poly(d, [(cx + 28, cy + 10), (cx + 52, cy + 50), (cx + 28, cy + 40)], hx('#ff4d4f'))
        fl = 22 + 14 * abs(math.sin(t * 6.28 * 3)); poly(d, [(cx - 16, cy + 40), (cx + 16, cy + 40), (cx, cy + 40 + fl + 18)], hx('#ffb300')); poly(d, [(cx - 8, cy + 40), (cx + 8, cy + 40), (cx, cy + 40 + fl)], hx('#fff3b0'))
    elif kind == 'bulb':
        ell(d, cx, cy - 14, 46, 50, hx('#ffe066'), hx('#e0b000'), 4); d.rounded_rectangle([P(cx - 20), P(cy + 30), P(cx + 20), P(cy + 58)], radius=P(6), fill=hx('#9aa5b1')); line(d, [(cx - 12, cy + 40), (cx + 12, cy + 40)], hx('#6b7785'), 4)
        glow = int(abs(math.sin(t * 6.28 * 2)) * 8)
        for k in range(8): a = k * math.pi / 4; line(d, [(cx + math.cos(a) * 62, cy - 14 + math.sin(a) * 66), (cx + math.cos(a) * (74 + glow), cy - 14 + math.sin(a) * (78 + glow))], hx('#ffe066'), 5)
    elif kind == 'coffee':
        d.rounded_rectangle([P(cx - 46), P(cy - 20), P(cx + 36), P(cy + 54)], radius=P(14), fill=hx('#ffffff'), outline=hx('#cbd5e1'), width=P(4)); arc(d, cx + 44, cy + 10, 22, 22, 270, 90, hx('#ffffff'), 9)
        ell(d, cx - 5, cy - 14, 40, 8, hx('#7b4a2a'))
        for k in range(3): ph = (t + k / 3) % 1; line(d, [(cx - 24 + k * 22, cy - 28 - ph * 34), (cx - 18 + k * 22, cy - 40 - ph * 34)], hx('#ffffff', int(220 * (1 - ph))), 5)
    return L
FACES = [
 ('Laughing', 'funny', {'eyes': 'happy', 'mouth': 'laugh', 'tears': True}, 'shake', ('#ff9a3c', '#d9480f')),
 ('Rolling laugh', 'funny', {'eyes': 'happy', 'mouth': 'laugh', 'tears': True}, 'roll', ('#7f00ff', '#e100ff')),
 ('Silly tongue', 'funny', {'eyes': 'wink', 'mouth': 'tongue'}, 'wobble', ('#11998e', '#38ef7d')),
 ('Dizzy', 'funny', {'eyes': 'dizzy', 'mouth': 'o'}, 'wobble', ('#396afc', '#2948ff')),
 ('Plot twist', 'funny', {'eyes': 'side', 'mouth': 'smirk'}, 'sway', ('#614385', '#516395')),
 ('Alien hello', 'funny', {'eyes': 'big', 'mouth': 'smile', 'shape': 'alien', 'skin': '#7ee081', 'shade': '#3fa84a'}, 'bounce', ('#0f2027', '#2c5364')),
 ('Ghost boo', 'funny', {'eyes': 'big', 'mouth': 'o', 'shape': 'ghost', 'skin': '#f4f6fb', 'shade': '#cfd6e6'}, 'float', ('#3a1c71', '#7b2ff7')),
 ('Heart eyes', 'love', {'eyes': 'hearts', 'mouth': 'grin'}, 'pulse', ('#ff5f6d', '#a4123f')),
 ('Blowing kiss', 'love', {'eyes': 'closed', 'mouth': 'kiss', 'blush': True}, 'pulse', ('#f857a6', '#7b1fa2')),
 ('Blushing', 'love', {'eyes': 'happy', 'mouth': 'smile', 'blush': True}, 'sway', ('#ee9ca7', '#ff758c')),
 ('Cat love', 'love', {'eyes': 'hearts', 'mouth': 'smile', 'shape': 'cat', 'skin': '#ffb46b', 'shade': '#d9822b'}, 'bounce', ('#c2185b', '#4a148c')),
 ('Winking', 'reactions', {'eyes': 'wink', 'mouth': 'smile'}, 'bounce', ('#f7971e', '#b8470b')),
 ('Shocked', 'reactions', {'eyes': 'big', 'mouth': 'o', 'sweat': True}, 'shake', ('#c0392b', '#6b1010')),
 ('Thinking', 'reactions', {'eyes': 'side', 'mouth': 'flat'}, 'sway', ('#34495e', '#1b2631')),
 ('Angry', 'reactions', {'eyes': 'angry', 'mouth': 'frown', 'skin': '#ff6b5a', 'shade': '#c0392b'}, 'shake', ('#2c3e50', '#000000')),
 ('Crying', 'reactions', {'eyes': 'sad', 'mouth': 'frown', 'tears': True}, 'sway', ('#2980b9', '#124a73')),
 ('Cool', 'reactions', {'eyes': 'shades', 'mouth': 'smirk'}, 'bounce', ('#00c6ff', '#0072ff')),
 ('Sleepy', 'reactions', {'eyes': 'closed', 'mouth': 'sleep'}, 'sway', ('#141e30', '#35577d')),
 ('Nerd', 'funny', {'eyes': 'nerd', 'mouth': 'teeth'}, 'bounce', ('#16a085', '#0a5c4d')),
 ('Money eyes', 'business', {'eyes': 'dollar', 'mouth': 'grin'}, 'pulse', ('#0a6e6e', '#0d1b2a')),
 ('Robot', 'business', {'eyes': 'robot', 'mouth': 'robot', 'shape': 'robot', 'skin': '#b8c4d6', 'shade': '#6b7a90'}, 'bounce', ('#1e3c72', '#2a5298')),
 ('Party', 'celebrate', {'eyes': 'happy', 'mouth': 'grin', 'hat': True}, 'bounce', ('#f12711', '#8a0e3a')),
 ('Star struck', 'celebrate', {'eyes': 'stars', 'mouth': 'grin'}, 'pulse', ('#8e2de2', '#4a00e0')),
 ('Cat party', 'celebrate', {'eyes': 'happy', 'mouth': 'grin', 'shape': 'cat', 'skin': '#d6d6f5', 'shade': '#9a9ad0', 'hat': True}, 'wobble', ('#f7971e', '#8f4a00')),
]
OBJ = [('Thumbs up', 'reactions', 'thumbs', 'bounce', ('#0a6e6e', '#074040')), ('Big heart', 'love', 'heart', 'pulse', ('#ee0979', '#8e2de2')), ('Gold star', 'celebrate', 'star', 'spin', ('#141e30', '#35577d')),
       ('Trophy', 'celebrate', 'trophy', 'bounce', ('#396afc', '#2948ff')), ('Launch', 'business', 'rocket', 'float', ('#0f2027', '#2c5364')), ('Big idea', 'business', 'bulb', 'pulse', ('#4568dc', '#2a3b8f')), ('Coffee break', 'greetings', 'coffee', 'sway', ('#614385', '#2a1a45'))]
def transform(L, anim, t):
    dx = dy = 0; ang = 0; sc = 1
    if anim == 'bounce': dy = -abs(math.sin(t * 6.28 * 2)) * 16; sc = 1 + .04 * abs(math.cos(t * 6.28 * 2))
    elif anim == 'shake': dx = math.sin(t * 6.28 * 6) * 7; ang = math.sin(t * 6.28 * 6) * 3
    elif anim == 'wobble': ang = math.sin(t * 6.28 * 2) * 9
    elif anim == 'sway': dx = math.sin(t * 6.28) * 8; ang = math.sin(t * 6.28) * 5
    elif anim == 'pulse': sc = 1 + .08 * math.sin(t * 6.28 * 2)
    elif anim == 'roll': ang = -t * 360
    elif anim == 'spin': ang = t * 0
    elif anim == 'float': dy = math.sin(t * 6.28) * 12
    if ang or sc != 1: 
        L = L.rotate(ang, resample=Image.BICUBIC, center=(T*S/2, T*S/2 + 14*S)); 
        if sc != 1: 
            w = int(T*S*sc); L2 = L.resize((w, w), Image.LANCZOS); L = Image.new('RGBA', (T*S, T*S), (0, 0, 0, 0)); L.paste(L2, ((T*S - w)//2, (T*S - w)//2 + 10*S), L2)
    if dx or dy: L = L.transform(L.size, Image.AFFINE, (1, 0, -dx*S, 0, 1, -dy*S), resample=Image.BICUBIC)
    return L
def render(slug, bg, draw_fn, anim):
    frames = []
    for i in range(N):
        t = i / N; im = tile(*bg); L = transform(draw_fn(t), anim, t)
        # soft ground shadow
        sh = Image.new('RGBA', (T*S, T*S), (0, 0, 0, 0)); ImageDraw.Draw(sh).ellipse([P(70), P(212), P(170), P(226)], fill=(0, 0, 0, 60)); im = Image.alpha_composite(im, sh)
        im = Image.alpha_composite(im, L).resize((T, T), Image.LANCZOS)
        # GIF has 1-bit transparency: flatten the corner onto the tile mask so the rounded corners stay transparent and edges are clean
        a = im.getchannel('A').point(lambda v: 255 if v > 128 else 0); rgb = im.convert('RGB').quantize(colors=96, method=Image.MEDIANCUT, dither=Image.NONE)
        pal = rgb.copy(); idx = list(pal.getdata()); mask = list(a.getdata())
        pal.info['transparency'] = 255; fr = Image.new('P', (T, T)); pp = pal.getpalette()[:95*3+3] + [0, 0, 0] * 0
        fr.putpalette(pal.getpalette()); fr.putdata([255 if m == 0 else min(x, 254) for x, m in zip(idx, mask)]); fr.info['transparency'] = 255; frames.append(fr)
    os.makedirs(OUT, exist_ok=True); frames[0].save(f'{OUT}/{slug}.gif', save_all=True, append_images=frames[1:], duration=DUR, loop=0, disposal=2, transparency=255, optimize=False)
def slugify(s): return ''.join(c.lower() if c.isalnum() else '-' for c in s).strip('-')
if __name__ == '__main__':
    import json; meta = []
    for name, cat, spec, anim, bg in FACES: s = slugify(name); render(s, bg, lambda t, spec=spec: face(spec, t), anim); meta.append((s, name, cat))
    for name, cat, kind, anim, bg in OBJ: s = slugify(name); render(s, bg, lambda t, kind=kind: objects(kind, t), anim); meta.append((s, name, cat))
    print(len(meta), 'stickers')
