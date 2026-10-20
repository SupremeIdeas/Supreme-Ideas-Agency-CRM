# NaaraSim — Provider Capability Radar
### A monitoring system that watches your providers' own API evolution and turns it into reviewed, human-gated upgrade proposals in the admin Updater section. Master repo only. For Claude Sonnet + Graphify.

---

## 0. What this is, and — just as importantly — what it is not

**What it is**: a scheduled system that watches each configured provider
(Twilio, Paystack, ESIMGo, Airalo, Stripe, and every other integrated
provider) for signs their API has grown new capability, fetches the actual
current evidence of that change, and uses Claude to turn that evidence into
a structured, reviewable proposal: "Provider X added capability Y; here is
what it could let NaaraSim do; here's a rough shape of what building it
would involve." These proposals land in a queue in the admin Updater
section for a human to accept, defer, or dismiss.

**What it is not, under any circumstance**: a system that writes, merges,
or deploys code on its own initiative, changes a provider integration
automatically, or is treated as ground truth without a human reading the
actual evidence it cites. Every proposal must link back to the literal
fetched content that triggered it, so a reviewer can verify the claim
themselves in thirty seconds rather than trusting an AI's summary blind.
This mirrors the exact human-gated design already used for port-in
requests earlier in this engagement, applied here because the stakes
(shipping a real code change) are at least as high as approving a carrier
port.

---

## Phase A — Per-provider detection strategy (the hard, honest part)

Not every provider exposes the same signal. Build a pluggable interface,
following this codebase's existing `EsimProviderInterface`/
`NumberProviderInterface` pattern exactly, so each provider's watcher is
swappable and the scheduler never touches a concrete implementation:

```php
interface ProviderCapabilityWatcherInterface
{
    /** A stable identifier for what's being watched (a changelog URL, a spec hash source, etc.) */
    public function sourceDescription(): string;

    /** Fetch the current state of whatever signal this provider exposes. Raw, unprocessed. */
    public function fetchCurrentSignal(): string;
}
```

Implement one watcher per provider, using whichever of these three
strategies actually fits that provider — in order of reliability, use the
strongest one available:

1. **Official changelog/RSS feed** (most reliable, use whenever it exists —
   Stripe, Twilio, and Paystack all publish real changelogs): fetch the
   changelog page or RSS feed, extract new entries since the last known
   snapshot.
2. **Published OpenAPI/Swagger spec diff** (second choice, when a
   changelog doesn't exist or is too vague to act on): fetch the
   provider's published spec, diff its JSON structure against the last
   stored snapshot — new endpoints, new fields, new enum values are all
   real, structural evidence of new capability, not just marketing copy.
3. **Live API response schema-diff** (fallback, for providers with neither
   of the above): periodically compare the actual shape of real API
   responses this codebase already receives (via the existing provider
   service classes) against a stored baseline — a new field appearing in a
   real response is a weaker but genuine signal something changed.

Audit every currently-integrated provider (the full list from the
`config/services.php` work done earlier in this engagement) and assign
each one the strongest strategy it actually supports — do not force
strategy 1 onto a provider that doesn't publish a real changelog; a
watcher that "detects" nothing because it's reading the wrong source is
worse than an honest fallback to strategy 3.

---

## Phase B — Detection → diff → storage

1. `provider_capability_snapshots` table: `provider`, `source_type`
   (changelog/openapi/response-schema), `content_hash`, `raw_content`,
   `fetched_at`.
2. A scheduled job (`provider:capability-scan`, following this codebase's
   existing scheduled-command conventions — daily is a sensible cadence,
   this isn't time-critical) runs each configured watcher, fetches current
   content, hashes it, and compares against the most recent stored
   snapshot for that provider+source.
3. On a hash mismatch: store the new snapshot, and compute an actual diff
   (unified text diff for changelog/text sources; a structural diff — keys
   added/removed/changed — for OpenAPI/schema sources) between the old and
   new raw content. This diff, not the full new document, is what feeds
   Claude — keep the AI's context tightly scoped to what actually changed,
   not the entire spec every time.

---

## Phase C — Claude-generated proposal (structured, evidence-linked)

Use the existing `AnthropicClient::completeJson()` — this codebase already
has a proven pattern for structured AI output; don't build a second one.

1. System prompt should include: what NaaraSim currently uses of this
   specific provider's API (pull this from the actual provider service
   class's real method list — `EsimAccessService`'s public methods, say —
   so Claude reasons against what's *actually integrated today*, not a
   guess), and an explicit instruction to only propose based on the
   supplied diff content, never on the model's own prior knowledge of the
   provider.
2. User message: the actual diff content from Phase B.
3. Requested structured output: `{ "summary": string, "capability_added":
   string, "relevance_to_naara": string, "proposed_feature": string,
   "estimated_effort": "small"|"medium"|"large", "confidence": "high"|
   "medium"|"low", "requires_human_verification_because": string }`. That
   last field is deliberately mandatory — force the model to state, every
   single time, why a human should double-check this before acting on it,
   rather than presenting a proposal as if it were already verified fact.
4. Store as a `CapabilityProposal` record: the structured fields above,
   the raw diff it was generated from, a link/reference back to the
   original fetched source, `status` (`new`, `reviewing`, `accepted`,
   `deferred`, `dismissed`), and timestamps.

---

## Phase D — Surface in the admin Updater section

1. Extend `app/Livewire/Admin/Updater.php` (the existing Updater screen)
   with a new tab/section — "Capability Radar" or similar — listing
   `CapabilityProposal` records newest-first, each showing the structured
   summary, the confidence level, the effort estimate, and a link to view
   the actual raw evidence (the diff/changelog excerpt) inline, so a
   reviewer never has to take the summary on faith.
2. Admin actions per proposal: **Accept** (moves it into whatever this
   codebase's actual feature-backlog/planning mechanism is — if none
   exists yet, a simple `accepted_at` + free-text admin note is enough for
   v1; don't build a full project-management system as part of this),
   **Defer** (re-surface it in N days, useful for "interesting but not
   now"), **Dismiss** (with an optional reason, which is worth capturing
   so patterns in dismissed proposals — e.g. always dismissing anything
   about a provider you're deprioritizing — become visible over time).
3. This is explicitly **additive to**, not a replacement for, the existing
   Updater's job of shipping *your own* platform updates down to White
   Label instances — that pipeline is about distributing changes you've
   already decided to make; this new one is about surfacing *candidate*
   changes before anyone has decided anything. Keep the two visually and
   functionally distinct in the UI so an admin never confuses "this update
   is being installed" with "this is just a suggestion."

---

## Phase E — Verification

1. Manually trigger the scan against at least two providers using
   different detection strategies (one changelog-based, one schema-diff-
   based), confirm a snapshot is stored, and confirm no false proposal
   fires when nothing has actually changed (run the scan twice in a row
   with no real upstream change and confirm the second run produces zero
   new proposals).
2. Manually seed a fake but realistic diff for one provider and confirm
   Claude produces a well-formed structured proposal, that it correctly
   populates `requires_human_verification_because` with a genuine caveat
   rather than a throwaway phrase, and that the raw evidence is correctly
   linked and viewable from the admin screen.
3. Confirm accept/defer/dismiss actions correctly update `status` and that
   deferred proposals actually re-surface after their deferral window.

---

## Constraints

- No auto-implementation, no auto-deployment, no auto-merge — this system
  produces reviewable proposals only, full stop, restated here because it
  is the single most important constraint in this entire blueprint.
- Every proposal must link to real, fetched evidence — never let Claude
  generate a proposal from an empty or missing diff; if a scan run finds
  no real signal, produce no proposal that day rather than inventing one
  to have something to show.
- Reuse `AnthropicClient::completeJson()` and this codebase's existing
  provider-interface pattern — do not build a second AI-calling mechanism
  or a second provider-abstraction pattern alongside the ones that already
  exist.
- Rate/cost-manage the Claude calls sensibly — only call the model when
  Phase B's hash comparison actually detects a real change, never on a
  fixed schedule regardless of whether anything changed; this keeps cost
  proportional to actual upstream activity, not to how often the scheduler
  runs.

## Deliverable

Confirmation the scan runs cleanly against every currently-integrated
provider with an assigned detection strategy, at least one real, non-fake
proposal successfully generated and reviewed end-to-end in the admin UI,
and an explicit accounting of which providers ended up on strategy 1
(changelog), 2 (spec diff), or 3 (response schema-diff) and why — so future
you knows exactly how much to trust each provider's radar coverage rather
than assuming they're all equally reliable.
