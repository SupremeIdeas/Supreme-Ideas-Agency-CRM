# Self-hosted comms — Phase 1: codebase audit

Scope: `marketing-mockups` branch (= `main` + 23 marketing commits). Business Suite (`business-suite`) and NaaraBrain (`NaaraBrain`) are separate branches and were not merged into this read; where they matter it says so. Paths are repo-relative. Evidence tags: **CODE** (read in this repo), **DOC** (a repo doc), **NOT IN REPO** (data we need from the owner).

## 1. Messaging stack — **CODE**
Naara already runs a **multi-provider, multi-lane** number/SMS stack, not "Twilio + Telnyx":

| Lane | Providers (in router order) | Where |
|---|---|---|
| Permanent number (voice + 2-way SMS) | twilio, telnyx, vonage, sinch, plivo, sonetel | `app/Services/SMS/PermanentNumberRouter.php:52` (`$lane`); `SmsNumberRouter::laneFor()` returns only `['twilio','telnyx']` for TYPE_PERMANENT (`SmsNumberRouter.php:363`) |
| One-time OTP / rental numbers | getatext (US only), fivesim, herosms, virtsms, smspool, onlinesim | `SmsNumberRouter::laneFor()` |

- Two contracts: `SmsProviderInterface` (OTP/rental, `app/Services/SMS/SmsProviderInterface.php`) and `NumberProviderInterface` (permanent number: `searchNumbers`, `buyNumber`, `sendSms`, `outboundSmsCost`, `releaseNumber`, `monthlyCost`). Implementations: `app/Services/SMS/Numbers/{Twilio,Telnyx,Vonage,Sinch}Service.php`, `PlivoService`, `SonetelService`, and the six OTP services.
- Outbound send (the money spine): `MessageSenderService` — live PricingEngine quote, atomic wallet debit before send, refund on provider failure, idempotent reference, `sms.max_send_segments` (default 6).
- **A new SMS provider is a driver, not an architecture change**: the code itself documents the four places to touch (`PermanentNumberRouter::$lane`, `ProviderModels::MODELS[...]['lane']` + `PROVIDER_KEY_FIELD` in `app/Support/ProviderModels.php`, `ProviderHealth::PROVIDERS`, `SmsInboundWebhookController::PROVIDERS`).

## 2. Voice stack — **CODE**
- `VoiceProviderInterface` (`app/Services/SMS/VoiceProviderInterface.php`) is implemented by **Twilio, Vonage, Sinch** only. **Telnyx does NOT implement voice here** (only `NumberProviderInterface`). So today voice = Twilio-primary (matches CLAUDE.md), with Vonage/Sinch as capability-checked options.
- Features on top: forwarding, voicemail + transcription, in-browser dialer (Twilio access tokens), recording webhooks, spam block (`app/Http/Controllers/Webhooks/Twilio*WebhookController.php`, `app/Services/Voice/VoiceDialerService.php`). AI Voice Agent rides Twilio ConversationRelay (Business Suite, other branch).
- N2N (Naara-to-Naara) calls are **own WebRTC** and "need a VPS" (coturn + Reverb); cPanel installs keep calls off (**DOC** `docs/N2N-CALLS.md`).

## 3. Inbound handling — **CODE**
- `POST /webhooks/sms-inbound/{provider}` (`routes/web.php:436`, `SmsInboundWebhookController`): shared-token check with `hash_equals` **before** touching the payload, generic field mapping (so a new provider needs a token, not a controller), unmatched numbers = 200 no-op, queued `RecordInboundMessageJob`, idempotent per `(provider, provider_ref)`. Getatext and Twilio voice/recording/dial have dedicated signature-verified controllers.
- A self-hosted SIM gateway that can POST a webhook (httpSMS, sms-gate, textbee all can) would fit this controller with one array entry plus a token. That is the cheap part of the integration.

## 4. Provider selection — **CODE**
Lane-bounded routing (fall back only inside a lane), `CircuitBreaker`, `CandidateOrdering`, NCI success scoring (`app/Services/NCI`), `ProviderHealth`, per-provider markup + low-balance alert settings (`pricing.sms_markup_pct.*`, `database/seeders/PricingSettingsSeeder.php`), MarginGuard on every quote.
**Implication for H6 (outage/lock-in):** a six-provider permanent lane and a six-provider OTP lane with breakers already exist. Resilience is not the gap.

## 5. Data model — **CODE**
`virtual_numbers`, `outbound_messages`, `inbound_messages` (+threads), `call_events`, `voice_calls`, `call_forwarding_rules`, `blocked_numbers`, `spam_blocked_callers`, `esim_usage_snapshots` (migrations `2026_07_12_150007`…`2026_09_14_040000`). Provider cost is read live and never exposed; retail = cost + markup via PricingEngine.

## 6. Multi-tenancy / white-label — **CODE + DOC**
White-label children ship the same provider code; providers are credential-gated (`ProviderModels`, `FeatureEntitlements`). Installs run on **shared cPanel with a cron-drained database queue** or on a VPS with Redis/Horizon (**DOC** `docs/SCALING-TRIGGERS.md`, `docs/INSTALLATION.md`). Anything that needs a long-running daemon (Asterisk, FreeSWITCH, Jasmin + RabbitMQ + Redis, a Go API + CockroachDB for httpSMS) **cannot run on the cPanel target** and would have to live on a separate server per tenant or be run by us.

## 7. Observability — **CODE**
Heartbeats (`JobHeartbeats`), `SystemHealth`, `AlertService`, provider health/breaker state, delivery status stored per outbound message. Delivery-status normalisation exists per provider driver.

## 8. Queues / infrastructure — **DOC**
Default today: shared hosting, DB queue drained once a minute (~60 s latency floor). VPS + Horizon is the documented graduation path. No evidence in the repo of a server on which extra daemons already run.

## 9. Real traffic — **NOT IN REPO**
There is no production usage data in the repository. **Exit gate 1 cannot be passed from the code.** The owner must supply (last 3 months, by month):
1. Outbound SMS segments by destination country and by provider, and the provider invoice total.
2. Inbound SMS counts by provider.
3. Voice minutes in/out by destination country, and provider invoice total.
4. Number of active permanent numbers and the monthly rental total.
5. Any customer/support requests mentioning "own SIM", "own gateway", "data sovereignty", or a country we can't serve.

Where the rows exist in the live database, the owner (or a session with read access) can pull them from `outbound_messages`, `inbound_messages`, `voice_calls` and `virtual_numbers`. Until then every cost conclusion is a model, not a measurement.
