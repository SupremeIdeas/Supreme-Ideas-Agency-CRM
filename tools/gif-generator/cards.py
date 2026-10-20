# Original, license-clean animated GIF presets for the composer GIF library: our own artwork (text cards, shapes, motion), no third-party content.
import math, os, random
from PIL import Image, ImageDraw, ImageFont
W, H, N, DUR = 320, 240, 14, 80
F = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'public', 'images', 'gifs')
def font(sz): return ImageFont.truetype(F, sz)
def hexrgb(h): h = h.lstrip('#'); return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))
def grad(c1, c2):
    a, b = hexrgb(c1), hexrgb(c2); im = Image.new('RGB', (W, H)); d = ImageDraw.Draw(im)
    for y in range(H):
        t = y / (H - 1); d.line([(0, y), (W, y)], fill=tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3)))
    return im
def fit(d, text, maxw, start):
    sz = start
    while sz > 14 and d.textlength(text, font=font(sz)) > maxw: sz -= 2
    return font(sz)
def wrap(d, text, maxw, start):
    words = text.split(); 
    for sz in range(start, 15, -2):
        f = font(sz); lines = []; cur = ''
        for w in words:
            t = (cur + ' ' + w).strip()
            if d.textlength(t, font=f) <= maxw: cur = t
            else: lines.append(cur); cur = w
        lines.append(cur)
        if len(lines) <= 2 and all(d.textlength(l, font=f) <= maxw for l in lines): return f, lines
    return font(16), words
def text_c(d, lines, f, cx, cy, fill, shadow=True, dy=0):
    lh = f.size * 1.18; y = cy - lh * len(lines) / 2 + dy
    for l in lines:
        w = d.textlength(l, font=f)
        if shadow: d.text((cx - w / 2 + 2, y + 2), l, font=f, fill=(0, 0, 0))
        d.text((cx - w / 2, y), l, font=f, fill=fill); y += lh
def star(d, cx, cy, r, fill, rot=0):
    pts = []
    for i in range(10):
        rr = r if i % 2 == 0 else r * .45; a = math.pi * i / 5 - math.pi / 2 + rot
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    d.polygon(pts, fill=fill)
def heart(d, cx, cy, s, fill):
    pts = []
    for i in range(60):
        t = i / 60 * 2 * math.pi
        x = 16 * math.sin(t) ** 3; y = -(13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t))
        pts.append((cx + x * s / 16, cy + y * s / 16))
    d.polygon(pts, fill=fill)
PALS = {
 'funny': [('#ff7a18', '#af002d'), ('#7f00ff', '#e100ff'), ('#f7971e', '#c0392b'), ('#00c6ff', '#0072ff'), ('#f953c6', '#b91d73'), ('#11998e', '#0a5f57')],
 'love': [('#ff5f6d', '#a4123f'), ('#ee0979', '#8e2de2'), ('#f857a6', '#7b1fa2'), ('#e53935', '#6a1b4d'), ('#ff6e7f', '#bf2c6e'), ('#c2185b', '#4a148c')],
 'business': [('#0a6e6e', '#0d1b2a'), ('#1e3c72', '#2a5298'), ('#134e5e', '#0d1b2a'), ('#283e51', '#0d1b2a'), ('#0f4c75', '#0b2545'), ('#0a6e6e', '#074040')],
 'celebrate': [('#f7971e', '#b8470b'), ('#8e2de2', '#4a00e0'), ('#ff512f', '#a4123f'), ('#00b09b', '#0a6e6e'), ('#f12711', '#8a0e3a'), ('#396afc', '#2948ff')],
 'thanks': [('#0a6e6e', '#07403f'), ('#b8860b', '#6b4f06'), ('#2c7a4b', '#0b3d24'), ('#5b2c83', '#2d1650'), ('#0072ff', '#0a2a6b'), ('#d35400', '#7a2f00')],
 'greetings': [('#2193b0', '#0b5a74'), ('#f2994a', '#b34700'), ('#141e30', '#35577d'), ('#11998e', '#0b5a52'), ('#4568dc', '#2a3b8f'), ('#6a3093', '#2b1055')],
 'reactions': [('#c0392b', '#6b1010'), ('#16a085', '#0a5c4d'), ('#8e44ad', '#4a1a63'), ('#2980b9', '#124a73'), ('#d35400', '#7a2f00'), ('#34495e', '#1b2631')],
 'motivation': [('#f7971e', '#8f4a00'), ('#0a6e6e', '#0d1b2a'), ('#cb2d3e', '#5a0f1a'), ('#1d976c', '#0b4a33'), ('#2b5876', '#14293b'), ('#614385', '#2a1a45')],
}
def make(cat, slug, text, idx, anim):
    c1, c2 = PALS[cat][idx % 6]; base = grad(c1, c2); frames = []; rnd = random.Random(hash(slug) & 0xffff)
    parts = [(rnd.random() * W, rnd.random() * H, rnd.random(), rnd.random()) for _ in range(18)]
    for i in range(N):
        t = i / N; im = base.copy(); d = ImageDraw.Draw(im, 'RGBA'); cx, cy = W / 2, H / 2 + 6
        f, lines = wrap(d, text, W - 56, 52)
        dy = 0; scale_fill = (255, 255, 255)
        if anim == 'bounce': dy = -abs(math.sin(t * math.pi * 2)) * 22
        elif anim == 'shake': dy = 0; cx += math.sin(t * math.pi * 8) * 7
        elif anim == 'pulse': pass
        elif anim == 'blink': scale_fill = (255, 255, 255) if (i // 3) % 2 == 0 else (255, 224, 130)
        elif anim == 'slide': cx += (1 - min(1, t * 2.2)) * -W * .7 if t < .45 else 0
        # ambient motion layer by anim
        if anim in ('confetti', 'bounce', 'shake', 'blink', 'slide', 'pulse'):
            for k, (px, py, a, b) in enumerate(parts):
                if anim == 'confetti' or anim == 'bounce':
                    y = (py + t * H * (0.8 + a)) % H; x = px + math.sin(t * 6.28 + k) * 10
                    col = [(255, 255, 255, 200), (255, 214, 102, 220), (255, 120, 150, 220), (120, 220, 255, 220)][k % 4]
                    d.rectangle([x, y, x + 6, y + 10 * (0.5 + b)], fill=col)
                else:
                    r = 2 + 3 * abs(math.sin(t * 6.28 + k)); d.ellipse([px - r, py - r, px + r, py + r], fill=(255, 255, 255, 60))
        if anim == 'hearts':
            for k, (px, py, a, b) in enumerate(parts[:9]):
                y = (py - t * H * (0.7 + a * .6)) % H; s = 10 + 12 * b
                heart(d, px + math.sin(t * 6.28 + k) * 12, y, s, (255, 255, 255, 120 + int(80 * a)))
            hb = 1 + .08 * math.sin(t * 6.28 * 2); heart(d, cx, 72, 66 * hb, (255, 255, 255, 235)); cy += 52
        if anim == 'stars':
            for k, (px, py, a, b) in enumerate(parts[:12]):
                r = 4 + 8 * abs(math.sin(t * 6.28 + k * 1.3)); star(d, px, py, r, (255, 236, 160, 230), t * 2)
        if anim == 'ring':
            for k in range(3):
                rr = ((t + k / 3) % 1) * 150; d.ellipse([cx - rr, H / 2 - rr, cx + rr, H / 2 + rr], outline=(255, 255, 255, int(190 * (1 - rr / 150))), width=3)
        if anim == 'check':
            p = min(1, t * 1.6); d.ellipse([cx - 44, 60 - 44 + 6, cx + 44, 60 + 44 + 6], outline=(255, 255, 255, 255), width=6)
            pts = [(cx - 20, 66), (cx - 4, 84), (cx + 24, 46)]
            seg1 = min(1, p * 2); seg2 = max(0, p * 2 - 1)
            d.line([pts[0], (pts[0][0] + (pts[1][0] - pts[0][0]) * seg1, pts[0][1] + (pts[1][1] - pts[0][1]) * seg1)], fill=(255, 255, 255, 255), width=9)
            if seg2 > 0: d.line([pts[1], (pts[1][0] + (pts[2][0] - pts[1][0]) * seg2, pts[1][1] + (pts[2][1] - pts[1][1]) * seg2)], fill=(255, 255, 255, 255), width=9)
            cy += 58
        if anim == 'wave':
            f2 = fit(d, text, W - 40, 46); x = (W - d.textlength(text, font=f2)) / 2
            for ch in text:
                w = d.textlength(ch, font=f2); yy = H / 2 - f2.size / 2 + math.sin(t * 6.28 - x / 28) * 14
                d.text((x + 2, yy + 2), ch, font=f2, fill=(0, 0, 0)); d.text((x, yy), ch, font=f2, fill=(255, 255, 255)); x += w
            frames.append(im.convert('P', palette=Image.ADAPTIVE, colors=64)); continue
        if anim == 'pulse':
            s = 1 + .07 * math.sin(t * 6.28 * 2); f = font(int(f.size * s))
        if anim == 'rise':
            for k, (px, py, a, b) in enumerate(parts[:10]):
                y = (py - t * H * (0.5 + a)) % H; r = 3 + 6 * b; d.ellipse([px - r, y - r, px + r, y + r], fill=(255, 255, 255, 80))
            dy = (1 - min(1, t * 2)) * 24 if t < .5 else 0
        text_c(d, lines, f, cx, cy, scale_fill, dy=dy)
        frames.append(im.convert('P', palette=Image.ADAPTIVE, colors=64))
    os.makedirs(f'{OUT}/{cat}', exist_ok=True)
    frames[0].save(f'{OUT}/{cat}/{slug}.gif', save_all=True, append_images=frames[1:], duration=DUR, loop=0, optimize=True, disposal=2)
LIB = {
 'funny': [('LOL', 'lol', 'bounce'), ('Plot twist', 'plot-twist', 'shake'), ('Nope', 'nope', 'shake'), ('Epic fail', 'epic-fail', 'blink'), ('Big mood', 'big-mood', 'wave'), ('Awkward', 'awkward', 'pulse')],
 'love': [('Love you', 'love-you', 'hearts'), ('Miss you', 'miss-you', 'hearts'), ('Big hug', 'big-hug', 'pulse'), ('Be mine', 'be-mine', 'hearts'), ('Sweet dreams', 'sweet-dreams', 'stars'), ('Thinking of you', 'thinking-of-you', 'hearts')],
 'business': [('Invoice sent', 'invoice-sent', 'check'), ('Meeting at 3', 'meeting', 'pulse'), ('Deal closed', 'deal-closed', 'check'), ('Payment received', 'payment-received', 'check'), ('Order shipped', 'order-shipped', 'slide'), ('Out of office', 'out-of-office', 'pulse')],
 'celebrate': [('Congrats', 'congrats', 'confetti'), ('Happy birthday', 'happy-birthday', 'confetti'), ('You did it', 'you-did-it', 'stars'), ('Well done', 'well-done', 'confetti'), ('Cheers', 'cheers', 'bounce'), ('New milestone', 'new-milestone', 'stars')],
 'thanks': [('Thank you', 'thank-you', 'ring'), ('Much appreciated', 'much-appreciated', 'pulse'), ('You rock', 'you-rock', 'stars'), ('Thanks a million', 'thanks-a-million', 'confetti'), ('Grateful', 'grateful', 'rise'), ('Noted, thanks', 'noted-thanks', 'check')],
 'greetings': [('Hello', 'hello', 'wave'), ('Good morning', 'good-morning', 'rise'), ('Good night', 'good-night', 'stars'), ('Welcome', 'welcome', 'slide'), ('See you soon', 'see-you-soon', 'pulse'), ('Happy weekend', 'happy-weekend', 'bounce')],
 'reactions': [('Yes', 'yes', 'bounce'), ('No', 'no', 'shake'), ('Wow', 'wow', 'pulse'), ('On it', 'on-it', 'slide'), ('OK', 'ok', 'check'), ('Hmm', 'hmm', 'blink')],
 'motivation': [('Keep going', 'keep-going', 'slide'), ('You got this', 'you-got-this', 'pulse'), ('Rise and grind', 'rise-and-grind', 'rise'), ('One more step', 'one-more-step', 'slide'), ('Stay focused', 'stay-focused', 'ring'), ('Believe', 'believe', 'stars')],
}
if __name__ == '__main__':
    n = 0
    for cat, items in LIB.items():
        for i, (text, slug, anim) in enumerate(items): make(cat, slug, text, i, anim); n += 1
    print(n, 'gifs')
