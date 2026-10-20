#!/usr/bin/env python3
"""Build a Naara handbook (MASTER-ONLY tooling, see CLAUDE.md "Handbook editorial standard").

  build.py GBR-04 [--epub] [--pdf] [--install]

Source of truth: handbooks-src/{ID}/meta.json, manuscript/*.md (pandoc Markdown), images/*.webp + credits.json.
Output: handbooks-src/{ID}/build/{ID}.epub and .pdf; --install copies them to database/seeders/handbooks/.
Callouts: ::: {.verify} / .example title="..." / .plain title="..." / .checklist title="..." / .insight / .next / .warn
Heading levels: # = Part (class .part number="I" sub="..."), ## = Chapter (number="1"), ### = section. [VERIFY: x]{.v} marks an unconfirmed fact.
"""
import argparse, glob, json, os, re, shutil, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..'))
REPO = os.path.abspath(os.path.join(ROOT, '..'))


def sh(cmd, **kw):
    r = subprocess.run(cmd, capture_output=True, text=True, **kw)
    if r.returncode:
        sys.exit(f"FAILED: {' '.join(cmd[:4])}\n{r.stderr[-1500:]}")
    return r.stdout


def assemble(book, meta):
    parts = []
    for f in sorted(glob.glob(f'{book}/manuscript/*.md')):
        parts.append(open(f).read().strip() + '\n')
    md = '\n\n'.join(parts)
    # Photo credits appendix from the images actually used
    used = sorted(set(re.findall(r'images/([A-Za-z0-9_.-]+\.webp)', md)))
    cp = f'{book}/images/credits.json'
    if used and os.path.exists(cp):
        credits = json.load(open(cp))
        rows = ['| Figure file | Photo | Author | Licence |', '|---|---|---|---|']
        for u in used:
            c = credits.get(u)
            if c:
                rows.append(f"| {u[:-5]} | [{c['title'].replace('File:', '')[:70]}]({c['url']}) | {c['author'][:60] or 'Unknown'} | {c['licence']} |")
        md += ('\n\n### Photo credits {.unnumbered}\n\nPhotographs are from Wikimedia Commons and are used under the licences shown (CC0, public domain, CC BY or CC BY-SA). '
               'Each file is linked to its source page. Naara resized and compressed them for this book.\n\n' + '\n'.join(rows) + '\n')
    return md


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('id'); ap.add_argument('--epub', action='store_true'); ap.add_argument('--pdf', action='store_true'); ap.add_argument('--install', action='store_true')
    a = ap.parse_args()
    both = not (a.epub or a.pdf)
    book = os.path.join(ROOT, a.id)
    meta = json.load(open(f'{book}/meta.json'))
    out = f'{book}/build'; os.makedirs(out, exist_ok=True)
    # images/ is reachable as images/... from the md; tools assets as _tools/assets/... from ROOT-relative html, so run with resource path
    md_path = f'{out}/{a.id}.md'
    open(md_path, 'w').write(assemble(book, meta))
    fmt = 'markdown-tex_math_dollars+smart+fenced_divs+bracketed_spans+pipe_tables+grid_tables+implicit_figures+link_attributes'
    common = ['pandoc', md_path, '-f', fmt, '--lua-filter', f'{HERE}/callouts.lua', '--resource-path', f'{book}:{ROOT}']
    if a.pdf or both:
        hero = meta.get('hero')
        cmd = common + ['-t', 'html5', '--standalone', '--template', f'{HERE}/book.html', '--toc', '--toc-depth=3', '--section-divs',
                        '-V', f'lang={meta.get("language", "en")}', '-V', f'pagetitle={meta["title"]}', '-V', f'title={meta["title"]}', '-V', f'subtitle={meta.get("subtitle", "")}',
                        '-V', f'shorttitle={meta["short_title"]}', '-V', f'bookid={a.id}', '-V', f'slotline={meta.get("slot_line", "")}']
        if hero:
            cmd += ['-V', f'hero=images/{hero}']
        html = sh(cmd)
        # the TOC heading + figure paths relative to the book dir
        m = re.search(r'<nav id="TOC" role="doc-toc">.*?</nav>', html, re.S)
        if m:
            toc = m.group(0).replace('<nav id="TOC" role="doc-toc">', '<nav id="TOC" role="doc-toc"><div class="toc-title">Contents</div>')
            html = html.replace(m.group(0), '')
            html = html.replace('<div class="toc-here"></div>', toc) if '<div class="toc-here"></div>' in html else html.replace('<body>', '<body>', 1)
        # `_tools/assets/...` and `images/...` both resolve from a base dir that has both: use ROOT with a symlink to the book's images
        base = os.path.join(out, '_base'); shutil.rmtree(base, ignore_errors=True); os.makedirs(base)
        # The PDF gets JPEG copies of the (lighter) WebP figures: WeasyPrint passes JPEG straight through, but would expand WebP to raw pixels.
        os.makedirs(os.path.join(base, 'images')); os.symlink(HERE, os.path.join(base, '_tools'))
        from PIL import Image
        for wp in glob.glob(os.path.join(book, 'images', '*.webp')):
            Image.open(wp).convert('RGB').save(os.path.join(base, 'images', os.path.basename(wp)[:-5] + '.jpg'), 'JPEG', quality=74, optimize=True, progressive=True)
        html = re.sub(r'(images/[A-Za-z0-9_.-]+)\.webp', r'\1.jpg', html)
        open(os.path.join(base, 'book.html'), 'w').write(html)
        from weasyprint import HTML, CSS
        pdf = f'{out}/{a.id}.pdf'
        HTML(os.path.join(base, 'book.html'), base_url=base + '/').write_pdf(pdf, stylesheets=[CSS(f'{HERE}/pdf.css')], optimize_images=True, uncompressed_pdf=True)
        print('PDF ', pdf, os.path.getsize(pdf) // 1024, 'KB')
    if a.epub or both:
        ep = f'{out}/{a.id}.epub'
        yaml = f'{out}/epub-meta.yaml'
        open(yaml, 'w').write(json.dumps({'title': meta['title'], 'subtitle': meta.get('subtitle', ''), 'author': 'Naara Family', 'publisher': 'Naara Family (Naara Connect Technologies Limited)',
                                          'lang': meta.get('language', 'en'), 'date': meta.get('as_of', ''), 'rights': f"© 2026 Naara Connect Technologies Limited. Naara.app. {a.id}", 'identifier': f'naara:{a.id}'}))
        sh(common + ['-t', 'epub3', '-o', ep, '--metadata-file', yaml, '--css', f'{HERE}/epub.css', '--toc', '--toc-depth=3', '--split-level=2', '--epub-title-page=true'])
        print('EPUB', ep, os.path.getsize(ep) // 1024, 'KB')
    if a.install:
        dst = os.path.join(REPO, 'database/seeders/handbooks')
        for ext in ('epub', 'pdf'):
            f = f'{out}/{a.id}.{ext}'
            if os.path.exists(f):
                shutil.copy(f, f'{dst}/{a.id}.{ext}'); print('installed', f'{dst}/{a.id}.{ext}')


if __name__ == '__main__':
    main()
