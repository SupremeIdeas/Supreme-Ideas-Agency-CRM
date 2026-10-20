# Skin readiness report

Status as of 2026-10-03 (updated after the Batch 3-4 conversions), master repo only. Skins are **not** in `NaaraSim-WhiteLabel` or `NaaraSim-White-Label-Extented`; nothing here has been ported.
This is the document to read before deciding which skins, and under what conditions, go to the white-labels.

## Short answer

- **35 skins are built, and every one of them renders.** Naara Surface is the platform default.
- **50 of 52 member-facing views are on the skin system; 2 are still on the old styling** (listed below). A user who picks a skin sees it on the converted pages and the old look on the rest.
- **Contrast and icon visibility are scanned, not eyeballed.** Results below.
- **The free-versus-Pro gate is not enforced.** Tier labels exist in config; the access layer that checks them (Prompt 21) is not built. Today every user can pick every skin.
- **Recommendation:** do not ship Pro skins to a white-label until the access layer exists and the pending views are converted. The engine plus the five free skins could ship earlier. That is your call.

## The 35 skins and their intended tier

| Tier | Skins |
|---|---|
| Free (5) | Naara Surface (default), Clay, Dot-grid, Glass, Neo |
| Pro, plan tier 1 (10) | Calm, Vivid, Ledger, Daybreak, Paper, Noir, Handset, Receipt, Canopy, Pop |
| Pro, plan tier 2 (10) | Aurora, Halo, Boarding Pass, Chipset, Wavelength, Airmail, Harmattan, Ankara, Danfo, Aso-oke |
| Pro, plan tier 3 (10) | Adire, Blueprint, Clarity, Neon Lagos, Passport, Golden Hour, Vault, Prism, Nebula, Tide |

Tiers live in `config/appearance.php` (`access`, `min_plan_tier`). They are labels only until Prompt 21 is built.

## What has been verified, and how

| Check | Method | Result |
|---|---|---|
| Fidelity to the wireframes | Pixel-diff harness (`tests/visual/skins/diff.py`) across all 35 | All ported and compared in S2 |
| Text contrast, WCAG AA | `contrast-pages.cjs`: every text node on real converted pages, 35 skins x light/dark, pixel-sampled behind the text | 0 failures on teal across Numbers, Wallet, Catalogue, Home, Sounds, Messages and the rest of the scan set. The other five accents were scanned earlier; the two marginal combinations found (Glass dark destructive ink, Vivid "best" pill) are fixed |
| Icon visibility, 3:1 | `icons.cjs`: every visible SVG icon, sampled against the real background with the icon hidden. Disabled controls and Lottie artwork are excluded | 0 failures after this pass. Fixed: orange tile ink (all skins, light), Nebula orbs, Ankara ring pattern running through icons, Handset teal ink, Vivid pill specificity |
| Layout at phone and desktop widths | `matrix.cjs` | Re-run 2026-10-03 after the language switcher, bottom-nav and admin-overview work: 1,400 checks (35 skins x light/dark x 430/1280 x 10 pages), 0 JS errors, 0 page-level horizontal overflow. One known item: `/catalogue` at 430 reports an element poking past the viewport in every skin (a horizontally scrolling row, not a layout break) |
| Glass More menu | Solid sheet, no blur, widgets sit under it | Done and screenshotted |
| Glass dark | Mesh rebuilt with three accent blobs | Looks right in the Messages screenshot. Not yet reviewed on every page |
| Contract for new pages | `SkinContractGuardTest`, `nx:coverage`, `SKIN-CONTRACT.md` | Any view registered as converted fails the build if it hard-codes colour |

Known and documented deviations are in `CONTRAST-DEVIATIONS.md`.

## Page coverage

Converted (61 incl. widgets): My Lines partials, Wizard, notification centre and alert popup, journey launcher, country and service pickers, Nia chat and send-message composers (all via `scripts/nx_tokenize.py`, contrast-scanned on all 35 skins with 0 failures),  Admin overview (revenue hero, split donut, stat tiles, provider health and network cards; screenshotted on surface, noir, clay, pop, neon and paper, light and dark, phone and desktop), Brand Hunt (dashboard-style hero, admin-editable under Admin > Hunt hero), Brand profile, Brand manage, Get listed, Call forwarding, Guide, Identity verification, Merchant dashboard / earnings, Partner earnings, Payout dashboard, Port-in, Profile, Security center, Send earnings, Become a merchant, plus: More menu, Account, Developer API, Merchant clients, Merchant invoices, Contacts, Dialer, Notifications, Naara Gift, Appearance, Sound settings, Catalogue, Checkout, Home dashboard, Data estimator, Get a number, My Journey, Messages, My Lines (page shell only), Payout guide, Payout step-up, Receipts, Referrals, Rewards, Wallet, Withdraw, Numbers bento and the Verify / Rent / Line sheets.

Pending (1): Merchant white-label (master-only merchant purchase flow, deliberately last). Owner call 2026-10-03: the member side is finished first; admin sub-pages stay pending until the user side and the Prompt 22 / N2N work are done. Other admin sub-pages still render on the admin chrome with their own Tailwind styling; the admin shell, nav and overview take the skin.

Previously partly converted (now done): **My Lines** has its header, buttons and empty state on tokens and the new port-in card is skin-aware, but the two partials it includes (`my-connectivity`, `my-lines-analytics`, about 350 lines) still use the old colours. On dark skins the "No eSIMs yet" line in that block reads dim. It is queued with Batch 6.

Also skinned outside the page list: bottom navigation (active tab and the centre More button take each skin's CTA recipe, verified on all 35 skins; signed-in members always use the `default` nav family so the skin controls it), Nia support chat (everything except Nia's own background, as you asked), the Chat Composer Pro, notification toasts.

## Language switcher

The header chip and language modal sit on every layout and follow the skin. 14 locales ship (nine Beta); see `docs/LOCALIZATION.md` for scope and gaps. Many converted dashboard pages still hard-code English.

## Not built yet

1. **Prompt 21 access layer.** Nothing checks `access` or `min_plan_tier`. There is no "unlock" journey and no upsell on a locked skin.
2. **1 pending view** above, the other admin sub-pages (about 90 screens of tables and forms), and Nia support pages beyond chat.
4. **White-label port.** Waiting on your decision.

## Decisions for you

1. **Which skins reach the white-labels?** Skins carry no license or oversight content, so they pass the white-label discipline test on content grounds. The open question is commercial: should white-label customers get skins at all, and are Pro skins a master-only upsell?
2. **When?** My suggestion is after the access layer is built and the pending views are converted, so a white-label never ships a half-skinned dashboard.
3. **Default skin on a white-label:** Naara Surface, unless you say otherwise.
