# Naara Respond: Phase 0 spike results (ADR set, master only)

Run 2026-10-19 in the dev sandbox (MariaDB 10.11, PHP 8.3, Laravel 12). Blueprint section 16C. Numbers below are measured unless a row says "model" or "blocked".

| Spike | Status | Decision |
|---|---|---|
| S3 Search at scale | MEASURED | Tenant-scoped search. See ADR-S3 |
| S4 Queue on cPanel cron | MEASURED | Database queue on cPanel is enough. See ADR-S4 |
| S5 Realtime | DECIDED from the existing N2N pattern | Polling everywhere, push on VPS only. See ADR-S5 |
| S6 Retrieval | PARTIAL | FULLTEXT first, embeddings optional later. See ADR-S6 |
| S7 AI cost model | MODEL (inputs are the repo's own, unverified price table) | Budgets and a per-conversation cap. See ADR-S7 |
| S8 Telegram + web chat slice | CONTRACT FIRST | The adapter contract is proven in Phase 1 with a FakeChannel; the live bot test needs one BotFather token from the owner |
| S1, S2, S9 Meta (Embedded Signup, webhooks and human-agent tag, templates) | BLOCKED | Needs the owner's Meta app, test WABA and Business verification (credentials checklist section 1) |

Phase 1 may start now for everything that does not touch Meta: the blueprint's own rule is "after S8 passes and ADRs S1 to S7 are recorded", so the Meta ADRs stay open and Phase 1 builds against a FakeChannel (its stated proof).

## ADR-S3 Search at scale
Test: 1,000,000 messages over 200 workspaces (186 MB with a FULLTEXT index on `body`), 150 queries per case.

| Query | p50 | p95 |
|---|---|---|
| FULLTEXT natural language, one workspace filter | 134 ms | 213 ms |
| FULLTEXT boolean (`+refund +lagos`), one workspace | 224 ms | 271 ms |
| FULLTEXT with no workspace filter (cross-tenant, never allowed) | 108 ms | 132 ms |
| `LIKE %word%` inside one workspace (~5,000 rows each) | 9.8 ms | 16 ms |
| Thread page (workspace, conversation, last 50) | 0.1 ms | 0.2 ms |
| Messages per workspace in the last 24 h | 0.1 ms | 0.1 ms |

Pass criterion was p95 under 300 ms: met by every tenant-scoped query. Decision: every message row carries `workspace_id` and every query is scoped by it first (also the tenant-isolation rule). Search uses an indexed workspace range with `LIKE` for workspaces under about 100,000 messages and FULLTEXT above that; both stay under the bar at 1 M rows total. A FULLTEXT index adds about 25 percent to storage and 8 s of build per million rows. cPanel hosts with MySQL 8 or MariaDB have InnoDB FULLTEXT, so no external search service is needed.

## ADR-S4 Queue on cPanel cron
Test: 2,000 queued jobs on the database queue, one `queue:work --stop-when-empty`: 17.6 s, about 114 jobs per second, 0 failed.
The installer's shared profile already runs `schedule:run` every minute and the scheduler starts `queue:work --max-time=50`, so one cron minute drains about 5,700 jobs, well above the 1,000 messages per minute target. Cost of cron: a reply can wait up to about 60 seconds to start. Decisions:
1. The webhook controller only verifies the signature, stores the raw event (unique on provider event id) and answers 200; all processing is queued. It never calls a provider inline.
2. Minimum supported mode: database queue with the one cron. Quoted promise on shared hosting: replies begin within a minute. VPS with Redis and Horizon: within seconds.
3. A one-minute heartbeat (`job_heartbeats`) already exists; the omni health console reads it.

## ADR-S5 Realtime
The N2N chat already runs on a short-poll table (`n2n_realtime_events`) that works on both hosting profiles. Decision: the inbox v2 uses the same polling contract (3 to 5 s while the tab is visible, 30 s hidden) on every install; a push transport (Reverb) stays an optional VPS upgrade behind the same event interface. No new hosting requirement.

## ADR-S6 Retrieval
Not measurable without three real brand profiles and an embeddings key. Decision for now: knowledge chunks are stored with a FULLTEXT index; retrieval is FULLTEXT top-k with the brand's own FAQ boosted. The chunk table gets a nullable `embedding` column so embeddings can be added later without a migration of data. Recall@k on three sample brands is owed once the owner supplies them.

## ADR-S7 AI cost model (model, not measurement)
Inputs: the repo's `config/ai.php` price table (third-party list prices, flagged UNVERIFIED there), a retrieval prompt of about 2,500 tokens (system plus top-k chunks), about 400 tokens of history per extra turn, and 120 output tokens per reply. With the standard model class (USD 2 in / 10 out per million, cache read 0.20): one answered customer message is roughly 2,500 uncached input plus 120 output, about USD 0.0062; with the system prompt cached it falls to roughly USD 0.0036. A 6-turn conversation is therefore about USD 0.02 to 0.04. Decisions: a per-workspace monthly AI budget and a per-conversation cap (default 8 AI replies) are enforced before every call, metered through the existing `AiUsage` ledger; the figures are to be replaced by measured tokens from the first sandbox runs. Meta's own business agent is billed separately by Meta (see the grounding report) and is not part of this meter.

## ADR-S8 Telegram and web chat slice
The Telegram client and webhook controller already exist and match the official Bot API (grounding report section 1). What is missing is the adapter contract they should satisfy. Phase 1 defines it (`ChannelAdapter`) and proves it with a FakeChannel; the existing Telegram code is then wrapped as the first real adapter in Phase 2. A live round trip needs one BotFather token from the owner.
