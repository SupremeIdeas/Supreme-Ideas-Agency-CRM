# NaaraBrain — SOURCE OF TRUTH (read this first, then only the files it points to)
**For: Claude Code, working in the NaaraSim master repo.** Master-only by default (CLAUDE.md white-label discipline applies; port nothing).

## 1. The files and what each one is for
| File | Role | Use it for |
|---|---|---|
| `00-SOURCE-OF-TRUTH.md` (this) | **Rules, precedence, build order, resolved conflicts** | Deciding what to build next and which document wins |
| `01-MASTER-SPEC.md` (was NAARABRAIN-MASTER-BLUEPRINT) | **Security model, runtime architecture, data model, automation engine, compliance** | The "how it must be built safely" spec. §3 (isolation) is non-negotiable |
| `02-BUILD-PLAN-AND-PRICING.md` (was NAARABRAIN-V2-IF-NAARA-WERE-MINE) | **Sprint order, repo findings, cost model, pricing** | Execution order and what already exists in the repo |
| `03-SKILLS-CATALOG.md` (was NAARABRAIN-ON-DEMAND-SKILLS-CATALOG) | **What Brain does for users, per product area, with tiers** | Tool/pack definitions; each skill = a tool or workflow |

## 2. Precedence (when documents disagree)
1. **Owner instructions in the current session** beat everything.
2. **What the real code does** beats every document. If a document says "X exists" and the repo says otherwise, trust the repo and report the mismatch.
3. `01` §3 (security/isolation) and `03` §7 (must-not-do list) beat any convenience in `02`.
4. `02` beats `01` on **build order, streaming, schema size, and metering** (see §3 below).
5. `03` is the catalog; `01`/`02` decide *how* and *when*.

## 3. Conflicts already resolved
| Topic | Decision |
|---|---|
| Execution order | Use **`02` Sprints 0–6**. `01`'s "Batches 15–21" are *content references* per sprint (map in §4), not a separate schedule |
| Streaming | **Not in v1.** Non-streaming with step progress first; streaming on VPS is a later polish |
| Schema | Start with the **small set** (`brain_threads, brain_messages, brain_runs, brain_automations, brain_approvals, ai_usage_log`); add memory/digest/handle tables in the sprint that needs them |
| Opaque handles | Relation-scoped queries from Sprint 1; **handles arrive with the first T2 tool (Sprint 4)** |
| Claude clients | Merge `AnthropicClient` + `ClaudeChatModel` into one gateway in Sprint 0 |
| Agent loop | Extract from `NaaraCareAgent`; do not write a second loop |
| Tool registries | **Separate per persona.** NaaraCare keeps support-only tools (goodwill, resolve, refresh code). User Brain never gets them |
| Metering | **DECIDED in Sprint 0 (2026-10-05):** the only `UsageMeter` in the repo is `App\Services\N2N\Credit\UsageMeter` (hold → settle on NaaraCredit/wallet, built for N2N). Brain must not import an N2N class, so for now Brain compute is **free to the user under a per-person daily question ceiling** (`brain.daily_messages`, default 30) and every call is **measured** in `ai_usage_log` (feature `brain_chat`). Sprint 6 either extracts a neutral meter from that class (same hold → settle semantics) or builds one beside it, then shows the user **one** cost line. `WalletService::charge()` stays for P15/P17 provider minutes. |
| Prices | `02` §8 is a **hypothesis**. Do not publish or hard-code prices until Sprint 0 telemetry measures real cost per task |
| Model names | Config/admin settings only, never constants. Verify currently available model IDs at implementation time |

## 4. Mapping: sprint → spec sections
| Sprint (from `02` §9) | Read in `01` | Read in `03` |
|---|---|---|
| 0 Meter | §4.1–4.2, §11 | §9 |
| 1 Runtime & chat (read-only packs) | §3, §4, §5 (T0 tools), §10 | §1, §3 Packs 1, 2, 13 |
| 2 Digests | §7 | §4 (Morning Brief), §5 |
| 3 Watch engine | §6 | §3 Packs 3, 4, 6; §5 |
| 4 Approvals & T2 actions | §3.6–3.8, §4.3 | §3 Packs 3, 4, 6, 7 (writes), §6 |
| 5 Business packs | §9 | §3 Packs 8–12; §6 |
| 6 Metering, plans, launch gate | §9, §10, §11 | §7, §8, §9 |

## 5. Ground rules for Claude Code (every sprint)
1. **Read the repo before coding.** The documents were written from partial reads. Anything labelled `UNVERIFIED`, or any class/column named in the docs, must be confirmed in the actual code first. Report mismatches before building on them.
2. **One sprint at a time.** Do not start a sprint until the previous one passes its acceptance checks. Update `PROGRESS.md` after every task.
3. **Isolation first.** Before any tool ships, the isolation tests exist and pass: tools start from `$ctx->user` relations (or the server-resolved workspace), no actor id in any tool schema, architecture test forbids direct `Model::find/query` in Brain tools, canary users seeded, deploy blocked on failure.
4. **Reuse, don't duplicate.** Every action calls an existing service (`CreditService`, `WalletService`, `MessageSenderService`, `IdentityResolver`, etc.). No parallel billing, auth, or send path.
5. **Tiers are enforced in code**, not prompts: T3 (money, identity, destructive) never executes inside Brain; it hands off to the real UI with step-up.
6. **Report measured numbers only** (latency, tokens, cost per task, cache hit rate, isolation results). Estimates in the docs are placeholders to be replaced.
7. **Feature-flag every pack and tier**; Brain failure must never degrade messaging, calls, or checkout.
8. **No claims of E2EE or "private from Naara"** in any copy. Disclose that a model provider processes content when Brain is used.
9. Money paths on sandbox keys only until the hardening sprint.

## 6. Decisions assumed (Frank may override; change here and nothing else needs editing)
| # | Decision | Assumed default |
|---|---|---|
| D1 | Model routing (cheap/standard/top) | Approved, admin-configurable |
| D2 | Brand Concierge (AI replying to third parties) | **Not in v1** |
| D3 | Default autonomy | **Ask-first** |
| D4 | Brain reads N2N chats | **Opt-in, default none**; sender disclosure pending legal |
| D5 | Pricing shape | Free (read-only help) + paid tiers; bundling TBD after Sprint 0 |
| D6 | Provider data-retention terms | Owner must confirm with Anthropic before launch |
| D7 | cPanel | Degraded mode (no streaming, slower automations), VPS preferred |
| D8 | Erasure vs financial records | Owner/accountant decision before Sprint 6 |
| D9 | Admin Brain | Out of scope; separate registry/guard if ever built |
| D10 | Bounded self-serve OTP/eSIM resend | **Support-only** until owner approves |
| D11 | Brain places calls | **No** (hand-off to call UI only) |

## 7. First message to paste into Claude Code
> Read `naarabrain/00-SOURCE-OF-TRUTH.md`, then `01-MASTER-SPEC.md` §3–4 and `02-BUILD-PLAN-AND-PRICING.md` §1 and §9. Do **Sprint 0 only**: merge `AnthropicClient` and `ClaudeChatModel` into one gateway, add an `ai_usage_log` table capturing tokens in/out/cache, model, feature and user for every existing AI call, enable prompt caching, add retry/backoff and configurable model/version/timeouts, and build a small admin cost view. First verify in the real code every class and behavior the docs claim about the AI services, and report mismatches. Do not build Brain features yet. When done, report measured cost per existing AI feature and update `PROGRESS.md`.


## 8. Sprint 0 report (2026-10-05): doc vs real code
| Doc claim | Real code | Action |
|---|---|---|
| Two Claude clients (`AnthropicClient`, `ClaudeChatModel`) | **Three**: those two plus a direct HTTP call in `ClaudeFixProposer` (maintenance loop). No usage logging anywhere | All three now go through `App\Services\AI\ClaudeGateway` |
| Default model id `claude-sonnet-5` | Not a valid id; verified current ids: `claude-sonnet-5-5`, `claude-opus-5-5`, `claude-haiku-4-5-20251001` | Default corrected; ids live in `config/ai.php` + `ANTHROPIC_MODEL`, never in code |
| `UsageMeter` exists (B5) | Exists, but only as the N2N-namespaced meter | See Metering row |
| Prompt caching, retries, timeouts | None existed | Gateway: `cache_control` on system + last tool, bounded retries with backoff on 408/409/429/5xx/529, `ai.timeout`, `ai.retries`, `ai.backoff_ms` |
| Measured cost per feature | **Not available**: the sandbox has no live Anthropic key, so zero real calls were measured (tests use `Http::fake`). Prices in `config/ai.php` are list-price hypotheses (editable in Admin > AI costs) | The cost view fills from real calls; **no price may be published until it has**. |
