# NaaraBrain — On-Demand Skills Catalog
### Built from the FULL text of Business Suite Prompts 10, 11, 13–19 and N2N Batches 3–14 (not just headers).
**Companion to:** `NAARABRAIN-MASTER-BLUEPRINT.md` (security + runtime spec) and `NAARABRAIN-V2-IF-NAARA-WERE-MINE.md` (build order + pricing). **This file = everything a user can ask Brain to do on demand, per Naara product area, and what each skill needs underneath.**

**Evidence labels:** `SPEC` = stated in your prompt/batch documents. `CODE` = verified in NaaraSim-main earlier (SupportTools, NaaraCareAgent, AnthropicClient, BrandPlanSeeder). `UNVERIFIED` = not checked. Prompts 13–19 and B3–B14 are specs; **whether each is merged is not something I can see**, so every pack below is feature-flagged and lights up only when its dependency exists.

---

## 0. What the full read changed in my plan

| Discovery in the documents | Change to the Brain plan |
|---|---|
| **P17 (AI Voice Agent)** defines tools like `check_order_status`, `book_callback`, `transfer_to_human` that must call "the same underlying service a human support flow already calls", reusing what `SupportChat`'s AI assistant queries. **P15** already commits to the Anthropic API for call intelligence. | Don't build three tool systems (NaaraCare, Voice Agent, Brain). Build **one Naara Action Layer** (§1). Every AI surface is a *persona* over the same scoped tools. |
| **P13**: acting scope is `session('active_wallet_group_id')`, authorization is Spatie **team-scoped** permissions (`$user->can('manage_billing', $walletGroup)`), writes spend from the **group owner's wallet** with the member's `spend_cap_*`. Roles: owner / admin / agent / viewer; family groups don't expose roles. | Brain must run in **"Personal" or "Workspace X"** mode, resolved server-side, and every org tool re-checks the same permission + cap. Brain can never exceed the human's role. |
| **P15/P17/P16**: all provider/AI calls are wrapped in `WalletService::charge()` with a `Closure` and automatic refund on failure, and `VoiceDialerService` uses quote → hold → settle. | Meter Brain with the **same wrapper**; don't invent a billing path. (B5's `UsageMeter` stays for N2N message/media/call credit; Brain compute rides `WalletService::charge()`/usage credit. Pick one ledger path in Sprint 0 — see §9.) |
| **P18** adds `contacts:read, calls:read, messages:read, webhook:subscribe` scopes and outbound HMAC-signed webhooks (`call.completed`, `message.received`, `call.recording_ready`). | These events are a **ready-made Brain trigger bus** for businesses, and Brain can *configure* integrations on the user's behalf (T2). Also a future path for "Brain as an API" using the same scopes. |
| **P10/P11 are trust & gap features** (refund guarantee, success score, eSIM compatibility, mobile money labels, auto-renew consent, tax line, spam block, voicemail, shared wallets, localization, porting, blacklist pre-check). | Huge **explain-and-act** surface: users will ask "why was I charged", "will this work on my phone", "block this number". Brain answers from the user's real records and the public-safe projections only. |
| **P11 §4**: localization (FR, PT, AR) is a planned, large effort. | Brain is **multilingual from day one** (replies in the user's language, RTL-safe output) — a cheap win while string extraction is still in flight. |
| **B14/B13**: kill switches, legal hold, erasure, minors policy, reminders with exactly-once. | Brain packs inherit these; each pack has a kill switch; planner reminders are a Brain action target. |

---

## 1. The Naara Action Layer (the one idea that organizes everything)

```
                 ┌──────────────── Personas (who is talking) ───────────────┐
                 │ NaaraBrain chat │ Ask-Brain sheets │ Automations │ NaaraCare (support) │ AI Voice Agent (P17) │ future: API/MCP │
                 └───────────────────────────┬───────────────────────────────┘
                                             ▼
                         BrainContext {user, activeScope: personal | workspace(wallet_group_id, role, caps), persona, taint, budget, locale}
                                             ▼
                    ToolRegistry (packs, flags, tiers, per-persona allow-lists)
                                             ▼
        Existing services only: CreditService, WalletService::charge(), VoiceDialerService, MessageSenderService,
        IdentityResolver, N2N send pipeline, WalletGroupService, CallFlowResolver, Webhook/ApiClientService, SupportTools
```

Rules that fall out of the documents:
1. **One action = one service call that already exists.** If a human UI path exists, Brain calls that service, so policies, caps, logging and refunds are identical. (P17's own rule, generalized.)
2. **Persona allow-lists.** NaaraCare keeps `grant_goodwill_credit`, `resolve_ticket`, `refresh_number_code`. User Brain never gets them. Voice Agent gets a tiny read-heavy subset (`check_order_status`, `book_callback`, `transfer_to_human`). Brain Free gets T0 only.
3. **Scope resolution is never model input.** Personal mode queries start at `$user->relation()`; workspace mode starts at the `WalletGroup` and re-checks `can(..)` + `spend_cap_*` (the exact P13 §4 order: role check in front of the existing cap check).
4. **Output honesty guard** (generalize `SupportReplyGuard`): Brain's reply may not claim an action that the executor didn't perform (refund, number change, message sent).

---

## 2. How a user asks (interaction patterns)

- **Command**: "Renew my Ghana eSIM." → proposal card → approve → hand-off to checkout (T3 never silent).
- **Question over own data**: "Why was I charged $4.20 yesterday?" → ledger + order lookup → plain-language explanation.
- **Watch**: "Tell me when anyone in the sales workspace gets a pricing question on WhatsApp." → automation spec → dry-run → enable.
- **Report**: "How did my line do this week?" → numbers + narrative + CSV.
- **Setup assistant**: "Set up my business phone for Mon–Fri 9–5, after hours go to voicemail." → drafts a call flow → shows diff → applies after approval.
- **Teach/explain**: "What does 'fair usage' mean on this plan?" → plan facts from the catalog (global data), no user data.
- **Proactive nudge ("Brain noticed…")**: eSIM about to expire, credit low, brand profile stale, unanswered request, auto-renew about to charge. All off-by-default categories can be muted.

Brain replies in the user's language; keeps answers short with a "Do it" button when an action is possible.

---

## 3. SKILL PACKS (on-demand help per product area)

Tier key: **T0** read · **T1** reversible write · **T2** sends/spends/changes visible state (approval card; bounded standing permission possible) · **T3** money/identity/destructive (always approved + step-up; often hand-off to real UI) · **N** not exposed in v1.
Dep = what must exist. ESTIMATE costs reuse v2 §8 assumptions (Haiku ≈ 0.1¢ per classification, Sonnet ≈ 1–3¢ per agentic request); no measured data yet.

### Pack 1 — Account, Wallet, Credits & Billing explainer (CODE + P10 + P11 §3 + B5)
| User asks | Brain does | Tier | Notes |
|---|---|---|---|
| "What's my balance / where did my money go?" | wallet + NaaraCredit (both buckets) + ledger summary by category | T0 | B5 buckets: loyalty vs usage; never mix in explanations |
| "Why was I charged / refund me?" | finds the transaction, explains it, shows the **refund policy that actually applies** (e.g. auto-refund when OTP didn't arrive, per P10 §1 and `PollSmsOtpJob`) | T0 | **Brain cannot refund.** It can open a ticket with context (T2) or escalate; goodwill stays support-only |
| "Top up / buy credit" | explains options, labelled **mobile-money rails actually reachable** for their country (P10 §4), then deep-links checkout | T3 hand-off | Brain never handles payment details |
| "Buy N2N credit from wallet" | proposal with $→credit preview (B5 peg) | T2/T3 | one-way conversion warning shown |
| "Share credit with Ada" | resolver validation, caps display, approval | T3 | B5 caps; gift-received credit not re-shareable (state explicitly) |
| "Show pending gifts / expiring in 24h" | list + countdown | T0 | B5 escrow |
| "Is there a tax/fee on this?" | explains the checkout line (P10 §6) | T0 | |
| Auto-renew: "What renews next and when? Turn off auto-renew for number X" | reads renewal schedule (`RenewVirtualNumbersCommand` data); toggle after confirmation | T0 / T2 | P10 §6 consent surfaces must exist first |
| Spend digest | weekly "where credit went" + projection (labelled estimate) | T0 | |

### Pack 2 — Buying & choosing: eSIM, Numbers (Verify/Rent), Lines (CODE + P10 + P11)
| User asks | Brain does | Tier |
|---|---|---|
| "I'm going to Ghana 2 weeks, need ~5 GB, which plan?" | catalog recommendation, data estimate (`estimate_data` exists in SupportTools), fair-usage disclosure, compatibility prompt | T0 |
| "Will this work on my Pixel 8?" | device check via `check_device_compatibility` (exists) — warning not block (P10 §3) | T0 |
| "Which country/service gets OTPs fastest for WhatsApp?" | **public-safe success rate only** (P10 §2 `publicSuccessRate`), with the min-sample rule; never internal NCI risk/confidence | T0 |
| "My OTP never came" | checks the order state/timeout, explains auto-refund, `refresh_number_code`/resend remain **support-persona only** unless you decide to expose a bounded self-serve version | T0 (T1 optional) |
| "Where's my eSIM QR / how do I install it?" | `get_my_esim_setup` + manual LPA steps (exist) | T0 |
| "How much data left / when does it expire?" | usage + expiry; low-data watch | T0 |
| "Port my number in/out" | **explain only** until the P11 §5 provider audit is done; then guided checklist (documents/LOA) | T0 → T1 |
| "Is this permanent number clean?" | shows blacklist-check result once P11 §6 exists | T0 |
Proactive: eSIM expiring/low data; renewal reminders; "number about to be released".

### Pack 3 — Naara Line messaging & unified inbox (P16 + P11 §1–2)
Channels per spec: SMS, WhatsApp (24-h free-form window vs templates), Telegram, Instagram, Email.
| User asks | Brain does | Tier | Spec-driven guardrails |
|---|---|---|---|
| "Summarize everything I missed today" | cross-channel brief | T0 | one brief, not per-feature polling |
| "Reply to Ada on WhatsApp saying …" | drafts; checks **24-hour window**; if outside window it must use a pre-approved template or say it can't | T1→T2 | never fabricate a template; Meta rules |
| "Who's texting me spam?" / "Block this number" | per-user block (always) + **Report as spam** into the shared threshold system | T1 | P11 §1; Brain doesn't change thresholds |
| "Don't disturb me, auto-reply 'back at 9'" | template-only auto-reply, capped | T2 standing perm. | STOP/UNSUBSCRIBE handling stays as-is |
| Voicemails | list, transcript summary, "call back" draft | T0/T1 | P11 §2 voicemail appears in inbox |
| Lead capture | inbound matching intent → follow-up item (+ CRM row if integration on) | T1 | |
| Opt-out hygiene | "who opted out?" read-only | T0 | Brain never re-subscribes anyone |

### Pack 4 — N2N Chat (B6, B12, B3, B9)
| User asks | Brain does | Tier |
|---|---|---|
| "Find that message where Ada sent the address" | scoped conversation search | T0 |
| "Summarize my chat with Ada" | reads one conversation (consent scope) | T0 |
| "Message John: …" | `IdentityResolver` (DTO only, no phone/email back) → draft → approval with **cost preview (0.5 credit)** through the B6 pipeline (block, link filter, charge) | T2 |
| "Invite my friend Kemi with 20 credit" | invite flow + pending gift (B5) with sender referral link | T3 |
| Pin/star/mute/archive, chat theme choices | execute B12 per-user prefs | T1 |
| "Export this chat" / "delete selected media for me" | B12 export (text only) with privacy notice; storage manager | T1/T2 |
| Message-request triage | list requests, label likely-spam, **accept/decline/block only on user say-so** | T0/T1 |
| Privacy dashboard: "who can message/call me? hide last seen" | read + toggle settings | T0/T1 |
| Identity: "claim username / copy my Naara ID" | availability check + claim (B3 constraints, race handled by DB) | T2 |
| Phone link: "link my number for discovery" | **guides to the OTP flow**; Brain never receives the code | T3 hand-off |
| Media: "Why can't I send a photo?" | explains Naara Pro gating (B7) + shows unlock path | T0 |
| Connections: "who follows me / follow back" | B9 graph; follow-back action | T0/T1 |
| Updates ring: "update my brand's Updates" | gallery coach (below) | T1 |

### Pack 5 — Calls (N2N B10/B11 and PSTN dialer)
| User asks | Brain does | Tier |
|---|---|---|
| "Who called me / missed calls" | recents | T0 |
| "Call back Ada" | **hand-off button** into the call UI (Brain does not place calls in v1) | N→hand-off |
| "Why did my call end?" | explains reason: low credit, heartbeat loss, etc. from `end_reason` | T0 |
| "How much did calls cost this month?" | hold/settle history summary | T0 |
| Call policy: "only connections can call me, DND 10pm–7am" | B10 Phase F settings | T1 |
| Group call setup: "host a room Friday, max 5 speakers, create link" | B11 + planner event; link expiry/approval options; **billing reminder: speakers pay while appointed** | T2 |

### Pack 6 — Planner & follow-ups (B13)
"Schedule a call with Ada Fri 4pm" (event card for approval; invitee count shown) · "Remind me to call John Monday" (follow-up) · "What's on my agenda?" · RSVP chaser · `.ics` / Google Calendar link generation · timezone-safe confirmations ("4pm WAT = 3pm in London"). T1 for self reminders, T2 when invitees are involved.

### Pack 7 — Brand & directory (B7–B9, brand subscription)
| User asks | Brain does | Tier |
|---|---|---|
| "Write my bio/tagline/captions" | copy suggestions, diff preview before applying | T0 → T2 |
| "Am I within my limits?" | explains profile-type limits (general vs music, images/videos/links) and **why the 6th image fails** | T0 |
| "Add my Spotify/Apple Music links" | validated against `music_platforms` host allow-list; rejects look-alike domains with explanation | T1/T2 |
| "Create an album smart link" | B8 album + OG preview checklist | T2 |
| "Why isn't my ring showing in Updates?" | checks conditions: Pro active? connected? gallery has images? visibility setting? | T0 |
| "Is my Pro about to lapse / what stops?" | renewal date + effects (media sending, CTAs, Updates ring) | T0 |
| "Show Follow-Hunt progress" | read-only handle/reward stats; **never edits reward/verification fields** | T0 |
| Brand Pulse | weekly: followers, Updates views, CTA taps if tracked | T0 |
| Brand Concierge (optional) | answers inbound requests from brand FAQ only, labelled AI, handoff | T2 |
| Local profile privacy | "who sees my email/phone/DOB?" → explains the visibility matrix; toggles per-field reveals | T0/T1 |

### Pack 8 — Business workspace & team (P13, wallet groups)
Only in `kind = business` workspaces for roles that allow it; family groups get the simple spend-cap helper only.
| User asks | Brain does | Tier / role |
|---|---|---|
| "Who's in my workspace / their roles / caps?" | member list with roles and `spend_cap_*` | T0 (owner/admin) |
| "Invite Ada as agent with $50 cap" | creates the invite through the **existing invite path** with role + cap; email confirmation | T2 (owner/admin) |
| "Remove Tunde / change cap" | proposal + approval | T3 (owner) |
| "Who spent what this month?" | `spent_by_user_id` attribution report | T0 (owner/admin/viewer by perm) |
| "Verify our business email" | explains why/how (P13 §3), triggers verification mail | T1 |
| "Switch to my Acme workspace" | session scope switch (UI hand-off) | T1 |
| Spend guard | alerts when a member nears cap or wallet is low | proactive T0 |

### Pack 9 — Business phone setup & operations (P14)
Setup help is where Brain beats everything else in the suite: a plain-English → **drafted call flow** (menus, queues, ring groups, hours, voicemail), shown as a diff in `CallFlowBuilder` terms.
| User asks | Brain does | Tier |
|---|---|---|
| "Set up: press 1 sales, 2 support, after hours voicemail" | proposes `call_flow` + nodes (strict config JSON per node type), business hours schedule with IANA timezone, ring group; **dry-run explanation** ("a call at 8pm Sunday goes to voicemail") | T2 (admin/owner) |
| "Why did that call go to voicemail?" | traces the flow decision using call log + `call_flow_node_id` | T0 |
| "How many calls did we miss after 5pm?" | `LogCallEventJob` data | T0 |
| "Pause my phone tree for the holiday" | status active/paused or holiday override | T2 |
| Sequential vs simultaneous advice | explains tradeoff, applies on approval | T2 |
Constraints in spec: Twilio-provisioned numbers only; Telnyx numbers out of scope → Brain must say so, not improvise.

### Pack 10 — Call recordings & intelligence (P15, P17)
| User asks | Brain does | Tier |
|---|---|---|
| "Summarize last call / what did I promise?" | transcript → summary, action items, follow-ups | T0 (+T1 create follow-ups) |
| "Which calls were negative this week?" | sentiment from `call_intelligence_summaries` | T0 |
| "Coach my team" | talk/listen ratio + coaching notes aggregates (only if diarization data exists; otherwise says it can't) | T0 (owner/admin) |
| "Turn recording on/off for my number" | org default on / personal opt-in; **shows the consent/legal notice**, doesn't decide compliance | T2 |
| AI Voice Agent: "review what the agent handled", "tune its greeting/hand-off rules" | session outcomes, edit `ai_voice_agent_config` drafts | T0 / T2 |
Brain's own call-summary compute is **separate from** the transcription/agent minutes P15/P17 bill through `WalletService::charge()`.

### Pack 11 — Developer & integrations (P18)
"Create an API key for Zapier with read-only scopes" (T3: shows secret once, never logs it) · "Add a webhook for new messages" (T2) · "Why did my webhook fail?" (delivery status, retry state from the portal) · "What scopes do I have?" (T0). Brain never prints an existing secret and never grants scopes beyond what the user selects.

### Pack 12 — Trust, safety & privacy self-service (P10, B14, B3)
Block/report from natural language; "who can see my profile"; "export my data" (APP-EXPORT) and "delete my account" are **explained and handed off** (T3, not executed by Brain); incident explainers ("why was my message blocked?" → link filter explanation); minors/consent copy is never overridden.

### Pack 13 — Help, navigation, localization (P10 §5, P11 §4)
`suggest_navigation` (exists) generalized to deep links across every screen · "How do I…" over the **global** help index (separate from user data) · escalate to human with an auto-written context summary (exists as `escalate_to_human`, add the summary) · answer in French/Portuguese/Arabic (RTL-safe) before the UI is fully translated, with a banner that UI strings may still be English.

---

## 4. Cross-product "do it for me" workflows (Brain's real edge)

These chain packs. All are previewed as a plan with total cost, then approved once (each T2/T3 step still binds to its own approval token).

1. **Morning Brief (all channels)**: N2N + Line/WhatsApp/Telegram/IG/email unread, missed calls, voicemails, pending requests, today's events, eSIM/credit alerts → ≤10 bullets with action buttons.
2. **Trip Mode**: "I'm traveling to Kenya on the 12th" → check device compatibility → recommend eSIM → remind renewal → set an "away" auto-reply → add a follow-up to top up wallet → daily data-left nudge.
3. **New Business Setup**: create/verify workspace → invite team with roles and caps → buy/assign a number → draft call flow with hours + voicemail → turn on recording per policy → connect omnichannel → set up the daily team digest. (Each step hands to the real UI where money or identity is involved.)
4. **Lead-to-Follow-up**: inbound pricing question on any channel → classify → create follow-up + draft reply → RSVP/schedule call → after call, summary + next-step reminder → CRM webhook fired (if connected).
5. **Credit Rescue**: low usage credit while a call/message is blocked → shows Buy / Ask-a-friend / share-from-wallet, drafts the ask message to a friend (T2), tracks pending gift.
6. **Brand Launch**: bio + gallery + music links + album page + Updates ring check + Pro status → readiness checklist with one-tap fixes.
7. **Spam Defense**: detect repeated unknown senders → propose block list + report → weekly "blocked this week" report.
8. **Monthly Business Review**: calls, missed rate, response time, spend by member, top topics, recommended changes (hours, ring strategy).

---

## 5. Proactive nudge engine ("Brain noticed…")
Event sources already implied by the documents: `credit.low`, `esim.expiring`, `esim.low_data`, `renewal.upcoming`, `pro.lapsing`, `call.missed`, `voicemail.received`, `message.received` (per channel), `webhook.failing`, `gift.expiring`, `event.reminder`, `member.near_cap`, `wallet.low`, `number.release_soon`. Rules: user-controlled categories, daily nudge cap, quiet hours, no nudge that reveals sensitive content on a lock screen, nudges are free (no model) unless a summary is requested.

---

## 6. Workspace-aware behavior (critical, from P13)
- Brain always shows **"Acting in: Personal / Acme (admin)"** at the top of every conversation and action card.
- Org data is queried by `wallet_group_id`, personal by `user_id`. **Never mixed in one answer** unless the user asks for a combined view and holds both scopes.
- `viewer` role: Brain is read-only. `agent`: own assigned numbers/inbox/dialer, no seat or billing tools. `admin`: seats/numbers/call flows, no group deletion. `owner`: everything including billing owner change (always T3).
- Spending in a workspace uses the owner's wallet **and** the member's cap; Brain shows "this will use $X of your $Y cap" before approval.
- A member's personal chats/credit are never visible to workspace admins through Brain. Only workspace resources are.

---

## 7. What Brain must NOT do (explicit)
Place PSTN or N2N calls in v1 · take payment details · see or output OTP codes the user didn't ask to view in their own order (and never other users') · refund or grant credit (support-only, human or bounded autopilot) · change password/phone/email/username without T3 step-up through the real UI · delete the account or workspace · alter Follow-Hunt rewards/verification · modify NCI scoring or routing · read or reveal any other user's profile beyond the resolver DTO · message anyone not named by the user · say "E2EE"/"private" · claim a legal-compliance answer (recording consent, porting rules) — it flags and escalates · show another workspace's data.

---

## 8. Build mapping (extends v2 sprints)
| v2 Sprint | Added from this catalog |
|---|---|
| 0 — Meter | decide single metering path (§9); merge Claude clients; usage log |
| 1 — Runtime | Action Layer + persona allow-lists; BrainContext with `activeScope`; **Packs 1, 2, 13** (all read-only on existing CODE tools) → fastest visible value |
| 2 — Digests | Morning Brief, Spend digest, eSIM/credit/renewal nudges (Pack 1/2 + §5) |
| 3 — Watch engine | Packs 3–4 triggers; spam defense; recipes |
| 4 — Approvals | N2N send, SMS/WhatsApp reply, planner events, brand diff, auto-renew toggle, workspace invites |
| 5 — Business packs | Packs 8–11 behind P13–P18 flags; call-flow drafting; call intelligence; webhooks/API keys |
| 6 — Launch gate | per-pack kill switches, persona tests, workspace-role isolation tests, red team |

New tests this catalog requires: **role matrix** (owner/admin/agent/viewer × every workspace tool), **scope-mixing test** (personal vs workspace never leak), **persona allow-list test** (support tools absent from user Brain), **handoff test** (T3 never executes inside Brain), **reply-honesty test** (no claimed action without executor record).

---

## 9. Pricing impact
- The catalog adds **breadth, not per-task cost**: most skills are T0 reads or deterministic actions (0–3¢ ESTIMATE). Heavy ones are summaries (call/inbox) and call-flow drafting (~3–6¢ ESTIMATE).
- Business packs justify the **Brain Business** tier ($79 per workspace in v2) because they replace admin time (call-flow setup, team digests, call intelligence). Consider letting a business owner's allowance be **shared across seats by role** (agents consume from the pool, viewers read-only) with per-member caps reusing `spend_cap_*`.
- **Don't double-charge**: transcription and AI voice-agent minutes are billed via `WalletService::charge()` per P15/P17; Brain's summary of them is a separate small allowance item. Make that visible in the cost preview.
- **Metering decision (Sprint 0):** your documents use two money mechanisms (`WalletService::charge()`+Closure for provider calls; B5 `UsageMeter` hold/settle for N2N credit). Pick one for Brain compute, reuse the other for N2N actions, and show **one** line in the UI.
- Free tier recommendation updated: Brain Free covers **Pack 1, 2 and 13 read-only help** (balance, charges explained, eSIM/number guidance, navigation) — this is exactly what NaaraCare already deflects, so it should reduce support cost. Pro tiers add watchers, digests, writes and business packs.
- Prices still **unpublished until Sprint 0 measures real cost**.

---

## 10. Decisions this catalog adds for you
1. **Self-serve vs support-only:** should Brain be allowed a *bounded* "resend OTP/eSIM setup" (NaaraCare has it today) or keep it support-only?
2. **Brain places calls?** v1 says hand-off only. Confirm.
3. **Workspace data sharing:** should owner/admin see Brain-generated summaries of agents' conversations? (privacy + legal; default no for chats, yes for call analytics on business numbers.)
4. **Language promise:** announce FR/PT/AR Brain replies now, or wait for UI localization?
5. **Concierge** (brand-facing AI) — still the one feature that speaks to third parties; v2 or later.
6. **Which packs ship first:** my order is Packs 1, 2, 13 → 3, 4, 6 → 7 → 8–11 as P13–P18 land.
