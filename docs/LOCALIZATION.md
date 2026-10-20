# Localization (i18n)

Status as of 2026-10-03. 14 locales ship; 9 of them are **Beta** (AI-drafted, awaiting a native reviewer).

## What a visitor sees

- A header chip (`EN`, `FR`, `AR` ...) on the member dashboard, admin, marketing, help centre and auth layouts (`<x-lang-switcher />`).
- Clicking it opens the platform's one modal engine (`x-ui.modal`, event `open-modal` with `{name}`) listing every language by its native name. Beta languages carry a "Beta" tag.
- The choice is stored in the session, a one-year cookie (so guests keep it) and, when signed in, `users.language`. `POST /locale` (`LocaleController`, throttled 30/min) validates the code and falls back to English.
- Arabic flips the page: `SetLocale` shares `$htmlDir`, and `<html dir="rtl">` is set once per request.

## Resolution order (`App\Support\Locale::resolve`)

session -> `users.language` -> cookie -> country guess (stable, non-beta locales only) -> `config('app.locale')`.

Nigeria no longer maps to Hausa: a beta locale is never chosen automatically.

## Locales

| Code | Language | Status |
|---|---|---|
| en | English | source |
| fr, ar, sw | French, Arabic (RTL), Swahili | curated |
| pt, es, de, hi, zh | Portuguese, Spanish, German, Hindi, Chinese (Simplified) | Beta |
| ha, yo, ig, zu, am | Hausa, Yoruba, Igbo, Zulu, Amharic | Beta, reduced scope |

Reduced scope (ha/yo/ig/zu/am) means the payout/rail screens (export lines 408-584) and the long architecture/diagnosis modal HTML (709-763) fall back to English. hi/zh/ar/sw also skip 709-763.

## Where strings live

- `lang/{locale}/*.php` - Laravel groups (`common`, `nav`, account, checkout, numbers, messages ...). `lang/en` is the source of truth.
- `lang/{locale}/site.php` - marketing copy keyed by SiteContent key (`home.hero.title` ...). `SiteContent` overlays it **under** admin edits: an admin override that differs from the English default always wins; one identical to the default is treated as "not customised" and the translation shows.
- Bottom nav / More sheet labels go through `BottomNav::localize` (`nav.*`).

## Editing marketing copy in the Site Editor

- Text you never touched keeps its curated translation in every language.
- Text you edit is shown in English to every visitor **until it is translated**. Saving a page queues `TranslateSiteCopyJob`, which translates only the fields you changed into every shipped language through Claude (needs an Anthropic key under Provider keys; no key means English fallback, nothing breaks). "Translate edits" in the editor re-runs it on demand.
- Each translation is stored (`site.tr.{page}`) with a fingerprint of the English it came from. Edit the English again and the old translation goes inert at once, so a visitor never sees a stale line; the job refreshes it.
- Links, colours, sizes and numbers are never sent for translation, brand names are protected, and a malformed reply is discarded.
- Not covered: blog posts, DB-driven FAQ rows, footer/nav links (`SiteChrome`) and legal pages are separate content stores.

## Tooling

```
php artisan i18n:export --out=source.json            # flat key => English, stable order
# translator returns numbered lines  "N|text"  (N = position in export order)
python3 scripts/i18n_lines_to_json.py source.json xx.txt xx.json
php artisan i18n:import xx xx.json                    # writes lang/xx/*.php
```

`i18n:import` skips files whose content did not change and treats values equal to English as untranslated, so re-importing never churns reviewed files. Keep `:placeholders`, `{n}` tokens and `{1} .. |[2,*] ..` plural forms intact (`LocaleTest` enforces placeholder parity and that translated HTML keeps the same tags).

Gotcha: a translated string must not begin with `<` followed by a letter - the tag-parity check reads it as an HTML tag. Write "Under 3 min", not "<3 min".

## Known gaps

- Many converted skin dashboard pages still hard-code English; only the extracted groups are translated.
- Floating-nav labels, `SiteChrome`/footer links, blog posts and other database content are not translated.
- Beta languages need native review before the Beta tag is removed (`beta` flag in `Locale::SUPPORTED`).
