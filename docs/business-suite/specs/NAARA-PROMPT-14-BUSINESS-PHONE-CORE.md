# NAARA PROMPT 14 — Business Phone Core: IVR, Call Queues, Ring Groups, Business Hours
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** Prompt 13 (`WalletGroup`/`WalletGroupMember` generalized with `kind`/roles, `virtual_numbers.wallet_group_id`) must be merged first.

**Confirmed before writing this prompt:**
- `TwilioVoiceWebhookController::__invoke()` today does exactly this, in this order: verify the Twilio signature via `VoiceProviderInterface::verifyWebhook()` → log a `WebhookLog` row → if unverified, `<Reject/>` → look up `CallForwardingRule::where('twilio_number', $to)->where('status', ACTIVE)->first()` → if none, reject gracefully → else dispatch `LogCallEventJob` and build forward TwiML.
- `CallForwardingRule` fields: `user_id`, `virtual_number_id`, `twilio_number`, `forward_to_number`, `fallback_number`, `status`. This is a single-target forward and stays exactly as-is for every individually-owned (non-Organization) number — nothing in this prompt modifies `CallForwardingRule` or its resolution path.
- `VoiceProviderInterface` is implemented **only by Twilio** (`TelnyxService` has no voice methods at all — confirmed by direct read, no `attachVoiceWebhook`/`forwardTwiml`/`bridgeCall`). **This entire prompt is scoped to Twilio-provisioned numbers.** If a number was provisioned via Telnyx, none of this applies to it until a separate `TelnyxVoiceService` implementing `VoiceProviderInterface` exists — do not build against an assumption that Telnyx numbers can use this.

---

## §1 — New data model (additive, does not touch `CallForwardingRule`)

**Build:**
1. `call_flows` table: `id`, `wallet_group_id` (FK, required — a `CallFlow` only ever exists for an org-owned number), `virtual_number_id` (FK, must have `wallet_group_id` matching — enforce in a model boot/validation, not just a foreign key), `root_node_id` (nullable FK to `call_flow_nodes`, nullable because the flow can be created before its first node), `status` (`active`/`paused`), timestamps.
2. `call_flow_nodes` table: `id`, `call_flow_id` (FK), `type` (enum: `menu`, `queue`, `forward`, `voicemail`), `config` (JSON — schema per type, see §2), timestamps.
3. `ring_groups` table: `id`, `wallet_group_id` (FK), `name`, `strategy` (enum: `simultaneous`, `sequential`), timestamps.
4. `ring_group_members` pivot: `ring_group_id`, `organization_member_id`, `priority` (integer, used for `sequential` ordering — ignored for `simultaneous`).
5. `business_hours_schedules` table: `id`, `wallet_group_id` (FK), `timezone` (string, IANA name), `windows` (JSON array of `{day, start, end}`), `holiday_overrides` (JSON array of `{date, closed: bool, windows?: [...]}`), timestamps.
6. Models: `CallFlow` (`belongsTo(WalletGroup::class)`, `belongsTo(VirtualNumber::class)`, `hasMany(CallFlowNode::class)`), `CallFlowNode` (`belongsTo(CallFlow::class)`, self-referential children resolved through `config['children']` node IDs for `menu` type), `RingGroup`, `BusinessHoursSchedule`.

## §2 — `config` JSON shape per node `type` (define this exactly — do not let it drift per-node)

- `menu`: `{"prompt_text": "...", "digits": {"1": <child_node_id>, "2": <child_node_id>, ...}, "invalid_retry_node_id": <node_id>, "timeout_seconds": 5}`
- `queue`: `{"ring_group_id": <id>, "hold_music_url": "...", "max_wait_seconds": 120, "overflow_node_id": <node_id>}`
- `forward`: `{"target_number": "+234...", "fallback_number": "+234..."}` — deliberately the same shape as `CallForwardingRule.forward_to_number`/`fallback_number`, so the TwiML-building helper (§3) can share code between the two.
- `voicemail`: `{"greeting_url": "...", "transcribe": true}`
- A `menu` node's `business_hours_schedule_id` (optional field on the node config) lets a single node branch to a different child depending on whether `BusinessHoursSchedule::isOpenNow()` — build that method to check the schedule's `windows`/`holiday_overrides` against `now()` converted to the schedule's `timezone`.

## §3 — Webhook resolution change (the only touch point in existing code)

**Build:**
1. In `TwilioVoiceWebhookController::__invoke()`, **after** signature verification and **before** the existing `CallForwardingRule::where('twilio_number', $to)...` lookup, add: `$callFlow = CallFlow::whereHas('virtualNumber', fn($q) => $q->where('phone_number', $to))->where('status', 'active')->first();`. If found, resolve and return TwiML from the `CallFlow`'s root node (new method, e.g. `CallFlowResolver::buildTwiml(CallFlow $flow, Request $request): string`) and **do not** fall through to the `CallForwardingRule` lookup. If not found, execution continues exactly as it does today, completely unchanged — this is what guarantees zero impact on every individually-owned number.
2. `CallFlowResolver::buildTwiml()` walks from `root_node_id`, building: `<Gather>` for `menu` (with nested prompt + digit collection, looping to `invalid_retry_node_id` on bad input, per Twilio's own `<Gather>` semantics), `<Dial><Queue>` combined with a parallel `<Enqueue>`/agent-side pickup for `queue` (or, if simpler to implement correctly first, a `<Dial>` with multiple `<Number>` children for `simultaneous` ring groups, deferring true hold-queue `<Enqueue>` mechanics to a fast-follow if agent-availability signaling turns out to need more state than a stateless TwiML response can carry), `<Dial numberToDial fallback>` for `forward` (reusing the existing forward-TwiML-building code path from `VoiceProviderInterface::forwardTwiml()` rather than duplicating it), and `<Record>`+`<Say>` greeting for `voicemail`.
3. `LogCallEventJob` dispatch stays exactly as-is, with `rule_id` becoming nullable and a new `call_flow_id`/`call_flow_node_id` pair added alongside it, so a single call log table serves both paths without a schema fork.

## §4 — Ring group answer-side (agents actually being reachable)

**Build:**
1. Each `WalletGroupMember` who should be reachable via a `RingGroup` needs an inbound destination — this is either their personal Naara Line number (if they have one) or a browser-based answer surface using the same `VoiceProviderInterface::accessToken()` WebRTC mechanism the browser dialer already uses for outbound calls, extended to accept inbound.
2. `sequential` strategy: dial `ring_group_members` in `priority` order, each with a short ring timeout before falling to the next, using chained `<Dial>` attempts (Twilio doesn't support this as one verb — build it as `LogCallEventJob`-tracked state with the `CallForwardingRule`'s existing fallback-on-no-answer TwiML pattern as the template for "if this leg doesn't answer, try the next one").
3. `simultaneous` strategy: one `<Dial>` with every member's destination as a `<Number>` child — Twilio connects whichever answers first natively, no custom state needed.

## §5 — Business admin UI

**Build:** a `CallFlowBuilder` Livewire component (new — no existing component to extend, this is genuinely new surface) for an org `admin`/`owner` to visually assemble menu → queue/forward/voicemail nodes, assign ring groups, and attach a business-hours schedule. Reuse `Catalogue.php`'s or `Wizard.php`'s existing step-based Livewire pattern for the builder's multi-step feel rather than inventing a new UI paradigm for this one screen.

---

## Acceptance checklist for this prompt

- [ ] `CallFlow`/`CallFlowNode`/`RingGroup`/`BusinessHoursSchedule` are new, additive tables — `CallForwardingRule` and its resolution path are byte-for-byte unchanged
- [ ] `TwilioVoiceWebhookController` checks for an active `CallFlow` first, falls through to existing behavior only when none exists
- [ ] Scoped strictly to Twilio-provisioned numbers; a Telnyx-provisioned org number is explicitly rejected or hidden from the CallFlow builder, not silently broken
- [ ] `forward`-type nodes reuse the existing forward-TwiML code path rather than a duplicate implementation
- [ ] Ring group `simultaneous` strategy ships first (native `<Dial>` multi-number, no custom state); `sequential` and true hold-queue `<Enqueue>` can follow once the simpler path is verified working end-to-end
- [ ] `LogCallEventJob` handles both `rule_id` and `call_flow_id`-based calls in one table, not a schema fork

Report back before Prompt 17 specifically: confirm whether the `sequential`/hold-queue mechanics needed real custom state tracking (vs. Twilio verbs handling it natively) — that finding changes how much new infrastructure the AI Voice Agent's hand-off-to-a-human-queue step in Prompt 17 can reuse versus needs to build itself.
