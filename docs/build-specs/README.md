# Build specs (permanent context)

The numbered NaaraSim build prompts ("NAARA-BUILD-N"), committed here so they
survive session compaction and are always available to Claude Code and the team.

| File | Scope | Runs after |
|------|-------|-----------|
| `NB5-risk-reconciliation-compliance-sweep.md` | Provider-health alerting, financial reconciliation, scaling triggers, App Store payment compliance, final regression sweep | Builds 1–4 |
| `NB6-wizard-pagebuilder-social-follow-lottie.md` | Wizard `naara_connect` fix, page-builder public render, social-follow-to-earn + Brand Partner Hunt, Lottie wiring | Builds 1–5 |
| `NB9-brand-directory-subscriptions.md` | Self-service **paid** brand-directory listings — extends BUILD-6's brand tables (they go hand in hand) | Build 6 |
| `NB12-homepage-audience-tabs.md` | Homepage "Who Naara Is For" audience-tabs section (admin-orderable) | Build 6 (uses homepage section system) |
| `NB-elevenlabs-widget-and-password-toggle.md` | Admin ElevenLabs Convai widget (+ backup provider for downtime) & show/hide password toggle | Standalone |
| `PROMPT21-EXT-merchant-v2-license-tiers.md` | Merchant V2 self-service license: seeded 4-tier plan catalog, balance-to-Extended upgrade, platform earnings wallet, plan carousel, resell-status threshold gating. Owner's own extension of the uploaded `NAARA-PROMPT-21-MERCHANT-V2-SELF-SERVICE-LICENSE.md` — supersedes only its §4.1 | Standalone (master-repo money path) |
| `PROMPT21-EXT2-project-intake-form.md` | Post-purchase project intake form (desired brand name, WhatsApp, 3-way hosting decision + encrypted credentials), admin review (Mark Seen), admin-set autopilot deploy-progress timeline, PDF handoff export, deploy-ready email | PROMPT21-EXT (needs an active license to exist) |
| `00-NAARASIM-MASTER-BLUEPRINT-INDEX.md` | Sequencing map for the whole post-white-label-licensing-crisis blueprint set (Tiers 0–5) — not a blueprint itself, read this first to know execution order | — |
| `TIER4-error-log-rootcause-system-health.md` | **DONE (2026-09-21).** Root-caused 3 real production error patterns (unconfigured-provider spam, MAC-invalid session/tenant-isolation errors, a stale-deployment Livewire bug) + shipped a real heartbeat-backed System Health hero (3-state: healthy/not-configured/broken) | Tier 4 #10 — depended on Tier 0/1, already landed |
| `TIER5-announcements-wallet-kyb.md` | **PENDING.** Two announcement presentation styles + sitewide deep-link CTA library, shared-wallet live in-session toast (email/push already confirmed working), automated business-KYC (KYB) document verification | Tier 5 #11 |
| `TIER5-provider-capability-radar.md` | **PENDING.** New system: watches provider API evolution (changelog/OpenAPI-diff/response-schema-drift) and uses Claude to generate reviewable, evidence-linked upgrade proposals in the admin Updater section — detection/triage only, never auto-implementation | Tier 5 #14 — independent, build whenever convenient |
| `TIER5-admin-overview-analytics.md` (+ `TIER5-admin-overview-wireframe-v2.html`) | **PENDING.** Per-provider live analytics cards (reuses `NciScorer`/`ProviderHealth`, no new scoring), 6 "supreme overview" tiles (several gated on other tiers' data existing first), a card-density/spacing polish pass. Everything appends below the existing revenue/oversight dashboard — nothing existing moves | Tier 5 #15 |

Progress is tracked in `../../PROGRESS.md`; platform state in `../PLATFORM-STATE.md`.
`NAARASIM-FINAL-HARDENING-ROLE-MASS-ASSIGNMENT-FIX.md`, `NAARASIM-UX-LOCALIZATION-PLATFORM-REACH-BLUEPRINT.md`, and
`NAARASIM-ERASURE-FIX-AND-CONTENT-BILLING-HOTMENU-BLUEPRINT.md` (Tier 5 #16, Tier 2 #7, and Tier 0 Phase A + Tier 3 #9
Phases B–G respectively) are already fully shipped — see PROGRESS.md's DONE section — and were deliberately not
re-stored here.
