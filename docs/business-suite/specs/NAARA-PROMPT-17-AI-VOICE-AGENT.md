# NAARA PROMPT 17 — AI Voice Agent
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** Prompt 14 (needs a `RingGroup` to hand off to) and Prompt 15 (the transcript/intelligence pipeline the agent's calls should land in, so reporting doesn't fork into a separate system for AI-handled vs. human-handled calls). Do not start this prompt before both have landed.

**Confirmed before writing this prompt:**
- `VoiceProviderInterface::accessToken()` already mints Twilio WebRTC tokens for the existing browser dialer — this is the same underlying Twilio Voice application infrastructure that Twilio's ConversationRelay attaches to, so the account/application-level Twilio setup is not new.
- `VoiceDialerService`'s funded-block billing pattern (quote → hold → settle on hangup → refund unused) is the only money-safety pattern to reuse here — do not build a second billing path for agent-minutes.
- `VoiceProviderInterface` is Twilio-only (confirmed in Prompts 14/15's context) — this prompt is Twilio-first for that same reason. Telnyx's competing native AI voice stack is a real future option, but only once `TelnyxService` implements `VoiceProviderInterface` at all, which it doesn't today.
- **This is the one prompt in the whole series where the vendor relationship is not new but the application code genuinely is.** Twilio's ConversationRelay is a managed STT/TTS/WebSocket layer — it hands transcribed caller speech to *your* server and speaks back whatever *your* server returns. Naara has to build and run that server. There's no shortcut around this; budget it as new application engineering, not a config flag.

---

## §1 — Data model

**Build:**
1. `ai_voice_agent_sessions` table: `id`, `wallet_group_id`, `virtual_number_id`, `call_reference` (same call-identity scheme as Prompt 15's `call_recordings.call_reference`), `status` (`in_progress`/`handed_off`/`ended`), `outcome` (nullable: `qualified`/`booked`/`handed_off`/`no_outcome`), `transcript` (JSON array of turns, appended to live during the call — or written once at the end from ConversationRelay's own transcript if that proves simpler than live-append), `tool_calls` (JSON array — what the agent invoked and what it got back), timestamps.
2. A configurable-per-organization `ai_voice_agent_config` (could be columns on `Organization` or its own small table): system prompt/persona text, which languages to offer, which `RingGroup` to hand off to, business-hours-awareness (reuse Prompt 14's `BusinessHoursSchedule`, don't duplicate schedule logic).

## §2 — The WebSocket application server (new, not a Livewire component)

**Build:**
1. This runs outside Laravel's normal HTTP request/response cycle — it needs a persistent WebSocket connection per active call, for the duration of that call. Use whatever the codebase's existing pattern is for long-running/queued processes as a starting point for how this is deployed and supervised (check how existing queue workers are process-managed in this deployment before introducing a new process-management approach for this one service).
2. TwiML for an inbound call routed to the AI agent (a new `CallFlowNode` type — `ai_agent` — added to Prompt 14's node-type enum) uses `<Connect><ConversationRelay url="wss://.../agent" .../></Connect>`, pointing at this new server.
3. Per turn: receive the caller's transcribed utterance from ConversationRelay → append to `ai_voice_agent_sessions.transcript` → call the Anthropic API with the running conversation history plus a fixed tool schema (see §3) → stream the text response back to ConversationRelay for TTS.
4. Interruption handling (the caller starts talking while the agent is still speaking) is a real, non-optional part of a usable voice agent — confirm ConversationRelay's own interruption/barge-in support before building custom logic for it; don't assume it needs to be built from scratch without checking what the platform already handles.

## §3 — Tools the agent can call: reuse existing actions, don't build a parallel action system

**Build:**
1. Define a small, fixed tool schema for the Anthropic API call — e.g. `check_order_status(reference)`, `book_callback(preferred_time)`, `transfer_to_human()`. Each tool's implementation calls the **same underlying service a human support flow already calls** — `check_order_status` hits whatever service `SupportChat.php`'s existing AI assistant already queries for order/account lookups, not a new lookup path built specifically for the voice agent.
2. `transfer_to_human()` triggers the hand-off (§4) rather than ending the call — the agent should treat this as one of its available actions, not a special-cased exit outside the tool-calling loop.

## §4 — Hand-off

**Build:**
1. When the agent calls `transfer_to_human()` (or after N failed clarification attempts, or on explicit caller request — define the trigger conditions clearly, don't leave "when does it hand off" implicit), the call transfers into the `RingGroup` configured in §1's `ai_voice_agent_config`, using the same `<Dial>` mechanics Prompt 14 already built for ring groups — this prompt does not build a second call-transfer mechanism.
2. The accumulated `ai_voice_agent_sessions.transcript` is attached as context visible to whichever agent picks up — surfaced in the same call-detail view Prompt 15's recordings/transcripts already render into, so a human agent sees "here's what the AI already covered" without needing a separate screen.

## §5 — Billing

**Build:** agent-minutes are metered and billed through `WalletService::charge()`, using the exact same funded-block pattern `VoiceDialerService` uses for human-dialed minutes — ConversationRelay's own per-minute rate plus standard Twilio voice minute cost, with whatever margin `PricingEngine` already applies to voice minutes elsewhere applied consistently here too, not a separately-invented margin.

---

## Acceptance checklist for this prompt

- [ ] Runs on Twilio ConversationRelay against numbers already provisioned through `PermanentNumberRouter` — no new telephony vendor account
- [ ] Conversation logic calls the Anthropic API
- [ ] Every tool the agent can call reuses an existing service/action (order lookup, booking, etc.) rather than a parallel implementation built just for voice
- [ ] Hand-off transfers into an existing Prompt 14 `RingGroup`, with transcript context attached at the hand-off point
- [ ] Billing reuses `WalletService::charge()` and `VoiceDialerService`'s funded-block pattern exactly, including the existing margin logic
- [ ] Interruption/barge-in handling confirmed against ConversationRelay's native support before any custom logic is written for it
