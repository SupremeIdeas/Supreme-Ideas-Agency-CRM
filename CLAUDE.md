# CLAUDE.md — Supreme Ideas Agency CRM Project Brief

> Claude Code reads this file automatically at the start of every session.
> It is the permanent context for this project. Do not delete it.
> The phase blueprints are `docs/blueprint/SupremeIdeasCRM_Phase1..10_*.md` in this repo.
> This codebase is a **fork of NaaraSim** (SupremeIdeas/NaaraSim, commit `7d062b7`, 2026-10-20).
> **Never push to, or otherwise modify, the NaaraSim repo from this project.** This fork exists
> so Supreme Ideas CRM can reuse NaaraSim's generic infrastructure (wallet, payments, KYC,
> merchants, support, theming, admin shell, the Updater/License-Authority system, etc.)
> without touching the source repo in any way.

---

## HOW WE BUILD (read this first)

- **You are the single developer.** No multi-agent orchestration. Build **one phase at a time**,
  in the order listed in `docs/blueprint/` (Phase 1 → 10), and in `PROGRESS.md`'s NEXT section.
- **Never start a phase whose dependency phase isn't finished** and passing its own deliverable
  checklist (each blueprint file ends with one).
- **After every task, update `PROGRESS.md`** (move finished work to DONE, put the exact next
  step at the top of NEXT) — same discipline NaaraSim used.
- **Test money paths on SANDBOX keys only.**

## WHY THIS FORK EXISTS

NaaraSim sells eSIM data plans and virtual/verification phone numbers. Supreme Ideas CRM is a
**full product fork of that same codebase** — not a white-label instance of NaaraSim — intended
to become Supreme Ideas Agency's own internal agency-operations platform (client workspaces,
briefs, timelines, invoicing, group collaboration, and eventually the master license authority
for every SaaS product Supreme Ideas sells, NaaraSim included). See
`docs/blueprint/SupremeIdeasCRM_Phase1_ForkAndStrip.md` for the full rationale.

**Owner:** Frank Charles Ebubedike, Supreme Ideas Agency, Onitsha, Nigeria.

## DISCONNECT-FIRST, NOT DELETE-FIRST (Phase 1 — done, 2026-11-16)

Phase 1 is complete. eSIM/Numbers/GiftCards/NCI code was **disconnected, not deleted**: routes
commented out, nav entries removed, the two eSIM/gift feature flags removed from the registry,
provider-dependent scheduled jobs unscheduled, and two Preloader Studio page-type registrations
removed. The actual service code (`app/Services/eSIM`, `SMS`, `NCI`, `Voice`, `Routing`,
`GiftCards` and their models/migrations) is **untouched on disk** — it is dormant, not deleted.

**Two exceptions kept deliberately live** (`numbers` and `numbers.messages` routes): the
onboarding Wizard, Business Suite's dashboard/compose button, and N2N chat's "text this
contact" action all call these route names unconditionally. Disconnecting them would have 500'd
those *kept* features instead of just hiding a dead link, so `GetNumber`/`Messages` stay
reachable — they're just not marketed anywhere (no nav entry, no dashboard showcase card).

**Do not re-enable eSIM/Numbers/GiftCards navigation, feature flags, or scheduled jobs** without
re-reading `docs/blueprint/SupremeIdeasCRM_Phase1_ForkAndStrip.md` §2 first — it lists exactly
what this phase does *not* do (does not delete the service folders, their models, their DB
tables/migrations, or their tests).

**The optional cleanup path** (physically deleting a dormant folder) is one folder per session,
only after confirming nothing routes to, imports, or schedules anything in it — see
`SupremeIdeasCRM_Phase1_ForkAndStrip.md` §6. Not urgent; dormant code is a cosmetic cost, not a
correctness or security one.

## Tech stack (inherited from NaaraSim — do not substitute)

- **Laravel 12** (PHP 8.2+), TALL stack: **Livewire 3** + **Alpine.js 3** + **Tailwind 3.4** (`darkMode: 'class'`)
- **MySQL 8** (utf8mb4, strict) · **Redis** (queue/cache/session) · **Horizon**
- **Sanctum** + **Fortify** (2FA/TOTP) + **Spatie Permission** (roles)
- **Wasabi S3** for all file storage (never local disk)
- Deploy: BOTH VPS and shared cPanel, installable like a CodeCanyon product

## MONEY-SAFETY RULES (non-negotiable — carried forward from NaaraSim, still binding)

These rules governed NaaraSim's Wallet/Payments/Payouts system, which Supreme Ideas CRM keeps
and builds on (invoicing, deposit-split payments, Cohurt funding — see Phases 4 and 6). They
apply to every money path in this codebase, old or new:

1. **Golden Rule:** Retail/charge amounts flow one way: quoted price → charged price. Never
   reversed, never silently adjusted downward after the fact.
2. **Never expose cost/net prices** in any user-facing response, template, or payload where a
   margin exists.
3. **All prices flow through the pricing engine.** No price literals scattered in code.
4. **Wallet debits/credits atomic.** DB transaction + `lockForUpdate()`; write
   `wallet_transactions` with `balance_before`/`after`.
5. **Never charge without delivering.** Orphan-charge guard: charged but downstream save
   failed → auto-refund + release.
6. **Money actions never blind-retry.** Failed order/debit → refund + alert. Idempotency keys
   on money-moving jobs.
7. **Every external API call is a queued job** (Horizon, retry+backoff) — never synchronous in
   the request cycle.
8. **Verify every webhook** with HMAC + `hash_equals()` before touching the payload. Idempotency
   on provider refs.
9. **No secrets in code.** Keys in `.env` via `config()`.

## UI RULES (non-negotiable — carried forward)

- **SVG icons only — no emoji anywhere.** Inline sprite (`resources/views/partials/icon-sprite.blade.php`).
- **Dark mode on every element.** Every bg/text/border/shadow has a `dark:` variant (or uses the
  skin-token system — see below).
- **Every action shows a loading state.** Money actions disable their button while in flight.

## FEATURE SWITCHES AND SKINS (carried forward, still binding)

- **Every new screen is skin-aware from its first commit.** Build it from the `x-nx.*` kit and
  `--nx-*` tokens (no hex, no Tailwind palette classes, no per-element `dark:` colour), wrap
  pages in `<x-nx.page>` (inside a single plain Livewire root).
- **A switched-off feature never answers a bare 404 to a person.** Use `App\Support\ComingSoon`
  / `feature.live:<key>` middleware for a friendly "Coming soon" sheet. Webhooks/API/JSON/
  Livewire updates/images/POSTs stay a plain 404.

## Graphify Rules

Graphify maps this codebase into a queryable knowledge graph under `graphify-out/` (gitignored —
see `.gitignore` / `.claudeignore`). Built with `graphify extract . --code-only` (no LLM, no API
cost) + `graphify cluster-only . --no-label --no-viz` (29,710 nodes / 82,598 edges as of the
Phase 1 fork commit). Use it as the primary architectural navigation layer for this large,
highly-interconnected platform.

- Before broad multi-file exploration or cascading searches, use Graphify when architectural
  relationships would materially speed up the investigation.
- Consult `graphify-out/GRAPH_REPORT.md` for entry points, architectural boundaries, dependency
  relationships, and the most-connected files ("God Nodes").
- Use `graphify query "..."`, `graphify explain "X"`, and `graphify path "A" "B"` when the graph
  answers a relationship question faster than a broad repository search.
- Use Graphify to decide **where** to start, then read the actual source directly — never a
  substitute for reading implementation code, tests, config, schemas, or docs.
- Do not blindly modify a highly-connected file. Before changing one, inspect its callers,
  dependencies, side effects, tests, and downstream impact — this is doubly true for the
  money-path hubs (`WalletService`, the pricing engine) and the `User`/`Setting` god nodes.
- Prefer the smallest relevant search scope when the target is already known.
- When the graph is stale (code changed since built — compare `git rev-parse HEAD` to the
  report's build commit), run `graphify update .` before relying on it (no API cost, no LLM).
- The community labels are placeholders (`Community N`) — built with `--no-label` to avoid any
  LLM cost. Run `graphify label .` later if named communities become worth the spend.

## Inherited documentation

- `docs/inherited-from-naarasim/NAARASIM-CLAUDE-ARCHIVE.md` — NaaraSim's own CLAUDE.md at the
  moment of the fork. Historical reference only; superseded by this file for this repo.
- `docs/inherited-from-naarasim/NAARASIM-PROGRESS-ARCHIVE.md` — NaaraSim's full build log
  (9000+ lines) up to the fork point. Explains *why* a given piece of inherited infrastructure
  looks the way it does; not a to-do list for this project.
- `docs/inherited-from-naarasim/NAARASIM-CHANGELOG-ARCHIVE.md` — ditto, NaaraSim's changelog.
