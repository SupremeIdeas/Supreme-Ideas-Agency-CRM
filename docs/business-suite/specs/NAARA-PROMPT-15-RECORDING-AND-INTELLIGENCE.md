# NAARA PROMPT 15 — Call Recording, Transcription, and Conversation Intelligence
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** Prompt 13 (Organizations). Prompt 14 is not a hard dependency — recording can attach to plain `VoiceDialerService` calls too — but the intelligence layer is far more useful once Prompt 14's call flows exist, since that's where real call volume shows up.

**Confirmed before writing this prompt:**
- `VoiceDialerService` already implements a funded-block billing pattern for outbound calls: quote the cost → hold funds → settle on hangup → refund unused balance. This is the *only* money-safety pattern this prompt should reuse — do not design a second one for recording/transcription minutes.
- `CaptureEsimUsageSnapshotJob` is the existing template for "a recurring job normalizes a provider's raw response into one clean table." Its shape (poll → normalize → write a snapshot row → leave raw data trace for support) is the template for `TranscribeCallRecordingJob` below.
- There is no existing `CallRecording`, `Transcript`, or call-intelligence model anywhere in the codebase — this is genuinely new, not an extension of a hidden existing feature (unlike Prompt 16's inbox).

---

## §1 — Data model

**Build:**
1. `call_recordings` table: `id`, `call_reference` (the existing call-log identifier used by `LogCallEventJob` — reuse whatever ID that job already keys calls by, don't invent a second call-identity scheme), `wallet_group_id` (nullable — personal-account calls can be recorded too, if the person opts in), `user_id`, `recording_url`, `duration_seconds`, `provider` (`twilio`), `provider_recording_sid`, `transcript_status` (`pending`/`processing`/`complete`/`failed`), timestamps.
2. `call_transcripts` table: `id`, `call_recording_id` (FK), `transcript_text`, `transcript_source` (`twilio_builtin`/`deepgram`/`assemblyai` — kept as a field specifically so a future provider swap doesn't require a schema change), timestamps.
3. `call_intelligence_summaries` table: `id`, `call_recording_id` (FK), `sentiment` (`positive`/`neutral`/`negative`), `talk_listen_ratio` (decimal, agent-vs-caller), `keywords` (JSON array), `coaching_notes` (text), `summary_text` (short paragraph), timestamps.

## §2 — Recording capture

**Build:**
1. Add a `record: bool` option to `VoiceDialerService`'s call-placement path and to Prompt 14's `CallFlowResolver`-built TwiML (`<Dial record="record-from-answer">` / `<Record>` — native Twilio verbs, zero new vendor). For Organization-owned numbers this should default on (configurable per org, since a business often needs recordings for QA/compliance); for personal accounts it stays opt-in and off by default, since a random consumer using the browser dialer to call a friend has no reason to be recorded without asking.
2. Twilio's recording-status-callback webhook (a new, small controller — same signature-verification pattern `TwilioVoiceWebhookController` already uses) writes the `call_recordings` row once the recording is ready, then dispatches `TranscribeCallRecordingJob`.
3. Any jurisdiction-specific two-party-consent requirement for call recording is a legal/compliance question, not an engineering one — flag it back to Angel rather than deciding it in code; the technical default above (on for business, opt-in for personal) is a starting point, not a compliance determination.

## §3 — Transcription

**Build:**
1. `TranscribeCallRecordingJob` — same shape as `CaptureEsimUsageSnapshotJob`: fetch the recording, call a transcription step, write a `call_transcripts` row, update `call_recordings.transcript_status`.
2. Start with Twilio's own built-in transcription add-on (already available on recordings from a Twilio account you're already paying for — the cheapest possible first version). Only add a dedicated STT vendor (Deepgram or AssemblyAI, either is fine, pick based on a short accuracy comparison against a handful of real Naara call recordings) if the built-in option's accuracy proves insufficient in practice — do not add a fourth voice-adjacent vendor speculatively.

## §4 — Conversation intelligence

**Build:**
1. Once a transcript is `complete`, dispatch a second job that sends the transcript to an LLM with a fixed prompt template requesting: sentiment, talk/listen ratio (if speaker-diarization data is available from the transcript source; otherwise omit rather than fabricate it), 3–5 keywords, one paragraph of coaching notes, and a short summary — parsed into the `call_intelligence_summaries` columns.
2. Use the Anthropic API for this step. This platform is already built around Claude for its own development process; keeping the runtime AI vendor consistent avoids a second AI vendor relationship, a second API key to secure, and a second set of rate limits to plan around, for a single feature.
3. Wrap both the transcription call and the intelligence call in `WalletService::charge()` — debit the org's (or personal user's) wallet, attempt the provider call inside the `Closure`, and let the existing refund-on-failure behavior handle any provider error, exactly as `Checkout.php` already does for eSIM orders. Do not write a bespoke try/catch-and-manually-refund block here — the pattern already exists and is already trusted.

## §5 — Surfacing it

**Build:** attach the recording/transcript/summary to wherever a call's detail is already viewable today (`Dialer.php`'s call history, and Prompt 14's call-flow call logs once that prompt lands) — this is a display addition to an existing view, not a new standalone page.

---

## Acceptance checklist for this prompt

- [ ] Recording uses native Twilio recording, no new vendor
- [ ] Transcription starts on Twilio's built-in option; a dedicated STT vendor is added only after a documented accuracy comparison shows it's needed
- [ ] Sentiment/scoring/coaching runs on the Anthropic API
- [ ] All billing for this prompt's steps goes through `WalletService::charge()`, reusing the existing refund-on-failure behavior — no new money-safety code written
- [ ] Recording defaults: on (configurable) for Organization-owned numbers, opt-in/off for personal accounts — flagged to Angel as a legal question, not silently decided
- [ ] Recordings/transcripts/summaries appear as an addition to existing call-history views, not a new standalone page
