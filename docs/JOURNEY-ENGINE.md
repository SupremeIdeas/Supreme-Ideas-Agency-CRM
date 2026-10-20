# Journey Engine: what exists, what is next

Blueprint: `04-JOURNEY-ENGINE.md` (J0-J6). Build log for **J0 to J4** (J4 brand flows only; business flows are blocked, see below). Everything works with **no AI key**.

## Built
| Phase | What | Where |
|---|---|---|
| J0 | `user_intents`, `journey_profiles`, `journey_channel_consents`, `journey_cards` tables | `database/migrations/2026_12_02_100000_create_journey_engine_tables.php` |
| J0 | Fixed list of 8 intents; free text and entry-source words can only **suggest** intents from that list; a suggestion is never active until the user confirms | `config/journey_engine.php`, `App\Services\JourneyEngine\IntentService` |
| J0 | First-run "What brings you to Naara?" chooser on the dashboard (skippable for 7 days) | `App\Livewire\JourneyEngine\TodayStack` |
| J0 | Signal library: live counts and states only (eSIM usage and expiry, number renewal vs wallet, missed-call COUNT, success moments). A test fails if it ever reads message bodies, voicemail, transcripts or contacts | `App\Services\JourneyEngine\Signals` |
| J1 | Rule catalog + validator (an opportunity without `evidence_fields` is refused; unknown signal/route/intent/copy is refused) | `RuleCatalog`, `config/journey_engine.php` |
| J1 | Card engine: candidates, suppression (snooze, dismissed, cooldown, dismissed-twice 60 days), opportunity caps (1/day, 3/week), holdout (10%), scoring, **one primary + two secondary, at most one suggestion** | `CardEngine` |
| J1 | Dashboard "Today" stack with "Why am I seeing this?" (the evidence), Not now / Remind me tomorrow, an attention count | `resources/views/livewire/journey-engine/today-stack.blade.php` |
| J2 (in-app only) | Protective alerts: data low (important, critical at 95%), eSIM expiring (critical in the last 6 hours), number renewal that the wallet cannot cover (critical). They outrank suggestions, ignore the suggestion caps, and a dismissed critical alert returns after a day | starter rules in `config/journey_engine.php` |

Starter rules: `install_guide`, `low_data`, `esim_expiring`, `number_renewal`, `missed_calls`, `referral_after_success`. Every threshold is a Setting (`journey.*`, 13 keys, seeded by Bootstrap); `journey.enabled` is the master switch.

## Added after J1
| Phase | What | Where |
|---|---|---|
| J2 | **Off-app delivery of protective alerts** (push and email). Guards, in order: master switch `journey.delivery_enabled` (**ships OFF**, blueprint section 11 wants consent reviewed per market first), the member's own per-channel opt-in, quiet hours in the member's timezone (21:00 to 08:00 by default), a daily cap (2), no repeat of the same alert on the same channel within 24 hours. One message per run, most urgent first. Only critical/important alerts are eligible: suggestions never leave the app. Evaluating for delivery does not count as an impression | `Delivery`, `ChannelConsent`, `JourneyAlertNotification`, `naara:journey:deliver` (hourly, watched by the scheduler health), `journey_deliveries` |
| J2 | **"Naara alerts" settings page** `/account/alerts`: two switches, both off by default, plain statement of what is and is not sent, audited. Linked from the dashboard stack | `AlertSettings`, route `account.alerts` |
| J3 | **Journey Studio** (Admin, System, super admin): switch a rule off, change its priority *within its class* (a safety alert stays a safety alert; a suggestion can only get quieter), change who it is for, rewrite its words (plain text, `:placeholders` limited to that rule's own evidence), "Who would see this" (a count over a sample, never a list), reset to shipped. The signal, route and evidence stay in code, so Studio cannot create a rule that reads private content or shows a card without a reason. Every change is audited | `Livewire\JourneyEngine\Studio`, `RuleCatalog::validateEdit`, `StudioAudience`, `journey_rule_overrides` |
| J4 | **Brand flows**: listing paused for billing (important, for any brand owner), finish your listing (suggestion, brand intent only, after 24 hours), get listed (suggestion, brand intent only, suppressed once you own a listing) | `Signals::brandListingPaused / brandSetupIncomplete / brandGetListed` |

**Business flows (J4) are not built, deliberately.** They need the Business Suite (workspaces, ring groups, IVR), which is master-only and lives on the `business-suite` branch. Shared code must not reference it, so those rules should be added on that branch (or after it merges) as `class_exists`-guarded master-only rules.

## Not built yet
- **WhatsApp delivery** (needs per-market opt-in rules; section 11), and the rest of the cross-sell matrix. A brand-new rule still needs code (a signal), by design.
- **Business flows** (see above).
- **J5:** Brain integration (`journey.next_actions`). **J6:** experiments, A/B, guardrail auto-pause, WhatsApp opt-in. Holdout assignment exists; nothing measures it yet.
- Entry-source capture on landing pages (the mapper exists, the landing pages do not set it), lifecycle stage computation, Opportunity Briefs, settings page "Naara alerts and suggestions", erasure/export wiring for `journey_*` tables.
- Abandoned-checkout and install-confirmation signals: no cart table or "installed" flag exists. `installGuide` uses "purchased, still not activated" (`activated_at` is null) and is best-effort until a real install signal exists.

## Honest caveats
- Every threshold and cap is a hypothesis (blueprint section 12), not data. The 10% holdout only pays off once J6 measures it.
- Copy exists in English only; other locales fall back to it.
- Legal review of profiling and consent per launch market is still needed before any push/email/WhatsApp delivery (blueprint section 11).
