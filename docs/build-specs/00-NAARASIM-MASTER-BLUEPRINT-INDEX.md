# NaaraSim — Master Blueprint Index
### Everything produced since the white-label licensing crisis was first discovered, in the order it should actually be executed. Give this file to Claude Code first, alongside every blueprint below — it's the sequencing map, not a blueprint itself.

---

## How to use this

Each entry names the file, states what it fixes or builds in one line, and
says what it depends on. **Work top to bottom.** Two entries are marked
🔴 CRITICAL — both are live production risks, both take priority over every
other file here, and they should be done together before anything else is
even started, regardless of which one you'd naturally reach for first.

---

## Tier 0 — Do these first, before anything else. Both are live risks, not features.

**1. 🔴 `NAARASIM-URGENT-TENANT-ISOLATION-AND-PROVIDER-VERIFICATION-BLUEPRINT.md`**
Master and a White Label subdomain were found sharing data (users, active
theme) — almost certainly a shared database/APP_KEY from a fast test
deployment. Phase 1 (isolation audit + fix) is the single most urgent item
in this entire index. Phase 2 (eSIM/Numbers provider + VPS queue
verification) is real but not urgent — do it after Phase 1 passes.

**2. 🔴 `NAARASIM-ERASURE-FIX-AND-CONTENT-BILLING-HOTMENU-BLUEPRINT.md` — Phase A only, for now**
`AccountService::approveDeletion()` currently performs an instant,
irreversible hard delete of a user's wallet transactions and orders the
moment an admin approves a deletion request — a likely compliance
exposure for a platform required to retain financial records for years.
Phase A (anonymize-and-retain, with a real deferred purge) is the other
Tier 0 item. **The rest of this file (B through G) belongs in Tier 3** —
don't do them yet, just pull Phase A forward now.

**Do both Tier 0 items, verify them, before opening anything below.**

---

## Tier 1 — Foundational: the white-label licensing boundary

Everything else in this index assumes this boundary is actually fixed.
Several later blueprints explicitly say "confirm neither child repo
carries X" — that check only means something once this tier is done.

**3. `NAARASIM-WHITELABEL-LICENSE-SURGERY-BLUEPRINT.md`**
The original discovery: White Label and White Label Extended were
carrying full license-issuer, oversight-registry, and update-distributor
code — capable of reselling the platform. Six-phase surgical removal,
with a permanent CI guard so it can't quietly come back.

**4. `SONNET-GRAPHIFY-EXECUTION-ADDENDUM.md`**
Use alongside #3, not on its own — converts that blueprint's few genuine
judgment calls into deterministic checklists, since #3 is executed on
Sonnet rather than Opus.

**5. `NAARASIM-CLAUDE-CODE-FIX-BLUEPRINT.md`**
Smaller and self-contained: syncs `.env`/`.env.example` with
`config/services.php` (several providers had no env slots at all), and
fixes `HostingGuide::phpBinary()` to share `Installer`'s safer PHP-binary
detection instead of a second, weaker implementation. Good to run
alongside #3 since it's the same "converge duplicate logic" discipline.

---

## Tier 2 — Platform-wide reconciliation (depends on Tier 1)

**6. `NAARASIM-PLATFORM-RECONCILIATION-BLUEPRINT.md`**
The big nine-phase one: theme bottom-nav parity across all 25 themes, the
"more app" showcase feature confirmed master-only, the developer-portal
fix plus a global color/contrast studio, admin menu consolidation, a real
total backup/restore system, dual-layout (modal vs. dedicated page) Bento
cards, port-in automation via Twilio (human-gated, not full autopilot),
the admin earnings wallet, and a cron/queue re-audit given all the new
background jobs this phase adds.

**7. `NAARASIM-UX-LOCALIZATION-PLATFORM-REACH-BLUEPRINT.md`**
Hero image cap (4→10), confirming link-preview customization already
works and closing its real gaps, brandable payment/social icons, real
Preloader Studio controls (duration + daily frequency cap), the company
logo showcase, honest country/KYC/payout reach audit, the one-line
microphone `Permissions-Policy` fix, and real multi-language support
(not just three "coming soon" placeholders).

---

## Tier 3 — Admin operations & money flow (depends on Tiers 1–2)

**8. `NAARASIM-ADMIN-OVERSIGHT-AND-RECURRING-BILLING-BLUEPRINT.md`**
Admin gets notified on new requests/payments/intakes (currently zero
notifications exist in that whole flow), a manual "mark deployed" gate
replaces the honor-system clock that could email a merchant "you're live"
falsely, structured one-time credential handoff, and recurring auto-debit
billing for whichever plans you flag `is_recurring` — never hardcoded
prices.

**9. `NAARASIM-ERASURE-FIX-AND-CONTENT-BILLING-HOTMENU-BLUEPRINT.md` — Phases B through G now**
(Phase A already done in Tier 0.) AI-assisted announcement composer and a
real rich-email builder (both reusing the `MarketingCopywriter`/
`AnthropicClient` infrastructure that already exists), login-notice
media, an Updater changelog system tied to a one-click announcement
builder, White Label purchase flow made approval-gated (no charge until
admin approves) plus the Basic→Extended balance/installment upgrade path,
and the admin "Hot Menu" quick-nav with live badge counts — including the
KYC-escalation count that directly plugs into blueprint #11 below.

---

## Tier 4 — Error-log fixes & the System Health rebuild

**10. `NAARASIM-ERROR-LOG-ROOTCAUSE-AND-SYSTEM-HEALTH-UPGRADE-BLUEPRINT.md`**
Root-caused three real production error patterns from actual logs: six
providers with blank credentials being hit every cycle forever (add a
real "is this configured" skip), the MAC-invalid encryption errors
(directly reinforces #1 — treat as the same investigation, not a new one),
and a stale-deployment Livewire bug (nothing to fix in source, the fix is
redeploying). Also builds the real-time, heartbeat-backed System Health
hero with honest three-state (healthy / not-configured / broken) status.

---

## Tier 5 — Frontend polish, competitive features, and new capability

These are independent of each other and can be sequenced by business
priority rather than technical dependency, except where noted.

**11. `NAARASIM-ANNOUNCEMENTS-WALLET-KYB-BLUEPRINT.md`**
Two selectable announcement presentation styles with sitewide deep links,
confirms shared-wallet notifications already work (email/push already
fire — only a live in-session toast was missing) and confirms the shared-
cap debit path is already safe, and automated business-KYC (KYB) document
verification to replace manual review. Its KYC-escalation count is what
Tier 3's Hot Menu badge (#9) surfaces.

**12. `NAARASIM-FRONTEND-UX-FIX-BLUEPRINT.md`**
Ten screenshot-grounded bugs: the notification popup and chat-input
styling get a Playwright-first diagnosis (since prior fix attempts didn't
stick — treat repeated failure as a signal to diagnose differently, not
retry the same way), homepage header/hero overlap, banner image cropping,
Bento card text overflow and dark-mode icon contrast, confirming the AI
marketing-copy generator already exists, and making promotional banners
placement-configurable (marketing page or dashboard, admin's choice).

**13. `NAARASIM-COMPETITIVE-GAP-ANALYSIS-BLUEPRINT.md`**
What's genuinely missing versus Twilio/Stripe/Airalo-tier platforms:
outbound webhooks to merchants (the biggest gap — nothing tells a
merchant's system when their own order completes), fraud scoring
relocated from gift-cards-only to the actually-higher-risk numbers/eSIM
flows, a merchant SDK, and sandbox/test-mode API keys. Phase B is
strategic positioning ideas (the multi-provider-failover reliability
story, the affiliate economy, the white-label network as a moat) — read
and prioritize yourself, not a Claude Code task list.

**14. `NAARASIM-PROVIDER-CAPABILITY-RADAR-BLUEPRINT.md`**
A new system: watches your providers' own API evolution (changelogs,
OpenAPI diffs, or response-schema drift, whichever each provider actually
publishes) and uses Claude to generate reviewable "here's what this could
let us build" proposals in the Updater section — detection and triage
only, never auto-implementation. Independent of everything else; build
whenever it's convenient.

**15. `NAARASIM-ADMIN-OVERVIEW-ANALYTICS-BLUEPRINT.md`** (+ `naarasim-admin-overview-wireframe-v2.html`)
Per-provider live analytics cards reusing the existing `NciScorer`/
`ProviderHealth` scoring engines, six "supreme overview" tiles (several of
which — money-in-vs-out, KYC resolution rate, White Label network health —
depend on #6, #9/#11, and #8 respectively, so don't build those specific
tiles before their data source exists), and a card-density fix. Everything
appends **below** the existing revenue/oversight dashboard sections — the
wireframe shows exactly where the line is; nothing existing moves.

**16. `NAARASIM-FINAL-HARDENING-ROLE-MASS-ASSIGNMENT-FIX.md`**
Small and standalone — `role` sits in `User::$fillable`, a latent
privilege-escalation risk. Safe to do anytime, including in parallel with
anything else here; doesn't need to wait for any other tier.

---

## What this whole index adds up to

Tier 0 stops the bleeding. Tier 1 closes the boundary that made the
White Label business model actually safe to sell. Tiers 2–4 make the
platform behave correctly and observably. Tier 5 is where it starts
pulling ahead of category leaders instead of just catching up to them.
Don't skip ahead to Tier 5 for the exciting parts before Tier 0 and 1 are
verified done — every blueprint after them was written assuming they
already landed.
