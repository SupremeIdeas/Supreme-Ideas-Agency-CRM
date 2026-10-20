# Handbook packager blueprint (master only)

Purpose: when the handbook work resumes, every remaining title is researched, written, imaged, built and published the same way, with real facts and data, without rediscovering the process. Standing rules live in `CLAUDE.md` (Handbook editorial standard); the pipeline lives in `handbooks-src/README.md`; this document is the plan and the checklist.

Status at 2026-10-19: 5 titles finished (GBR-01 to GBR-05), 53 drafts waiting. Pause here; resume from section 6.

## 1. The packager in one line
`handbooks-src/_tools/packager.py` plans, scaffolds and gates a book; `build.py` builds the EPUB and PDF; the importer (`handbooks:import --missing-only --publish --refresh-files`, hourly) puts finished files on the store; Admin > Handbooks downloads any file on record.

```
python3 handbooks-src/_tools/packager.py backlog          # every title, slot, status, official sources
python3 handbooks-src/_tools/packager.py scaffold ARE-01  # folder, meta, manuscript skeleton, SOURCE-LOG research checklist
python3 handbooks-src/_tools/packager.py check ARE-01     # quality gates (must all PASS)
python3 handbooks-src/_tools/build.py ARE-01 --install    # EPUB + PDF into database/seeders/handbooks/
```

## 2. Stages (each has an exit gate)
1. **Brief** (15 min): `scaffold`, read the slot template (section 3), list the official sources in the SOURCE-LOG. Gate: SOURCE-LOG has the checklist and source list.
2. **Research** (the real work): every fee, threshold, time and rule from the official page, with URL and date. Record in the SOURCE-LOG fact table. Gate: no number in the manuscript without a row, or a `[VERIFY: ...]`.
3. **Write**: owner draft (if any) corrected first, then expanded: worked examples (marked invented), costs in local currency and USD with the rate date, Africa-origin notes, scams, checklists, what changed recently, next steps. Gate: 12+ chapters, 15,000+ words, no TODO.
4. **Images, light-touch**: 6 to 14 figures per book, only where a picture teaches something (the place, the document, the process, the money). At most one per part plus a few key moments. If no honest photo is found in one pass, drop the figure; do not spend more time. Caption describes what the photo shows. WebP 900 px q70 (cap 120 KB), `credits.json` entry for each.
5. **Build**: `build.py ID --install`; render and look at the cover, a table page, a figure page and the TOC. The cover and running header use the wordmark only (it already contains the N, so never add the icon next to it).
6. **Gate and publish**: `packager.py check ID` all PASS, handbook tests pass, `database/seeders/handbooks/ID.json` description and `last_verified_on` updated, PROGRESS.md entry. The importer publishes it when the checklist passes.

## 3. Slot templates (what every book of that slot must cover)
| Slot | Kind | Must cover |
|---|---|---|
| S1 | Land and Live | before you go, first 30 days, money, phone and eSIM, housing, work, health, safety, long-run settling |
| S2 | Visa and immigration routes | each route with fee, income or savings threshold, processing time, refusal reasons, appeals, scams |
| S3 | Work and earn | rights, pay and tax, finding work, CVs and interviews, freelancing, scams, switching status |
| S4 | Housing and settling in | real rents by city, finding and viewing, contracts, deposits, tenant rights, scams, utilities, moving out |
| S5 | Study | choosing a course, applying, funding, visa, arrival, campus life, part-time work limits, graduate routes |
| S6 | Culture and communication | etiquette, language, business culture, taboos, mistakes outsiders make |
| S7 | Explorer (travel) | itineraries, regions, transport, budget tiers, food, seasons, safety, connectivity |
| S8 | Business and money | setting up, licences, banking, tax, suppliers, trade shows, contracts, fraud |
| S9 | Safety and special trips | risk map, specialist or medical trips, what to verify, insurance, emergencies, aftercare |

## 4. Facts and data standard
- Official sources first (government, regulator, statistics office, university or hospital pages); second-hand blogs only to find the official page.
- Every fee, threshold and date has a source and a check date; anything unconfirmed is `[VERIFY: what to check]` and is listed in the SOURCE-LOG.
- Prices in local currency and USD with the rate date; worked examples are labelled invented.
- Recently changed rules get a dated 'What changed recently' table.
- Never include platform cost or margin data. Naara products (eSIM, virtual numbers) are mentioned only where they truly help and only as the platform really works.
- Open question carried from GBR-04/05: confirm the "Naara Data" and virtual-number wording with the owner.

## 5. Quality gates (`packager.py check`)
15,000+ words; 12+ chapters; imprint "Naara.app" and "Naara Connect Technologies Limited"; 4 to 18 figures; no missing or over-120 KB figure files; every figure credited; SOURCE-LOG present with the VERIFY list; no TODO; as-of date set. Plus: handbook tests green, EPUB opens (`EpubInspector`), the PDF is readable by the download stamper (the build writes it uncompressed on purpose), PDF pages looked at.

## 6. Backlog and resume plan
Resume in this order (highest value to the platform first): S1 flagships (17), then S2 visa guides (8), S5 study (7), S4 housing (3), S3 work (5), then S7/S8/S9/S6. One book per working session; do not start a book without its SOURCE-LOG research rows filled for at least the costs and the rules.

Owner input wanted per book: the draft DOCX if one exists, and any country facts only the owner knows (local partners, real examples).

### Full backlog (generated)
5 finished, 53 draft, 58 total

| ID | Title | Slot | Status | Official sources |
|---|---|---|---|---|
| ARE-01 | Land and Live in the UAE | S1 Land and Live | draft | u.ae, ICP, MOHRE, RERA/Dubai Land Department, free-zone authority sites |
| ARE-02 | Work and Freelance in the UAE | S3 Work and earn | draft | u.ae, ICP, MOHRE, RERA/Dubai Land Department, free-zone authority sites |
| ARE-03 | Start a Business in the UAE: Free Zones, Licences and Banking | S8 Business and money | draft | u.ae, ICP, MOHRE, RERA/Dubai Land Department, free-zone authority sites |
| ARE-04 | Renting in Dubai and Abu Dhabi: Areas, Contracts and Costs | S4 Housing and settling in | draft | u.ae, ICP, MOHRE, RERA/Dubai Land Department, free-zone authority sites |
| AUS-01 | Land and Live in Australia | S1 Land and Live | draft | immi.homeaffairs.gov.au, ATO, studyaustralia.gov.au, Fair Work Ombudsman |
| AUS-02 | Australia Visa Guide: Study, Work and Skilled Routes | S2 Visa and immigration routes | draft | immi.homeaffairs.gov.au, ATO, studyaustralia.gov.au, Fair Work Ombudsman |
| AUS-03 | Study in Australia: Universities, Costs and Part-Time Work | S5 Study | draft | immi.homeaffairs.gov.au, ATO, studyaustralia.gov.au, Fair Work Ombudsman |
| CAN-01 | Land and Live in Canada | S1 Land and Live | draft | canada.ca (IRCC), CRA, CMHC, provincial tenancy boards |
| CAN-02 | Canada Immigration Pathways: Study, Work and PR | S2 Visa and immigration routes | draft | canada.ca (IRCC), CRA, CMHC, provincial tenancy boards |
| CAN-03 | Study in Canada: Colleges, Permits and Work Rules | S5 Study | draft | canada.ca (IRCC), CRA, CMHC, provincial tenancy boards |
| CAN-04 | Housing and Settling In Canada: Cities, Rent and Winter | S4 Housing and settling in | draft | canada.ca (IRCC), CRA, CMHC, provincial tenancy boards |
| CHN-01 | Land and Live in China | S1 Land and Live | draft | nia.gov.cn, studyinchina.csc.edu.cn, embassy visa pages, Canton Fair site |
| CHN-02 | Trade Trip China: Sourcing, Suppliers and Canton Fair | S8 Business and money | draft | nia.gov.cn, studyinchina.csc.edu.cn, embassy visa pages, Canton Fair site |
| CHN-03 | Study in China: Scholarships and Campus Life | S5 Study | draft | nia.gov.cn, studyinchina.csc.edu.cn, embassy visa pages, Canton Fair site |
| DEU-01 | Land and Live in Germany | S1 Land and Live | draft | make-it-in-germany.com, auswaertiges-amt.de, DAAD, BAMF, anabin |
| DEU-02 | Germany Visa Guide: Opportunity Card, Blue Card and Beyond | S2 Visa and immigration routes | draft | make-it-in-germany.com, auswaertiges-amt.de, DAAD, BAMF, anabin |
| DEU-03 | Study in Germany: Low-Cost Degrees and Student Life | S5 Study | draft | make-it-in-germany.com, auswaertiges-amt.de, DAAD, BAMF, anabin |
| DEU-04 | Work and Careers in Germany | S3 Work and earn | draft | make-it-in-germany.com, auswaertiges-amt.de, DAAD, BAMF, anabin |
| EGY-01 | Land and Live in Egypt | S1 Land and Live | draft | visa2egypt.gov.eg, Ministry of Tourism |
| EGY-02 | Egypt Explorer: Cairo, Luxor and the Red Sea | S7 Explorer (travel) | draft | visa2egypt.gov.eg, Ministry of Tourism |
| ESP-01 | Land and Live in Spain | S1 Land and Live | draft | exteriores.gob.es, inclusion.gob.es, Spanish tourism board |
| ESP-02 | Spain Explorer: Cities, Beaches and Tapas Trails | S7 Explorer (travel) | draft | exteriores.gob.es, inclusion.gob.es, Spanish tourism board |
| ESP-03 | Spain Visa Guide: Study, Work and Digital Nomad Routes | S2 Visa and immigration routes | draft | exteriores.gob.es, inclusion.gob.es, Spanish tourism board |
| FRA-01 | Land and Live in France | S1 Land and Live | draft | service-public.fr, france-visas.gouv.fr, campusfrance.org, CAF |
| FRA-02 | France Explorer: Cities, Food and Itineraries | S7 Explorer (travel) | draft | service-public.fr, france-visas.gouv.fr, campusfrance.org, CAF |
| FRA-03 | Study in France: Universities, Grandes Écoles and Life | S5 Study | draft | service-public.fr, france-visas.gouv.fr, campusfrance.org, CAF |
| GBR-01 | Land and Live in the UK | S1 Land and Live | FINISHED | gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance |
| GBR-02 | UK Visa Routes Decoded | S2 Visa and immigration routes | FINISHED | gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance |
| GBR-03 | Work and Earn in Britain | S3 Work and earn | FINISHED | gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance |
| GBR-04 | Study in the UK: The Student Playbook | S5 Study | FINISHED | gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance |
| GBR-05 | Renting in the UK: Homes, Rights and Scams | S4 Housing and settling in | FINISHED | gov.uk, UKVI, Home Office immigration rules, ONS, Shelter/Citizens Advice, UKCISA, Student Finance |
| GHA-01 | Land and Live in Ghana | S1 Land and Live | draft | ghana.gov.gh, GIS, GRA, Bank of Ghana |
| GHA-02 | Ghana Explorer: Accra, Cape Coast and Beyond | S7 Explorer (travel) | draft | ghana.gov.gh, GIS, GRA, Bank of Ghana |
| GHA-03 | Ghana Culture, People and Communication | S6 Culture and communication | draft | ghana.gov.gh, GIS, GRA, Bank of Ghana |
| GHA-04 | Money and Business in Ghana | S8 Business and money | draft | ghana.gov.gh, GIS, GRA, Bank of Ghana |
| ITA-01 | Land and Live in Italy | S1 Land and Live | draft | find the national immigration, tax and housing authority |
| ITA-02 | Italy Explorer: Rome, Venice, Florence and Beyond | S7 Explorer (travel) | draft | find the national immigration, tax and housing authority |
| ITA-03 | Italy Visas and Residence Permits Explained | S2 Visa and immigration routes | draft | find the national immigration, tax and housing authority |
| JPN-01 | Land and Live in Japan | S1 Land and Live | draft | mofa.go.jp, isa.go.jp, JASSO, JNTO |
| JPN-02 | Japan Explorer: Tokyo, Kyoto and Beyond | S7 Explorer (travel) | draft | mofa.go.jp, isa.go.jp, JASSO, JNTO |
| JPN-03 | Japan Culture and Etiquette: The Unwritten Rules | S6 Culture and communication | draft | mofa.go.jp, isa.go.jp, JASSO, JNTO |
| KEN-01 | Land and Live in Kenya | S1 Land and Live | draft | find the national immigration, tax and housing authority |
| KEN-02 | Kenya Explorer: Nairobi, Safari and the Coast | S7 Explorer (travel) | draft | find the national immigration, tax and housing authority |
| KEN-03 | Business in Kenya: Mobile Money, Startups and Trade | S8 Business and money | draft | find the national immigration, tax and housing authority |
| MEX-01 | Land and Live in Mexico | S1 Land and Live | draft | find the national immigration, tax and housing authority |
| MEX-02 | Mexico Explorer: Cities, Beaches and Street Food | S7 Explorer (travel) | draft | find the national immigration, tax and housing authority |
| TUR-01 | Land and Live in Türkiye | S1 Land and Live | draft | mfa.gov.tr, e-Visa, Ministry of Health medical tourism pages |
| TUR-02 | Türkiye Explorer: Istanbul to Cappadocia | S7 Explorer (travel) | draft | mfa.gov.tr, e-Visa, Ministry of Health medical tourism pages |
| TUR-03 | Medical Trips to Türkiye: Hospitals, Clinics and Safety | S9 Safety and special trips | draft | mfa.gov.tr, e-Visa, Ministry of Health medical tourism pages |
| USA-01 | Land and Live in the USA | S1 Land and Live | draft | travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov |
| USA-02 | US Visas and Green Card Pathways Explained | S2 Visa and immigration routes | draft | travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov |
| USA-03 | Work and Hustle in America | S3 Work and earn | draft | travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov |
| USA-04 | Study in the USA: From Admission to Campus Life | S5 Study | draft | travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov |
| USA-05 | Money and Business in America | S8 Business and money | draft | travel.state.gov, USCIS, IRS, HUD, studyinthestates.dhs.gov |
| ZAF-01 | Land and Live in South Africa | S1 Land and Live | draft | dha.gov.za, SARS, SAPS crime stats, Dept of Tourism |
| ZAF-02 | South Africa Visas and Permits Made Simple | S2 Visa and immigration routes | draft | dha.gov.za, SARS, SAPS crime stats, Dept of Tourism |
| ZAF-03 | Work and Business in South Africa | S3 Work and earn | draft | dha.gov.za, SARS, SAPS crime stats, Dept of Tourism |
| ZAF-04 | Stay Safe in South Africa: Smart Travel and Living | S9 Safety and special trips | draft | dha.gov.za, SARS, SAPS crime stats, Dept of Tourism |
