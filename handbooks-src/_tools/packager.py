#!/usr/bin/env python3
"""Handbook packager (MASTER ONLY): plan, scaffold and gate every handbook the same way.

  packager.py backlog [--md]       every title in database/seeders/handbooks with status, slot template and research brief
  packager.py scaffold ID          create handbooks-src/ID/ (meta, manuscript skeleton, SOURCE-LOG research checklist, figure plan)
  packager.py check ID             quality gates (words, [VERIFY] count, figures exist and are light, credits, imprint, source log)
"""
import argparse, glob, json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SEED = f'{ROOT}/database/seeders/handbooks'
SRC = f'{ROOT}/handbooks-src'

SLOTS = {
    'S1': ('Land and Live', 'Flagship: before you go, first 30 days, money, phone, housing, work, health, safety, long-run settling',
           ['Before you go', 'Your first 30 days', 'Money, phone and paperwork', 'Living well and staying legal', 'Toolkit']),
    'S2': ('Visa and immigration routes', 'Every route with fees, income and savings thresholds, processing times, refusal reasons, appeals, scams',
           ['How the system works', 'Routes one by one', 'Applying without mistakes', 'Dependants, extensions and settlement', 'Refusals, appeals and scams', 'Toolkit']),
    'S3': ('Work and earn', 'Rights, pay and tax, how to find work, CVs and interviews, freelancing, scams, switching status',
           ['Work rights and the job market', 'Finding work', 'Pay, tax and contracts', 'Your rights and problems', 'Growing, freelancing, switching', 'Toolkit']),
    'S4': ('Housing and settling in', 'Where to live, real rents by city, contracts, deposits, tenant rights, scams, utilities, moving out',
           ['Choosing where to live', 'Finding and viewing', 'Contracts, deposits and rights', 'Living there (bills, repairs, neighbours)', 'Moving out', 'Toolkit']),
    'S5': ('Study', 'Choosing a course, applying, funding, visas, arriving, campus life, part-time work, graduate routes',
           ['Is it right for you', 'Applying and funding', 'Visa and arrival', 'Living as a student', 'Working and after graduation', 'Toolkit']),
    'S6': ('Culture and communication', 'The unwritten rules: greetings, etiquette, language, business culture, taboos, humour, mistakes outsiders make',
           ['Big picture', 'Everyday etiquette', 'Language and communication', 'Work and business culture', 'Mistakes and fixes', 'Toolkit']),
    'S7': ('Explorer (travel)', 'Itineraries, regions, transport, costs by budget, food, seasons, safety, etiquette, connectivity (eSIM)',
           ['Plan the trip', 'Regions and routes', 'Getting around', 'Eating, sleeping, money', 'Safety and etiquette', 'Toolkit']),
    'S8': ('Business and money', 'Setting up, licences, banking, tax, suppliers, trade shows, contracts, fraud, local money habits',
           ['Landscape', 'Setting up', 'Money and tax', 'Operating and selling', 'Risks and fraud', 'Toolkit']),
    'S9': ('Safety and special trips', 'Risk map, medical or specialist trips, what to verify, insurance, scams, emergencies, aftercare',
           ['Risk map', 'Preparing', 'On the ground', 'Emergencies', 'Aftercare and scams', 'Toolkit']),
}
OFFICIAL = {  # where the facts live; the packager lists these as the research starting points
    'GB': 'gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance',
    'US': 'travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov',
    'CA': 'canada.ca (IRCC), CRA, CMHC, provincial tenancy boards',
    'AU': 'immi.homeaffairs.gov.au, ATO, studyaustralia.gov.au, Fair Work Ombudsman',
    'DE': 'make-it-in-germany.com, auswaertiges-amt.de, DAAD, BAMF, anabin',
    'AE': 'u.ae, ICP, MOHRE, RERA/Dubai Land Department, free-zone authority sites',
    'FR': 'service-public.fr, france-visas.gouv.fr, campusfrance.org, CAF',
    'ES': 'exteriores.gob.es, inclusion.gob.es, Spanish tourism board',
    'CN': 'nia.gov.cn, studyinchina.csc.edu.cn, embassy visa pages, Canton Fair site',
    'JP': 'mofa.go.jp, isa.go.jp, JASSO, JNTO',
    'TR': 'mfa.gov.tr, e-Visa, Ministry of Health medical tourism pages',
    'ZA': 'dha.gov.za, SARS, SAPS crime stats, Dept of Tourism',
    'EG': 'visa2egypt.gov.eg, Ministry of Tourism',
    'GH': 'ghana.gov.gh, GIS, GRA, Bank of Ghana',
}
CHECKLIST = """## Research checklist (tick each with a source and date)
- [ ] Every fee, threshold and processing time checked on the official page above (record the page and the date).
- [ ] Every rule that changed in the last 12 months (what changed, effective date) in a 'What changed recently' table.
- [ ] Costs table in local currency and USD with the rate date; one worked example marked invented.
- [ ] Africa-origin reader: documents, bank rules, scams, visa refusal patterns, remittance and phone-number notes.
- [ ] Naara product hooks used honestly (eSIM before landing, virtual number for OTP) and nothing promised that the platform does not do.
- [ ] Every unconfirmed fact marked [VERIFY: what to check] and listed under 'Remaining [VERIFY]' below.
- [ ] No cost or margin data from the platform appears anywhere in the text.

## Facts checked
(table: fact, value, source URL, date checked)

## Corrections to the owner's draft
(what was wrong or stale, and the fix)

## Remaining [VERIFY]
"""


def drafts():
    out = {}
    for f in sorted(glob.glob(f'{SEED}/*.json')):
        j = json.load(open(f)); i = os.path.basename(f)[:-5]
        j['id'] = i
        j['finished'] = os.path.exists(f'{SEED}/{i}.epub') and os.path.exists(f'{SEED}/{i}.pdf')
        out[i] = j
    return out


def cmd_backlog(a):
    rows = drafts().values()
    done = sum(1 for r in rows if r['finished'])
    lines = [f'{done} finished, {len(rows) - done} draft, {len(rows)} total\n', '| ID | Title | Slot | Status | Official sources |', '|---|---|---|---|---|']
    for r in rows:
        s = SLOTS.get(r['series_slot'], ('?', '', []))
        lines.append(f"| {r['id']} | {r['title']} | {r['series_slot']} {s[0]} | {'FINISHED' if r['finished'] else 'draft'} | {OFFICIAL.get(r['country_code'], 'find the national immigration, tax and housing authority')} |")
    print('\n'.join(lines))


def cmd_scaffold(a):
    r = drafts().get(a.id) or sys.exit(f'{a.id} is not in {SEED}')
    d = f'{SRC}/{a.id}'
    if os.path.exists(d): sys.exit(f'{d} exists; refusing to overwrite')
    name, brief, parts = SLOTS[r['series_slot']]
    for sub in ('manuscript', 'images'): os.makedirs(f'{d}/{sub}')
    json.dump({'id': a.id, 'title': r['title'], 'subtitle': r.get('subtitle', ''), 'short_title': r['title'].split(':')[0], 'slot_line': f"Series slot {r['series_slot']} · {name}", 'language': r.get('language', 'en'), 'as_of': '', 'sort': 0}, open(f'{d}/meta.json', 'w'), indent=2)
    open(f'{d}/manuscript/00-front.md', 'w').write(f"""## Copyright and imprint {{.front}}

Naara Handbook Series · {a.id}. © 2026 Naara Connect Technologies Limited. Published by Naara Family (Naara Connect Technologies Limited) · naara.app.

## Disclaimer and as-of statement {{.front}}

(general information only; not legal, immigration, tax or financial advice; facts current to the as-of date; unconfirmed items marked VERIFY)

## How to use this book {{.front}}

(situation to chapter map; how the callouts work)
""")
    n = 1
    for i, p in enumerate(parts, 1):
        open(f'{d}/manuscript/{10 + i * 10}-part{i}.md', 'w').write(f'# {p} {{.part number="{["I","II","III","IV","V","VI"][i-1]}" sub="TODO one line"}}\n\n## TODO chapter {{.chapter number="{n}"}}\n\nWho this is for, what you will know after it, the facts, the traps, a worked example, a checklist, next steps.\n')
        n += 1
    json.dump({'_note': 'at most one figure per part plus the cover moments that matter; look at each sheet before picking; captions must match the photo'}, open(f'{d}/images.plan.json', 'w'), indent=1)
    open(f'{d}/SOURCE-LOG.md', 'w').write(f"# Source log: {r['title']}\n\nSlot brief: {brief}\n\nOfficial sources to start from: {OFFICIAL.get(r['country_code'], 'find them')}\n\n{CHECKLIST}")
    print('scaffolded', d)


def cmd_check(a):
    d = f'{SRC}/{a.id}'
    txt = ''.join(open(f).read() for f in sorted(glob.glob(f'{d}/manuscript/*.md')))
    words = len(re.findall(r'\w+', txt))
    figs = re.findall(r'!\[[^\]]*\]\((images/[^)]+)\)', txt)
    miss = [f for f in figs if not os.path.exists(f'{d}/{f}')]
    heavy = [f for f in figs if os.path.exists(f'{d}/{f}') and os.path.getsize(f'{d}/{f}') > 120 * 1024]
    credits = json.load(open(f'{d}/images/credits.json')) if os.path.exists(f'{d}/images/credits.json') else {}
    credited = len(credits) if isinstance(credits, (list, dict)) else 0
    chap = len(re.findall(r'\{\.chapter', txt))
    gates = [
        ('words >= 15000', words >= 15000, words), ('chapters >= 12', chap >= 12, chap),
        ('imprint Naara.app', 'naara.app' in txt.lower() and 'Naara Connect Technologies Limited' in txt, ''),
        ('figures 4..18 (only where it matters)', 4 <= len(figs) <= 18, len(figs)), ('no missing figure files', not miss, miss),
        ('no figure over 120 KB', not heavy, heavy), ('every figure credited', credited >= len(figs), f'{credited}/{len(figs)}'),
        ('SOURCE-LOG.md', os.path.exists(f'{d}/SOURCE-LOG.md'), ''), ('no TODO left', 'TODO' not in txt, txt.count('TODO')),
        ('as_of set', bool(json.load(open(f'{d}/meta.json')).get('as_of')), ''),
        ('VERIFY items listed', txt.count('VERIFY') == 0 or 'VERIFY' in open(f'{d}/SOURCE-LOG.md').read(), txt.count('VERIFY')),
    ]
    bad = 0
    for n, ok, v in gates:
        print(('PASS ' if ok else 'FAIL ') + n, v if v != '' else ''); bad += not ok
    sys.exit(1 if bad else 0)


if __name__ == '__main__':
    ap = argparse.ArgumentParser(); sp = ap.add_subparsers(dest='c', required=True)
    sp.add_parser('backlog').add_argument('--md', action='store_true'); sp.add_parser('scaffold').add_argument('id'); sp.add_parser('check').add_argument('id')
    a = ap.parse_args(); {'backlog': cmd_backlog, 'scaffold': cmd_scaffold, 'check': cmd_check}[a.c](a)
