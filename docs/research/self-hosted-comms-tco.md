# Self-hosted comms — Section 9: TCO and break-even

**There is no real volume data in the repo (audit §9), so this is a break-even model, not a forecast.** The three scenarios (current, 3x, 10x) are replaced by a table of the monthly volume you would need for each option to beat the current provider. Compare that number with your invoices.

## Assumptions (all `ASSUMPTION`: replace with real figures)
- Engineering $40/h. Build 160 h (SMS driver + admin + monitoring) or 240 h (voice). Ops 10 h/month (SMS) or 15 h/month (voice). The brief's 1.5x safety factor is applied to the "1.5x" column; "1x" and "2x" shown for sensitivity.
- SMS pilot hardware: 5 Android phones at $100, SIM plans $8/month each, VPS $20/month. No operator fair-use terms were read; plans may forbid automated use.
- Capacity: ~1,000 messages/day per SIM (kept under the "tens-to-low-hundreds per hour" caution), so one SIM costs ~$0.0003 per message at full use.
- Voice: VPS + TURN $60/month. Trunk minutes are **still bought** from a carrier.

## Provider rates used (US list prices fetched 2026-10-06)
| | Rate | Source / confidence |
|---|---|---|
| Telnyx outbound US long code | $0.004/part **plus** carrier fees ~$0.0035-0.0045 | telnyx.com/pricing/messaging, VERIFIED |
| Telnyx inbound US long code | **$0.004/part plus carrier fees (NOT free)** | same page. **Correction: the brief says inbound is free.** |
| Twilio SMS US | $0.0083/segment, carrier fee ~$0.0035-0.0045, long code $1.15/mo | twilio.com/en-us/sms/pricing/us, VERIFIED |
| Twilio SMS Nigeria outbound | $0.3868/message | twilio.com/sms/pricing/ng via search snippet, PARTIALLY VERIFIED (re-pull before deciding) |
| Telnyx voice (US) | $0.002/min Voice API + $0.005 outbound / $0.0032 inbound SIP | telnyx.com/pricing/voice-api, VERIFIED. Nigeria rates not on page |
| Twilio voice outbound US | $0.014/min | brief; not re-fetched, UNVERIFIED |

## SMS: 12-month TCO and break-even
| Engineering multiplier | 12-month TCO | Break-even vs Telnyx US (saves ~$0.0075/msg) | Break-even vs Twilio Nigeria (saves ~$0.386/msg) |
|---|---|---|---|
| 1x | $12.4k | ~138k msgs/month | ~2.7k msgs/month |
| 1.5x | $18.0k | ~200k msgs/month | ~3.9k msgs/month |
| 2x | $23.6k | ~262k msgs/month | ~5.1k msgs/month |
(TCO = build + 12 months ops + hardware + SIMs + VPS. Savings per message = provider price minus ~$0.0003 SIM cost.)

Reading it: **in the US, SIM gateways only win above ~140-260k messages/month**, and US A2P rules make consumer-SIM sending a poor idea anyway. **For Nigeria, the case is the price gap against Twilio specifically**, and that gap is exactly what a regional aggregator driver closes without any phones. Aggregator rates were not found in this research; the break-even for a driver is simply driver build hours (~40-60 h) divided by (Twilio price minus aggregator price).

## Voice: 12-month TCO and break-even
Self-hosted PBX: 240 h x $40 x 1.5 = $14.4k build, ops 15 h/month x $40 x 1.5 = $10.8k, infra $0.7k = **~$25.9k**. Saves only the $0.002/min Voice-API line versus Telnyx, because the SIP trunk is still bought. Break-even ~12.9M minutes/year = **~1.1M minutes/month**. Automatic NO-GO trigger #3 of the brief (benefit is voice-minute savings at volumes we do not have).

The real voice lever is different: Twilio $0.014/min outbound versus Telnyx $0.007/min is a **2x** gap, and Telnyx has no voice driver in the codebase (it implements numbers/SMS only). A Telnyx voice driver is a few-day, no-new-infrastructure change. Needs Nigeria/Africa Telnyx rates from its price sheet first.
