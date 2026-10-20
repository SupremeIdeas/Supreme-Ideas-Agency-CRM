#!/usr/bin/env python3
"""Handbook image toolchain (MASTER-ONLY, see CLAUDE.md "Handbook editorial standard").

  commons.py sheet "query" [-n 8] [--out sheet.png]      contact sheet of Wikimedia Commons candidates (look at it, pick one)
  commons.py get "File:Name.jpg" GBR-04-c03-visa-biometrics [--width 900] [--quality 70] [--crop 4:3]
                                                           download, fit, WebP, strip metadata, append credits.json
Only CC0 / public domain / CC BY / CC BY-SA files are accepted. Throttled and 429-aware (the shared egress IP is rate limited).
"""
import argparse, io, json, os, re, sys, time, urllib.parse, urllib.request
from PIL import Image, ImageDraw, ImageOps

UA = 'NaaraHandbookBot/1.0 (https://naara.app; supremeideasofficial@gmail.com)'
API = 'https://commons.wikimedia.org/w/api.php'
OK_LICENCE = re.compile(r'^(cc0|public domain|pd\b|pdm|cc[- ]by(?![- ]?(nc|nd))|cc[- ]by[- ]sa)', re.I)
HERE = os.path.dirname(os.path.abspath(__file__))
_last = [0.0]


def http(url, binary=False, gap=None):
    gap = gap if gap is not None else (1.3 if binary else float(os.environ.get('APIGAP', '9')))
    for attempt in range(8):
        wait = gap - (time.time() - _last[0])
        if wait > 0:
            time.sleep(wait)
        req = urllib.request.Request(url, headers={'User-Agent': UA})
        try:
            with urllib.request.urlopen(req, timeout=25) as r:
                _last[0] = time.time()
                data = r.read()
                return data if binary else data.decode()
        except urllib.error.HTTPError as e:
            _last[0] = time.time()
            if e.code in (429, 503):
                print('  429/503, sleeping', e.headers.get('Retry-After'), flush=True)
                time.sleep(min(int(e.headers.get('Retry-After', '20')) + 3, 130))
                continue
            raise
        except Exception as ex:
            print('  retry', type(ex).__name__, str(ex)[:60], flush=True)
            time.sleep(3 + attempt * 2)
    raise RuntimeError('giving up on ' + url)


def api(**p):
    p['format'] = 'json'
    return json.loads(http(API + '?' + urllib.parse.urlencode(p)))


import hashlib
OV = 'https://api.openverse.org/v1/images/'
OK_OV = {'by', 'by-sa', 'cc0', 'pdm'}


def commons_thumb(url_original):
    """960px thumb path on upload.wikimedia.org (a standard step) from the original file URL."""
    m = re.match(r'https://upload\.wikimedia\.org/wikipedia/commons/(?:[0-9a-f]/[0-9a-f]{2}/)?(.+)$', url_original)
    if not m:
        return url_original
    name = urllib.parse.unquote(m.group(1))
    h = hashlib.md5(name.encode()).hexdigest()
    q = urllib.parse.quote(name)
    if name.lower().endswith(('.jpg', '.jpeg')):
        return f'https://upload.wikimedia.org/wikipedia/commons/thumb/{h[0]}/{h[:2]}/{q}/960px-{q}'
    return f'https://upload.wikimedia.org/wikipedia/commons/thumb/{h[0]}/{h[:2]}/{q}/960px-{q}.jpg'


def openverse(q, n=12):
    src = None
    if '|' in q:
        q, src = q.split('|', 1)
    p = dict(q=q, page_size=n, extension='jpg', license=','.join(sorted(OK_OV)), mature='false', category='photograph')
    if src:
        p['source'] = src
    d = json.loads(http(OV + '?' + urllib.parse.urlencode(p), gap=1.0))
    out = []
    for i, r in enumerate(d.get('results', [])):
        if (r.get('width') or 0) < 700 or r['license'] not in OK_OV:
            continue
        lic = {'cc0': 'CC0', 'pdm': 'Public domain'}.get(r['license']) or f"CC {r['license'].upper()} {r.get('license_version') or ''}".strip()
        thumb = r['url']
        if r['source'] in ('wikimedia_commons', 'wikimedia'):
            thumb = commons_thumb(r['url'])
        out.append(dict(thumb2=r.get('thumbnail') or '', title='File:' + (r.get('title') or r['id']), index=i, licence=lic, ok=True, author=(r.get('creator') or '')[:120], w=r['width'], h=r['height'], thumb=thumb, url=r.get('foreign_landing_url') or r['url']))
    return out


def info(titles=None, search=None, n=8, width=900):
    if search and not search.startswith('cat:') and not titles and os.environ.get('SRC', 'openverse') == 'openverse':
        return openverse(search, max(n, 12))
    n = max(n, 20)
    p = dict(action='query', prop='imageinfo', iiprop='url|extmetadata|size|mime', iiurlwidth=960, iiextmetadatafilter='LicenseShortName|Artist')
    if search and search.startswith('cat:'):
        p.update(generator='categorymembers', gcmtitle='Category:' + search[4:], gcmtype='file', gcmlimit=n)
    elif search:
        p.update(generator='search', gsrsearch=search + ' filetype:bitmap', gsrnamespace=6, gsrlimit=n)
    else:
        p['titles'] = titles
    out = []
    for pg in (api(**p).get('query', {}).get('pages', {}) or {}).values():
        ii = (pg.get('imageinfo') or [None])[0]
        if not ii or not ii.get('mime', '').startswith('image/') or ii['mime'] in ('image/svg+xml', 'image/gif'):
            continue
        m = ii.get('extmetadata', {})
        strip = lambda k: re.sub(r'<[^>]+>', '', (m.get(k, {}) or {}).get('value', '') or '').strip()
        lic = strip('LicenseShortName')
        out.append(dict(title=pg['title'], index=pg.get('index', 0), licence=lic, ok=bool(OK_LICENCE.search(lic)), author=strip('Artist')[:120],
                        w=ii['width'], h=ii['height'], thumb=ii.get('thumburl') or ii['url'], url=ii['descriptionurl']))
    return sorted(out, key=lambda x: x['index'])


def fetch_img(r):
    last = None
    for u in (r.get('thumb'), r.get('thumb2')):
        if not u:
            continue
        try:
            return Image.open(io.BytesIO(http(u, True)))
        except Exception as e:
            last = e
    raise RuntimeError(f'no usable thumbnail: {last}')


def cmd_cats(a):
    for pg in (api(action='query', list='search', srsearch=a.query, srnamespace=14, srlimit=15).get('query', {}).get('search', [])):
        print(pg['title'])


def cmd_sheet(a):
    rows = [r for r in info(search=a.query, n=a.n) if r['ok'] and r['w'] >= 700][:a.n]
    if not rows:
        print('no usable candidates'); return
    tw, th = 360, 270
    cols = 4
    sheet = Image.new('RGB', (cols * tw, ((len(rows) + cols - 1) // cols) * (th + 34)), 'white')
    d = ImageDraw.Draw(sheet)
    for i, r in enumerate(rows):
        try:
            im = Image.open(io.BytesIO(http(r['thumb'], True))).convert('RGB')
        except Exception as e:
            continue
        im = ImageOps.fit(im, (tw - 6, th - 6))
        x, y = (i % cols) * tw, (i // cols) * (th + 34)
        sheet.paste(im, (x + 3, y + 3))
        d.text((x + 4, y + th), f"{i + 1}. {r['title'][5:50]}", fill='black')
        d.text((x + 4, y + th + 12), f"{r['licence']} {r['w']}x{r['h']}", fill='gray')
        print(f"{i + 1}. {r['title']} | {r['licence']} | {r['w']}x{r['h']} | {r['author'][:50]}")
    sheet.save(a.out)
    print('sheet ->', a.out)


def save_image(r, a):
    im = fetch_img(r)
    im = ImageOps.exif_transpose(im).convert('RGB')
    if a.crop:
        cw, ch = map(float, a.crop.split(':'))
        im = ImageOps.fit(im, (im.width, int(im.width * ch / cw)), centering=(0.5, 0.45))
    if im.width > a.width:
        im = im.resize((a.width, round(im.height * a.width / im.width)), Image.LANCZOS)
    bid = re.match(r'^([A-Z]{3}-\d+)', a.name).group(1)
    out_dir = os.path.abspath(a.dir or os.path.join(HERE, '..', bid, 'images'))
    os.makedirs(out_dir, exist_ok=True)
    q = a.quality
    while True:
        buf = io.BytesIO()
        im.save(buf, 'WEBP', quality=q, method=6)
        if buf.tell() <= a.max_kb * 1024 or q <= 40:
            break
        q -= 5
    path = os.path.join(out_dir, a.name + '.webp')
    open(path, 'wb').write(buf.getvalue())
    cp = os.path.join(out_dir, 'credits.json')
    credits = json.load(open(cp)) if os.path.exists(cp) else {}
    credits[a.name + '.webp'] = dict(title=r['title'], author=r['author'], licence=r['licence'], url=r['url'])
    json.dump(credits, open(cp, 'w'), indent=1, ensure_ascii=False)
    print(f"{path}  {im.width}x{im.height}  {buf.tell() // 1024} KB  q{q}  {r['licence']}")


def cmd_get(a):
    rows = info(titles=a.title, width=max(a.width, 900))
    if not rows or not rows[0]['ok']:
        sys.exit('file not found or licence not allowed: ' + str(rows[:1]))
    save_image(rows[0], a)


def main():
    ap = argparse.ArgumentParser()
    sp = ap.add_subparsers(dest='cmd', required=True)
    s = sp.add_parser('sheet'); s.add_argument('query'); s.add_argument('-n', type=int, default=8); s.add_argument('--out', default='/tmp/sheet.png'); s.set_defaults(f=cmd_sheet)
    g = sp.add_parser('get'); g.add_argument('title'); g.add_argument('name'); g.add_argument('--width', type=int, default=900); g.add_argument('--quality', type=int, default=70)
    g.add_argument('--max-kb', type=int, default=70); g.add_argument('--crop'); g.add_argument('--dir'); g.set_defaults(f=cmd_get)
    c = sp.add_parser('cats'); c.add_argument('query'); c.set_defaults(f=cmd_cats)
    b = sp.add_parser('batch'); b.add_argument('plan'); b.add_argument('outdir'); b.add_argument('--only'); b.add_argument('--force', action='store_true'); b.set_defaults(f=cmd_batch)
    k = sp.add_parser('pick'); k.add_argument('outdir'); k.add_argument('name'); k.add_argument('index', type=int); k.add_argument('--width', type=int, default=900); k.add_argument('--quality', type=int, default=70)
    k.add_argument('--max-kb', type=int, default=70); k.add_argument('--crop'); k.add_argument('--dir'); k.set_defaults(f=cmd_pick)
    a = ap.parse_args(); a.f(a)


def cmd_batch(a):
    """batch PLAN.json OUTDIR : for every name run its queries, save a contact sheet + candidates json (look, then `pick`)."""
    plan = json.load(open(a.plan)); os.makedirs(a.outdir, exist_ok=True)
    for name, queries in plan.items():
        if a.only and a.only not in name: continue
        sp = os.path.join(a.outdir, name + '.png')
        if os.path.exists(sp) and not a.force: continue
        rows = []; seen = set()
        for q in queries:
            print('  q:', q, flush=True)
            for r in info(search=q, n=10):
                if r['ok'] and r['w'] >= 700 and r['title'] not in seen:
                    seen.add(r['title']); rows.append(r)
        rows = rows[:12]
        if not rows:
            print('NONE', name); continue
        tw, th, cols = 300, 225, 4
        sheet = Image.new('RGB', (cols * tw, ((len(rows) + cols - 1) // cols) * (th + 30)), 'white'); d = ImageDraw.Draw(sheet)
        for i, r in enumerate(rows):
            try:
                im = fetch_img(r).convert('RGB')
            except Exception as ex:
                print('   thumb fail', r['title'][:30], str(ex)[:50], flush=True); continue
            im = ImageOps.fit(im, (tw - 6, th - 6)); x, y = (i % cols) * tw, (i // cols) * (th + 30)
            sheet.paste(im, (x + 3, y + 3)); d.text((x + 4, y + th - 2), f"{i + 1}. {r['title'][5:45]}", fill='black'); d.text((x + 4, y + th + 9), f"{r['licence']} {r['w']}x{r['h']}", fill='gray')
        sheet.save(sp); json.dump(rows, open(os.path.join(a.outdir, name + '.json'), 'w'))
        print('ok', name, len(rows), flush=True)


def cmd_pick(a):
    rows = json.load(open(os.path.join(a.outdir, a.name + '.json')))
    save_image(rows[a.index - 1], a)


if __name__ == '__main__':
    main()
