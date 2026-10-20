# Self-hosted SMS and calling: decision report

**Date:** 2026-10-06 · **Supporting detail:** `self-hosted-comms-{codebase-audit,candidates,legal-risk,fit-matrix,tco}.md`

## Verdict: **NO-GO on self-hosting. Manage what we have, and extend through drivers, not infrastructure.**

Naara already runs a six-provider permanent-number lane and a six-provider OTP lane with circuit breakers, health scoring and per-provider markup (audit §1, §4), so "more resilience" is not a gap. Self-hosted voice (Asterisk/FreeSWITCH) only saves the $0.002/min Voice-API fee because the SIP trunk is still bought, which needs ~1.1 million minutes a month to break even. Self-hosted SMS (SIM gateways) only pays off above ~140-260k messages a month in the US, and for Nigeria its case is the price gap against Twilio, which a regional SMS aggregator driver closes with no phones, no SIMs and no legal red line. Our shared-cPanel install target can't run any of these daemons, and there is no demand evidence for a "bring your own gateway" feature. **There is also no volume data in the repo, so no cost conclusion beyond break-even is possible** (Gate 1 failed).

## Hypotheses
| | Verdict | Evidence |
|---|---|---|
| H1 SIM gateway lowers per-message cost | **Only above break-even** (~140-260k msgs/mo US; ~3-5k/mo vs Twilio Nigeria) | TCO doc. Telnyx US is $0.004 + carrier fees, not the figures that make SIM look cheap |
| H2 Adds coverage where numbers/registration are hard | **Unproven, legally risky.** Real signal: Twilio Nigeria outbound $0.3868/msg (PARTIALLY VERIFIED) | Legal doc §2-3 (SIM terms, A2P bypass, SIM-box red line, COUNSEL) |
| H3 Self-hosted voice cuts per-minute cost | **Disproved at our scale** | Saves only $0.002/min; ~$25.9k 12-month TCO |
| H4 Sellable white-label feature | **No evidence of demand**; cPanel can't host it; AGPL (httpSMS) conflicts | Fit matrix; installation docs |
| H5 New SIM-backed number products | **Disproved/RED LINE** if resold (SIM-box); OTP lane already has 5 providers | Legal doc §3 |
| H6 Reduces lock-in/outage risk | **Disproved**: 6+6 providers with breakers already | Audit §4 |

## Gates
| Gate | Result | Why |
|---|---|---|
| A value | **FAIL** | No hypothesis has VERIFIED evidence plus a quantified benefit; there is no volume to quantify against |
| B cost | **FAIL / unknown** | 12-month TCO $12-24k (SMS), ~$26k (voice) vs unknown benefit |
| C risk | **FAIL for httpSMS and any resale use; conditional for own-use** | AGPL; SIM-box red line; SIM terms, voice licensing unresearched |
| D fit | **PARTIAL** | A SIM driver would fit the provider interface and inbound webhook; it would not fit cPanel installs or "live cost" pricing |

## The three numbers
1. **Current volume/spend:** unknown. Not in the repo. Needed from you: 3 months of SMS segments, voice minutes and provider invoices by country.
2. **Best-case saving:** Nigeria SMS vs Twilio (~$0.39 each), and voice Twilio $0.014 vs Telnyx $0.007 per minute (a **provider switch**, no self-hosting).
3. **12-month TCO:** SMS pilot $12.4k-$23.6k; voice self-host ~$25.9k (assumption-driven, owner to replace).

## What I recommend instead (cheap, no new infrastructure)
1. **Pull the volume data** above. Everything else depends on it.
2. **Add a Telnyx voice driver** (it implements numbers/SMS but not voice in this codebase). Re-check Nigeria/Africa rates on Telnyx's price sheet first. A few days of work behind the existing `VoiceProviderInterface`.
3. **Add one regional SMS aggregator as a normal API driver** for Nigeria/Africa outbound, after pulling its real rates. Same four registration points the code already documents (audit §1). Aggregator names and prices were not verified in this research.
4. Do **not** build a SIM gateway, a PBX, Jasmin or Jambonz now.

## Top 5 risks if you went ahead anyway
1. SIM-box / interconnect-bypass exposure in Nigeria (RED LINE, counsel).
2. Operator ToS and throttling kill SIMs; messages limbo when a phone is offline.
3. AGPL obligations (httpSMS) vs the white-label model.
4. cPanel white-labels cannot run any of it; tenant support burden moves to us.
5. Toll fraud / SMS pumping on self-run infrastructure.

## Revisit triggers
Revisit if any holds: sustained SMS above ~140k/month in one country with an acceptable aggregator not available; a paying white-label customer asks for private gateways; counsel clears own-use SIM sending in a target market; or voice passes ~1M minutes/month.

## Corrections to the brief
- **Telnyx inbound SMS is not free** on its pricing page ($0.004/part + carrier fees).
- Twilio US SMS is **$0.0083/segment** (confirmed) and Nigeria outbound is far higher (~$0.3868, re-verify).
- Jambonz self-hosting **requires a licence key** (confirmed).
- **Telnyx has no voice driver in this codebase**; voice is Twilio/Vonage/Sinch.

## Sources and confidence
Repos (licence, size) VERIFIED: github.com/NdoleStudio/httpsms (AGPL-3.0), capcom6/android-sms-gateway (Apache-2.0), vernu/textbee (MIT), jookies/jasmin (Apache-2.0), asterisk/asterisk (GPL-2.0), signalwire/freeswitch (MPL), huenique/sms-gateway-android (Apache-2.0, 7 stars). Pricing VERIFIED: telnyx.com/pricing/messaging, telnyx.com/pricing/voice-api, twilio.com/en-us/sms/pricing/us. Jambonz licence VERIFIED: docs.jambonz.org/self-hosting. Packagist laravel-ami-toolkit VERIFIED (82 installs). PARTIALLY VERIFIED / secondary (search results): Twilio Nigeria price, NCC SIM-box coverage (ncc.gov.ng, guardian.ng, nairametrics.com), NDPA transfer rules (iapp.org, mondaq.com), SIM carrier throttling (themenonlab.blog, docs.sms-gate.app). **Not verified:** release/last-commit dates (GitHub API blocked), operator ToS, telecom licensing, Kannel, FreePBX, Kamailio, UK GDPR, any aggregator pricing.
