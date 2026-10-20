# NAARA PROMPT 20 (rev 11) — Surface System: tokens, bento, sheets, More menu, eSIM detail + user Appearance presets

Repo: `NaaraSim-main` (snapshot `NaaraSim-Master_Platform_Sept_22.zip`)
Visual contracts (same folder, open them first). Every color, radius, spacing value and component structure is implemented there and is copy-ready. If this document and an HTML file disagree on a *visual* value, the HTML wins. If they disagree on *behavior or data*, this document wins.

| File | Role |
|---|---|
| `naara-surface-system-wireframe-v3-final.html` | **The default skin ("Naara Surface") and the canonical token values for the whole platform.** Replaces `naara-surface-reference.html` as the visual contract for §2–§5. |
| `naara-dashboard-system-v4-dotgrid-core13.html` … `-v14-paper-core13.html` | **Eleven editions of the navigable whole-dashboard engine (Part C, Part D), each carrying the CORE 13 skins** (Naara Surface, Calm, Vivid, Ledger, Daybreak, Paper, Clay, Dot-grid, Aurora, Glass, Halo, Neo, Noir). 30 pages wired together, 6 accent presets plus custom accent, 6 dials, light/dark/system, per-account isolation, user Appearance and Admin → Appearance. Only the platform-default skin, demo accounts and first-open mode differ between editions. |
| `naara-dashboard-system-v15-new22-skins-lab.html` | **V15 · New Skins Lab: the 22 skins added in rev 6 to rev 11** plus Naara Surface as the base: Adire, Blueprint, Clarity, Neon Lagos, Passport, Golden Hour, Vault, Boarding Pass, Chipset, Wavelength, Airmail, Handset, Prism, Receipt, Nebula, Harmattan, Ankara, Danfo, Aso-oke, Tide, Canopy, Pop. Same engine and pages. It opens on the Appearance gallery. Extra demo controls: "Active eSIM" (Passport) and "Time" (Golden Hour). |

**The two file families are complementary, not duplicates.** The full skin catalog (§15.1) is the union: 13 skins in the core editions plus 22 in V15 = 35. Skin CSS for the core 13 comes from `naara-dashboard-system-v9-surface-core13.html`; skin CSS for the 22 new skins comes from V15. Shared engine code (tokens, components, dials, custom accent, pages) is identical in both; where it differs, treat V9-core13 as the source.

| Edition | File | Platform default skin | Opens in |
|---|---|---|---|
| V4 | `naara-dashboard-system-v4-dotgrid-core13.html` | Dot-grid | dark |
| V5 | `naara-dashboard-system-v5-aurora-core13.html` | Aurora | dark |
| V6 | `naara-dashboard-system-v6-glass-core13.html` | Glass | dark |
| V7 | `naara-dashboard-system-v7-ledger-core13.html` | Ledger | light |
| V8 | `naara-dashboard-system-v8-halo-core13.html` | Halo | dark |
| V9 | `naara-dashboard-system-v9-surface-core13.html` | **Naara Surface (production default)** | dark |
| V10 | `naara-dashboard-system-v10-daybreak-core13.html` | Daybreak | light |
| V11 | `naara-dashboard-system-v11-noir-core13.html` | Noir | dark |
| V12 | `naara-dashboard-system-v12-clay-core13.html` | Clay | light |
| V13 | `naara-dashboard-system-v13-neo-core13.html` | Neo | light |
| V14 | `naara-dashboard-system-v14-paper-core13.html` | Paper | light |

The top bar of every edition (core and lab) has a Page dropdown (all 30 pages), Skin and Accent dropdowns, Dark/Light, Flows (Modal / Dedicated page) and an Account switcher (Frank, Amaka, Guest) to demonstrate isolation. All core editions share identical CSS and behavior, so **V9-core13 is the single source for engine CSS, component markup and the core 13 skins**; the others exist only for comparison. Skins are scoped with `@scope` so previews in the picker cannot inherit the active skin's rules (§15.3). Where the wireframes disagree with Part D (codebase facts), Part D wins. 
| `…-v2-reference.html` and `…-wireframe.html` (V1) | History only. Their looks live on as the **Vivid** and **Calm** skins, re-implemented as token deltas on V3 structure (§15). Do not build from them directly. |

### Revision 2 notice: what changed versus the first version of this document
1. **V3 is the default skin.** Token values in §2.3 are superseded by the table in §14. Everything else in §2 (mode-flipping semantic tokens, theme-safety, Tailwind wiring) still applies.
2. **Open decision §12.3 is resolved:** the More menu ships as the V3 inset grouped list (hierarchy-preserving rows). The solid-accent-row look exists only as the *Vivid* skin.
3. **The CTA contrast correction in §2.4 is already applied in V3.** Keep it.
4. **New Part B (§13–§22):** users can choose a *Skin* (structure/depth style), an *Accent* (colour family) and a *Mode* in their account; admins govern what is available. All per-account, isolated.

### Revision 3 notice
5. **Skin catalog grows from 3 to 8:** Naara Surface, Calm, Vivid, **Ledger**, **Dot-grid**, **Aurora**, **Glass**, **Halo** (§15). Dot-grid and Aurora are the V4/V5 ornament layers promoted to proper skins. Ledger, Glass and Halo are new (V7, V6, V8).
6. Skins are grouped **Core / Textured / Material** in the picker. Combinations to test: 35 skins × 7 accents (6 presets + custom) × 2 modes = 490.
7. Skin CSS is scoped per skin with `@scope` (§15.3) so previews nest safely.
8. Reduced-motion, reduced-transparency and no-`backdrop-filter` fallbacks are now required (§15.4).

### Revision 11 notice
23. **Seven more skins built** (35 total): Harmattan, Ankara, Danfo, Aso-oke (new group **Culture**), Tide, Canopy (Atmosphere), Pop (new group **Playful**). Matrix: 35 × 7 × 2 = 490.

### Revision 10 notice
22. **Eight more skins built** (28 total): Boarding Pass, Chipset, Wavelength, Airmail, Handset (new group **Telecom**), Prism (Material), Receipt (Themed), Nebula (new group **Atmosphere**). Matrix: 28 × 7 × 2 = 392. Free/pro assignment is **not** part of this document; it lives in the separate skin-access blueprint (see `SKIN-ACCESS-RULES-NOTES.md`).

### Revision 9 notice
21. **Two more skins built: Golden Hour and Vault** (20 total). Matrix: 20 × 7 × 2 = 280. Golden Hour adds a time-of-day contract (§45); Vault adds the "foil is not money-gold" rule (§46).

### Revision 8 notice
20. **Two more skins built: Neon Lagos and Passport** (18 total). Matrix: 18 × 7 × 2 = 252. Passport introduces a data dependency (active eSIM country) specified in §44.

### Revision 7 notice
19. **Three more skins built: Adire, Blueprint, Clarity** (16 total; groups Themed and Access added). Matrix: 35 skins × 7 accents × 2 modes = 224. All three follow the §42 contrast contract.

### Revision 6 notice
17. **Vivid text bug fixed and generalized into a contrast contract (Part E, §42).** Found on a real phone in light mode: text, pills and tags invisible on saturated fills. Root cause and the rule that prevents it in every skin are in §42; it is mandatory for Claude Code.
18. **Skin idea pipeline** (§43) for future skins.

### Revision 5 notice (grounded in the real codebase; see Part D)
13. **Wireframes were checked against the repository** (routes, Livewire classes, views, `BottomNav`, `ThemePreset`, `CLAUDE.md`). Part D lists what matched, what did not, and every correction. The More menu in the wireframes now mirrors `BottomNav::eligibleMainItems()`.
14. **Collisions fixed:** CSS component hooks in Part B/C are renamed `ns-*` (the repo already owns `.nx-card`, `.nx-btn`, `.nx-bento`, `.nx-glass-tile`, `.nx-switch`, `.nx-toast`); `--nx-glass`/`--nx-ga` became `--nx-frost`/`--nx-frost-a` (repo owns `--nx-glass-opacity/blur`); the admin page route is `admin.user-appearance` because `admin.appearance` is the existing Splash page.
15. **Four more skins** (Noir, Clay, Neo, Paper): 13 total (§15.1). **Six personalisation dials** (§37) and a **custom accent colour with reset** (§38).
16. **Glass no longer blurs sheets:** `more-sheet.blade.php` records a hotfix (blur on a sliding sheet caused Android GPU artifacts). Skins must not put `backdrop-filter` on anything that slides.

### Revision 4 notice
9. **Whole-dashboard scope (Part C, §23–§28).** A skin must reach every user-facing and admin screen, not just the four target areas. Screen inventory, extra component hooks, rollout order and a coverage gate are specified.
10. **Admins get their own appearance, explicitly (§26).** Admin and user dashboards use the same per-account preference; platform defaults are a separate admin function.
11. **Skins 9 to 13: Daybreak** (§15.1). Test matrix is now 9 × 6 × 2 = 108.
12. **eSIM checkout** is a skinned page (§24).

---

## 0. Ground rules (read before touching anything)

1. **Do not touch the global header or the bottom nav markup.** `components/floating-nav.blade.php`, `partials/bottom-nav-item.blade.php`, `partials/floatnav-item.blade.php`, `partials/numbers-nav-item.blade.php`, and the header partials are out of scope for structure, spacing and behavior. The only permitted nav change is the token-only color pass in §9.
2. **No new provider logic.** Nothing in this prompt changes what `GetNumber::order()`, `pickOperator()`, `PollSmsOtpJob`, `PermanentNumberRouter`, Twilio/Telnyx/5sim/HeroSMS/VirtSMS services do. This is a presentation-and-admin-settings pass.
3. **Build on a new branch from an exact baseline tag**, then package with `NAARA-FEATURE-FIX-UPGRADE-PACKAGE-TEMPLATE.md` (`code_and_migrations`, `tier_requirement: null`). This is a core fix, not a Naara Pro feature: do **not** build it with `--master-only`.
4. **Do not invent data.** Every number, price, count, badge and "Active" state in the reference HTML is placeholder. Each one has a real source listed in §6. Where no real source exists, the element is hidden, not faked.
5. **Verify before you trust this document.** Line numbers and counts below come from a read of the Sept 22 snapshot. Re-check anything you're about to change.
6. Stop and ask before: dropping any column, renaming any existing translation key, or editing a file outside the list in §11.

---

## 1. Audit findings (verified in the snapshot)

### 1.1 The root cause of the "vibe-coded" look
The dark palette is not one system. It is at least four, mixed on the same screen:

| Layer | Where | Example |
|---|---|---|
| Theme-derived tokens | `resources/css/app.css` lines ~86–96 | `--brand-card-dark`, `--brand-card-border-dark`, `--brand-card-inner-dark`, `--brand-card-hover-dark` (derived from `--brand-navy` by `color-mix`) |
| Frozen navy hex literals | across `resources/views` | `#0D1B2A` ×112, `#243352` ×730, `#2D4060` ×813, `#1A2840` ×202, `#1B2A44` ×45, `#152238` ×11, `#0F1D33` ×5, `#101d33` ×13, `#0f2030` ×5 |
| White-alpha overlays | modals, rows | `dark:bg-white/5`, `dark:border-white/10` |
| Literal Tailwind palette | badges, states | `green-100/700`, `amber-*`, `red-*`, `from-fuchsia-500 to-purple-500` (bento badge fallbacks) |

Result: the modal shell is `#0D1B2A`, the rows inside it are `white/5`, the sticky footer is `#0D1B2A` again, tiles in Rent/Line are `#243352`, and the bento cards are a `--brand-card-*` gradient. Four different "dark blues" touching each other, none of which is the deep teal-navy in the reference screens.

### 1.2 Files that produce the target surfaces
| Target surface | File(s) |
|---|---|
| Numbers landing + bento | `resources/views/livewire/get-number.blade.php`, `partials/numbers-bento.blade.php`, `partials/numbers-hero.blade.php`, `app/Support/NumbersBento.php`, `app/Models/NumbersBentoCard.php` |
| Verify / Rent / Line modals | `partials/numbers-modals.blade.php` (shell for both modal and page mode), `partials/numbers-modal/{verify,rent,line}.blade.php`, `app/Livewire/GetNumber.php` |
| Country / service pickers (opened from those modals) | `livewire/country-picker.blade.php`, `livewire/service-picker.blade.php` |
| eSIM plan detail | `resources/views/livewire/catalogue.blade.php` (the `openPlan` / plan-detail block, approx. lines 20–95) and `livewire/checkout.blade.php` |
| More menu | `components/more-sheet.blade.php` (rendered by `components/app-shell.blade.php`) |
| Admin | `app/Livewire/Admin/NumbersBento.php` + `livewire/admin/numbers-bento.blade.php`, `app/Console/Commands/ImportNumbersBentoCommand.php` |

### 1.3 Modal ⇄ dedicated page switch: **it already exists. Do not rebuild it.**
Confirmed in code:
- Migration `2026_09_08_170000_add_display_mode_to_numbers_bento_cards_table.php` adds nullable `display_mode` (`modal`|`page`).
- `App\Support\NumbersBento::TOGGLABLE = ['verify','rent','line']`; `isPageMode($key)` reads the cached row (cache key `numbers.bento.v2`, 10 min, flushed on admin save).
- `resolveLink()` turns a togglable card into a `route('numbers', ['modal' => $key])` anchor when in page mode.
- `partials/numbers-modals.blade.php` renders the **same** header + `@include('partials.numbers-modal.'.$modal)` in both modes. Only the outer chrome differs. `get-number.blade.php` hides hero + bento while a page-mode flow is open.
- `Admin\NumbersBento::save()` validates `display_mode` as `required|in:modal,page` and stores it.

**Why this cannot cause duplicate provider configuration:** `display_mode` is a presentation flag on a CMS row. Both modes call the same `GetNumber` Livewire component and the same `order()` action. No provider setting, credential or queue is touched by the switch.

**What is genuinely missing (build these, small):**
1. **Bulk control.** Admin must set all three at once: a "Set all three to: Modal / Page" action (one server action, one audit log line `numbers.entry_mode_bulk`).
2. **Global default.** New `Setting` key `numbers.entry_mode_default` (`modal`|`page`, group `ui`). `isPageMode()` resolution order becomes: row `display_mode` if set → global default → `modal`. An untouched install must still behave exactly as today.
3. **Mid-flow safety test.** Add a feature test: user has an open reserved order in Verify, admin flips mode, next render keeps `$order`, `$modal`, `$service`, `$country`, `$operator` unchanged and only the chrome changes. Also assert `openModal()` still rejects names outside `['verify','rent','line']`.
4. **Stale URL guard.** `?modal=verify` must still open the flow in whichever mode is active (deep links from emails/notifications must not 404 after a mode flip).
5. **Double-submit guard check.** Read `GetNumber::order()`. If it does not already disable re-entry (wire:loading.attr=disabled exists in the views; confirm a server-side guard too), add a per-user lock or idempotency key so that a fast double tap in either mode cannot reserve two numbers. Report what you found before changing anything.

---

## 2. Token architecture

### 2.1 Design decision
Replace the `dark:` prefix pattern with **mode-flipping semantic tokens.** Tokens are defined once for light (`:root`) and once for dark (`.dark`, matching the project's existing class strategy, e.g. `.dark .nx-preview-frame` in `sections.css`). Views use `bg-nx-surface border-nx-line text-nx-text` and never write `dark:` for surface, border or text colors. This removes the whole class of "forgot the dark variant" bugs and makes the dark palette a single file.

### 2.2 Theme-safety (important: do not break white-label themes)
Themes only emit whitelisted variables through `ThemePreset::emitVars()` (`primary`, `primary_dark`, `accent`, `accent_dark`, `navy`, `action`, radii, fonts). Do not widen that whitelist for this work.

- Naara Official defaults are set as literal channel triples in `app.css` (values in §2.3).
- For every other theme, derive the same tokens from `--brand-navy` / `--brand-primary` / `--brand-accent` with `color-mix(in srgb, …)` inside an `@supports` block, exactly like the existing `--brand-card-*` derivation.
- First read `ThemePreset::styleCss()` to see how the default theme is distinguished from the others, and scope the Naara literals to the default only. Report the selector you chose.
- `ThemeTokenRemediationTest` already rejects literal Tailwind palette colors that bypass tokens. Your new code must pass it unmodified.
- Keep the old `--brand-card-*` variables defined and mapped: `--brand-card-dark: rgb(var(--nx-surface))` etc., so any file you don't migrate in this pass keeps working.

### 2.3 Token table (Naara Official)
Channel triples ("R G B") so `/opacity` utilities work, matching the existing `--brand-*` convention. Dark values were sampled from the reference screenshots (pixel-sampled where marked ●, matched by eye where marked ○).

| Token | Role | Dark | Light |
|---|---|---|---|
| `--nx-canvas` | page background | `1 20 37` #011425 ● | `238 244 246` #EEF4F6 |
| `--nx-canvas-2` | top of page gradient / banner tint | `0 25 42` #00192A ● | `243 247 249` #F3F7F9 |
| `--nx-surface` | cards, sheets | `0 31 49` #001F31 ● | `255 255 255` |
| `--nx-surface-2` | rows, inputs, nested tiles | `1 24 42` #01182A ● | `245 249 250` #F5F9FA |
| `--nx-surface-3` | raised / hover / segmented track | `5 40 58` #05283A ○ | `233 241 243` #E9F1F3 |
| `--nx-line` | hairline border | `11 58 68` #0B3A44 ○ (sampled selected border #00363C) | `211 226 231` #D3E2E7 |
| `--nx-line-strong` | focus/selected border | `20 83 94` #14535E ○ | `181 205 213` #B5CDD5 |
| `--nx-text` | primary text | `234 244 248` #EAF4F8 ○ | `11 37 48` #0B2530 |
| `--nx-text-2` | secondary text | `156 180 196` #9CB4C4 (7.85:1 on surface) | `71 98 111` #47626F |
| `--nx-text-3` | tertiary/hints | `111 140 160` #6F8CA0 (4.78:1 on surface) | `86 113 127` #56717F (5.17:1 on white) |
| `--nx-teal` | brand fill | `0 175 170` #00AFAA ● | `0 149 143` #00958F |
| `--nx-cta-a` / `--nx-cta-b` | primary CTA gradient | `0 168 162` → `0 138 133` | `0 149 143` → `0 128 124` |
| `--nx-teal-ink` | teal used as text/icon | `45 212 200` #2DD4C8 (9.16:1) | `0 128 124` #00807C (4.80:1) |
| `--nx-gold` | commit CTA, badges | `245 193 58` #F5C13A | `245 193 58` |
| `--nx-gold-2` | gold gradient end | `231 159 0` #E79F00 ● | `231 159 0` |
| `--nx-gold-ink` | gold used as text | `245 193 58` | `138 97 0` #8A6100 (5.54:1) |
| `--nx-on-gold` | label on gold fill | `43 32 0` #2B2000 (9.6:1) | same |
| `--nx-cyan` | hero headline accent | `34 229 240` ○ | `0 128 140` |
| `--nx-ok` / `--nx-warn` / `--nx-bad` | states | `52 211 153` / `245 193 58` / `248 113 113` | `4 120 87` / `146 100 0` / `185 28 28` |

### 2.4 ⚠ Contrast correction to the reference (don't skip)
The reference CTA is white text on `#00AFAA`. Measured contrast is **2.72:1**, which fails WCAG for every text size. Do not copy it literally. Use the gradient in the table (`#00A8A2 → #008A85`), a **18px / 700** label (counts as large text, 3:1 floor; measured 3.3–4.2:1), and keep the icon at full white. The look stays the same; the label stops disappearing on sunlit phones. `ThemePresetContrastTest` exists; extend it (or add `SurfaceTokenContrastTest`) to assert every text-on-surface pair in the table ≥ its floor (4.5 body, 3.0 large/UI) for both modes.

### 2.5 Tailwind wiring
In `tailwind.config.js` `theme.extend.colors`, add (all `rgb(var(--nx-*) / <alpha-value>)`):
`nx.canvas, nx.canvas2, nx.surface, nx.surface2, nx.surface3, nx.line, nx.lineStrong, nx.text, nx.text2, nx.text3, nx.teal, nx.tealInk, nx.gold, nx.goldInk, nx.onGold, nx.cyan, nx.ok, nx.warn, nx.bad`.
Utilities become `bg-nx-surface`, `border-nx-line`, `text-nx-text-2`, `text-nx-teal-ink`, etc. Also add `boxShadow.nx-card`, `nx-sheet`, `nx-glow-teal`, `nx-glow-gold` reading the shadow tokens in §3.4, and `borderRadius.nx-card: 22px, nx-sheet: 28px, nx-row: 16px, nx-pill: 999px` mapped onto the existing `--radius-card` / `--radius-pill` variables where they exist.

---

## 3. Component primitives (Blade, `resources/views/components/nx/`)

Build these once. Every target screen is composed only from them.

| Component | Props | Reference class in HTML |
|---|---|---|
| `x-nx.sheet` | `mode` (`modal`\|`page`), `title`, `icon`, `subtitle`, `closeAction`, slot | `.panel` / `.as-sheet`. Replaces both branches in `numbers-modals.blade.php` so chrome exists in exactly one place. |
| `x-nx.info-banner` | `icon`, `title`, `text`, `dismissKey` | `.banner` (dismissible, dismissal persisted per user in `localStorage` key `nx.dismiss.{key}`) |
| `x-nx.segmented` | `options[]`, `model`, `disabledOptions` | `.seg` (Prices/Statistics, Mobile/Toll-free, Single/Any service; disabled option shows "soon") |
| `x-nx.row` | `selected`, `disabled`, `icon`, slot, `trailing` | `.row` (network rows, picker rows) |
| `x-nx.field` | `label`, `value`, `leading`, `wire:click` | `.field` (Country / Service selector cards) |
| `x-nx.pill` | `variant` (`best`,`recommended`,`popular`,`premium`,`stock`) | `.pill.*` |
| `x-nx.chip` | `icon`, `label` | `.chip` |
| `x-nx.cta` | `variant` (`teal`\|`gold`), `icon`, `loadingTarget` | `.cta.teal` / `.cta.gold` |
| `x-nx.price-bar` | `fees`, `total`, `local`, `note`, sticky | `.foot` (Taxes & fees + You pay + local equivalent) |
| `x-nx.note` | `icon`, slot | `.note` (refund guarantee, subscription notice) |
| `x-nx.bento-card` | `card` array from `NumbersBento::cards()` | `.card.*` |

Rules:
- **CTA semantics.** Teal = instant utility (Get my code, Search available numbers). Gold = commits wallet funds to a time-bound product (Rent this number, Buy this plan, Top up). Do not use gold for anything else.
- **Gold is one per screen.** At most one gold surface per viewport besides small badges.
- Loading and disabled states use the existing `x-ui.spinner` and `wire:loading.attr="disabled"`; keep every `wire:target` exactly as it is now.
- Focus: `focus-visible:ring-2 ring-nx-teal-ink ring-offset-2 ring-offset-nx-canvas`. Reduced motion: no transitions on `prefers-reduced-motion`.

### 3.4 Depth
Depth is expressed by three surface steps plus one border, never by heavier shadows.
- Card: `border nx-line`, inner top highlight `inset 0 1px 0 rgb(255 255 255 / .04)` (dark only), shadow `0 12px 32px -18px rgb(0 0 0 / .65)` dark / `0 8px 24px -16px rgb(11 37 48 / .25)` light.
- Sheet: same plus `0 -20px 60px -20px rgb(0 0 0 / .6)` dark.
- Glow (Auto row, featured card border): `0 0 0 1px rgb(var(--nx-gold) / .5), 0 0 24px -8px rgb(var(--nx-gold) / .45)` dark; halve it in light.
- Never stack a blur on a card that already has a gradient fill (matches the standing dashboard rule). The More sheet stays **solid, no backdrop-blur** (existing HOTFIX §6, Android GPU artifacts).

---

## 4. Screen specs

### 4.1 Numbers landing (`get-number.blade.php`, `numbers-bento.blade.php`)

**Order:** Active-line strip (conditional) → "What do you need today?" heading + one line → bento grid.
**Bento layout (locked, same on mobile):** 2 up / 1 full / 2 up / 1 full.

```
[ Verify 3 ][ Rent 3 ]
[      Naara Line 6    ]
[ Internet 3][ Forward 3]
[    Contact Mgmt 6    ]
```
Change `NumbersBento::LAYOUT` to `verify 3, rent 3, line 6, internet_calls 3, call_forwarding 3, contact_management 6` (Verify was 4 / Rent 2). Mobile keeps `grid-cols-2`; full cards `col-span-2`. `md:` uses the same spans on a 6-col grid.

Card anatomy (top to bottom): icon tile 44px (`rounded-2xl`, tinted fill + 1px border) → title (`font-display`, 20px, `text-nx-text`) → description (13px, `text-nx-text-2`, `line-clamp-2`) → chip row (icon + label, max 3 chips on two-up cards, 4 on full) → badge pinned top-right (POPULAR gold fill, PREMIUM gold outline, others neutral outline). Chevron top-right when no badge.
- Per-card accent gradient via CSS vars `--g1`, `--g2`, `--ga` (glow). Verify: teal. Rent: cyan-blue. Line: deep navy with gold hairline. Internet Calls: warm (orange accent). Forwarding: gold accent. Contacts: neutral steel.
- Remove the crc32 per-card random badge gradients (`fuchsia/purple/sky/indigo` fallbacks) and the `from-emerald-500 to-emerald-600` literals. Badge variants map to tokens only (§3 `x-nx.pill`).
- Remove the giant explanatory comment blocks in `numbers-bento.blade.php` when you rewrite it. Keep the *why* in `docs/design/SURFACE-SYSTEM.md`.
- The verify card keeps its live "+N more" chip from `bulletsFor()`.

**Active-line strip** (reference "Your Naara +234… Active | Manage | Top Up"): see §6.4 for the data source. Hidden when the user has no active permanent line. Never render a placeholder number.

Hero (`numbers-hero.blade.php`) is not redesigned here; only replace literal colors with tokens.

### 4.2 Naara Verify sheet (`numbers-modal/verify.blade.php`)
Structure top to bottom:
1. `x-nx.info-banner` "Naara Verify — Secure your network. Verify your identity." dismissible.
2. Manual / Smart Buy `x-nx.segmented` (keep, restyle).
3. Service `x-nx.field`, Country `x-nx.field` (hidden in Smart Buy exactly as now).
4. **Networks block:** label + `x-nx.segmented` (Prices | Statistics) + download button (existing `exportOperatorsCsv`).
   - **Auto row:** gold-glow border, bolt icon, "Auto — best network", `recommended` pill, chevron. Selected when `$operator === ''`.
   - **Operator rows:** signal-bars icon in teal-ink, `label`, `best` pill, price (`$op['retail']`) or stats (`available` · `success%`), chevron. Out of stock: 45% opacity, `stock` pill, `@disabled`.
   - Selected row: `border-nx-line-strong` + `bg-nx-surface` (not a color flood).
5. NaaraCredits row (existing logic) restyled as `x-nx.note` with dashed border.
6. Sticky `x-nx.price-bar`: Taxes & fees (`$0.00`, info icon) | You pay `$total` + local equivalent from `CurrencyService` (already used in `checkout.blade.php`) | teal CTA "Get my code" | refund `x-nx.note` using `PollSmsOtpJob::TIMEOUT_MINUTES` (keep the constant, never hardcode 15).
When `$modalPrice === null` show "Priced at reservation" exactly as now.

Success state (reserved number + OTP) keeps its logic; restyle to token surfaces. The OTP code uses `text-nx-teal-ink`, tabular numerals, `select-all`.

### 4.3 Naara Rent sheet (`numbers-modal/rent.blade.php`)
Header with back arrow in page mode (existing chevron rotated is fine). Then: `x-nx.segmented` Single service | Any service. "Any service" shows "(soon)" and is `disabled` **unless** the multi-service provider path (HeroSMS primary / VirtSMS fallback per the standing plan) is enabled; drive it from the existing setting/flag that gates that tab today, do not hardcode "soon".
Steps: (1) Select country `x-nx.field` (2) Select service `x-nx.field` (3) Rental length: three equal chips 1 week / 1 month / 3 months, selected = teal border + `bg-nx-surface`. Section headers use an icon tile + title + one-line hint. Numbers 1–3 are a real sequence here, so numbering is correct.
Auto-renew checkbox row. **Do not change its default in this pass.** The reference shows it checked; confirm what the code does today, keep that, and flag to Frank that a pre-checked auto-charge box is a consent risk (relates to the auto-renewal consent work in Prompt 10).
Footer: price bar (`You pay $x` + "(1 week rental)"), **gold** CTA "Rent this number", lock line "Charged from your wallet. Auto-refund if unavailable." (only if that refund behavior is true in code; verify), "Why rent a number?" `x-nx.note` link card.

### 4.4 Naara Line sheet (`numbers-modal/line.blade.php`)
Header "Naara Line / Your permanent international number." Segmented Mobile numbers | Toll-free. Copy line: "A permanent international number that's yours to keep — voice calls and SMS, billed monthly." Country field. "Find a memorable number (optional)" search input (placeholder "e.g. 777 or ends with 0000"; use the existing pattern-search parameter, do not add a new provider query). Subscription `x-nx.note` (first month charged now, renews monthly, voice & SMS included). Teal CTA "Search available numbers". Keep the two-provider permanent-number routing untouched.

### 4.5 eSIM plan detail (`catalogue.blade.php` plan block)
Layout: back link → hero row (thumbnail 96px rounded-2xl left, `Data` pill + plan name right) → **spec list** replacing the 2×2/4-col tile grid: four hairline-separated rows (Data, Validity, Type, Country) each with a teal-ink icon, tiny label, value. Data comes from the existing `$facts` array; do not hardcode. → "Is your device eSIM-ready? Check compatibility" `x-nx.note` link (keeps `open-compatibility` dispatch) → sticky price bar: "You pay", price, local equivalent, gold CTA "Buy this plan", plus the existing heart/help icon buttons if they exist in code (do not add features that don't).
Keep `display_fair_usage_note` and `display_tooltip` blocks, restyled as `x-nx.note` (warn variant uses `--nx-warn`, not `amber-*`).
`checkout.blade.php`: same token migration (`bg-[#243352]`, `bg-[#1B2A44]`, `amber-*`) so the flow doesn't visually snap back to the old palette one tap later.

### 4.6 More menu (`components/more-sheet.blade.php`)
- Sheet on `bg-nx-surface`, top radius 28px, handle, title "More", grid/list toggle as `x-nx.segmented` (icon-only) with the same persistence as today.
- Promo banner slot stays (`Banners::for('menu_sheet')`, default `nx-float-card`); keep it, restyle borders/radius only.
- **List rows:** full-width, 56px, `rounded-nx-row`, icon left, label, active row = gold-tinted with star affordance only if the route is a favourite/pinned item in the model (`$item['favorite']` or equivalent; if no such concept exists, active = teal-tinted with `aria-current`, no star).
  Reference uses solid teal for every row. Do not: nine equal solid teal bars destroy hierarchy. Use `bg-nx-surface-2 border-nx-line`, icon in `text-nx-teal-ink`, active = `bg-nx-surface-3 border-nx-line-strong` + 3px left teal edge.
- Grid mode: 4-col tiles, same tokens.
- Keep `@click="moreOpen = false"`, `wire:navigate`, and the requirement that an ancestor provides `moreOpen`/`moreLayout` (Help Center layout).

---

## 5. Light mode
Every screen above must be first-class in light mode, not an inversion. Rules: canvas `#EEF4F6`, cards white with `nx-line`, gradient cards use the tinted light gradients in the HTML (never a saturated fill with white text unless it passes 4.5:1), teal CTA uses the darker light-mode gradient, gold ink text uses `--nx-gold-ink`. The HTML has a live Light/Dark switch; use it to check both.

---

## 6. Backend: migrations, seeders, admin, data sources

### 6.1 Migrations (new, timestamp after `2026_09_08_170000`)
`add_surface_fields_to_numbers_bento_cards_table`:
- `accent` string(16) nullable, allowed: `teal|cyan|navy|amber|warm|steel`. Default resolved in code from card key when null.
- `chips` json nullable: `[{"label":"WhatsApp","icon":"whatsapp"}, …]`. **Backfill** in the same migration from existing `bullets` (label = bullet, icon = `badge-check`) so nothing goes blank. Keep `bullets` (do not drop). `NumbersBento::cards()` prefers `chips`, falls back to `bullets`.
- `layout_span` is **not** added: the layout stays locked in code (`LAYOUT` const) per the standing non-negotiable.
`down()` drops only the two new columns.

No new table. No provider tables touched.

### 6.2 Seeder / defaults
Update `NumbersBento::defaults()` with `accent` and `chips` for all six cards using the exact copy in the HTML. `Admin\NumbersBento::mount()` already `firstOrCreate`s rows: extend the create payload with the two new fields. Existing rows keep their admin edits; a small `SurfaceSystemSeeder` (idempotent, `updateOrCreate` on `key`, only fills `accent`/`chips` where null) handles installs that already have rows. Register it in the update package's post-migrate step, not in `DatabaseSeeder` only.
Flush `NumbersBento::flush()` at the end of both.

### 6.3 Admin panel (Admin → Numbers cards, existing screen)
Add, without removing anything:
1. Per-card **Accent** select (six values, live swatch).
2. Per-card **Chips** repeater: label (max 24) + icon select from the icon-sprite allowlist (validate against the names in `partials/icon-sprite.blade.php`; add `whatsapp`, `telegram`, `pin`, `calendar`, `lock`, `star` symbols if missing). Max 4 chips. The Verify "+N more" chip is appended in code and not editable.
3. **Entry mode** panel at the top: global default (Modal / Page) + "Set all three to Modal / Page" bulk buttons. Existing per-card `display_mode` select stays (verify/rent/line only). Show a one-line caption: "Presentation only. Doesn't change providers, pricing or orders."
4. **Active-line strip** on/off (`Setting numbers.active_line_strip`, default on).
5. Server-side validation: `accent` in list, `chips.*.icon` in sprite allowlist, `chips` ≤ 4. Every save writes `Auditor::log('numbers.bento_updated', …)`. Authorization stays `super_admin|admin` re-checked in `booted()` and each action.
6. `ImportNumbersBentoCommand` (bulk .webp zip): keep unchanged; confirm it still works with the new columns.

### 6.3b Images
Reference cards are icon + gradient + text, not photo cards. The `image_path` pipeline stays as an **optional** overlay (subtle, right-aligned, 30% opacity, masked to the card) for admins who upload art. If no image, the gradient stands alone with no broken-image placeholder.

### 6.4 Real data sources for everything that is placeholder in the HTML
| Reference element | Real source |
|---|---|
| Network names ("Virtual 8" …), prices, BEST, out of stock, stats, CSV | `GetNumber` `$operators` array (`label`, `retail`, `best`, `in_stock`, `available`, `success`), already rendered today |
| ≈ local price | `App\Services\Pricing\CurrencyService` via `LocaleCurrency::resolve(auth()->user())`, as in `checkout.blade.php` |
| Refund minutes | `PollSmsOtpJob::TIMEOUT_MINUTES` |
| "+N more" | `count(NumberCatalogue::services())` |
| Rent price/length | existing rent pricing in `GetNumber` (do not hardcode $8.50) |
| Active line strip | the same source `my-lines.blade.php` / `MyLines` uses to list the user's active permanent number. Find it; if the user has none, hide the strip. Manage → `route('my-lines')` (confirm name), Top Up → wallet top-up route |
| eSIM facts | `$facts` in `Catalogue`, `$plan->final_retail_usd` |

---

## 7. Site-wide token rollout (the "extend everywhere" part)

### Phase A: foundation (no visual change yet)
Add tokens + Tailwind mapping + old-variable aliasing (§2). Ship the contrast test. Verify nothing moves.

### Phase B: target surfaces
Migrate the files in §1.2 to components + tokens. This is where the look changes.

### Phase C: codemod for the rest of the site
Run the audit script below in **report mode first**, commit the report to `docs/design/surface-migration-report.md`, then run `--apply` only on files the report marks `safe` (pure literal-for-token swaps inside a `dark:` variant). Anything ambiguous stays for manual review.

```python
#!/usr/bin/env python3
# scripts/surface_audit.py  —  usage: python3 scripts/surface_audit.py [--apply]
import re, sys, pathlib, collections
ROOT = pathlib.Path('resources/views')
MAP = {  # literal -> (token utility suffix)
  '#0D1B2A': 'nx-canvas', '#0d1b2a': 'nx-canvas',
  '#1A2840': 'nx-surface', '#1a2840': 'nx-surface',
  '#152238': 'nx-surface-2', '#0F1D33': 'nx-surface-2', '#0f1d33': 'nx-surface-2',
  '#101d33': 'nx-surface-2', '#0f2030': 'nx-surface-2',
  '#1B2A44': 'nx-surface-3', '#1b2a44': 'nx-surface-3', '#243352': 'nx-surface-3',
  '#2D4060': 'nx-line', '#2d4060': 'nx-line',
}
PAT = re.compile(r'(dark:)?((?:bg|border|from|to|via|ring|divide)-)\[(#[0-9A-Fa-f]{6})\]')
report, changed = collections.Counter(), 0
for f in ROOT.rglob('*.blade.php'):
    s = f.read_text(encoding='utf-8'); orig = s
    def sub(m):
        dark, prop, hexv = m.groups()
        tok = MAP.get(hexv)
        if not tok: report[('unmapped', hexv)] += 1; return m.group(0)
        report[(str(f), hexv)] += 1
        return f'{prop}{tok}'            # token flips with mode; drop the dark: prefix
    s = PAT.sub(sub, s)
    if s != orig and '--apply' in sys.argv: f.write_text(s, encoding='utf-8'); changed += 1
for (k, v), n in sorted(report.items(), key=lambda x: -x[1])[:80]: print(f'{n:4d}  {k}  {v}')
print('files changed:' , changed)
```
Caveat you must handle: dropping `dark:` is only correct when the paired light class already exists on the same element. The script must not remove a `dark:` prefix when the element has no light counterpart; make it emit those as `manual` in the report instead of applying. Also grep for `dark:bg-white/5`, `dark:border-white/10` in the target files and convert by hand.

### Phase D: tests
- `SurfaceTokenContrastTest` (§2.4).
- Extend `ThemeTokenRemediationTest` scope to fail on any `bg-[#…]` / `border-[#…]` / `from-[#…]` in the §1.2 files.
- `NumbersEntryModeTest` (§1.3 items 1–4).
- Snapshot/Playwright at 375px and 1280px, light + dark, for: Numbers landing, each of the three sheets in both modes, eSIM detail, More (grid + list). Assert no horizontal scroll and that the sticky price bar never overlaps the last field.
- Run the full existing theme suite (`Theme*Test.php`). Switch through Capable, Aries, Noir Reserve, Neon Vertex and confirm all new components repaint from that theme's own navy/primary/accent (no frozen Naara teal).

---

## 8. Motion
One orchestrated moment only: the bottom sheet's enter (translate-y 32px → 0, 260ms, ease-out) and the Auto row glow easing in once after the operator list loads. No per-card scroll-in animation, no hover lift on every card. Respect `prefers-reduced-motion`.

---

## 9. Bottom-nav color consistency (token-only, gated)
Do **not** edit nav markup, spacing, order or behavior. Only after Phase B is merged: grep the nav partials for literal colors and `text-primary`/`text-teal-*`/`text-amber-*` on icons and normalize to: inactive `text-nx-text-3`, active `text-nx-teal-ink`, center button fill `nx-teal`, "Soon" tag `nx-gold` fill + `nx-on-gold`. List every changed line in the PR description. If any change would require touching structure, skip it and report.

---

## 10. Acceptance checklist
- [ ] No `#0D1B2A`, `#243352`, `#1B2A44`, `#2D4060`, `#1A2840` remain in the §1.2 files.
- [ ] Verify/Rent/Line render identically in structure in Modal and Page mode; only the chrome differs.
- [ ] Admin can set modal/page per card, all-three bulk, and a global default; audit log lines written; untouched install still opens modals.
- [ ] Toggling mode mid-order loses nothing.
- [ ] All copy strings pass through existing translation keys; new strings added to `lang/en` and `lang/fr` (others fall back).
- [ ] Contrast test green in both modes. CTA label ≥ 3:1 large text.
- [ ] All Theme*Test suites green; four non-default themes visually verified.
- [ ] Header and bottom-nav diff = 0 lines of markup.
- [ ] No provider, queue, pricing or wallet code changed (`git diff --stat` reviewed).
- [ ] Packaged via the feature/fix/upgrade template; package built from the exact baseline tag; unsigned key never committed.

## 11. Files you may edit
Views listed in §1.2, `components/nx/*` (new), `resources/css/app.css`, `tailwind.config.js`, `app/Support/NumbersBento.php`, `app/Models/NumbersBentoCard.php`, `app/Livewire/Admin/NumbersBento.php` + view, the new migration/seeder, `partials/icon-sprite.blade.php`, `lang/en|fr/numbers.php`, `lang/en|fr/esim.php`, new tests, `docs/design/SURFACE-SYSTEM.md`, `scripts/surface_audit.py`. Anything else: stop and ask.

## 12. Open decisions for Frank
1. Auto-renew default on Rent (reference shows pre-checked; consent risk).
2. "Any service (soon)": enable when HeroSMS/VirtSMS multi-service goes live?
3. ~~More menu rows~~ **Resolved in rev 2:** V3 inset grouped list is default; solid accent rows = Vivid skin.
4. Whether the header toggle (currently labeled "Short-term use" / WhatsApp sub-label in your screenshots) gets its own pass later. Untouched here.


---

# PART B: Appearance presets (skins × accents × mode), per account

## 13. Theme layering and precedence
Four independent layers, resolved in this order. A lower layer can never override a higher layer's *invariants*.

| Layer | Who sets it | What it controls | Stored |
|---|---|---|---|
| L0 Platform theme | Admin / white-label operator (existing `ThemePreset`) | Brand navy/primary/accent, fonts, radii whitelist (§2.2, unchanged) | existing tables |
| L1 Skin | User (admin limits choices) | Structure and depth: radii, card treatment, bento saturation, More-menu style | `user_appearance.skin_key` |
| L2 Accent | User (admin limits choices) | The interactive colour family: CTA gradient, selected borders, tab/teal-ink, Verify card, promo | `user_appearance.accent_key` |
| L3 Mode | User | light / dark / system | `user_appearance.mode` |

**Invariants no preset may change:** gold (= money/commit: Rent CTA, Buy CTA, Popular pill, Auto-row glow), semantic states (`ok/warn/bad`), the logo mark and wordmark colours, country flags and brand logos (WhatsApp green etc.), out-of-stock treatment, text colours (`text/text-2/text-3`) and all canvas/surface/line neutrals per mode. Accent recolours *only* the tokens listed in §16.

**Default = no row.** A user with no `user_appearance` row (or null columns) gets the platform defaults: skin `surface`, accent `teal` ("Naara Teal"), mode = whatever the existing dark-mode mechanism does today. First find that mechanism (class `.dark` strategy, see §2.1) and route the Mode preference through it; do not change its UI.

**White-label:** on a non-default `ThemePreset`, accent `teal` means "inherit the theme's brand colour" (tokens derive from `--brand-primary` as in §2.2). Explicit accents (`ocean…graphite`) override `--nx-teal*` and `--nx-acc-*` only via the user layer. Operators can lock appearance (§17.4) so end-customers of a white-label never see presets.

**Attributes.** Emit on `<html>`: `class="dark"` (existing) plus `data-nx-skin="surface|calm|vivid"` and `data-nx-accent="teal|ocean|violet|emerald|rose|graphite"`. The wireframes use `data-skin` / `data-accent` / `data-theme`; rename to the `data-nx-*` / `.dark` forms in production. **Skin selectors must be scoped with `@scope` (§15.3) and never target `html` directly**, because the Appearance page renders previews inside wrapper elements that carry their own `data-nx-skin` / `data-nx-accent`. Accent rules only set custom properties on the element that carries the attribute, so plain `[data-nx-accent=ocean]{…}` is safe.

## 14. Canonical token values (Naara Surface = V3). Supersedes §2.3 values
Channel triples, same `--nx-*` names and Tailwind wiring as §2.3. Keep every token listed there; these are the new values. Extra tokens: `--nx-acc-a/b/c` (accent gradient stops), `--nx-hi` (inner highlight), `--nx-gold-2`.

| Token | Dark | Light |
|---|---|---|
| canvas / canvas-2 | `3 17 30` / `4 25 40` | `235 242 244` / `244 248 249` |
| surface / surface-2 / surface-3 | `8 32 47` / `11 41 58` / `16 54 72` | `255 255 255` / `244 248 249` / `230 239 242` |
| line / line-strong | `23 68 82` / `36 110 122` | `213 227 231` / `160 195 203` |
| text / text-2 / text-3 | `234 244 248` / `158 184 199` / `118 147 165` | `10 36 49` / `70 96 109` / `88 114 127` |
| teal / cta-a / cta-b | `16 181 174` / `16 179 170` / `6 128 124` | `10 155 148` / `12 158 150` / `5 118 114` |
| teal-ink | `61 219 208` | `0 127 121` |
| gold / gold-2 / gold-ink / on-gold | `242 185 59` / `226 152 8` / `242 185 59` / `38 28 0` | same / same / `133 92 0` / same |
| ok / warn | `52 211 153` / `242 185 59` | `4 120 87` / `133 92 0` |
| acc-a / acc-b / acc-c | `15 169 164` / `6 118 128` / `4 84 99` | same |
| Radii | card 24, sheet 30, row 18, pill 999 | same |
| Elevation | e1 `0 14px 30px -20px rgb(0 0 0/.75)`; e2 `0 -24px 60px -24px rgb(0 0 0/.7)` | e1 `0 10px 26px -18px rgb(10 36 49/.3)`; e2 `0 -14px 44px -18px rgb(10 36 49/.3)` |

**Contrast is not yet proven for these values.** Extend `SurfaceTokenContrastTest` so every text-on-surface pair (and every accent's CTA label, white on `cta-a`→`cta-b` at ≥18px/700 = 3:1) is asserted for both modes and all six accents. If a pair fails, darken the token; never loosen the test.

**Bento card tones** (`numbers_bento_cards.accent` from §6.1 maps to these): `teal` → follows the user's accent (`--nx-acc-a`); `cyan` `34 170 225`; `navy` `70 110 210` (Line also gets the 3px gold left edge); `warm` `242 120 40`; `amber` `242 185 59`; `steel` `120 150 175`. Only the `teal` tone follows the accent, so cards stay distinguishable in every accent.

**Depth rules (what stops it looking vibe-coded):** three neutral steps plus one line, nothing else. Cards = `surface` + tone tint at ≤ 20% + inset 1px `line` ring + top highlight. Colour is spent in three places only: the Verify card, the primary CTA, the gold commit CTA. Sentence-case labels throughout, no all-caps. Dot texture only on the saturated card.

## 15. Skins (CSS-only; same Blade, same markup)
### 15.1 Catalog
Files: `resources/css/nx-skins/{surface,calm,vivid,ledger,dotgrid,aurora,glass,halo}.css`, imported by `nx-skins.css`. Components expose stable hooks: `.ns-card`, `.ns-card--primary` (Verify), `.ns-tile`, `.ns-chips`, `.ns-list`, `.ns-list__row`, `.ns-list__row--pin` (Contacts), `.ns-promo`, `.ns-cta`, plus one decorative `<i class="ns-deco" aria-hidden="true">` as the first child of every `.ns-card` (emitted by the Blade card component, not by JS as in the wireframes). **No per-skin Blade forks.**

| Group | Key | Label | One-line identity | Key mechanics |
|---|---|---|---|---|
| Core | `surface` | Naara Surface (default) | Layered depth, one bold action card | Verify = saturated accent gradient + dot texture; other cards = tone tint ≤ 20%; radii 24/18/30 |
| Core | `calm` | Calm | Flat, tonal, quietest | Verify = same tint as others; radii 20/14/26; no CTA glow; flat promo |
| Core | `vivid` | Vivid | Saturated and confident | Every card = `color-mix(tone 68% → 34%, #02121f)` gradient, white text, dots on Rent; More = separate solid accent rows, gold Contacts with dark label |
| Core | `ledger` | Ledger | Statement/fintech, flat | Radii 14/10/20; no gradients or glows; 4px left tone bar per card; dashed chip and price-bar rules; solid CTAs; 6px pills; tabular numerals; left-aligned promo with gold edge |
| Textured | `dotgrid` | Dot-grid | Engineered texture | `.ns-deco` = two-scale dot lattice (12px fine + 48px anchor dots) fading from each card's own corner; dotted strip/banner/promo; dot underline on H1 |
| Textured | `aurora` | Aurora | Light and motion | Glass sheen + one motif per card (Verify aurora arcs, Rent orbit rings, Line signal waves, Internet equalizer bars, Forward chevrons, Contacts constellation); gold rays on promo; sheet top glow; pulsing "Active" dot |
| Soft | `daybreak` | Daybreak | Light-first pastel | Pastel teal/gold/violet wash behind the app, radii 26/20/32, round tiles, soft tone shadows |
| Soft | `paper` | Paper | Warm editorial | Warm neutral palette (own light and dark), serif headings, flat paper cards with a offset rule, dotted chip rules, slightly rotated stamp pills |
| Soft | `clay` | Clay | Inflated and tactile | Radii 30/22/36, cards with dual light/dark shadows and inner highlights, pressed (inset) inputs, puffy CTAs, round tiles |
| Themed | `adire` | Adire | Indigo and cream resist-dye | Own palette per mode (indigo/cream), concentric-ring and dot motif via `.ns-deco` fading from the top-right, circular outlined tiles, dashed chip rules, 6px pills, Verify = accent gradient with cream stitch ring and on-fill tokens, promo with dashed gold "stitch" outline |
| Themed | `blueprint` | Blueprint | Technical drawing | Navy (dark) or white-paper (light) palette, minor and major grid lines on the app background, 1.5px dashed card outlines, corner tick marks via `.ns-deco`, square dashed tiles, monospace numerals, registration-ring CTAs, radii 6/5/12; Verify = tinted fill with solid accent outline (normal text tokens) |
| Themed | `neon` | Neon Lagos | Night-city glow | Dark-first purple palette (own light variant, glow and skyline off in light), 1.5px tone-coloured neon rings with outer glow, soft text-shadow on headings (dark only), a faint building-silhouette band above the bottom bar (decorative, `pointer-events:none`, behind content), magenta top light on sheets, magenta-to-accent promo; Verify = tinted fill with solid accent ring (normal text tokens) |
| Themed | `passport` | Passport | Visa pages and stamps | Navy "cover" (dark) or parchment (light) palette, double-rule visa frames, serif headings, monospace figures, guilloche rings and a faint rotated stamp circle via `.ns-deco`, big faded country code watermark, stamp-style pills (rotated, double border), dotted perforation above price bars, accent underline on the header; **accent follows the active eSIM country** (§44); Verify and promo = country-accent gradient with on-fill tokens |
| Themed | `golden` | Golden Hour | Light that follows the clock | Warm palette (own light and dark), a background wash in two time-of-day colours (dawn peach/lavender, day sky/amber, dusk coral/violet, night indigo/teal), top-edge light on cards in the wash colour, sun-glow circle and rings via `.ns-deco`, wash-lit sheet edge, Verify = accent gradient with a sun glow in the corner (on-fill tokens). **Does not change light/dark**: Mode stays the user's setting (§45) |
| Themed | `vault` | Vault | Black and champagne foil | Near-black (dark) or ivory (light) palette, 1px foil borders with an inner engraving rule, guilloche (overlapping concentric rings) via `.ns-deco`, serif headings, tracked labels, foil-outlined pills and tiles, Verify and promo = deep accent to black with foil frame (on-fill tokens). Foil is the muted champagne `--nx-foil` (dark `200 168 98`, light `158 128 70`), **never the money gold** (§46) |
| Telecom | `boarding` | Boarding Pass | Travel ticket | Flat canvas, ticket cards with circular side notches punched in the canvas colour, dashed tear line above chips, barcode strip at the top of price bars, left accent edge on rows; Verify = accent gradient ticket (on-fill tokens) |
| Telecom | `chipset` | Chipset | SIM card and circuit board | Dark teal PCB palette (own light variant), faint board grid, cards with a cut top-right corner (`clip-path`), circuit traces and solder dots via `.ns-deco`, foil-outlined square tiles, double-rule strip; Verify and promo = accent gradient with on-fill tokens. Uses the decorative `--nx-foil` (not money gold) |
| Telecom | `wavelength` | Wavelength | Radio ripples | Concentric broadcast arcs on the app background, ripple rings from each tile via `.ns-deco`, three-ring halo on tiles; optional ripple pulse on tiles (disabled under reduced motion); Verify = accent gradient (on-fill) |
| Telecom | `airmail` | Airmail | Envelope and stamps | 5px red-white-blue diagonal-stripe border on cards (fixed decorative colours, same in both modes), stamp-frame pills and tiles, postmark ring and wavy lines via `.ns-deco`, striped top edge on sheets; Verify and promo = accent gradient (on-fill) |
| Telecom | `handset` | Handset | Retro LCD phone | Monochrome LCD palette (green-grey light, phosphor green dark), scanline overlay, monospace type, 2px radii, bevelled outset panels, inverted Verify block and promo (inverted token set), accent does not tint the UI (it only recolours nothing here; document in picker as "accent has no effect") |
| Material | `prism` | Prism | Holographic foil | Cards with a soft diagonal light sweep, a rainbow conic gradient border via `.ns-deco` (rotates slowly unless reduced motion), pastel glow on CTA, strip, banner and sheet edge; Verify = tinted fill with accent glow (normal text tokens) |
| Themed | `receipt` | Receipt | Thermal paper | Paper/ink palette (own dark variant), monospace everywhere, zigzag torn bottom edge via `.ns-deco` (drawn in the canvas colour), dashed rules, 2 to 4px radii, solid ink CTA, inverted near-black Verify block and promo with paper text |
| Atmosphere | `nebula` | Nebula | Deep space | Violet-black palette (own pale-lavender light variant), starfield dots and magenta/violet/accent clouds behind the app (light mode keeps clouds only), planet-like gradient tiles with glow, violet sheet light; Verify and promo = accent-to-violet gradient (on-fill) |
| Culture | `harmattan` | Harmattan | Dry-season haze | Sand/ochre palette (own light and dark), hazy sun disc and fine grain on the app background, dust-speck `.ns-deco` fading from a corner, round tiles, amber sheet edge; Verify and promo = accent gradient with a warm sun glow (on-fill) |
| Culture | `ankara` | Ankara | Printed-cloth band | Standard palette; a 10px woven-print band (orange, teal, magenta, indigo diagonal stripes, fixed decoration, no yellow) across the top of every card, sheet and promo; tiles show concentric-ring motif; slightly tilted pills; Verify = accent gradient (on-fill) |
| Culture | `danfo` | Danfo | Lagos transit signage | Asphalt (dark) or concrete (light) palette, road-sign double frames, heavy type, thick rules, a black and danfo-yellow hazard stripe on cards, sheets and promo (`--nx-danfo`, decoration only, **never a button, price or status colour**, and never near the gold CTA as a fill); Verify and promo = solid black sign with white text |
| Culture | `asooke` | Aso-oke | Woven strip cloth | Wine/plum (dark) or cream (light) palette, a 14px woven strip (wine, cream, indigo, ochre bands plus a fine weave overlay) down the left of every card, outlined square tiles, ochre sheet edge; Verify = accent gradient (on-fill) |
| Atmosphere | `tide` | Tide | Sea waves | Deep-sea blue (own pale-aqua light variant), two faint wave lines along the app bottom, a wave line along every card bottom drifting slowly (SVG data-URI, stopped under reduced motion), round tiles; Verify = accent gradient with a sea-green glow (on-fill) |
| Atmosphere | `canopy` | Canopy | Forest leaves | Forest-green palette (own light variant), leaf-shaped cards (asymmetric radii that still scale with the roundness dial), soft vein lines via `.ns-deco`, leaf-shaped tiles, green sheet edge; Verify = accent gradient (on-fill) |
| Playful | `pop` | Pop | Comic and halftone | Cream (light) or midnight-violet (dark) palette with a halftone dot background, 2.5px outlines, tone-washed flat cards, halftone dots plus a comic burst corner via `.ns-deco`, tilted pills, heavy CTAs; Verify = flat accent fill with white halftone (on-fill) |
| Access | `clarity` | Clarity | High-contrast, large print | Pure black or white palette, 2px solid outlines (4px for selected), no gradients, glows, patterns, motion or blur, text ≥ 10:1 (measured 10.5 to 10.8), content zoom ×1.1 on top of the Text size dial, 3px focus ring, Verify and CTA use `acc-c` darkened 25% (≥ 9.9:1 for the on-fill text set), gold CTA with black text |
| Bold | `neo` | Neo | Loud outlines | Radii 12/10/16, 2px text-colour outlines, hard offset shadows, tone-tinted card fills, solid accent Verify card |
| Bold | `noir` | Noir | OLED true black | Pure-black canvas (white in light mode), 1px hairlines, no gradients, accent only on the Verify outline, CTAs and ink |
| Material | `glass` | Glass | Frosted layers | Colour-mesh app background from `--nx-acc-a/b` + gold; every card/row/field = translucent `--nx-frost` at `--nx-frost-a` alpha + `backdrop-filter: blur(14–18px) saturate(150%)`; Verify = translucent accent gradient; **sheets, the More sheet, price bars and promo are NOT blurred** (solid at .9 to .94 alpha), see §15.4 |
| Material | `halo` | Halo | Glow and orbit | `.ns-deco` = 1.5px conic-gradient border (tone → gold) via mask-composite, slowly rotating through `@property --ang`; tone glow shadow; glowing CTAs, tiles and sheet top edge |

The exact CSS for every row is in the V9 file: lift it. Calm and Vivid remain approximations of V1/V2 on V3 structure.

### 15.2 Skin-owned variables
Each skin may set only: radii (`--nx-r-card/row/sheet`), `--nx-frost`/`--nx-frost-a` (Glass), `--dc`/`--da` (ornament colour/alpha, set on `.ns-card`), and component-level properties. A skin may **not** set accent tokens from §14 (they belong to accent) or gold/status colours. **Exception:** the palette skins `harmattan`, `tide`, `canopy`, `pop`, `danfo`, `asooke`, `paper`, `noir`, `adire`, `blueprint`, `clarity`, `neon`, `passport`, `golden`, `vault`, `chipset`, `receipt`, `handset` and `nebula` may set neutral surface, line and text tokens (per mode) because their identity is the palette; every such token pair is covered by the contrast test. The `surface` skin explicitly resets the radii on its root so a Surface preview nested inside a Ledger page does not inherit Ledger radii.

### 15.3 Scoping (required)
Write each skin as `@scope ([data-nx-skin=calm]) to ([data-nx-skin=calm] [data-nx-skin]) { :scope .ns-card--primary { … } }`. The `to` boundary stops the active skin's rules at any nested `[data-nx-skin]` preview. Inside the block, prefix selectors with `:scope` to keep the specificity the wireframes use. Light-mode variants: `:where([data-theme=light],[data-theme=light] *):scope .ns-card { … }` (use the project's `.dark` class inversion accordingly). Browser floor for `@scope`: Chrome/Edge 118, Safari 17.4, Firefox 128. For older browsers, the picker previews degrade to the active skin, which is cosmetic only; the live page is unaffected. If Frank requires older support, compile skins to class-prefixed selectors (`.nx-skin-calm .ns-card`) with a Tailwind/PostCSS step and render previews in an iframe-free wrapper that omits the html-level class.

### 15.4 Fallbacks and performance (required)
- **No `backdrop-filter` on anything that slides or is animated with `transform`** (the More sheet, quick sheet, notifications sheet). `more-sheet.blade.php` documents a hotfix: blur plus a slide-up caused GPU artifacts on many Android builds, and the owner chose solid cards there. Glass applies blur only to static cards, rows and fields. Do not stack it on `.nx-glass-tile` (the admin-dialled glass surface): inside `.ns-card` set `--nx-glass-blur:0px` so the two never compound.
- `@media (prefers-reduced-motion: reduce)`: no animation (Halo border stops, Aurora pulse off).
- `@media (prefers-reduced-transparency: reduce)` and `@supports not (backdrop-filter: blur(1px))`: Glass raises `--nx-frost-a` to `.16` dark / `.88` light so cards remain legible without blur.
- `@supports not (color: color-mix(in srgb, red, blue))`: Vivid uses `rgb(var(--tone)/.6)` fallback.
- Halo's rotation needs `@property`; without it the border is static (acceptable).
- Budget: at most 12 `backdrop-filter` layers on screen (Glass); no `filter: blur` on scrolling content; decorative layers are `pointer-events:none`. On `navigator.connection.saveData` or a device flagged low-memory, the server may serve `surface` regardless of the saved skin (write this as a documented, optional middleware toggle, off by default).
- **Contrast for Glass:** text sits on translucent layers over a colour mesh. `SurfaceTokenContrastTest` must compute text contrast against the worst-case composite (mesh at full alpha behind the lightest `--nx-frost-a`), not against plain `surface`.

## 16. Accent presets
Accent sets exactly: `--nx-teal`, `--nx-cta-a`, `--nx-cta-b`, `--nx-teal-ink`, `--nx-acc-a/b/c`. Dark values; light overrides only `teal-ink`, `cta-a`, `cta-b` (and for `teal`, `teal`).

| Key | Label | teal | cta-a → cta-b | teal-ink | acc-a / b / c | Light: teal-ink, cta-a → cta-b |
|---|---|---|---|---|---|---|
| `teal` | Naara Teal (default) | `16 181 174` | `16 179 170` → `6 128 124` | `61 219 208` | `15 169 164` / `6 118 128` / `4 84 99` | `0 127 121`, `12 158 150` → `5 118 114` |
| `ocean` | Ocean | `40 150 235` | `36 132 220` → `22 96 184` | `125 194 255` | `38 140 226` / `22 96 176` / `14 64 128` | `16 96 176`, `30 120 208` → `18 88 170` |
| `violet` | Violet | `140 110 240` | `134 104 232` → `98 68 200` | `190 170 255` | `128 98 232` / `92 64 190` / `58 40 130` | `94 62 192`, `112 80 214` → `84 56 178` |
| `emerald` | Emerald | `34 197 94` | `20 150 76` → `10 110 54` | `110 231 160` | `24 150 80` / `14 110 60` / `8 76 42` | `10 112 55`, `18 140 70` → `10 106 52` |
| `rose` | Rose | `240 84 120` | `226 70 108` → `182 38 76` | `255 150 176` | `220 64 102` / `170 36 72` / `116 24 52` | `178 30 68`, `206 54 90` → `166 34 70` |
| `graphite` | Graphite | `130 150 170` | `104 124 146` → `66 84 104` | `190 206 220` | `96 116 138` / `62 80 100` / `40 54 68` | `50 72 90`, `84 104 126` → `56 74 94` |

Seed these in `config/appearance.php` (source of truth for keys, labels, CSS class mapping) and mirror the enable/default flags in the DB (§17). Adding a future accent = one CSS block + one config entry + one contrast-test row.

## 17. Backend: per-account isolation
### 17.1 Migrations (new, after the §6.1 migration)
1. `create_appearance_presets_table`: `id`, `kind` string(8) (`skin|accent`), `key` string(24), `label` string(40), `enabled` bool default 1, `is_default` bool default 0, `sort` smallint default 0, timestamps; unique(`kind`,`key`). Exactly one `is_default` per kind (enforce in the model/service in a transaction, not with a partial index, to stay DB-agnostic).
2. `create_user_appearance_table`: `user_id` unsigned big int **unique**, FK → `users` `cascadeOnDelete`, `skin_key` string(24) nullable, `accent_key` string(24) nullable (preset key or `custom`), `accent_hex` char(7) nullable (`#rrggbb`, lowercase, required when accent is `custom`), `mode` string(8) nullable (`light|dark|system`), dials all nullable strings: `round`, `dens`, `ts`, `depth`, `font`, `motion` (values in §37), timestamps. Migration file name: `2026_10_01_100000_create_user_appearance_table.php` (latest existing migration is `2026_09_22_100400`). **Do not add columns to `users`.** Null = follow platform default.
3. Setting key `appearance.lock_users` (bool, default false) in the existing settings store. If white-label operator config lives in its own table, add `appearance_locked` bool default false there instead (locate it first; report what you found).
`down()` drops only what `up()` created. No provider, wallet or order tables touched.

### 17.2 Seeder
`AppearancePresetSeeder` (idempotent `updateOrCreate` on `kind,key`; never overwrites `enabled`/`is_default` on existing rows). Seeds 8 skins (`surface` default; `calm`, `vivid`, `ledger`, `dotgrid`, `aurora`, `glass`, `halo`) with `group` (`core|textured|material`) stored in `config/appearance.php`, and 6 accents (`teal` default, `ocean`, `violet`, `emerald`, `rose`, `graphite`), all enabled. Register in the post-migrate step like `SurfaceSystemSeeder`.

### 17.3 Resolution and rendering
- `App\Support\Appearance\AppearanceResolver::for(?User): array{skin,accent,mode}`. Order: lock on → platform defaults; else user's value if the preset still exists **and is enabled**, else platform default. A disabled/removed preset falls back silently **without deleting the user's row**, so re-enabling restores their choice.
- Middleware `ApplyAppearance` (web group, after auth) shares the result to the layout. Layout renders `<html class="{dark?}" data-nx-skin data-nx-accent>` server-side. No flash: `mode=system` needs one tiny inline script (existing CSP nonce) in `<head>` that resolves `prefers-color-scheme` before first paint.
- **Guests** always get platform defaults (mode follows system). No guest persistence of skin/accent. After login the DB row wins.

### 17.4 Isolation rules (non-negotiable, each needs a test)
1. Cache keys include the user id (`appearance:user:{id}`), forgotten on save. Never a global key.
2. Authenticated HTML is never served from a shared/full-page/CDN cache: `Cache-Control: private`, `Vary: Cookie`. Check the existing response-cache middleware and exclude the appearance-dependent layout.
3. The DB is the source of truth. localStorage may mirror **mode only**, to avoid flash, and is cleared on logout.
4. Livewire components read the resolver each request. Never persist appearance in component state or the session shared across users.
5. Emails, PDFs, receipts, invoices and public pages always use platform defaults.
6. Admin impersonation: render the impersonated user's appearance, but **disable writes** while impersonating.
7. Writes go through one action (`UpdateUserAppearance`): policy = own record only; values validated with `Rule::in(enabled keys)`; throttle 30/min; rejected when locked. Responds with the resolved values.
8. Admin changes (defaults, enable/disable, lock) write `Auditor::log('appearance.admin_updated', …)`. User changes are not audited.
9. Admin cannot disable the current default skin or default accent (validate and show the message seen in V5: "The default … must stay enabled").

## 18. User UI (contract: V4 and V5 files)
- **Account → Appearance** (`/account/appearance`, route name `account.appearance`, Livewire `Account\Appearance`). Layout per V4: back row, title, one-line "Only your account changes", a **live preview panel** (real `nx` components: Verify + Rent cards and a CTA), **Mode** segmented control (Light/Dark/System), **Skin** cards grouped under Core / Textured / Material headings, each with a scoped mini-preview (a wrapper with its own `data-nx-skin`, rendered from the real card components, never screenshots), **Accent** swatch grid (3 columns, 2 below 340px container width), helper text that gold/status/flags never change. **Autosave** on tap with a toast: "Saved to your account". Guest state: controls disabled, "Sign in to personalise". Locked state: disabled, "Appearance is managed by your operator".
- **More menu** gets one extra row, "Appearance" (inside `more-sheet.blade.php`, which is in scope). Header and bottom-nav markup stay untouched. Two delivery options, pick per Open Decision 22.1: V4 behavior (row links to the page) or V5 behavior (row opens `components/appearance-quick-sheet.blade.php`: Mode, Skin segmented, Accent swatches, link to the full page).
- Everything sizes from container width (`cqw` clamps, 340px breakpoint) so it fits 320px phones to desktop; verify at 320, 360, 390, 430 and ≥1024.

## 19. Admin UI (contract: V5 "Admin" tab)
Admin → User appearance (route `admin.user-appearance`; **not** `admin.appearance`, which is the existing Splash page) (`super_admin|admin`, re-checked in `booted()` and each action, as in §6.3): platform default skin and accent, per-skin and per-accent enable toggles, and "Lock appearance for all users" (operator control). Caption: "Presentation only. Never changes providers, pricing, wallet or orders." Saves write the audit line and forget the affected caches. Build in the normal admin layout; V5 shows it at phone width only for review.

## 20. Tests (add to §7 Phase D)
`AppearanceResolverTest` (defaults, disabled fallback keeps row, lock, guest), `AppearanceIsolationTest` (user A's save never changes user B's render; shared cache key test; impersonation read-only; logout clears mirror), `AppearanceValidationTest` (unknown/disabled keys rejected, throttle), `AppearanceAdminTest` (default cannot be disabled, audit written), `SurfaceTokenContrastTest` extended across 35 skins × 6 accents × 2 modes for CTA and text pairs, `ThemeTokenRemediationTest` unmodified and green, plus a render test that each skin's selectors work on a wrapper element (picker previews).

## 21. Acceptance additions and scope
- [ ] Fresh install renders Surface + Naara Teal with zero DB rows in `user_appearance`.
- [ ] Two accounts on two browsers keep different skin/accent/mode; changing one never affects the other, including after cache warm-up.
- [ ] Guest and emails always use platform defaults.
- [ ] Disabling a skin/accent in admin falls users back without data loss; re-enabling restores.
- [ ] Gold, semantic colours, logo and flags are identical across all 108 skin×accent×mode combinations (automated screenshot diff on the four target screens, plus manual check of Glass and Halo).
- [ ] Each skin previews correctly inside the picker while a different skin is active (nesting test).
- [ ] Reduced-motion, reduced-transparency and no-`backdrop-filter` fallbacks verified; Glass text contrast passes on the worst-case composite.
- [ ] Non-default `ThemePreset` with accent `teal` inherits the theme brand; explicit accents override only the §16 tokens.
- [ ] Header and bottom-nav markup diff is still 0 lines.
- [ ] Appearance page usable at 320px width without horizontal scroll.

**Files you may additionally edit:** `resources/css/nx-skins.css` and `resources/css/nx-skins/*.css` (new, one file per skin), `config/appearance.php` (new), `app/Support/Appearance/*` (new), `app/Http/Middleware/ApplyAppearance.php` (new), `app/Livewire/Account/Appearance.php` + view (new), `app/Livewire/Admin/Appearance.php` + view (new), `components/appearance-quick-sheet.blade.php` (new), the layout file that renders `<html>`, `components/more-sheet.blade.php`, the two migrations and seeder above, `lang/en|fr/appearance.php`, route files, new tests. Anything else: stop and ask.

## 22. Open decisions for Frank (Part B)
1. **Quick sheet vs page-only:** V5 (More row opens a quick sheet) or V4 (More row links to the page)? Recommendation: V5 behavior, since mode/accent changes are the common case.
2. Guests see platform defaults only (recommended) or may pick mode via cookie.
3. Keep the name "Graphite", or a warmer neutral?
4. Should any accent or skin be plan-gated later (e.g. Naara Pro)? Nothing here is gated.
5. **Platform default skin:** this document assumes Naara Surface. V6/V7/V8 show Glass/Ledger/Halo as defaults for comparison; approve one or keep Surface.
6. **Launch set:** eight skins is a lot to QA. Recommendation: launch with Surface, Calm, Vivid, Ledger, Glass (enabled) and ship Dot-grid, Aurora, Halo seeded but `enabled=0`, then switch on from Admin once verified on low-end Android. Approve or override.
7. Glass and Halo cost GPU on cheap phones. Enable the optional low-power fallback in §15.4?


---

# PART C: Whole-dashboard rollout (V9/V10 are the contract)

## 23. What "a skin touches everything" means
A skin applies to a screen only if that screen is built from the shared `nx-*` components and tokens (§3, §14, §15). Screens that still use one-off markup or hard-coded colours stay on the old look and make the platform look half-themed. Therefore:
1. **Coverage gate:** a CI check (`php artisan nx:coverage`) scans Blade views under the user and admin layouts and fails if a view (a) uses hard-coded colour classes or hex values outside the allow-list (logo, flags, provider brand marks), or (b) renders a card/list/CTA/tile/sheet/foot with raw markup instead of the `x-nx.*` components. The report lists offending files by path. Wire it into the existing `ThemeTokenRemediationTest` flow.
2. **Layout reach:** the user layout, the admin layout, the auth pages (login, register) and the checkout flow all render `<html data-nx-skin data-nx-accent>` through the same resolver (§17.3). Public marketing pages do not.
3. **Rollout in batches**, each shippable on its own; a skin only changes screens already converted:
   - Batch 1 (already specified in §4–§5): Numbers bento, Verify/Rent/Line sheets, More menu, eSIM detail.
   - Batch 2: Home, eSIM catalog and **eSIM checkout**, Wallet (all four tabs), Account & privacy, Appearance.
   - Batch 3: Call abroad, Contacts, Messages, Rewards, My Journey, Data estimator, Naara Gift, Notifications.
   - Batch 4: Clients, Invoices, Developer API.
   - Batch 5: Admin overview and admin sub-pages (providers, health, KYC, orders, users, settings).
   Header and bottom-nav markup stay untouched throughout. Skins only restyle through tokens, so the existing "Bottom menu style: floating" setting keeps working in every skin; verify the floating bar's offset in Glass and Halo.

## 24. Screen inventory (from the dashboard screenshots; V9 page ids in brackets)
| Page (V9 id) | Reached from | Components it needs |
|---|---|---|
| Home "My Connectivity" (`home`) | Logo, More → Home | page title + sub, promo banner, `ns-list` rows with tiles, NIA helper note |
| eSIM plans (`esims`) | Bottom nav eSIMs, Home | filter chips (Trending/Countries/Regions), destination cards, plan rows, estimator link |
| eSIM detail (`esim`) | Catalog | spec list, readiness note, price bar |
| **eSIM checkout** (`checkout`) | Detail → Buy this plan | banner, order card with chips, insufficient-balance warn note, coupon field + Apply, price bar, gold CTA (Top up if short → Wallet) |
| Data estimator (`estimator`) | Plans, More | option rows, progress bar, result card, CTA to plans |
| Numbers bento, Verify, Rent, Line | Bottom nav Numbers | Part A |
| Call abroad (`dial`) | Numbers → Internet Call, More | readout field, keypad, call CTA |
| Contacts (`contacts`) | Numbers → Contacts, Call abroad, More | search, grouped list with avatars, favourite star, call action |
| Messages (`messages`) | Contacts, More | page title with action, empty state |
| Wallet (`wallet`) | Bottom nav Wallet, checkout | currency chip, tabs (Top up / Payout / Spending / Shared), balance hero, amount chips, payout card, warn and ok notes, stat tiles, empty state |
| Naara Gift (`gift`) | Bottom nav Gifts | category cards with "Soon" pills |
| Rewards (`rewards`) | Home, More | credits hero, "Ways to earn" cards, claim button, referral link |
| My Journey (`journey`) | More | tabs (Milestones / Goals / Travel), timeline, progress bars, chips |
| Clients (`clients`) → Invoices (`invoices`) | More | action buttons, stat tiles (overdue in danger tone), search, empty state |
| Developer API (`dev`) | More | stat tiles, empty state, create CTA |
| Account & privacy (`account`) | More | link row to Appearance, toggle row, export row, danger row |
| Appearance (`appearance`, `quick`) | Account, More | §18 |
| Notifications (`notif`) | Header bell | sheet with list rows |
| Admin overview (`overview`) | More (admins only) | stat tiles, revenue card, provider-health list, jobs note |
| Admin → Appearance (`admin`) | Admin overview | §19 |
Pages present in the platform but not drawn here (Referrals, KYC, order history, support, provider detail, settings sub-pages) follow the same component set; convert them in the matching batch.

## 25. Extra component hooks (every skin must style each)
`ns-balance-hero` (accent gradient card with amount, `tabular-nums`), `ns-stat` (tile with label + value; `--tone` danger/ok variants), `ns-tabs` (reuses segmented control, panels switch without reload), `ns-chips-filter` (horizontally scrolling pills), `ns-avatar`, `ns-keypad`, `ns-timeline` (done/pending nodes), `ns-progress`, `ns-empty` (dashed), `ns-toggle`, `ns-danger-row`, `ns-order-card`, `ns-provider-row`. Each needs a rule in all 9 skin files and an entry in the per-skin visual test. Money and numerals always use tabular figures. Danger uses `--nx-bad` (add: dark `248 113 113`, light `185 28 28`); it is not affected by accent.

## 26. Admin appearance (explicit)
1. **An admin has their own appearance**, exactly like any user: same table `user_appearance`, same `UpdateUserAppearance` action, same Account → Appearance page (`account.appearance`). It changes how *their* user dashboard **and** the admin panel look, and nobody else's.
2. **Platform defaults are a different function** (Admin → Appearance, §19): default skin/accent, which presets exist, lock. Changing platform defaults never alters any admin's or user's own saved choice. When an admin hits "lock appearance", the lock applies to end-users; admin accounts (`super_admin|admin`) are exempt so they can still reach and test every skin.
3. The admin layout renders the same `<html data-nx-skin data-nx-accent>` attributes via `ApplyAppearance` (§17.3) and uses the same components (§25). The Admin overview page shows a card linking to "Your admin dashboard has its own appearance".
4. **Isolation tests:** admin A's choice never affects admin B or any user; impersonation renders the target's appearance read-only (§17.4); logging out clears the mirror.
5. No admin-only skins and no admin-only tokens: the admin panel must look good in all 35 skins. Dense tables use `ns-stat`/list rows on mobile and the existing table markup on desktop, skinned by the same tokens.

## 27. Navigation unification (V9 behavior is the spec)
- **Bottom nav:** eSIMs → plans, Numbers → bento, centre → More, Gifts → Naara Gift, Wallet → Wallet. Active state follows the current section (sub-pages keep their parent active). Markup unchanged.
- **Header:** logo → Home, bell → Notifications sheet, menu → More. Admin header differences stay as they are today.
- **Sheets over pages:** Verify, Rent, Line, Appearance quick, Notifications and More open over the *page you came from*, not always Numbers; closing returns there. Admin "Flows: Modal / Dedicated page" (§6) switches those flows to full pages.
- **Cross-links:** Buy plan → Checkout → Top up → Wallet; Contacts ↔ Call abroad ↔ Messages; Clients → Invoices; Plans → Data estimator → Plans; Account → Appearance; Admin overview → Admin → Appearance.
- Every `data-go` in V9 resolves to a page (verified). Use the same route names the platform already has; map each V9 id to its existing route and report any V9 page with no matching route.

## 28. Acceptance and tests (Part C)
- [ ] `nx:coverage` reports zero offenders for the batch being shipped; remaining batches are listed, not hidden.
- [ ] Each shipped screen renders correctly in 35 skins × 6 accents × 2 modes (automated screenshot diff on the four Batch 1 screens plus the screen being added; manual spot-check of Glass, Halo and Daybreak).
- [ ] Wallet, checkout and Admin overview use `tabular-nums` and show no horizontal scroll at 320px.
- [ ] The checkout insufficient-balance state, the Wallet paused-withdrawals state and every empty state exist in all skins.
- [ ] Admin account: own appearance changes persist, do not affect others, survive platform-default changes and lock.
- [ ] Floating bottom-nav setting still works in every skin.
- [ ] Skins never change provider, wallet, pricing, order or KYC behavior.

**Daybreak (9th skin, Core group):** light-first. Pastel teal/gold/violet wash behind the app, 26/20/32 radii, round tiles, soft tone-coloured shadows, saturated Verify and balance cards. Matches the pastel look of the current light mode. In dark mode it becomes the same wash at low intensity.

## 29. Open decisions (Part C)
1. Convert Batch 2 (money screens) before Batch 1 ships, or ship Batch 1 first? Recommendation: Batch 1 first; Wallet and checkout immediately after.
2. Pages in V9 with no existing route (e.g. Naara Gift catalogue, Journey "Goals" tab): build now or ship as "Soon" cards?
3. Should Guest users on login/register pages see platform default skin only? Recommended yes.


---

# PART D: Codebase reconciliation (verified against `NaaraSim-Master_Platform_Sept_22.zip`)

Everything below was read from the repository, not assumed. Where the wireframes or earlier parts of this document disagree with Part D, Part D wins.

## 31. Verified facts that shape the build
| Fact | Where it lives | Consequence |
|---|---|---|
| Dark mode is Tailwind `darkMode: 'class'`; the choice is `localStorage.theme` (default **dark** when unset), applied by an inline pre-paint script and re-applied on `livewire:navigated` | `resources/views/components/layouts/app.blade.php`, `tailwind.config.js` | Mode preference stays on this mechanism. The DB row (§17) mirrors it; the script prefers a server-rendered `data-nx-mode` when the user is logged in. |
| `wire:navigate` morphs a fresh server-rendered `<html>`; the layout comment says attributes the server does not render are lost | same file | The server **must render** `data-nx-skin`, `data-nx-accent`, the dial attributes and the custom-accent `style` on `<html>` on every response, so morphs never drop them. Do not set them from JS only. |
| Per-user UI preferences today are **browser-only**: `nx_more_layout`, `nx_nav_floating`, `nx_nav_collapsed`, `theme` | `components/app-shell.blade.php`, `account.blade.php` | No per-user preference table exists. `user_appearance` (§17) is new. On a user's first save, seed it from `localStorage.theme`. Leave the three `nx_*` keys alone. |
| Brand colour reaches Tailwind through `--brand-primary`, `--brand-primary-dark`, `--brand-accent`, `--brand-accent-dark` (`primary` = deep teal, `accent` = warm gold) | `tailwind.config.js`, `ThemePreset::styleCss()` | User accent presets and the custom accent map to **`primary`/`primary-dark` only**, never to `accent` (gold = money). |
| There are **40 platform theme presets** (`theme_presets` table, `App\Support\ThemePreset`, body class `theme-<slug>`, default `naara-official`) with swappable header, bottom nav, login, footer and landing sections | `CLAUDE.md` Theme rules, `app/Support/ThemePreset.php` | Skins are a different, user-level layer for **dashboard inner pages**. Header, bottom nav, login, footer, landing are untouched. L0 in §13 is this system. |
| An admin glass dial already exists (`.nx-glass-tile`, `--nx-glass-opacity*`, `--nx-glass-blur`) | `app/Support/GlassmorphismSettings.php` | Do not reuse those names; Glass skin must not compound with it (§15.4). |
| The More sheet is deliberately **solid, no blur** (Android GPU hotfix) and data-driven by `BottomNav::eligibleMainItems()` | `components/more-sheet.blade.php`, `app/Support/BottomNav.php` | §15.4 rule; §33 menu contents. |
| Admin routes sit under a secret gateway prefix `{adminGateway}` (`AdminPath`), route names start `admin.`; `admin.appearance` already exists (Splash), plus `admin.theme`, `admin.dashboard-theme`, `admin.theme-toggle-studio` | `routes/web.php` | New admin page route: `admin.user-appearance`. Link to it from `admin.dashboard-theme`. Never link admin URLs from user UI (S25). |
| Font loading is build-time only; runtime font injection is forbidden (FOUT/CLS, S24) | `ThemePreset::FONT_ALLOW`, `CLAUDE.md` | The Typeface dial (§37) uses system stacks only. No web fonts. |
| Icons come from the inline sprite `resources/views/partials/icon-sprite.blade.php` with ids prefixed `i-`; emoji are forbidden | `CLAUDE.md` UI rules | See icon table below. |
| Existing CSS classes `.nx-card`, `.nx-btn`, `.nx-bento*`, `.nx-switch`, `.nx-check`, `.nx-toast`, `.nx-skeleton`, `.nx-glass-tile` | `resources/css/*` | New component hooks use `ns-*` (§14 notice). Reuse `x-ui.btn`, `x-ui.switch`, `x-ui.modal`, `x-ui.tag`, `x-ui.search`, `x-ui.skeleton-*` where they exist instead of inventing equivalents. |

**Icons to add to the sprite before use** (lucide-style stroke paths, per CLAUDE.md rule 6): `chevron-left`, `arrow-left`, `calendar`, `receipt`, `code`, `user`. Already present: shield, shield-check, hash, phone, phone-forwarded, globe, map-pin, zap, signal, chevron-right, x, bell, menu, download, info, lock, users, gift, wallet, grid, list, star, search, plus, check, heart, message-circle, send, credit-card, file-text, settings, key, package, id-card, help-circle, smartphone. Wireframe name to sprite id: `pin`→`map-pin`, `bolt`→`zap`, `bars`→`signal`, `chat`→`message-circle`, `cards`→`credit-card`, `cog`→`settings`, `file`→`file-text`, `idcard`→`id-card`, `fwd`→`phone-forwarded`, `left`→`chevron-left`, `chev`→`chevron-right`.

## 32. Page map: wireframe id → real route → Livewire class → view
| Wireframe page | Route name | Class | View | Notes |
|---|---|---|---|---|
| `home` | `dashboard` | `App\Livewire\Dashboard` | `livewire/dashboard.blade.php` + `partials/dashboard/_hero`, `_wallet` | The drawn Home is an approximation of the screenshots; convert the real partials, do not copy the wireframe layout. |
| `esims`, `esim` | `catalogue` | `Catalogue` | `livewire/catalogue.blade.php`, `livewire/catalogue/*` | Detail view is inside the catalogue component. |
| `checkout` | `checkout` (`/checkout/{plan}`) | `Checkout` | `livewire/checkout.blade.php` | Strings "Secure wallet payment", coupon field confirmed in `lang/en/checkout.php`. |
| `estimator` | `data-estimator` | `DataEstimator` | `livewire/data-estimator.blade.php` | |
| `landing` (Numbers bento), `verify`, `rent`, `line` | `numbers` | `GetNumber` | `livewire/get-number.blade.php`, `service-picker`, `country-picker` | Verify / Rent / Line are modals inside this component today (admin can switch to dedicated pages per §6). Strings confirmed in `lang/en/numbers.php`. |
| `dial` | `numbers.dialer` | `Dialer` | `livewire/dialer.blade.php` | "Call abroad" confirmed. Only when Twilio is active. |
| `contacts`, `messages` | `numbers.contacts`, `numbers.messages` | `Contacts`, `Messages` | `livewire/contacts.blade.php`, `messages.blade.php` | Also existing, not drawn: `numbers.lines`, `numbers.forwarding`, `numbers.port-in`. |
| `wallet` | `wallet` | `Wallet` | `livewire/wallet.blade.php` | "Top up" and "Payout & withdrawals" confirmed. The four tabs come from your screenshots; verify the tab labels in the view before styling. |
| `gift` | `gift-cards` (+ `gift-cards.orders`, `gift-cards.order`) | `GiftCards` | `livewire/gift-cards.blade.php` | Feature-gated: 404 until `naara_gift` is live; the bottom bar shows a "Soon" badge when not configured. Skins must not make the gated page reachable. |
| `rewards` | `rewards` | `Rewards` | `livewire/rewards.blade.php` | "Ways to earn", "Daily check-in", "Watch & earn" confirmed. Watch & earn renders only when a compliant ad provider is configured. |
| `journey` | `journey` | `Journey` | `livewire/journey.blade.php` | "Milestones", "Goals" confirmed in the view. |
| `clients`, `invoices` | `merchant.clients`, `merchant.invoices` | `MerchantClients`, `MerchantInvoices` | `livewire/merchant-clients.blade.php`, `merchant-invoices.blade.php` | **Only for active V2 merchants.** The wireframe "Developer API" page = `developer` / `DeveloperPortal`, shown only when `developer_api.enabled`. |
| `account` | `account` | `Account` | `livewire/account.blade.php` | Already contains an "Appearance" block (light/dark switch) and "Bottom menu style". **The new skin/accent/dial controls link from here.** |
| `profile`, `security`, `receipts`, `referrals`, `help` | `profile`, `security`, `receipts`, `referrals`, `help.center` | `Profile`, `SecurityCenter`, `Receipts`, `Referrals`, `HelpCenter` | `livewire/profile`, `security-center`, `receipts`, `referrals`, `help-center` | `help.center` uses its own layout (`components/layouts/help-center.blade.php`); it must declare the same Alpine scope as `more-sheet` expects (comment in that file). |
| `notif` | `notifications` | `Notifications` | `livewire/notifications.blade.php`, `notification-center.blade.php` | |
| `appearance`, `quick` | **new** `account.appearance` | **new** `Account\Appearance` | **new** | §18. |
| `overview` | `admin.dashboard` | `Admin\Dashboard` | admin views | Admins only. |
| `admin` | **new** `admin.user-appearance` | **new** `Admin\UserAppearance` | **new** | §19. |
Existing user-facing routes **not drawn** (convert in the matching batch, same components): `welcome`, `guide`, `account.verify`, `numbers.lines`, `numbers.forwarding`, `numbers.port-in`, `rewards.hunt` (+ profile), `rewards.withdraw`, `brand.get-listed`, `brand.manage`, `partner.earnings`, `merchant.apply|dashboard|earnings|white-label`, `support`, `esim.qr`.

## 33. More menu (truth from `BottomNav::eligibleMainItems()`)
Bottom bar: eSIMs (`catalogue`), Numbers (`numbers`), Gifts (`gift-cards`, only when `naara_gift` is admin-enabled and not locked; badge "Soon" until configured), Wallet (`wallet`). More sheet, in order: *(if active merchant)* My Storefront, then if V2 Invoices, Clients (prepended, so Clients first); *(if partner)* Partner earnings; Contacts; Internet calls (only if Twilio active); Help & Support; Rewards; My Journey; Developer API (only if `developer_api.enabled`); Receipts; List my brand (unless Brand Hunt feature-locked); Referrals; Estimator; Profile; Account; Security; plus admin/download/guide per `ALL_ITEMS`. The wireframe More menu now mirrors this list and tags conditional items. **"Appearance" is a new, optional More item** (§18) added through the same catalog; admins can place it from the existing bottom-nav settings (`admin.bottom-nav`). The wireframe's earlier invented rows (Home, Messages, Data estimator under that name, Admin overview as a normal row) were corrected.

## 34. Collisions and how they are resolved
1. `.nx-*` component classes already exist: new hooks are `ns-*` (§15.1 table uses these names). `--nx-*` colour tokens from §2.3 do not collide (existing `--nx-pl-*`, `--nx-bento-ink`, `--nx-glass-*`, `--nx-glow-angle` are different names). Do not define any new variable starting `--nx-glass`, `--nx-pl`, `--nx-glow`.
2. `admin.appearance` = Splash screen. New route is `admin.user-appearance`.
3. The user "Appearance" copy already exists in `lang/en/account.php` (`appearance`, `appearance_bright`, `appearance_dark`, `nav_style*`). Add new keys in `lang/en|fr/appearance.php`; reuse the existing heading, do not duplicate it.
4. `Account` page already has the theme switch: keep it working; it writes the same `localStorage.theme` and now also calls the save action.
5. `ThemePreset` (L0) keeps owning header, bottom nav, login, footer, landing, section styles. A skin never edits `theme_presets`.

## 35. Compliance with the repository's own rules (CLAUDE.md)
- **Build one batch at a time**, update `PROGRESS.md` after each (DONE list and exact next step at the top of NEXT). Do not attempt all 35 skins in one pass: ship the engine plus Surface first (§40).
- **No emoji, SVG only.** Add missing icons to the sprite properly (§31).
- **Dark mode on every element:** tokens flip by mode; any file not yet migrated keeps working through the legacy `--brand-card-*` mapping (§2).
- **Loading states on actions**, money buttons disabled in flight: unchanged by skins.
- **Tailwind compiles only classes present in Blade at build time.** Skins are plain CSS files, so new skin rules work, but any new utility class used in Blade needs `npm run build` before screenshot checks. Browser-verify every converted page at mobile and desktop widths.
- **White-label discipline:** user appearance and the admin platform-defaults page involve only this install's own users and UI (no license, oversight or other-instance content), so they ship to the master repo **and** both white-label children. Before porting, `git show HEAD:<file>` the target repo; do not assume parity.
- **S25:** the admin page lives behind `{adminGateway}`; the "Admin" More item renders for admin roles only, and no admin URL appears in any user-facing markup.
- **Accessibility:** one modal engine (S31) for the quick sheet; focus and Escape behavior match `x-ui.modal`.

## 36. Persistence and flow (final)
Resolver order: lock, then `user_appearance`, then platform defaults. The layout renders `<html class="{dark}" data-nx-skin data-nx-accent data-nx-round data-nx-dens data-nx-ts data-nx-depth data-nx-font data-nx-motion data-nx-mode style="{custom accent vars}">`. The existing pre-paint script keeps toggling `dark` from `localStorage.theme` for guests and, for logged-in users, prefers `data-nx-mode`. Save path: Livewire action `UpdateUserAppearance` (§17.4), then dispatch a browser event that updates `localStorage.theme` and the `<html>` attributes immediately, so the page changes without reload.

## 37. Personalisation dials (new, per account)
Stored as nullable strings on `user_appearance`; null = default. Applied as `<html>` attributes; skins and components read the derived variables.
| Dial | Values (default first) | Mechanism |
|---|---|---|
| Corner roundness `round` | `def`, `sharp`, `round` | `--nx-rs` multiplier 1 / .35 / 1.35. Every radius is `calc(var(--nx-r-x) * var(--nx-rs))`. Pills (999px) are unaffected. |
| Density `dens` | `comf`, `compact` | Row height 56 → 48, card padding, grid gap and note padding shrink. |
| Text size `ts` | `def`, `s`, `l` | Root `font-size` 100% / 92% / 112%. Tailwind uses rem, so everything scales. (The wireframe uses CSS `zoom` only because it is built with px.) |
| Card depth `depth` | `soft`, `flat`, `deep` | Overrides `--nx-e1`/`--nx-e2` elevation tokens. |
| Typeface `font` | `naara`, `system`, `serif` (headings), `mono` (numerals) | `--font-display` / `--font-num` set to **system stacks only** (no web fonts). `naara` = the current bundled fonts. |
| Motion `motion` | `full`, `reduced` | Disables transitions and animations app-wide; also honours `prefers-reduced-motion` regardless of the setting. |
"Reset all personalisation" nulls every dial (skin, accent and mode are separate). Admin lock forces defaults for all dials.

## 38. Custom accent colour (new)
- **UI:** the accent grid ends with a "Custom" swatch. Selecting it shows a colour picker, a hex field and **Reset to default**. Reset returns to the *platform default accent preset* (`admin.def.accent`) and clears `accent_hex`. Choosing any preset also works as a reset. The picker previews live while dragging and saves on release.
- **Storage:** `accent_key = 'custom'`, `accent_hex = '#rrggbb'` (validated `^#[0-9a-f]{6}$`, lower-cased). Setting `appearance.allow_custom_accent` (default true) lets admin disable it; when off, saved custom values fall back to the platform default without deleting the hex.
- **Readability guarantee (server-side, never trusted from the client):** `App\Support\Appearance\AccentDeriver::derive(string $hex, string $mode)` returns `teal`, `cta_a`, `cta_b`, `teal_ink`, `acc_a`, `acc_b`, `acc_c`: start from the user's colour; darken `cta_b` toward `#02121F` in 6% steps until white-on-`cta_b` ≥ 4.5:1; `cta_a` until ≥ 3:1; move `teal_ink` toward white (dark mode) or `#02121F` (light mode) until ≥ 4.5:1 against the mode's surface (`8 32 47` dark, white light); `acc_c` = `cta_b` mixed 45% toward `#02121F`. Return an `adjusted` flag so the UI can say "Adjusted slightly so buttons and text stay readable." The colour is never rejected. The JavaScript in V9 is the reference implementation; port it exactly.
- **Rendering:** write the derived triples as CSS variables in the `<html style>` attribute for the current mode (recomputed when mode changes; for `system` mode emit both and let `prefers-color-scheme` pick via two variable sets). Also set `--brand-primary` = `cta_b` and `--brand-primary-dark` = `acc_c` so existing Tailwind `primary` utilities follow the accent. Never touch `--brand-accent*` (gold).
- **Tests:** property test of 500 random hex values in both modes asserting the three ratios (the V9 audit passed 600 random colours in JS); PHP-vs-JS parity on 20 fixed colours; invalid hex rejected; custom blocked when disabled; lock overrides; isolation (user A's custom never reaches B).

## 39. Launch recommendation
Ship the engine and Naara Surface first, then enable in this order, each after the visual checks in §28: Calm, Vivid, Ledger, Noir (cheap, flat), **Clarity (enable early: accessibility value, near-zero GPU cost)** → Adire, Blueprint, Neon Lagos → Daybreak, Paper → Dot-grid, Aurora → Clay, Neo → Glass, Halo → Passport (after §44 data is confirmed) (GPU cost on cheap Android). Seed all 35 rows from day one with `enabled` only for the batch that has been verified; Admin → User appearance flips them on.

## 40. Updated acceptance additions
- [ ] Every Part D route name exists in `routes/web.php` and every `data-go` page maps to one (§32); pages marked "new" are the only new routes.
- [ ] The More menu matches `BottomNav::eligibleMainItems()` for four test users: plain user, merchant V2, partner, admin.
- [ ] No `backdrop-filter` on any sliding element in any skin (grep gate).
- [ ] `<html>` carries all `data-nx-*` attributes and the custom-accent `style` after a `wire:navigate` morph.
- [ ] Custom accent passes the contrast property test in both modes and resets to the platform default.
- [ ] All six dials apply, persist per account, reset, and are overridden by the admin lock.
- [ ] Missing sprite icons added (§31); no emoji introduced.
- [ ] `PROGRESS.md` updated after each batch.

## 41. What was and was not verified (honest scope, supersedes the earlier verification note)
Verified by script on every edition: page loads, no JS errors, no duplicate IDs, every icon reference exists, every link resolves and renders, 35 skins × 6 accents × 3 modes apply correctly, the picker lists all skins plus Custom, 600 random custom colours meet the contrast targets in both modes, all dials apply and reset, admin disable of custom accent works, guests cannot change defaults, lock overrides, Dedicated-page mode renders. Verified against the repository: routes, classes, views, labels, More menu logic, theme and preference infrastructure, repo rules. **Not verified:** pixel rendering, real-browser CSS (`@scope`, `color-mix`, `backdrop-filter`, `@property`), layout at 320 to 430px, and the Home page layout (drawn from screenshots, not from the real partials).


---

# PART E: Contrast contract and skin pipeline

## 42. The on-fill contrast contract (mandatory; fixes the Vivid bug and prevents repeats)
**What went wrong (seen on a real phone, light mode, Vivid skin):** Vivid painted cards and More rows with saturated gradients but left their children on the mode-flipping text tokens. In light mode `--nx-text-2` and `--nx-text-3` are dark, so subtitles, pills and tags became dark-on-teal or white-on-pale and vanished. Related defects of the same family: a card with no `--tone` got an invalid `color-mix` and silently rendered with no background (white text on a light page); a gold star on a gold pinned row; status colours (red "Overdue") on a red fill; white text sitting on the brightest part of a gradient.

**The contract (every skin, every component, every future batch):**
1. **Any surface with a saturated or tinted-dark fill must set the on-fill token set on that same element**, so all descendants (titles, `small`, pills, tags, chevrons, stat values) inherit readable colours:
   `--nx-text: 255 255 255; --nx-text-2: 235 247 250; --nx-text-3: 222 241 245; --nx-teal-ink: 255 255 255; --nx-gold-ink: 255 232 160; --nx-line-strong: 255 255 255; --nx-bad: 255 200 200; --nx-ok: 170 245 210; --nx-warn: 255 224 140;`
   The wireframe uses short names (`--text`, `--text2` …); production uses the `--nx-*` names. Never colour a child with a hard-coded hex to compensate.
2. **A gold (pinned) fill uses the dark-ink set** (`--nx-text: 43 32 0; --nx-text-2: 78 60 8; --nx-text-3: 88 68 16`), and icons on it (the star) use that ink, never gold.
3. **Gradients under text must be dark enough at their lightest point.** White body text needs 4.5:1. Brand-bright stops (`--nx-acc-a`) are only allowed as a corner highlight (radial, top-right, away from text); text-bearing areas use `--nx-acc-b`/`--nx-acc-c`. Vivid tone fills start at `color-mix(tone 50%, #02121F)` and end at 24%.
4. **No variable may be unset.** `:where(.ns-card){ --tone: var(--nx-teal) }` provides the default; every `color-mix`/`rgb(var(--x)/a)` that depends on a skin variable needs a fallback. A missing variable must never make a background vanish.
5. **Pills on fills** use a white 16 to 20% wash and a 70% white ring, not the semantic tint used on surfaces.
6. **Skins that keep a normal surface** (Calm, Ledger, Noir, and non-Verify cards in most skins) must not apply the on-fill set.

**Surfaces that are saturated by skin (apply rule 1):** Surface, Dot-grid, Aurora, Glass, Halo, Daybreak, Clay, Neo, Paper, Adire, Clarity, Passport, Golden Hour, Vault, Boarding Pass, Chipset, Wavelength, Airmail, Nebula → the Verify/balance/credits card; Vivid → every card, every More row, the promo.

**Verified numbers (script, worst case across six accents):** on-fill text ≥ 4.9:1 on `acc-b` for `--nx-text-2`, ≥ 4.6:1 for `--nx-text-3`, ≥ 5.3:1 for `--nx-text`; Vivid tone fills (teal, cyan, navy, warm, amber, steel) ≥ 4.78:1 for `--nx-text-3` at the 50% start. The old 68% start measured 3.03:1 and was the cause of the amber/gold failure.

**Gates (add to §28):**
- `SkinContrastScanTest`: Playwright + axe-core `color-contrast` rule on Numbers, Home, Wallet, Gift, Rewards, More and Appearance for **every skin × accent × mode** (490 combinations). Zero violations allowed; list any failing selector.
- Static lint (CI): fail if a CSS rule sets a saturated `background`/`background-image` on a surface class without the on-fill set in the same block (maintain a small allow-list for decorative layers).
- Unit test: no skin CSS may reference a variable that is not defined for the element (`--tone` default test).

## 43. Skin pipeline: ideas not built yet (ranked)
Each idea says what makes it unique, the build cost, and the main risk. Build only after the engine and the first batch ship (§39).
| # | Skin | Identity | Cost | Risk |
|---|---|---|---|---|
| 1 | ~~**Adire**~~ built (§15.1) | Indigo and cream, resist-dye geometric pattern as a quiet card texture; grounded in the Nigerian market | Low (CSS pattern) | Pattern must stay behind text; keep contrast contract |
| 2 | ~~**Neon Lagos**~~ built (§15.1) | Near-black, magenta/cyan edge light, danfo-yellow accents, glow headings | Medium | Glow on cheap GPUs; ensure neon text meets contrast |
| 3 | ~~**Blueprint**~~ built (§15.1) | Navy and cyan technical-drawing look: grid paper, dashed outlines, dimension ticks; ideal for Developer API users | Low | Thin lines on low-DPI screens |
| 4 | ~~**Vault**~~ built (§15.1) | Black and gold, guilloche line engraving on balance cards; premium-plan feel | Low | Gold used decoratively conflicts with "gold = money": keep it to borders and the Vault palette only on this skin |
| 5 | ~~**Passport**~~ built (§15.1) | Skin follows the user's active eSIM country: accent and a subtle motif change per country, stamp-style pills | Medium (needs active-country data) | Data availability; never block rendering on it |
| 6 | ~~**Golden Hour**~~ built (§15.1) | Warm gradient canvas that shifts with local time of day (auto-switch day/night palettes) | Low | Must not conflict with the explicit Mode setting; treat as a skin that picks light/dark itself |
| 7 | **Console** | Monospace, phosphor green on near-black, scanlines | Low | Readability of long text; mono only for numerals and labels |
| 8 | **Mercury** | Chrome/metallic gradients with specular highlights | Medium | Banding; hard to keep text contrast |
| 9 | **Swiss** | Extreme whitespace, large type, hairline rules, grid labels | Low | Plain; needs the dials (density/text size) to shine |
| 10 | **Fold** | Paper-fold diagonal facets (hard light/dark triangles) | Medium | Clip-path complexity |
| 11 | **Arcade** | Pixel-stepped corners, chunky borders, optional CRT tint | Medium | Needs a pixel-corner technique that still scales with the roundness dial |
| 12 | **Seasonal packs** | Naija Green (Independence Day, 1 Oct), Detty December, Ramadan lantern warm, Valentine: admin schedules a window; users may keep it afterwards | Low per pack | Colour-only packs should reuse existing skins plus a custom accent, not new CSS |
| 13 | ~~**Clarity**~~ built (§15.1) | High-contrast and large-print preset for accessibility; also helps outdoor readability on cheap screens | Low | Must exceed 7:1 and honour the dials |
Built: Adire, Blueprint, Clarity, Neon Lagos, Passport, Golden Hour, Vault. Remaining ideas: Console, Swiss, Mercury, Fold, Arcade, Matchday, Abyss, Rainfall, Stained Glass, Isometric, Newsprint, Sumi, Zen, Candy, seasonal packs (reuse existing skins plus a custom accent first).


## 44. Passport skin: country-aware accent (data contract)
- **Attribute:** the server renders `data-nx-country="ng|be|au|us|jp|…"` on `<html>` (two-letter lowercase ISO code) only when the user's skin is `passport`. For every other skin it is omitted.
- **Source of the country (do not guess):** Claude Code must locate where an active eSIM's destination country is stored (inspect the order/profile/plan models behind `catalogue` and `checkout`) and use the country of the user's most recently *active* eSIM; if none, the user's profile country; if none, the platform default country. Report which model and field were used. Compute in a small `ActiveCountryResolver`, cache per user for 10 minutes (key includes user id), and **never block or fail page render** on it (fallback to default on any error).
- **Which accents it overrides:** only when the user's accent is the platform default (`teal`). If the user picks any other preset or a custom colour, their choice wins and the country only drives the watermark. This is written in CSS as `[data-skin=passport][data-accent=teal][data-country=xx]{…}`.
- **Seeded countries in the wireframe:** Nigeria (green), Belgium (red), Australia (blue), USA (indigo), Japan (rose). Each defines `teal`, `cta-a`, `cta-b`, `teal-ink` (dark and light), `acc-a/b/c`. Measured: white on `cta-b` ≥ 5.86:1, on-fill `--nx-text-3` on `acc-b` ≥ 5.45:1. Countries without an entry fall back to the default Naara Teal accent; add a new country with one CSS block plus a contrast row in `SkinContrastScanTest` (white on `cta-b` ≥ 4.5:1).
- **Watermark:** a large faded two-letter code (`content: attr(data-cc)` on the app wrapper). Decorative only (`aria-hidden`).
- **Wireframe demo:** the top-bar dropdown "Active eSIM: …" simulates the data; it is not a user preference and must not become one.
- **Neon Lagos note:** the skyline band is a pure-CSS gradient set; keep it at 55% opacity, behind content, and hide it in light mode.


## 45. Golden Hour: time-of-day contract
- **Attribute:** `data-nx-tod="dawn|day|dusk|night"` on `<html>`, rendered only when the user's skin is `golden`.
- **Boundaries (local time):** dawn 05:00 to 07:59, day 08:00 to 16:59, dusk 17:00 to 19:59, night 20:00 to 04:59.
- **Whose clock:** the user's, not the server's. Server renders a default from the user's saved timezone if one exists (look for it on the profile; do not assume a column) else `day`. A small inline script then corrects it from the device clock before first paint, re-checks every 10 minutes and on `visibilitychange`. No network call, no flash larger than one repaint.
- **It must not change Mode.** Light/dark remains the user's setting (including `system`). Golden Hour only tints the background wash and card edge light; both modes are specified and contrast-tested (measured: dark ≥ 6.5:1, light ≥ 5.3:1 for palette text; the light wash alpha is .3 so text on the washed canvas stays ≥ 4.9:1 in the worst measured case; confirm with the axe scan).
- **Reduced motion:** the wash changes on a tod boundary with no animation. Do not add a transition.
- **Wireframe demo:** the "Time: …" dropdown simulates the clock; it is not a user preference.

## 46. Vault: foil is not money-gold
Gold (`--nx-gold`) means a money or commit action (Rent CTA, Buy CTA, Popular pill, Auto row glow). Vault's decoration uses a separate muted champagne token `--nx-foil` that is defined only inside the Vault skin. Rules: foil is used for borders, guilloche, rings and hairlines, never for text, buttons, prices or status; the money gold CTA stays bright gold in Vault; a lint (§42 static lint) fails any Vault rule that uses `--nx-gold*` for decoration.


## 47. Notes for the eight skins added in rev 10
- **Boarding Pass notches** are painted in `--nx-canvas`, so the app background in this skin is a flat canvas (no gradient). If a screen puts a card over a non-canvas colour, the notch will show; keep ticket cards directly on the page.
- **Chipset** uses `clip-path` on cards: inner box-shadows are clipped at the cut corner by design; do not add outer shadows to these cards.
- **Receipt and Handset** set `--font` to a system monospace stack for the whole app: check long labels and the keypad at 320px and with Text size Large.
- **Handset** accent has no visible effect (monochrome LCD); the Appearance page should show a one-line note when this skin is active.
- **Airmail** stripe colours are fixed decoration (red/white/blue) in both modes and are not semantic; never use them for status.
- **Prism and Wavelength** animations follow the same reduced-motion rule as Halo (§15.4). Prism reuses the `@property --ang` rotation.
- **Nebula** starfield is static CSS dots; no canvas or image.
- All eight follow the §42 contrast contract. Measured palette contrast (script): Receipt 5.55/4.95, Chipset 6.2/6.25, Handset 5.17/4.97, Nebula 6.21/6.64 (light/dark pairs as listed in the wireframe). Confirm with the axe scan.


## 48. Notes for the seven skins added in rev 11
- **Fixed decorative colours** (never semantic, never customisable by accent): Ankara band (orange, teal, magenta, indigo), Aso-oke strip (wine, cream, indigo, ochre), Airmail stripes, Danfo yellow. Lint (§42) must flag these values appearing on buttons, prices, pills or status.
- **Danfo yellow vs money gold:** they are close hues. Rule: danfo yellow only appears in the diagonal hazard stripe (8 to 9px tall) and nowhere else; the gold CTA keeps its normal gold gradient and black label. Review on a real phone: if users confuse the stripe with the commit colour, drop the stripe to the black-and-white version.
- **Canopy** overrides card radii directly; keep `calc(... * var(--nx-rs))` so the Corner roundness dial still works.
- **Tide** wave lines are inline SVG data URIs (no network). The drift animation is gated by reduced motion.
- **Pop** reuses the on-fill set but keeps the outline colour readable (dark outline in light mode, pale in dark mode); Pop Verify is a flat accent fill, so white text on `acc-b` must keep 4.5:1 (true for all six presets and the custom derivation).
- Measured palette contrast (script, worst pair): Harmattan 5.12 / 5.69, Tide 6.09 / 5.98, Canopy 6.03 / 5.58, Pop 7.02 / 6.23, Danfo 7.09 / 7.03, Aso-oke 6.0 / 5.7 (dark / light pairs as in the wireframe). Confirm with the axe scan.
