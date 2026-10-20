# NAARA PROMPT 18 — CRM & Integration Marketplace
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** Prompt 13 loosely (org-scoped API clients, if integrations should be managed at the workspace level rather than tied to one member's personal API client) — otherwise self-contained.

**Confirmed before writing this prompt:**
- `ApiClient::SCOPES` is a plain const array: `['catalogue', 'quote', 'order', 'status']`, used as Laravel Sanctum token abilities. `hasScope(string $scope): bool` is the existing check method.
- `ApiClientService` and `ApiWalletService` already implement a scoped-API-key-plus-prepaid-balance system — this is the exact shape a CRM/integration surface needs. Do not build a second API-key or billing system for this prompt.
- `DeveloperPortal.php` is the existing Livewire surface where a user manages their `ApiClient`s today — extend this, don't build a parallel developer-facing page.

---

## §1 — New scopes

**Build:**
1. Extend `ApiClient::SCOPES` to include: `contacts:read`, `calls:read`, `messages:read`, `webhook:subscribe`. Each new scope gates access to read-only endpoints exposing, respectively: org contacts (counterparts from Prompt 16's `message_threads`), call logs (from `LogCallEventJob`'s table, extended in Prompt 14/15), message history (Prompt 16's `InboundMessage`/`OutboundMessage`), and the ability to register a webhook subscription.
2. These are read-only by design for the first version — a CRM/Zapier integration pulling data out of Naara is a much smaller trust surface than one able to write back into it (e.g. sending messages, placing calls). Writing scopes (`messages:send`, `calls:place`) can follow once the read-side is live and something has actually asked for write access.

## §2 — Webhook subscriptions (so integrations don't have to poll)

**Build:**
1. `webhook_subscriptions` table: `id`, `api_client_id` (FK), `event_type` (`call.completed`, `message.received`, `call.recording_ready` — extend as new event-producing prompts land), `target_url`, `secret` (for HMAC-signing outgoing payloads, the same way `PaymentWebhookController` verifies *incoming* signatures — mirror that pattern for outgoing signing so integration builders get a familiar, standard verification contract), `status` (`active`/`failed`/`disabled`), timestamps.
2. A `DispatchWebhookJob`, fired wherever the relevant event already occurs in the codebase (e.g. at the end of `LogCallEventJob`'s processing for `call.completed`, at the point `InboundMessage::create()` already happens for `message.received`) — this is an additive dispatch call added to existing job/event points, not a new event-detection system.
3. Failed deliveries (non-2xx response) retry with backoff; after N consecutive failures, flip `status` to `failed` and surface that in `DeveloperPortal.php` so the integration owner can see and fix it, rather than silently dropping events forever.

## §3 — Zapier first, native CRMs on confirmed demand

**Build:**
1. Zapier's own app-builder (their "Platform" tooling) consumes a scoped-API + webhook surface almost exactly like what §1/§2 produce — build a small Zapier app definition against these endpoints once they exist. This alone unlocks the "100+ integrations" positioning CallHippo uses, without native Salesforce/HubSpot/Pipedrive/Zendesk builds.
2. Do **not** build native CRM connectors in this prompt speculatively. Add a specific one (Salesforce, HubSpot, Pipedrive — whichever is actually asked for) as its own small follow-up prompt once a real business customer requests it, using the same `ApiClient` scopes as the integration point rather than a bespoke auth mechanism per CRM.

## §4 — `DeveloperPortal.php` UI additions

**Build:** scope-selection checkboxes for the four new scopes (alongside the existing four), a webhook-subscription management panel (add/remove/view delivery status per subscription), and a link to the Zapier app listing once published.

---

## Acceptance checklist for this prompt

- [ ] New scopes added to the existing `ApiClient::SCOPES` array and Sanctum ability system — no second auth mechanism
- [ ] All four new scopes are read-only in this first version; write scopes are a deliberate, separate future prompt
- [ ] Webhook payloads are HMAC-signed using the same pattern Naara already uses to verify *incoming* provider webhooks, applied to outgoing delivery
- [ ] `DispatchWebhookJob` calls are added at existing event-producing points in the code, not a new event bus
- [ ] Zapier integration ships before any native CRM connector
- [ ] Native CRM connectors are explicitly out of scope for this prompt, gated on confirmed demand
