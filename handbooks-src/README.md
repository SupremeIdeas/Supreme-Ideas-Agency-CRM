# Handbook source and build pipeline (MASTER ONLY)

The standing editorial rules are in the root `CLAUDE.md` ("Handbook editorial standard"). This folder is the source of truth for every expanded handbook; the EPUB and PDF in `database/seeders/handbooks/` are build output.

## Layout
```
handbooks-src/
  _tools/            build.py, commons.py (image toolchain), callouts.lua, docx2naara.lua, ins.py, pdf.css, epub.css, book.html, assets/
  {ID}/              GBR-04, GBR-05 ...
    meta.json        title, subtitle, short_title, slot_line, as_of
    manuscript/      00-front.md, 10-part1.md, 11-ch01.md ... (pandoc Markdown, sorted by file name)
    images/          {ID}-cNN-slug.webp + credits.json
    images.plan.json {figure name: [openverse queries, optionally "query|source1,source2"]}
    SOURCE-LOG.md    facts checked, corrections to the draft, remaining [VERIFY] items
    build/           generated: {ID}.md, {ID}.epub, {ID}.pdf (git-ignored)
```

## Adding the next handbook (06, 07 ...)
1. `pandoc draft.docx -t markdown-simple_tables-multiline_tables-grid_tables+pipe_tables+fenced_divs+bracketed_spans --wrap=none --lua-filter _tools/docx2naara.lua` turns the owner's callout tables into `:::` divs; split into part and chapter files.
2. Correct and expand to a finished book (CLAUDE.md rules): re-check every fact on official sources, finish every promised part, add country-specific advice, worked examples, checklists, Source Log.
3. Plan figures in `images.plan.json`, then `python3 _tools/commons.py batch {ID}/images.plan.json /tmp/sheets` (contact sheets plus candidate json), LOOK at each sheet, and `python3 _tools/commons.py pick /tmp/sheets {name} {index} --crop 3:2`. Captions must not claim a place the photo does not show.
4. `python3 _tools/build.py {ID} --install` writes `database/seeders/handbooks/{ID}.epub` and `.pdf` (WebP in the EPUB, JPEG in the PDF, a Photo credits page from credits.json).
5. Update `database/seeders/handbooks/{ID}.json` (description, last_verified_on) and PROGRESS.md.

## Markdown conventions
`#` = Part (`{.part number="I" sub="..."}`), `##` = chapter (`{.chapter number="4"}`) or front matter (`{.front}`), `###` = section. Callouts are fenced divs: `.verify`, `.example title="..."`, `.plain title="..."`, `.checklist title="..."` (bullet list), `.insight`, `.next`, `.warn`, `.src`. Unconfirmed facts: `[VERIFY: ...]{.v}`. Figures: `![Caption](images/{ID}-cNN-slug.webp)`. Dollar signs in prose are written `US\$`.

## Image sources and limits
Openverse (CC0, public domain, CC BY, CC BY-SA only; sources such as geographorguk, stocksnap, wordpress, woc_tech) and Wikimedia Commons (rate limited on shared egress; `SRC=commons` and `APIGAP=10` to use it). WebP about 900 px wide, quality 70, at most 70 KB (hard cap 120 KB).

## Packager
`python3 _tools/packager.py backlog|scaffold ID|check ID` plans, scaffolds and gates a book. Plan and resume order: `docs/HANDBOOK-PACKAGER-BLUEPRINT.md`.
