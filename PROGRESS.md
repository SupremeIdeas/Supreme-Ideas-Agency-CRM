# PROGRESS.md — Supreme Ideas CRM Build Log (our save point)

> Read this at the start of every session. It says exactly where we stopped.
> After every task: move finished work to DONE, keep the next step at the TOP of NEXT.
> Full detail per phase: `docs/blueprint/SupremeIdeasCRM_Phase1..10_*.md`.
> We are building WITHOUT multi-agent orchestration — single developer, one phase at a time.

---

## NEXT

1. **Get Phases 2–10 blueprint docs back into the repo.** The original upload
   (`Supreme_Ideas_Agency_CRM_builds.zip`, 10 phase files) was only read into this session's
   context, not saved to disk before an environment reset cleared the scratchpad/uploads
   directory. Only Phase 1's full text survived (it's now committed at
   `docs/blueprint/SupremeIdeasCRM_Phase1_ForkAndStrip.md`, reconstructed verbatim from
   conversation context). **Ask the owner to re-send the zip** before starting Phase 2
   (Generalize the License Authority) — do not start Phase 2 from memory/guesswork about what
   it says.
2. Once Phase 2's doc is back: read it fully, confirm Phase 1's deliverable checklist still
   holds (nothing has since re-added an eSIM/Numbers/GiftCards route, nav entry, or schedule),
   then start Phase 2.
3. Rebrand pass is **partial** — only the things Phase 1 touched for disconnect/safety reasons
   were changed. `config/app.php`, `.env.example`, mail templates, and marketing copy still say
   "NaaraSim" / use NaaraSim's own name throughout. Phase 1 §3 calls for a full rebrand pass;
   it was deprioritized this session in favor of the disconnect work and verification. Do this
   before anything customer-facing ships.
4. **Verify composer/npm install + a real app boot** in a fresh environment. This session ran
   `composer install` to sanity-check the route/nav changes but the VCS-based dependency
   resolution (several packages installing via `git clone --mirror` instead of a packagist
   dist) was still running when this session's work concluded — treat the Phase 1 changes as
   statically verified (every `route()` call site touched or newly-reachable was traced and
   guarded; see DONE below) but not yet confirmed by an actual `php artisan serve` + login.
   Finish the install, run `php artisan migrate`, `php artisan schedule:list`, and click through
   login → dashboard → admin before trusting this is 100% runtime-clean.

---

## DONE

- **Fork NaaraSim → Supreme Ideas CRM, Phase 1: Fork & Disconnect (2026-11-16).**
  - Copied NaaraSim's `main` branch working tree (commit `7d062b7`, 2026-10-20, ~165MB /
    14,871 files) into this repo as a single baseline commit. No git history carried over
    (NaaraSim's own history isn't relevant to this product and the local NaaraSim clone was
    shallow, which made a true git-level fork-of-history impractical anyway) — committed as a
    working-tree snapshot instead.
  - **Disconnected** (commented out, not deleted) every eSIM/Numbers/GiftCards/NCI entry point:
    - `routes/web.php` / `routes/api.php`: the eSIM storefront + QR route, the entire Naara
      Gift storefront + admin pages + webhook, the Numbers product's My Lines/Call
      Forwarding/Dialer/Contacts/Port-In pages + their webhooks (Twilio/Telnyx voice, SMS
      inbound/delivery, Getatext), the eSIM Control Center and NCI Operations Center admin
      sections, and the entire `v1/merchant-widget` API block (sells eSIM/number/gift-card
      products via embed).
    - **Exception, kept live on purpose:** the `numbers` (GetNumber) and `numbers.messages`
      (Messages) routes. Kept features — the onboarding Wizard, Business Suite's dashboard +
      compose button, N2N chat's "text this contact" action — call these route names
      unconditionally; disconnecting them would 500 those *kept* pages instead of just hiding a
      dead Numbers link. Both render fine with zero numbers ever owned.
    - `routes/console.php`: unscheduled `providers:health-check`, `nci:recompute`,
      `nci:prune-outcomes`, `virtual:renew`, `esim:sync`, `esim:sync-usage`,
      `esim:prune-usage-snapshots`, `numbers:catalogue-sync`, `giftcards:sync`,
      `giftcards:reconcile-processing`, `merchant:client-subscriptions`.
    - `app/Support/FeatureFlags.php`: removed the `naara_connect` and `naara_gift` registry
      entries entirely (satisfies the "admin Features screen shows no eSIM/Gift entries"
      deliverable check — flipping `default` alone wouldn't have).
    - `app/Providers/AppServiceProvider.php`: removed the `numbers`/`gifts` Preloader Studio
      page-type registrations.
    - `resources/views/components/layouts/marketing.blade.php` chain: the `/naara-gift`
      marketing landing route.
  - **Fixed every render-path break this caused** (the expensive part the blueprint warned
    about doing exhaustively for *deletion* — bounded here because it's just "does this specific
    `route()` call still resolve," not "does anything reference this class"):
    - `app/Support/BottomNav.php::eligibleMainItems()` and
      `resources/views/components/layouts/admin.blade.php`: both build nav item lists that get
      rendered with an **unguarded** `route($item['route'])` in `app-shell.blade.php` — a single
      missing route here would have 500'd every authenticated page (customer, business, N2N,
      admin — all four layouts share this component). Fixed centrally: both now filter their
      item lists down to routes that still exist before returning them, rather than patching
      each call site. Also dropped the "eSIMs"-labelled primary nav slot (now "Catalogue") and
      the promoted "Numbers" primary slot — the routes stay registered for the kept-feature
      dependencies above, just not marketed in nav.
    - `resources/views/livewire/partials/dashboard/_showcase.blade.php`: this was the
      first-visit dashboard showcase — every single row in it marketed eSIM/Numbers/Naara Gift.
      Emptied rather than patched row-by-row.
    - Guarded with `Route::has()`: `app-shell.blade.php`'s `$inNumbers`-gated alternate header
      (was keying off a route-name wildcard that still matched the kept `numbers.messages`
      route), `_lines.blade.php`, `_analytics.blade.php`, `numbers-bento.blade.php` (rendered
      inside the still-live `numbers` page), `merchant-clients.blade.php` (eSIM QR image on a
      *kept* Merchants feature's client-order view), and four call sites inside
      `messages.blade.php` (the view for the kept `numbers.messages` route — unread-contacts
      empty state, the SMS "call" button in two places, "save contact").
    - Confirmed safe without changes: every other file that still references a disconnected
      route name is itself only reachable *through* a now-disconnected route (e.g.
      `contacts.blade.php`, `dialer.blade.php`, `my-lines.blade.php`, `port-in.blade.php`,
      `gift-cards/*.blade.php` are the view templates *for* the disconnected Livewire
      components — dead code, never rendered) or already self-guards
      (`MegaFooter.php`'s link-building helper already drops any route that doesn't resolve;
      `AdminHotMenu.php` already checks `Route::has()` per item).
  - Installed and ran **Graphify** (`uv tool install graphifyy`; `graphify install --platform
    claude`): `.claudeignore`/`.gitignore` entries for `graphify-out/` came over from the
    NaaraSim fork already correct. Built the graph with `graphify extract . --code-only` (local
    AST only, no LLM/API cost) then `graphify cluster-only . --no-label --no-viz` (skipped LLM
    community naming and the >5000-node HTML viz, both to avoid unnecessary spend) →
    `graphify-out/GRAPH_REPORT.md` + `graph.json`: 29,710 nodes, 82,598 edges, 2,007 communities.
  - Archived (renamed, not deleted) NaaraSim's own `CLAUDE.md`, `PROGRESS.md`, `CHANGELOG.md` to
    `docs/inherited-from-naarasim/NAARASIM-*-ARCHIVE.md` and wrote this repo's own `CLAUDE.md`
    and `PROGRESS.md` from scratch.
  - **Not done this session** (see NEXT): full rebrand pass (app name/config/.env.example/mail/
    marketing copy still says NaaraSim), and a real runtime boot verification (composer install
    was still resolving dependencies via slow VCS clones when this session's work concluded).

---

## Deliverable checklist for Phase 1 (from the blueprint) — status

- [x] No scheduled job attempts to fire against disconnected code — verified by reading
      `routes/console.php` directly (not yet re-confirmed with a live `php artisan
      schedule:list`; see NEXT #4).
- [x] The admin Features toggle screen shows no eSIM/Numbers/GiftCards entries — the two
      offending registry entries were removed from `FeatureFlags::FEATURES` entirely.
- [x] Preloader Studio page-type registrations for Numbers/Gifts removed; the Studio's own
      `BUILT_IN_TYPES` list never included them, so no orphaned-registration risk.
- [ ] **The app boots, login works, no page renders a broken/dead link** — statically verified
      (every route() call site to a disconnected name was traced; see DONE above) but not yet
      confirmed by an actual running instance. This is the one box NEXT #4 exists to close.
