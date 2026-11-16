# Supreme Ideas CRM — Master Build Blueprint
## Phase 1 of 10 — Fork & Disconnect (revised)

**Companion to:** the NaaraSim Updater/License Authority batches (1–7), already built on the NaaraSim master codebase. Supreme Ideas CRM is a full product fork of that same master — not a white-label instance — and is intended to become the master license authority for every SaaS product Supreme Ideas sells, NaaraSim included.

**Revision note:** the original version of this phase specified a full rip-and-delete of eSIM/Numbers/GiftCards code in one pass. A real attempt at that burned a full season of usage on Sonnet without finishing — full delete requires tracing every reference across the repo before anything can be safely removed, and that tracing is what's expensive, not the deleting. This revision replaces rip-and-delete with **disconnect-first**: make the eSIM/Numbers/GiftCards code unreachable at the routing/nav/feature-flag layer, leave the actual files physically untouched, and build every later phase on top of that dormant code. Physical deletion becomes optional cleanup, done later, one folder at a time, if ever.

**Full phase roadmap:**
1. **Fork & Disconnect** ← this document
2. Generalize the License Authority (product-agnostic master registry)
3. Core Agency OS (client workspaces, AI brief intake, timelines, task board, live tracking)
4. Invoicing, Deposit-Split Payments & Confidential Handoff
5. Cohurt — Groups & WhatsApp-style Collaboration Layer
6. Cohurt — Funding, Approval & License Issuance Loop
7. Multi-Product Catalogue & Per-Product API Contract
8. CEO/Admin Analytics, AI Training Panel & Audit Log
9. Update/Patch Distribution to Installed Products (multi-product Updater)
10. Hardening Pass

---

## 0. Read this first — why disconnect-first is the correct order of operations here

A dormant, unreferenced file costs nothing at runtime. It never executes, never appears in a menu, never shows up in an API response, and never gets touched by a migration once its table is out of the active schema path. The only cost of leaving `app/Services/eSIM` sitting in the repo, unrouted, is disk space and a slightly longer `find` listing — not correctness, not security, not performance.

Full deletion, by contrast, requires Claude Code to prove a negative for every file it removes: "nothing else in this ~166MB codebase imports this class, references this table, or depends on this route" — across services, Livewire components, tests, seeders, and queued jobs. That proof-of-safety work is what consumed a full season's budget without finishing. Disconnecting is a much smaller, bounded task: find every *entry point* (routes, nav items, feature flags, scheduled jobs) that leads *into* the eSIM/Numbers/GiftCards code, and cut those specific threads. That's a search over a few dozen files, not the whole repo.

---

## 1. What to disconnect this session — entry points only

Do **not** open, edit, or delete anything inside `app/Services/eSIM`, `app/Services/SMS`, `app/Services/NCI`, `app/Services/Voice`, `app/Services/Routing`, `app/Services/GiftCards`, or their models/migrations. This session touches only the wiring *around* them:

1. **Routes** — remove or comment out every route in `routes/web.php`/`routes/api.php` that points at an eSIM/Numbers/GiftCards controller or Livewire component. A route to a controller that still exists on disk is fine to leave commented rather than deleted, if that's faster to verify — commented-out is still fully disconnected.
2. **Navigation** — remove eSIM/Numbers/GiftCards entries from the main nav, the admin sidebar, and any mobile bottom-nav equivalent. A user should never see a link that leads nowhere.
3. **Feature flags** — flip `naara_gift` and any eSIM/Numbers/verification-specific `FeatureFlags::FEATURES` entries to `default: false` and remove them from whatever admin screen lists "active" features, OR remove the array entries entirely if `FeatureFlags::enabled()` is never called from a live code path anymore after step 1 — either is acceptable, whichever is faster for Claude Code to verify in one pass.
4. **Scheduled jobs / cron** — remove any `Schedule::job(...)` or console command registration that dispatches eSIM/Numbers/GiftCards queued jobs. A dormant job class is harmless; a scheduled job that still fires and tries to call code with no live provider credentials is not — this is the one category here that can cause an actual runtime error if skipped, so don't skip it.
5. **Preloader Studio page-type registrations** tied only to eSIM/Numbers/GiftCards catalogue pages — remove the registration, leave the preset files.
6. **Marketing site** — remove eSIM/Numbers/GiftCards pages/routes from the public marketing site nav and sitemap.

## 2. What this session explicitly does NOT do

- Does not delete `app/Services/eSIM/*`, `SMS/*`, `NCI/*`, `Voice/*`, `Routing/*`, `GiftCards/*`, or their models
- Does not touch or drop any eSIM/Numbers/GiftCards database table or migration
- Does not delete tests for the disconnected features — a test that still imports a class that still exists will still pass; leave it. (If it fails because it was testing a now-unreachable *route*, either skip that specific assertion or delete just that one test file — not the whole test suite for the feature.)
- Does not attempt to verify "nothing anywhere references this" — that's exactly the expensive, unbounded search that burned the previous attempt. This session only verifies "nothing *routes to* or *schedules* this," which is a much smaller, closed set of files to check.

## 3. Rebrand pass — unchanged from the original plan

- App name, `config/app.php`, `.env.example`, mail templates, marketing site copy: NaaraSim → Supreme Ideas CRM
- Do not touch brand-color CSS token names/structure — only the values

## 4. What to keep and build on — unchanged from the original plan

Everything confirmed generic in the original audit is untouched and carried forward exactly as before: `FeatureFlags`, `PreloaderSettings`, `Wallet`/`Credits`/`Payments`/`Payouts`, `Kyc`, `Referrals`/`Merchants`/`Partners`, `WhatsApp`, `Support`, `Wizard`/`Builder`/`Journey`/`Marketing`/`Analytics`/`Maintenance`/`Backup`/`Staff`/`Social`/`Push`/`Api`/`AppExport`/`Blog`/`Brands`/`Pricing`, and the full Updater/License Authority blueprint batches (1–7).

## 5. Deliverable check — this session is not done until

- The app boots, login works, no page renders a broken/dead link to a disconnected feature
- The admin Features toggle screen shows no eSIM/Numbers/GiftCards entries
- No scheduled job attempts to fire against disconnected code (check `php artisan schedule:list` output directly — this is the fastest way to confirm step 1.4 actually worked)
- Preloader Studio still boots cleanly with no orphaned page-type registration errors

## 6. The cleanup path — later, optional, one folder at a time

Once the CRM is live and stable, and only if it's actually worth the session cost: pick **one** folder (e.g. just `app/Services/GiftCards`, nothing else) and ask Claude Code specifically to "confirm nothing routes to, imports, or schedules anything in `app/Services/GiftCards`, then delete it and its migration." One folder per session keeps the reference-tracing work small and bounded instead of repeating the all-at-once search that didn't finish. If disk clutter never actually becomes a problem, this step can be skipped indefinitely — a fork with dormant, disconnected code sitting unused is not a functional or security issue, just a cosmetic one.

---

*This revision exists because the original all-at-once deletion plan proved too expensive to complete in practice. Every other phase in this build (2 through 10) was written against the assumption that eSIM/Numbers/GiftCards code is inert and unreferenced from the live application — which disconnect-first achieves just as completely as deletion does, at a fraction of the cost. Nothing downstream needs to change.*
