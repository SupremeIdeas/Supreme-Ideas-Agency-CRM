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
3. Rebrand pass is **still partial, but the part that mattered for safe co-installation is now
   done** (see DONE below — `APP_NAME`, `DB_DATABASE`, cache/Redis/Horizon prefixes, session
   cookie, Wasabi bucket, and the Updater product identifier are all unique to this fork now).
   What's left is cosmetic, not risky: mail templates, marketing site copy, and any UI text
   that still literally says "NaaraSim" instead of "Supreme Ideas CRM". Do this before anything
   customer-facing ships, but it's no longer blocking safe deployment alongside NaaraSim.
4. **Run `php artisan migrate` + an actual login click-through.** `composer install` finished
   this session (see DONE) and the framework itself is confirmed clean — `artisan about`,
   `route:list`, `route:cache`, and `view:cache` (every Blade template in the app, not just the
   ones touched) all ran with zero errors against the real dependency tree. What's *not* yet
   confirmed: an actual DB-backed request (no migrations were run, no MySQL/SQLite configured
   in this session — `artisan about` reports the driver as `mysql` with no connection). Stand up
   a DB, migrate, seed a user, and click through login → dashboard → admin before fully trusting
   this runtime-clean beyond the framework-boot level.

---

## DONE

- **Infra-isolation pass — make this fork impossible to confuse with NaaraSim at the
  database/cache/storage level (2026-11-16).** Prompted by the owner flagging that installing
  this on the same server as NaaraSim (or any other fork of it) must never collide. Every value
  that two Laravel installs sharing infrastructure could clash on was either made explicitly
  distinct or — where Laravel/Horizon already derive it safely from `APP_NAME` — had a
  NaaraSim-specific hardcoded override *removed* so the safe derivation applies:
  - `.env.example`: `APP_NAME` → `"Supreme Ideas CRM"` (this alone drives most of the derived
    values below), `DB_DATABASE` → `supreme_ideas_crm` (was `naarasim` — a straight MySQL
    database-name collision if both installs ever point at the same DB server),
    `WASABI_BUCKET` → `supreme-ideas-crm-prod` (was `naarasim-prod` — **critical**: left
    as-is, this would have made Supreme Ideas CRM write real uploads into NaaraSim's actual
    production S3 bucket if the owner ever reused the same Wasabi account), `SUPPORT_EMAIL` and
    `NAARA_UPDATE_PRODUCT` (→ `supreme-ideas-crm-core`, was `naarasim-core` — stops an Updater
    package built for one product from being silently applied to the other).
  - **Removed, not emptied:** the `CACHE_PREFIX=` and `HORIZON_PREFIX=naarasim_horizon:` lines
    in `.env.example`. Verified directly (`env()` with an empty-but-present key returns `""`,
    not the function's default argument) that leaving `CACHE_PREFIX=` blank would have meant
    *zero* cache-key namespacing — worse than the hardcoded NaaraSim value it was disguising.
    Both now fall through to `config/cache.php`/`config/horizon.php`'s own
    `Str::slug(APP_NAME).'_...'` defaults, which is Laravel's/Horizon's documented mechanism for
    exactly this (the Horizon config file's own comment says so verbatim). Confirmed via
    `php artisan tinker`: `cache.prefix` → `supreme_ideas_crm_cache_`, `horizon.prefix` →
    `supreme_ideas_crm_horizon:`, `database.redis.options.prefix` (no override existed, derives
    the same way) → `supreme_ideas_crm_database_`, `session.cookie` → `supreme_ideas_crm_session`,
    `backup.backup.name` → `Supreme Ideas CRM`.
  - `config/cookie_consent.php` hardcoded the *old* literal cookie name (`'naarasim_session'`)
    in the consent-banner's cookie list — a real bug the `APP_NAME` change would otherwise have
    introduced (banner showing a cookie name that no longer matches what's actually set). Fixed
    to compute it the same way `config/session.php` does (`Str::slug(env('APP_NAME')).'_session'`
    via `env()`, not `config()`, since this file is itself mid-load when the array is built) so
    it can never drift again on a future rebrand.
  - Cosmetic-but-outbound-facing fallback defaults also updated for consistency, though none of
    these were cross-install collision risks (each is already overridden by the explicit
    `.env.example` value above): `config/naara.php` support email/WhatsApp-message default,
    `config/updater.php` product-identifier default + comment, `config/brand_links.php`'s
    outbound link-checker User-Agent string.
  - Re-verified the full boot/route/view suite after these config changes (same commands as the
    Phase 1 verification below) — all still pass.
  - **Not done:** `config/app.php`'s generic `'Laravel'` fallback (harmless — `.env.example`
    always sets `APP_NAME` explicitly, so this only matters if `.env` itself is missing
    entirely), and the cosmetic marketing-copy/mail-template rebrand (NEXT #3).

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
  - **Ran `composer install` and verified the framework actually boots** on the real dependency
    tree (not just `php -l` on the touched files). The agent proxy's connection to
    `api.github.com` was unreliable this session (intermittent `ws_closed_mid_exchange`
    resets — see `/root/.ccr/__agentproxy/status`), which made Composer fall back to slow
    `git clone --mirror` for most packages instead of fast dist zips; a first attempt hit a
    10-minute background-task cap mid-install, but it had already warmed the local VCS cache,
    so a second `--no-dev` pass (skips the slow PHPUnit dependency chain, not needed to boot)
    finished in well under 5 minutes. Confirmed clean: `php artisan about` (no boot errors),
    `php artisan route:list` (shows exactly `numbers` and `numbers.messages` — nothing
    eSIM/gift/NCI-named at all), `php artisan schedule:list` (no esim/giftcards/nci/numbers/
    virtual:renew/providers:health-check jobs — `provider:capability-scan`/`provider:api-watch`
    correctly still present, they're Updater/License-Authority jobs, not eSIM/Numbers-specific),
    `php artisan route:cache` (would fail on any invalid/duplicate route definition), and
    `php artisan view:cache` (compiles **every** Blade template in the app, not just the ones
    this session touched — zero errors). Not run: migrations (no DB configured this session) or
    an actual authenticated page load — see NEXT #4.
  - **Not done this session** (see NEXT): full rebrand pass (app name/config/.env.example/mail/
    marketing copy still says NaaraSim), and the DB-backed login click-through above.

---

## Deliverable checklist for Phase 1 (from the blueprint) — status

- [x] No scheduled job attempts to fire against disconnected code — confirmed live with
      `php artisan schedule:list` against the real dependency tree.
- [x] The admin Features toggle screen shows no eSIM/Numbers/GiftCards entries — the two
      offending registry entries were removed from `FeatureFlags::FEATURES` entirely.
- [x] Preloader Studio page-type registrations for Numbers/Gifts removed; the Studio's own
      `BUILT_IN_TYPES` list never included them, so no orphaned-registration risk.
- [x] **The app boots, no page renders a broken/dead link** — confirmed live: `route:list`
      shows exactly the intended route set, `route:cache` and `view:cache` (all Blade templates,
      app-wide) both compile with zero errors.
- [ ] **Login works** — not yet confirmed. No DB was configured/migrated this session, so the
      one thing not tested live is an actual authenticated request. See NEXT #4.
