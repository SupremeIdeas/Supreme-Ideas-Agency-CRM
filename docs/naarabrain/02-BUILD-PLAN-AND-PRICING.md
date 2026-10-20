# NaaraBrain v2 — "If Naara were mine" build plan
### Grounded in the real repo (NaaraSim-main, Sept 22 snapshot), the Business Suite prompts 10–19, and N2N batches 3–14.
**Companion to** `NAARABRAIN-MASTER-BLUEPRINT.md` (v1 = full spec + security model). **This file = what I would actually build, in what order, what it can automate, and what I'd charge.** Where v2 disagrees with v1, v2 wins.

**Evidence labels:** `VERIFIED` = read in your code/zips this session. `ESTIMATE` = my arithmetic from stated assumptions; replace with measured numbers from Sprint 0 telemetry. `UNVERIFIED` = not checked.
I read a thin slice only (AI services, SupportTools, NaaraCareAgent, brand plan seeder, Business Suite prompt headers). Nothing here claims knowledge of code I did not open.

---

## 1. What I found in the repo (and why it changes the plan)

| Finding | Status | Consequence |
|---|---|---|
| `App\Services\AI\AnthropicClient`: single-turn Messages call, key via `services.anthropic.api_key` (admin ProviderKeys or .env), model from config (default `claude-sonnet-5`), `enabled()` guard, degrades gracefully | VERIFIED | Gateway exists. It has **no tools, no streaming, no prompt caching, no usage capture**. |
| `App\Services\Support\ClaudeChatModel`: tool-use calls, `max_tokens` 1024, **hard-coded** `anthropic-version`, 60 s timeout, no usage capture | VERIFIED | Two divergent Claude clients. Merge into one gateway (Sprint 0). |
| `NaaraCareAgent`: bounded tool loop (`MAX_STEPS = 6`), multimodal attachments, `SupportReplyGuard` (blocks the model claiming an action it never performed) | VERIFIED | **The agent runtime already exists.** NaaraBrain should be NaaraCare generalized, not a greenfield build. |
| `SupportTools` is constructed as `new SupportTools($user, $conversation)` and reads through relations like `$this->user->esimOrders()->whereKey($id)` | VERIFIED | The right isolation pattern is already in production: **user bound at construction; queries start from the user's relations, never `Model::find($id)`**. Make it a hard rule + CI test for every Brain tool. |
| `SupportTools` also exposes `grant_goodwill_credit` (capped, 1 per ticket, 1 per user per 7 days, admin switch `SupportAutopilot`), `refresh_number_code`, `resend_esim_setup`, `resolve_ticket` | VERIFIED | Good guardrail style. But these are **support-only** tools: they must not be inherited by user-facing Brain. Separate registries. |
| Tools return raw arrays containing real integer ids (`'id' => $o->id`) and the model passes ids back | VERIFIED | Fine for support. For Brain I'd switch to opaque per-run handles (v1 §3.3 L4). |
| No token/usage logging anywhere AI is called (only a `Dashboard` hit on a usage-like key) | VERIFIED (grep) | **We cannot price Brain honestly until we measure.** Telemetry is Sprint 0, before any feature. |
| Pro = brand subscription plans. Seeder: **Starter Reach $19, Growth $49, Spotlight $99 per month** (`BrandPlanSeeder`; production values may differ) | VERIFIED (seed), prod UNVERIFIED | Add-on pricing anchors to these. |
| Business Suite prompts 13–19 define: Organizations via generalized `WalletGroup`, Business Phone (IVR/queues/ring groups, Twilio-only), Call Recording + Intelligence, **Omnichannel Inbox** (WhatsApp/Telegram/Instagram/Email on `MessageThread`/`InboundMessage`), AI Voice Agent (Twilio ConversationRelay), CRM/API scopes on `ApiClient` (`contacts:read`, `calls:read`, `messages:read`, `webhook:subscribe`), native softphone | VERIFIED (headers only) | Brain's biggest value is **sitting on top of these** (summaries, routing, follow-ups). Build Brain packs so each lights up when its underlying prompt ships. |
| `VoiceDialerService`/`VoiceCall` quote → hold → settle pattern; `CreditService` with locks and ledger; `CurrencyService`, `PricingEngine`, `DiscountMarginGuard` | VERIFIED (named in your docs) | Reuse for Brain metering and margin floor. Do not invent a second billing path. |
| `AppStudio` is a webview wrapper | VERIFIED (per P19) | Brain UI is web-first; push for alerts; no native-only dependencies. |

**Master-only:** per `CLAUDE.md`, Business Suite and N2N are master-only. NaaraBrain is **master-only by default** (white-label review in the last sprint, nothing ported).

---

## 2. My thesis (how I'd run Naara if it were mine)

1. **Brain is not a chatbot bolted on. It is the "operator" of the user's account.** Chat is just one remote control. The product is *outcomes that happen while the user is away*: replies drafted, follow-ups created, expiries caught, reports delivered.
2. **Deterministic first, LLM only where language is.** Rules, SQL and events do 90% of the work. Claude is called to *understand* (classify/summarize) and *compose* (draft), never to decide who gets money or who is messaged without a guardrail.
3. **Cheap by construction.** Haiku for classification, Sonnet for conversation/drafting, top model only on explicit "think harder". Cache the static prompt. Batch the non-urgent work. Pre-filter so most events never reach a model.
4. **One runtime, many tool packs.** Support (NaaraCare), Chat, Line/Omnichannel, Calls, Brand, Wallet/eSIM, Org, Planner. A pack is loaded only when the intent needs it and its feature flag is on.
5. **Trust is the moat.** Every action visible in a Journal, every spend previewed, every risky action approved, zero cross-user data. If Brain ever leaks once, the product is dead, so isolation tests block deploys.

---

## 3. Architecture deltas from v1 (what I would change)

| v1 said | v2 decision | Why |
|---|---|---|
| Build `AnthropicClient` anew | **Merge** `AnthropicClient` + `ClaudeChatModel` into `BrainModel` (one gateway). Add: usage capture to `ai_usage_log`, `cache_control` on system+tools, retry/backoff, model router, per-call budget, configurable `anthropic-version` and timeouts | Both exist but diverge; neither measures cost |
| New agent loop | **Extract** the loop from `NaaraCareAgent` into `BrainAgent`; NaaraCare becomes `persona=support` + support tool registry | Reuse proven loop, step cap, reply guard |
| Opaque handles everywhere day 1 | Day 1: keep relation-scoped queries (already safe). Add handles when T2/T3 tools ship | Don't slow the first release; the relation rule already blocks cross-user reads |
| Streaming first | **Non-streaming first**, show step progress. Streaming is a Sprint-5 polish on VPS only | Streaming adds Reverb coupling; value is cosmetic; cPanel can't do it anyway |
| Automations compiled from NL by the model | Same, but **ship recipes first** (§5): one-tap templates with 2–3 fields. The NL builder comes after recipes prove demand | ClickUp's reported weakness is setup effort; recipes remove it |
| Separate `brain_*` mega-schema | Start with 6 tables: `brain_threads`, `brain_messages`, `brain_runs`, `brain_automations`, `brain_approvals`, `ai_usage_log`. Add memory/digests tables when their sprint lands | Smaller blast radius |
| Brain-specific credit grant only | Brain draws from a **monthly Brain allowance (usage credit grant, B5 pattern)**, overage from the user's usage credit | Reuses `UsageMeter`; keeps one ledger |

Everything else in v1 (isolation layers, taint tracking, approval-hash binding, kill switches, erasure/export coverage, red-team suite) stands.

### Team workspaces (Prompt 13) — the one place isolation gets harder
Org data is **shared by role**, so "only this user's data" becomes "this user's data **plus what their role in this org may see**". Rule: org-scoped tools take an explicit `OrgScope` resolved server-side from `WalletGroupMember` role; never from model input. Org owners get aggregated reports; members get their own activity. Brain usage in a workspace bills the group wallet and respects `spend_cap_*` per member. Ship org packs **after** personal packs are proven.

---

## 4. How Brain automates efficiently — the pipeline

```
Event (message, call, SMS, order, expiry, cron)
  → Rule match (SQL/PHP, free)                     ← 90%+ of events stop here
  → Prefilter (sender known? keyword? quiet hours?) ← most of the rest stop here
  → Haiku classify/extract → constrained JSON      ← ~0.1¢, no tools
  → Deterministic action (notify / digest / tag / reminder / template reply)
  → Sonnet only if a human-quality draft or answer is needed
  → Approval card if the action sends, spends or shares
  → Journal row (what, why, cost, undo)
```

Efficiency levers (all measurable in Sprint 0 telemetry):
- **Prefilter before the model.** E.g. "unknown sender + contains pricing words" is a regex; Haiku only labels the survivors.
- **Prompt caching** for the static system prompt + tool schemas (cache reads are billed at a small fraction of input; see §8 pricing).
- **Batch API** for digests, weekly reports, backfills (billed at roughly half; latency is minutes-to-hours, which is fine for a morning brief).
- **Tool packs by intent**: a tiny router picks 1–2 packs so the tool schema stays small.
- **Output caps** per task type; summarize from *structured rows* not raw history; truncate message bodies.
- **Dedup + exactly-once**: unique `(automation_id, event_id)` so retries never double-bill or double-send.
- **Budgets at three levels**: per run, per automation per day, per user per month. Hard stops, plain-language notice.

---

## 5. Automation catalog (the "recipes")

Each recipe = trigger → filter → action, with **tier** (T0 read, T1 reversible, T2 sends/spends with approval, T3 money/identity), **model class**, and **ESTIMATE cost per run** (§8 assumptions). Recipes marked ◇ depend on a Business Suite prompt shipping.

### A. Chat & inbox (N2N, B6/B12)
| # | Recipe | Trigger → Action | Tier | Model | ~Cost/run |
|---|---|---|---|---|---|
| A1 | **Important-message watch** | new N2N message from unknown/saved-contact filter → classify (urgent/pricing/complaint/other) → push only if urgent | T0/T1 | Haiku | 0.08¢ |
| A2 | **Inbox Brief** | daily 8:00 → summarize unread + unanswered + pending requests | T0 | Haiku/Sonnet | 1–5¢ |
| A3 | **Draft replies** | on demand / on A1 hit → 1–3 draft options, user taps send | T1→T2 | Sonnet | 1–2¢ |
| A4 | **Away auto-reply (template-only)** | quiet hours → fixed user-written text to saved contacts, capped/day | T2 (standing perm.) | none | 0 |
| A5 | **Follow-up catcher** | no reply from them for N days → reminder (B13 follow-up list) | T1 | none | 0 |
| A6 | **Chat search & recall** | "what did Ada say about the invoice?" → scoped search + answer | T0 | Sonnet | 2–3¢ |
| A7 | **Message-request triage** | new request → label spam/likely-legit → batch for user | T0 | Haiku | 0.08¢ |

### B. Naara Line SMS & omnichannel ◇(P16)
| # | Recipe | Notes | Tier | Model | ~Cost |
|---|---|---|---|---|---|
| B1 | **OTP/SMS digest** | summarize verification codes & receipts from rented numbers | T0 | Haiku | 0.1¢ |
| B2 | **Lead capture** | WhatsApp/IG/Telegram/email inbound matching "price/quote/book" → create CRM row (P18 scopes) + notify | T1 | Haiku | 0.1¢ |
| B3 | **Unified inbox brief** | all channels in one daily brief | T0 | Sonnet | 3–6¢ |
| B4 | **Reply suggestions per channel** | channel-aware tone/length; WhatsApp template rules respected | T1→T2 | Sonnet | 1–2¢ |

### C. Calls & phone ◇(P10 voice, P14–P15, P17)
| # | Recipe | Notes | Tier | Model | ~Cost |
|---|---|---|---|---|---|
| C1 | **Missed-call follow-up** | missed call → reminder + draft text | T1 | Haiku | 0.1¢ |
| C2 | **Call summary + action items** | transcript (P15) → bullets, tasks, sentiment | T0/T1 | Sonnet | ~1.5¢/15-min call (STT billed separately) |
| C3 | **Weekly call analytics** | volume, missed rate, busiest hours, top topics | T0 | Haiku batch | <1¢ |
| C4 | **AI voice agent hand-off notes** | after P17 agent call → summary + escalation reason | T0 | Sonnet | ~1.5¢ |

### D. Brand & Business Suite (B7–B9, P13)
| # | Recipe | Notes | Tier | Model | ~Cost |
|---|---|---|---|---|---|
| D1 | **Profile writer** | bio, tagline, captions from a few answers; diff preview before apply | T2 | Sonnet | 1–2¢ |
| D2 | **Gallery/Updates coach** | "your Updates ring has not changed in 14 days" nudge + caption drafts | T1 | Haiku | 0.2¢ |
| D3 | **Business Pulse** | weekly followers delta, Updates views, Follow-Hunt stats, CTA taps | T0 | Haiku batch | <1¢ |
| D4 | **Music link/album setup** | paste Spotify URL → platform allow-list validation stays server-side → album smart link | T1/T2 | Haiku | 0.2¢ |
| D5 | **Pro-renewal guard** | `pro.lapsing` event → warn, show what stops working | T0 | none | 0 |
| D6 | **Brand Concierge** (optional, owner decision) | answers inbound requests from brand FAQ only; labelled AI; handoff button | T2 | Haiku/Sonnet | 0.3–0.5¢/reply |
| D7 | **Team digest** ◇P13 | org owner sees per-member activity/spend summary | T0 | Haiku | <1¢ |

### E. Money, credits, eSIM, daily purchases
| # | Recipe | Notes | Tier | Model | ~Cost |
|---|---|---|---|---|---|
| E1 | **Low-credit/low-wallet watch** | `credit.low` → heads-up + "buy / ask a friend" cards | T0 | none | 0 |
| E2 | **eSIM expiry & data watch** | `esim.expiring`/`low_data` → alert + one-tap renewal link (checkout is human-approved, T3) | T0/T3 | none | 0 |
| E3 | **Trip planner** | "Ghana, 2 weeks, 5 GB" → plan recommendation from catalog (global data only) → deep-link to checkout | T0 | Sonnet | 2¢ |
| E4 | **Spend digest** | where credit/wallet went (messages, media, calls, Brain) + projection (labelled estimate) | T0 | Haiku | <1¢ |
| E5 | **Referral & Hunt tracker** | earnings, pending, next-step nudges (read-only, never touches reward fields) | T0 | Haiku | <1¢ |
| E6 | **Gift-pending reminder** | pending gift countdown (B5 24h) → nudge | T0 | none | 0 |

### F. Planner & productivity (B13)
| # | Recipe | Notes | Tier | Model | ~Cost |
|---|---|---|---|---|---|
| F1 | **Schedule from chat** | "set a call with Ada Friday 4pm" → event card for approval (invitees = T2) | T2 | Sonnet | 1–2¢ |
| F2 | **RSVP chaser** | no response 24h before → nudge draft | T1 | none | 0 |
| F3 | **Daily agenda** | merge events + follow-ups + pending approvals | T0 | Haiku | 0.5¢ |

### G. Support (NaaraCare, existing)
| # | Recipe | Notes |
|---|---|---|
| G1 | Self-serve help from user's real state (orders, wallet, number code) | exists today; becomes the `support` pack |
| G2 | Escalate with full context summary to a human | exists (`escalate_to_human`); add auto-summary |

### H. Later / bigger bets
- **Brain-as-MCP/API** for power users: expose the same scoped tools via P18 `ApiClient` scopes so users can drive Naara from Claude or other assistants. Same isolation rules, separate scopes.
- **Voice command** (browser speech-to-text → Brain).
- **Cohurt/CRM tie-ins** (Supreme Ideas CRM) — only via explicit scopes.
- **Admin Brain** (operator assistant for your own team) — a **different registry and auth guard**; never share tools with the user-facing registry.

---

## 6. Pain-point gap map (what Brain closes)

| Pain today | Brain's answer | Recipe |
|---|---|---|
| Too many places to check (N2N, SMS, WhatsApp, calls) | One brief, one inbox of "what matters" | A2, B3 |
| Missed leads/calls become lost sales | Auto-capture + follow-up reminders | B2, C1, A5 |
| eSIM runs out mid-trip / expires unnoticed | Proactive watch + one-tap renew | E2 |
| Not knowing which plan to buy | Conversational recommendation from catalog | E3 |
| Brand profile is empty or stale → weaker listing | Writer + nudges | D1, D2 |
| Credit runs out at the worst moment | Early warning with Buy/Ask-a-friend | E1 |
| Business owners can't see how they're doing | Pulse and call analytics | D3, C3 |
| Setup friction (ClickUp's known complaint) | Recipes + plain-English rule preview | §5 |
| Support load | NaaraCare already deflects; add context handoff | G1, G2 |
| Team owners can't supervise spend | Team digest + member caps | D7 |

---

## 7. Safety — the short list (full model in v1 §3)

1. Tools start from `$ctx->user` relations only (as `SupportTools` does today). CI fails any Brain tool that calls `Model::find/query` directly.
2. No actor id in any tool schema; ids from the model resolve through relation-scoped lookups (or handles from Sprint 4).
3. Inbound third-party text is data; **tainted runs cannot send/spend/share without approval**.
4. Approval tokens hash-bound to exact args and cost; single-use.
5. Reuse `SupportReplyGuard` idea for Brain: a reply may not claim an action the executor did not perform.
6. No URL fetch tool, no markdown images, Naara-issued links only.
7. Support-only tools (goodwill, ticket resolve, number refresh) never appear in the user Brain registry.
8. Kill switches per pack/tier/automation; Brain outage never blocks messaging or calls.
9. Copy never says "E2EE" or "private from Naara"; disclose AI processing by a model provider.
10. Isolation + injection suites run on every deploy touching Brain or wrapped services.

---

## 8. Cost model and pricing

### 8.1 Unit prices (third-party pricing pages, October 2026 — **verify on Anthropic's pricing page before launch**)
Sonnet 5.5 $2 in / $10 out per 1M tokens, cache reads ~$0.20; Haiku 4.5 $1 / $5; Opus 5.5 $4 / $20; Batch ≈ 50% off. Model IDs in config, never constants.

### 8.2 Task cost assumptions (ESTIMATE — replace with Sprint 0 measurements)
| Task | Assumed tokens | Model | ~Cost |
|---|---|---|---|
| Quick answer (1 step) | 4k cached sys+tools, 1.5k new in, 300 out | Sonnet | ~0.7¢ |
| Typical agentic request (3 steps, 2 tool calls) | 12k cached reads, 8.5k new in, 600 out | Sonnet | ~2.5¢ |
| Heavy summary (≈100 messages / weekly report) | 25k in, 1.2k out | Sonnet | ~6¢ |
| Watch classification | ~600 in, 30 out | Haiku | ~0.08¢ |
| Daily brief | 20k in, 1k out | Haiku | ~2.5¢ (~$0.75/mo) |
| Call summary (15-min, ~3–4k tokens transcript) | 4k in, 400 out | Sonnet | ~1.5¢ (STT extra) |
| Concierge reply | 0.5k new + 1.5k cached, 150 out | Haiku/Sonnet | 0.2–0.5¢ |

### 8.3 What I would charge (add-on to Naara Pro, USD-pegged, NGN via `CurrencyService`)
Anchor: your Pro plans are $19 / $49 / $99 (seed values). ClickUp's Brain add-ons are reported around $9 and $28 per user per month; same band, but Naara's tiers are usage-capped, so the bill never surprises.

| Tier | Price/mo | Included allowance (hard cap) | Compute cost at **100% use** (ESTIMATE) | Gross margin at full use | Typical (≈40–50% use) |
|---|---|---|---|---|---|
| **Brain Free** (any registered user) | $0 | 15 quick requests/mo (Haiku-routed), weekly eSIM/credit digest, no watchers | ~$0.15 | n/a (acquisition + support deflection) | ~$0.05 |
| **Brain Starter** | **$9** | 100 requests, 3 watchers, 1,000 classified messages, daily brief | ≈ $4.05 | **~55%** | ~80% |
| **Brain Pro** | **$29** | 300 requests, 10 watchers, 4,000 classified, 40 call/inbox summaries, reports + export, standing permissions | ≈ $12.80 | **~56%** | ~79% |
| **Brain Business** (per workspace, not per seat) | **$79** | 800 requests, 12,000 classified, 150 call/inbox summaries, team digests, org packs, Concierge up to 600 replies | ≈ $38.25 | **~52%** | ~76% |

Subtract roughly 5–8 points for payment fees, retries/failed runs and FX; margins above are **before** those.
- **Bundling recommendation:** include **Brain Starter in Growth ($49)** and **Brain Pro in Spotlight ($99)** (cost at full use ≈ 8% and 13% of plan revenue). Starter Reach ($19) buys the add-on. This lifts plan value, lowers churn, and the hard caps protect margin. If you'd rather maximize add-on revenue, keep all tiers paid.
- **Annual:** ~2 months free.
- **Overage:** sold as usage-credit top-ups at **≥2.5× measured compute cost**, controlled by an admin $/token setting (same mechanism as B5 $/MB), enforced by the existing margin guard. Default behavior at cap: Brain stops and offers top-up; never silent overspend.
- **Admin knobs (all `Setting`s):** per-tier allowances, markup, models per task class, per-automation caps.
- **Naira risk:** compute cost is in USD, revenue partly in NGN. Re-price NGN on a schedule from `CurrencyService`, and alert when margin at full use drops below the floor.
- **Reality check gates before publishing prices:** (1) Sprint 0 measured cost per task within ±30% of §8.2, (2) cache hit rate measured, (3) prefilter ratio measured on real inbox traffic. If measured cost is higher, reduce allowances before raising prices.

---

## 9. Build order (what I'd do, in sequence)

| Sprint | Scope | Done when | Depends on |
|---|---|---|---|
| **0 — Meter** (2–3 days) | Merge `AnthropicClient`+`ClaudeChatModel` → `BrainModel`; `ai_usage_log` (tokens in/out/cache, model, feature, user, cost); cache_control; retries; model router stub; admin cost dashboard | Every existing AI call (NaaraCare, Pricing Architect, Wizard, etc.) logs real tokens and cost | none |
| **1 — Runtime & chat** | `BrainAgent` from `NaaraCareAgent`; `ToolRegistry` with user-relation rule + arch/schema/contract tests; canary seeder; `/brain` chat UI (non-streaming) + step progress; Journal v1 | Isolation suite green; support persona still works; a user can ask balances/orders/eSIM and get correct answers | Sprint 0 |
| **2 — Digests (value, low risk)** | Inbox Brief, Spend digest, eSIM expiry watch, Business Pulse; Batch API for non-urgent; scheduler + `JobHeartbeat`; push | Users receive correct digests; cost per digest measured | S1, B6 events |
| **3 — Watch engine** | `brain_automations`, deterministic matcher on realtime/event log, Haiku classifier, notify/tag/remind actions, recipe gallery (A1, A5, A7, E1, E2, C1), caps, dedupe, loop prevention | One event → one execution under concurrency; injected message can't trigger a send | S1, B4 |
| **4 — Actions with approval** | Handles; `ApprovalService` (hash-bound), action cards, T2 tools (send N2N, send SMS, create event, apply brand diff); template-only auto-reply; taint tracking | Tamper/replay tests pass; credits charged once via B6/B5 pipelines | S1, B5, B6 |
| **5 — Business Suite packs** | Omnichannel (P16), call intelligence (P15), CRM scopes (P18), org scope (P13) as each underlying prompt lands; streaming on VPS; NL automation builder | Packs behind flags; org isolation tests green | P13–P18 |
| **6 — Metering, plans, launch gate** | `UsageMeter` hold/settle for runs, Brain allowance grants, tiers, admin Brain tab, kill switches, erasure/export/legal-hold coverage, red-team (human + automated), load/cost tests, white-label review (nothing ported), docs | Launch-readiness list; measured margins ≥ floor | all |

**Go/no-go metrics after Sprint 3 (before building more):** weekly active Brain users among Pro, % of users with ≥1 automation enabled, approval-accept rate for drafts, cost per active user, support-ticket deflection. If automations aren't being enabled, fix recipes before adding capability.

---

## 10. Risks and how I'd handle them
| Risk | Handling |
|---|---|
| Cross-user leak | §7; isolation suite blocks deploy; canaries; zero-tolerance post-mortem rule |
| Prompt injection via received messages | taint → approval; template-only auto-reply; no URL/image egress |
| Runaway cost | three-level budgets, prefilter, caps, anomaly alert, kill switch |
| Provider outage/model deprecation | gateway abstraction, model IDs in settings, graceful "Brain is resting", never blocks core features |
| Users over-trusting Brain | reply guard, visible Journal, approvals, "AI-generated" labels |
| Legal (AI-assisted messaging, recorded calls, minors) | owner/legal review per market; default Brain off for minors; disclosure on auto-sent messages |
| cPanel installs | degraded mode: no streaming, slower automations; or VPS-only (owner decision) |
| Support tools reused unsafely | separate registries; `grant_goodwill_credit` etc. never in user Brain |

## 11. Decisions I need from you
1. **Bundling:** include Starter in Growth and Pro in Spotlight, or keep all tiers paid?
2. **Brain Free for all users:** yes (support deflection + acquisition) or Pro-only?
3. **Autonomy default:** ask-first (my recommendation).
4. **Brand Concierge:** v1, v2, or never.
5. **Chat-reading consent & sender disclosure** (legal).
6. **cPanel:** degraded mode or VPS-only.
7. **Sprint 0 first, prices later:** agree not to publish prices until measured costs are in.
