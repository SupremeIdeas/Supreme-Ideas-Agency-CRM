# Self-hosted comms — Phase 3: legal, regulatory, licensing risk

Not legal advice. Everything marked **COUNSEL** must be answered by a Nigerian telecoms/data lawyer (and one per other launch market) before any pilot.

## 1. Licence impact on the white-label model
- **AGPL-3.0 (httpSMS).** Running a modified AGPL program as a network service obliges us to offer the corresponding source to its users. Our white-label children are distributed code that would *include or call* it; calling it over HTTP as a separate service is generally treated differently from linking it into the Laravel app, but that boundary is exactly what counsel must confirm. A white-label business that must keep its modifications private is a poor fit. **Recommendation: do not adopt httpSMS.** (AGPL-3.0 VERIFIED on the repo.)
- **MIT (textbee), Apache-2.0 (sms-gate, Jasmin):** permissive, no source-disclosure duty; keep notices. Safe for redistribution in a white-label.
- **GPL-2.0 (Asterisk), MPL (FreeSWITCH):** we would *run* them as separate servers, not ship them inside the Laravel package, so copyleft would attach only to changes we make to those programs. Fine if used unmodified.
- **Jambonz:** licence key per domain (VERIFIED), so it is a licensed product, not a free option, and a per-tenant key problem for white-labels.

## 2. SIM-based commercial messaging
Consumer-SIM tariffs and operator terms of service typically forbid automated/commercial sending and throttle or block SIMs (secondary sources only, `UNVERIFIED` per operator). Sending A2P traffic through personal SIMs also usually bypasses sender-ID/DLT-style registration regimes. **COUNSEL + operator-by-operator review required for each country; no operator terms were read in this research.**

## 3. Interconnect bypass / SIM-box — **RED LINE**
Nigeria's NCC treats SIM-boxing (terminating traffic through SIM banks at local rates to avoid international termination rates) as illegal; SIM boxes are not type-approved, and NCC has pressed operators to stamp it out (NCC page and Guardian/Nairametrics coverage via search, secondary). Reselling SIM-backed capacity, or using SIM gateways to terminate third-party calls or messages, sits on or over that line. **Any design that sells SIM-backed termination to customers is RED LINE — needs counsel and is rejected by this report.** Own-use (our own users' verification messages) is a different, narrower question and is still **COUNSEL**. Penalty specifics for SIM-boxing were **not** found in this research; the figures seen related to pre-registered SIMs, not SIM boxes.

## 4. Data protection
Message content and call metadata are personal data. Under the NDPA 2023 (enacted 12 June 2023; NDPC regulator) cross-border transfers need an adequacy basis or approved safeguards (secondary sources). Using Twilio/Telnyx means a processor abroad (already true today); self-hosting would *reduce* cross-border transfer for the self-hosted slice but make Naara (or the licensee) solely responsible for security, retention and breach notification. Net effect: neutral-to-worse unless the servers are in-country and operated with real security practice. UK GDPR and other regimes: not researched.

## 5. Telecom licensing for voice
Operating our own PBX/SIP and offering calling to customers may trigger a licence (e.g. a VoIP/ISP-type licence) beyond buying trunk minutes from a licensed carrier. **Not researched; COUNSEL** for Nigeria and each market.

## 6. Fraud exposure
Self-hosted PBXs are a top toll-fraud target and SMS-pumping a top abuse vector. Controls needed: per-user credit caps, destination allow-lists, rate limits, SIP hardening, geo-blocking, alerting. Naara already has wallet-first billing and caps; Twilio even sells pumping protection at $0.025/msg (Twilio pricing page via search, secondary), which is a cost we do not carry on a self-hosted route but must replace with our own controls.

## Gate 3 result
No unresolved red line **for own-use SIM gateways that only send our own users' messages in a country where counsel has cleared it**; **red line for any resale/termination use**; licence acceptable for sms-gate / textbee / Jasmin, **unacceptable for httpSMS**.
