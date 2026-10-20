# NaaraSim — Admin Overview: Per-Provider Live Analytics, Supreme-Overview Additions, and Spacing Polish
### Grounded in the existing NCI scoring engine and provider-health infrastructure — this is surfacing what already exists, not building a new analytics system. Master repo. For Claude Sonnet + Graphify.

---

## 0.1 — Placement: additive, appended below, nothing existing moves

Confirmed from the live dashboard directly: the current Overview page
already has, in this exact order, and all of it stays exactly as-is —
**do not restructure, restyle, or reorder any of it**: the revenue hero
card (last-30-days total, 7-day bar chart, delta-or-"first window" pill),
the revenue-split donut (eSIM data / Virtual numbers / Verification /
Naara Gift), the Cost/Gross-profit/Gross-margin trio with its footnote,
and the Oversight metrics row (Registered users, Profit this week, Profit
this month, API orders this week). Every section in this document (the
attention tile, the six supreme-overview tiles, the per-provider analytics
grid) is **appended below all of the above**, in the order given in this
document, separated by a clear visual break (a divider or section
heading) so it reads as a new, distinct layer under the existing
dashboard rather than interleaved with it. See the accompanying wireframe
(`naarasim-admin-overview-wireframe-v2.html`) for the exact intended
placement — its "EXISTING — keep as-is" section mirrors the live
dashboard's real markup precisely; only what's below its dashed divider is
new.

## Phase A — Per-provider live analytics cards on the Admin Overview

### What already exists (confirmed — reuse, don't rebuild)
- `App\Services\NCI\NciScorer` already computes a real, statistically
  sound success-rate score per provider: an exponential moving average
  over a 30-day window, with confidence scaling by sample size, and a
  `publicSuccessRate()` method already designed to be customer/admin-safe
  to display.
- `App\Support\ProviderHealth` already does live reachability checks
  across every configured provider (`checkAll()`, `cached()`,
  `unhealthy()`), cached under `providers:health`.
- The `isConfigured()` guard from the error-log/system-health blueprint
  (if that work has landed) is exactly the right filter here too: only
  show a provider on this overview if it's actually configured — an
  unconfigured provider cluttering the admin's main dashboard with a
  meaningless "0%" card is worse than not showing it at all.

### Build
1. Add a new section to `app/Livewire/Admin/Dashboard.php` — one card per
   currently-configured provider (eSIM, number, and gift-card providers
   alike), styled after the reference screenshot's density: a label, a
   small trend sparkline, a large current value, and a colored
   up/down percentage change versus the prior comparable window —
   matching that screenshot's exact information hierarchy (label → chart
   → big number → delta), not just its color palette.
2. Per card, surface: the provider's current NCI success-rate trend (a
   sparkline over the trailing 7 or 30 days, reusing `NciScorer`'s
   existing windowed data rather than computing a second trend line a
   different way), live reachability status from `ProviderHealth`, and
   order volume through that provider over the same window (a real
   business metric, not just a technical health signal — pull this from
   the actual order records already attributable to each provider).
3. Order the cards by whichever ranking already exists for provider
   priority in this codebase (if `NciScorer` or the routing logic already
   ranks providers for routing decisions, reuse that same ranking for
   display order — don't invent a second priority scheme) so the admin
   sees their most important providers first without needing to scan.
4. Verification: with at least three real configured providers, confirm
   each shows real, correct trend data (cross-check one card's numbers
   directly against `NciScorer`'s underlying data for that provider to
   confirm the display isn't drifting from the real computation), and
   confirm an intentionally-unconfigured provider does not appear at all.

---

## Phase B — Brainstormed additions for a genuinely "supreme" overview

These are proposals, not a mandate to build everything at once — sequence
them by what's already buildable today versus what depends on other
blueprints in this engagement landing first. Each one reuses
infrastructure this engagement has already designed elsewhere, which is
exactly why an overview built now can be far richer than what a
from-scratch build would allow.

1. **"Is everything actually running" tile** — once the `job_heartbeats`
   table from the System Health upgrade blueprint exists, a single
   compact tile on the main overview (not just the dedicated System
   Health page) showing "X background jobs completed successfully in the
   last hour" with a one-line note on anything currently failing — the
   admin's very first glance at the dashboard should already tell them
   whether the platform's plumbing is healthy, without a separate click.
2. **Money in vs. money out, side by side** — once the admin-earnings-wallet
   work lands, a tile distinguishing provider-wallet top-up spend (what
   you put in to stock the catalog) from platform earnings accrued over
   the same period — the exact "which number am I looking at" confusion
   flagged earlier in this engagement, solved by putting both numbers on
   the one screen an admin actually opens every day instead of a report
   they have to remember to go find.
3. **KYC automation resolution rate** — once the business-KYC automation
   work lands, a tile showing what fraction of KYC submissions the
   automated resolver handled outright versus how many escalated to human
   review — a rising escalation rate over time is itself an early warning
   signal (a provider's automated check degrading, or a new fraud pattern
   emerging) worth an admin noticing at a glance rather than discovering
   only once the review queue is already backed up.
4. **Pending admin-actionable queue, summarized** — a compact rollup of
   the same counts the Hot Menu badges show (new KYC, pending deletions,
   pending white-label approvals, open disputes) as a single "N items
   need your attention today" tile at the very top of the overview —
   complementary to the Hot Menu, not a replacement for it: this is the
   "how much is on my plate right now" glance, the Hot Menu is the
   "take me there" action.
5. **White-label network health, if you have any live instances** — count
   of active White Label instances, any currently `past_due` on recurring
   billing (from the recurring-billing blueprint), and any pending Basic-
   to-Extended balance upgrades approaching their deadline — a genuine
   "how is my reseller network doing" glance for a business model most
   single-platform competitors don't have a network to even show.
6. **Fraud/risk signal summary**, once the general fraud-signal service
   from the competitive-gap-analysis blueprint exists — a simple count of
   transactions flagged for review in the last 24 hours, trended against
   the prior period, so a spike is visible immediately rather than only
   discovered while reviewing the queue itself.

Build these in whatever order their underlying dependency actually lands
in your real build sequence — do not attempt any of items 2, 3, 5, or 6
before their respective source blueprint's work is actually in place;
item 1 and item 4 are buildable sooner since their dependencies
(heartbeats, the Hot Menu counting mechanism) are relatively self-
contained.

---

## Phase C — Card spacing and density polish

### What's confirmed
The current dashboard's metric cards use `p-6` padding with `gap-4`
between cards, and noticeably looser internal spacing between a card's
label, its chart, and its headline number (`mt-4`/`mt-6` steps) than the
tighter, denser reference screenshot, which reads as more premium
precisely because of how little wasted space it has — the eye moves from
label to trend to number with almost no dead space between them.

### Fix
1. Reduce outer card padding slightly (from `p-6` toward `p-4`/`p-5`) and
   tighten the internal vertical rhythm between label, sparkline, and
   value — this is a density change, not a redesign; the goal is the same
   content, noticeably less air around it.
2. Use Playwright to render the current dashboard and the reference
   screenshot's proportions side by side, adjust spacing values
   iteratively, and re-screenshot until the density genuinely matches —
   don't guess at exact pixel values without visually checking, per this
   engagement's established discipline for anything that's fundamentally
   a visual/rendering judgment call rather than a pure logic bug.
3. Apply the same tightened spacing to the new provider-analytics cards
   from Phase A from the start, so the whole overview reads as one
   consistent, dense, premium surface rather than old cards at one
   density and new cards at another.
4. Verification: Playwright before/after screenshots of the full overview
   page, confirming the new density and confirming nothing overflows or
   feels cramped at both desktop and mobile admin widths.

---

## Constraints

- Phase A must not introduce a second provider-scoring computation — every
  number displayed traces back to `NciScorer`/`ProviderHealth`'s existing
  data.
- Phase B items are explicitly sequenced by dependency — do not build a
  tile referencing data from a blueprint that hasn't been implemented yet;
  a broken or fake-data tile is worse than not having the tile at all.
- Phase C is a density adjustment, not a visual redesign — preserve the
  existing card content and color language, change only spacing.

## Deliverable

Per phase: confirmation of its verification step, plus for Phase B, an
explicit note on which of the six brainstormed tiles were actually built
in this pass versus deferred pending another blueprint's completion, so
nothing is silently skipped without you knowing why.
