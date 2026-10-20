# New provider drivers: Telnyx voice and the regional SMS gateway

Built 2026-10-06 from the self-hosted comms research verdict (`docs/research/self-hosted-comms-decision.md`: NO-GO on self-hosting, extend with drivers). Nothing here is switched on until credentials are entered.

## 1. Telnyx voice (TeXML)

`App\Services\SMS\Numbers\TelnyxVoiceService` implements `VoiceProviderInterface` (bound `voice.telnyx`). `App\Services\Voice\VoiceProviders` picks the voice stack for a number by the provider that owns it (lane rule: a Telnyx number is never served by Twilio and the reverse).

| Feature | Twilio | Telnyx |
|---|---|---|
| Call forwarding + fallback number | yes | **yes (new)** |
| Voicemail to the Messages inbox | yes | **yes (new)** |
| Click-to-call bridge | yes | yes (driver method, no UI caller yet) |
| In-browser dialer | yes | **no**: the dialer loads Twilio's Voice SDK. `accessToken()` is implemented and tested so swapping the browser SDK is a front-end task only |

**Webhooks** (both Ed25519-verified, `telnyx-signature-ed25519` + `telnyx-timestamp`, 5 minute replay window): `POST /webhooks/telnyx/voice` and `POST /webhooks/telnyx/recording`. They reuse the Twilio controllers, told which driver to use by a route default, because TeXML is TwiML-compatible.

**Setup (your 10 minutes in the Telnyx portal):**
1. Create a TeXML Application. Put its id in Admin, Provider keys, "Telnyx - TeXML Application ID" (`TELNYX_TEXML_APP_ID`). Its voice URL is set by the app when a user turns forwarding on.
2. Copy the account id (`TELNYX_ACCOUNT_ID`) and the portal public key (`TELNYX_PUBLIC_KEY`).
3. Enter the real per-country voice rates from Telnyx's price sheet as settings `pricing.voice_cost.telnyx.cc234` (Nigeria), `.cc233`, `.cc254`, ... and `pricing.voice_cost.telnyx.default`. **Until you do, the cost basis is 0.02 per minute (the same as the Twilio fallback), so switching a number to Telnyx cannot silently lower a price.** The research brief's "$0.007 vs $0.014 per minute" is UNVERIFIED and was not used.

**Verified against developers.telnyx.com on 2026-10-06:** TeXML application PATCH, number assignment `PATCH /phone_numbers/{id}/voice {connection_id}`, outbound `POST /texml/Accounts/{account_sid}/Calls {To, From, ApplicationSid, Url}`, WebRTC `POST /telephony_credentials` then `/token`, Ed25519 header names.
**Not verifiable without a sandbox (check before enabling):** that TeXML webhooks use the same Ed25519 scheme and the `timestamp|body` joiner as event webhooks; the response field carrying the call id on the outbound POST (the code accepts `call_sid`, `data.call_sid`, `sid`, `data.sid`); that `connection_id` can be cleared with `null`; that TeXML sends `RecordingUrl / RecordingSid / RecordingDuration` like Twilio.

**Behaviour change to know about:** a number on a provider with no voice driver (for example Vonage) used to accept a forwarding rule that did nothing. It now says "Call forwarding isn't available on this number yet." Rules written before providers were tracked (no linked number) still belong to Twilio.

## 2. Regional SMS gateway (Africa's Talking), send-only

`App\Services\SMS\Gateways\AfricasTalkingGateway` behind a new `OutboundSmsGateway` contract, with `RegionalSmsRouter` choosing it. **It is not a lane member and is not called by `MessageSenderService`.** The research assumed an aggregator could be a normal provider; the code audit shows it cannot:

- A Naara Line message is sent from the user's own number by the provider that leases that number. An aggregator leases no numbers, so it can only send from a sender ID, and a reply could never reach the user.
- `OutboundMessage` requires a `virtual_number_id`.

So the gateway is for traffic that is sent from a sender ID by design (platform notices, verification messages). Nothing in the shared code sends those today (the only platform SMS is the N2N verification code, which is master-only and stays on Twilio). **Wiring it into customer sends is a product decision: see the question at the end of the build report.**

Off by default: nothing is routed until `sms.regional.enabled` is true **and** `sms.regional.dial_codes` lists the dial codes it may carry (for example `234,254`). Cost is admin-set (`pricing.sms_send_cost.africastalking`, or per dial code `...africastalking.cc234`); the fallback 0.05 is a placeholder, **enter the real rate before enabling**. No price was found in this research.

Delivery reports: `POST /webhooks/sms-delivery/africastalking?token=...`. The callback is not signed, so it is guarded by a secret token compared with `hash_equals` (`AFRICASTALKING_CALLBACK_TOKEN`).

**Confirmed:** `POST /version1/messaging`, form fields `username / to / message / from`, headers `apiKey` and `Accept`, reply `SMSMessageData.Recipients[]` with `statusCode` (101 = sent), `messageId`, `cost`; sandbox host `api.sandbox.africastalking.com`, username `sandbox`. **Not verified:** that delivery callbacks are unsigned; Nigerian sender-ID registration rules; live per-message price.

Registered in: `config/services.php`, `ProviderKeys` (4 fields), `ProviderStatus`, `ProviderHealth` (reachability only), `ProviderRegistrySeeder`, the bootstrap credential registry (with its delivery URL).

## Not built (by decision)
Self-hosted SMS or voice (SIM gateways, PBX, Jasmin, Jambonz): owner decision 2026-10-06, for a future decision.
