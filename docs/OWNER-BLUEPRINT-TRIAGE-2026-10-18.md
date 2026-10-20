# Owner blueprint triage, 2026-10-18

Source: the four blueprints the owner sent on 2026-10-18 (Urgent fixes and Owner Mode, Provider-aware resolvers, Founder-mode QA, Menu icons and NaaraChat UI/UX).
Status key: **FIXED NOW** (this change) / **DONE BEFORE** (already shipped; confirm in the owner walkthrough) / **OPEN**.
"WL" = both white-label forks (`NaaraSim-WhiteLabel`, `NaaraSim-White-Label-Extented`). Master-only items are never ported (CLAUDE.md shipping discipline).

## Blueprint 1: urgent fixes

| Item | Status | Master | WL | Notes |
|---|---|---|---|---|
| A. NaaraChat bubbles escape the chat / sit over the composer | FIXED NOW (master only) | yes | no (N2N is master-only) | Reproduced and root-caused: the video-call Beauty looks sheet reused the bubble class `.ncb` with `position:absolute; bottom:0`, so every bubble stacked over the composer on every wallpaper and theme. Sheet renamed `nc-beauty*`; guard test `ChatBubbleLayoutGuardTest`; the White Label resume widget no longer floats over a conversation. Before/after in `test-results/n2n-chat-layout/`. |
| B1. Shield (cookie launcher) floats over app screens | FIXED NOW | yes | yes | Banner and shield render on public pages only; "Cookie settings" is in the More sheet. Earlier master fix (PR #197) now extended to every signed-in shell via `$bodyClass`. |
| B2. Widget Dock (drag, edge snap) | FIXED NOW (master only) | yes | no | One dock for Brain, Journey, WhatsApp, Wizard, quick Naara chat and the White Label resume widget: pointer drag (grip, or long-press on touch), left/right edge bands only with snap on release, clamped to the safe area, bottom nav and composer, auto-stacking, keyboard move (grip, arrows, Enter), per-device plus account sync, `NaaraWidgets.register()` registry, Reset in Account > Appearance. Playwright pass at 360/390/768/1440. |
| C. Blog share modal opens/hangs on read | FIXED NOW | yes | yes | Removed the `pushState`/`popstate` pair that fought `wire:navigate`; modal closes on `livewire:navigate`; listener removed on destroy. Opens only from the Share button. |
| D. Marketing floating nav hides at the footer | FIXED NOW | yes | yes | IntersectionObserver on the footer; nav fades out and goes inert while the footer is on screen. Verified at 390px on pricing/contact (opacity 1 -> 0 -> 1). |
| E. Footer blends into the illustration, browser colour matches | FIXED NOW (master only) | yes | no | `AuthFooterTone` drives footer, illustration feather and `theme-color`. Sign-in illustration is master-only. |
| F. Payout settings save bar covers the bottom nav | FIXED NOW | yes | yes | Bar docks above the bottom nav (safe-area aware), content gets matching bottom padding. Business Suite settings already had this; payout page now matches. |
| G. Gift card pricing/catalogue/logo wizard | FIXED (2026-10-18) | yes | yes | Logo wizard + pack shipped; popular-first ordering shipped; admin Pricing now has Gift cards markup % and minimum profit (they were hard-coded settings with no screen). |
| H. Verify/Rent/NaaraLine pickers, country resolver, admin layout | FIXED + PROBED (2026-10-19) | yes | yes | Country list follows real provider coverage (`NumberCatalogue::offerableCountries()`). Admin > Numbers cards: each of Naara Verify, Naara Rent and Naara Line has an **Opens as** choice, Modal (pop-up sheet) or Dedicated page, saved per card with Save card. Browser probe (`scripts/funnel/numbers-modes.mjs`): Modal opens the sheet over the bento; Dedicated page replaces the bento with the flow full page at `/numbers?modal=key`; no overflow at 390 px. Same code and tests in both forks. |
| I. Business Suite navigation + Setup Wizard | PARTLY DONE (2026-10-18); entry fixed 2026-10-19 | yes | no | Navigation verified earlier. Every Business Suite link (header button, More sheet, More-apps tile) now goes straight to the dashboard (`/business/workspace`) for anyone with a business workspace and to the public marketing page only for everyone else (`BusinessNav::entryRoute`, `BusinessEntryRouteTest`). Setup Wizard built (profile, channels with guides and Test and connect, team, call flows, recording, AI assistant, integrations; plan gating; self-test). Still open: guide screenshots, GIF/scheduling/refill/notification steps, per-tier fresh-account run. |
| J/K. Provider truth audit, eSIM end to end | DONE BEFORE | yes | generic parts | `docs/PROVIDER-LANE-AUDIT-2026-10-13.md`; Quibity client rebuilt, Airalo pagination fixed. Confirm in the owner walkthrough with live keys (read-only). |
| L. API version-watch in the Updater | FIXED NOW (master only) | yes | no | Updater > API versions tab. Daily queued read-only check (`provider:api-watch`, 06:40) of 21 providers: pinned version from the configured base URL, Deprecation/Sunset/version headers, and next minor/major probes judged against a nonsense-version control so a catch-all host never raises a false alarm. Findings stay until acknowledged and return only if they change. First live run: all reachable providers up to date, Ubigi did not answer from the sandbox. |
| M. Cron/queue proof | DONE BEFORE | yes | yes | Scheduler heartbeats shipped earlier (`job_heartbeats`). Confirm on the real server. |
| N. Payouts sandbox lock removed | DONE BEFORE | yes | yes | Honest payout health shipped in Engineering v3 phases 2-3. |
| O. Owner Mode run | OPEN | | | Runs last, with the owner's keys. |

## Blueprint 4: menu icons and NaaraChat UI/UX

| Item | Status | Notes |
|---|---|---|
| Custom icon set on More sheet, mega menu, floating nav, bottom nav, admin menus, hot menu | FIXED NOW (master only) | Per-slot resolver with admin override, light/dark pair per slot, generic sprite fallback. |
| More sheet clipped labels, shield overlap | FIXED NOW | Three-column grid, two-line labels, short names, no shield in signed-in shells. |
| Mega menu icons crowding text | FIXED NOW | 44px icon tile, two-line clamp. |
| Naara ID in the bottom bar | FIXED NOW | After Profile when N2N is on. |
| Admin icon override page | FIXED NOW (master only) | Admin > Menu icons: customer, marketing and admin menus, search, replace (WebP/SVG/PNG up to 512 KB) and reset per slot, through the existing `IconOverrides` store. |
| Hero art for Naara ID / NaaraCredit / Chat pages | FIXED NOW (master only) | Ten drawn illustrations (`nc-*`) on every Naara chat empty state and settings header, plus a section chip row on the inner pages. |

## White Label purchase

| Item | Status | Notes |
|---|---|---|
| Card payment "confirming your payment" state | FIXED NOW (master only) | Banner + 3 s poll after the gateway returns, no second top-up while pending, toast on credit, 10 minute timeout message, "I did not complete the payment" escape. The WL funnel is master-only, so there is no fork port. |

## Confirm later (owner walkthrough)

- Real Paystack test-key card payment through the purchase page (needs the keys resent).
- Footer/theme-colour blend on a non-default theme at the login screens.
- Hold-to-record mic on a real phone.
