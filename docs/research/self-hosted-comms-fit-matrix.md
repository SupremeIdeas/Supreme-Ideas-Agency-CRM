# Self-hosted comms — Phase 4: capability and business-fit matrix

Rating vs the **current** Twilio/Telnyx-plus-others stack: Better / Same / Worse / N/A. "SIM" = sms-gate or textbee (the only SIM options left after the licence gate). "SMPP" = Jasmin. "PBX" = Asterisk or FreeSWITCH.

| Capability | SIM gateway | SMPP (Jasmin) | PBX | Evidence |
|---|---|---|---|---|
| Country coverage | **Maybe better** in 1-2 countries where API providers are very expensive (Twilio lists Nigeria outbound at $0.3868/msg, search snippet of twilio.com/sms/pricing/ng, `PARTIALLY VERIFIED`); worse everywhere we have no SIM, no operator terms read | Same (needs an aggregator we do not have) | Same (still needs a carrier trunk) | Pricing pages; no per-country operator review done |
| Number availability | Own SIM numbers could receive verification SMS, but selling them is the **SIM-box red line** | N/A | N/A | Legal doc §3 |
| Inbound for verification | Worse: phone offline/SIM-blocked failure modes, no SLA | N/A | N/A | sms-gate docs warn against batch; Naara OTP lane already has six providers + refund discipline |
| Delivery receipts / observability | Worse (status as reported by the phone) | Better (real SMPP DLRs) | Same | Candidates doc |
| Scale / throughput | Worse: tens-to-hundreds per hour per SIM (secondary source) | Better | Same | Candidates doc |
| Reliability / failover | Fits as a router lane only with timeout + reroute; a SPOF per device | Same | Worse than Twilio's hosted voice | Router already has breakers (`CircuitBreaker`) |
| Cost per unit | Only wins where the API price is extreme (Nigeria vs Twilio) or volume is above break-even | N/A | Saves only the $0.002/min Voice-API fee vs Telnyx | TCO doc |
| Pricing/margin fit | Fits PricingEngine if cost is a per-SIM amortised setting; cost is not "live" like the rest of the system (violates "number/SMS costs fetched live") | Fits | Fits | CLAUDE.md rule 3 |
| White-label fit | **Worse**: cPanel installs cannot run the gateway; licensee must own phones and SIMs | Worse (RabbitMQ/Redis daemon) | Worse (daemon, SIP exposure) | `docs/INSTALLATION.md`, `docs/SCALING-TRIGGERS.md` |
| Compliance posture | Worse (SIM terms, A2P registration bypass, COUNSEL) | Same | Worse (telecom licence unknown) | Legal doc |
| Product opportunities | "Bring your own gateway" for white-labels is conceivable; **no demand evidence** | None | None | No tickets/requests in repo or docs (search of `docs/`, `resources/views`, `app/`) |
| Support burden | Worse: phones, SIMs, Firebase, battery, carrier blocks | Worse | Worse: SIP fraud on-call | |

## Hypotheses where demand evidence was sought
- **H4 (sellable to white-labels):** no mention of self-hosting/SIM/data-sovereignty demand anywhere in the repo or its docs. Absence of evidence — weighs toward NO-GO.
- **H5 (new products via SIM numbers):** the only product it unlocks is persistent SIM-backed numbers for receiving verification SMS, which is both already sold through five OTP providers and, if resold, the red line.
