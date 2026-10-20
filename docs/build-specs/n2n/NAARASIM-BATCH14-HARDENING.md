# NaaraSim — Batch 14: Hardening, Compliance & Oversight
### Master repo only. Last batch. Turns N2N from "works" into "safe to launch". For Claude Sonnet + Graphify.

**Depends on:** all previous batches.

## Phase A — Abuse and moderation
1. Admin moderation console: reports queue (`SpamReport` + N2N reports), reported-content excerpt access with an audit entry (`AuditLog`) on every view, actions (warn, mute sending, suspend N2N, ban).
2. Spam heuristics: new account messaging many strangers, repeated link-filter hits, message-request decline ratio, call-attempt bursts. Rate limits reviewed per endpoint.
3. Credit-fraud monitors from B5 (gift rings, velocity, refund loops) surfaced to admin.
4. Verification: each heuristic has a test scenario and a documented false-positive path.

## Phase B — Security review (checklist, with tests)
- **IDOR** on conversation, message, media, call, event and gift ids.
- Realtime channel authorization for every channel type.
- Output escaping for message bodies, names, captions, event titles.
- Upload attacks (B7), embed/XSS attacks (B8), open redirects, CSRF on the composer endpoint, signed-URL expiry.
- Money: concurrency, replay, rounding, negative amounts (B5).
- Secrets: TURN/SFU keys server-side only.

## Phase C — Privacy and data lifecycle
1. Extend **account erasure** (`docs/ACCOUNT-ERASURE.md`), **legal hold** records and **app export** (`docs/APP-EXPORT.md`) to every N2N table and Wasabi prefix. Define what happens to the other party's copy of a conversation when one user is erased.
2. Retention settings and their interaction with legal hold.
3. Age handling: `date_of_birth` exists; define the policy for minors receiving messages/calls from strangers (owner + legal decision) and implement the chosen gate.

## Phase D — Store and legal readiness
1. App-store review notes: user-generated content needs **report, block and filtering** paths (as far as I know this is an Apple requirement — verify current guideline text); credit purchase and IAP position (Master D9, read `docs/APP-STORE-PAYMENTS-COMPLIANCE.md`).
2. Country-by-country legal review of VoIP/OTT messaging and calling for launch markets (**UNVERIFIED here**), plus terms/privacy-policy updates for N2N.

## Phase E — Operations
1. Kill switches per feature (existing `admin/features` + `FeatureEntitlements`): messaging, media, calls, group calls, credit purchase, credit share.
2. `JobHeartbeat` coverage for every new scheduled job (gift expiry, reminders, event log pruning, orphan media, disappearing messages).
3. Observability: error log entries, realtime health, TURN/SFU health, credit-ledger reconciliation cron.
4. Docs: VPS install (Reverb, coturn, SFU, supervisor, proxy) and the **cPanel degraded-mode matrix** (chat polling yes, calls no).

## Phase F — Load and regression
1. Load test messaging, realtime and gift-expiry job at a stated target; report measured results.
2. Full PHPUnit baseline vs after; `npm run build`; manual browser/device matrix from B1, B2, B6, B10.
3. **White-label review**: list what is master-only (default: everything here) and what, if anything, is proposed for the children, per CLAUDE.md. Do not port anything in this batch.
4. Update `PROGRESS.md` and `docs/PLATFORM-STATE.md`.

## Deliverable
Security checklist with evidence, kill-switch and health screenshots, the erasure/export coverage table, the launch-readiness list of open legal/decision items, and the regression comparison.
