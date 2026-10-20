# Self-hosted comms — Phase 2: candidate profiles

Evidence from primary pages fetched 2026-10-06 (GitHub repo pages, docs). **Not verified** items are marked. GitHub's API returned 403 to the fetcher, so release dates and last-commit dates were **not** obtained; "active" below means the repo page showed recent activity, nothing more. Confirm with `git log -1` before relying on it.

## SMS — Android-phone (SIM) gateways

| | httpSMS | Android SMS Gateway (sms-gate.app) | textbee | huenique/sms-gateway-android |
|---|---|---|---|---|
| Source | github.com/NdoleStudio/httpsms | github.com/capcom6/android-sms-gateway | github.com/vernu/textbee | github.com/huenique/sms-gateway-android |
| **Licence** | **AGPL-3.0** (VERIFIED, repo page) | **Apache-2.0** (VERIFIED) | **MIT** (VERIFIED) | Apache-2.0 (VERIFIED) |
| Scale of project | 5.8k stars, 1.1k forks | 5.8k stars, 1.0k forks, 427 commits | 3.1k stars, 460 forks, 31 open issues | 7 stars, 28 commits (hobby grade) |
| Stack | Go (Fiber) API, Nuxt UI, Kotlin app, **CockroachDB + Redis**, **Firebase Cloud Messaging mandatory**, SMTP, Cloudflare Turnstile | Kotlin/Ktor app; **local-server mode** (the phone IS the API) or private cloud server; webhooks; SMS, MMS, data SMS; multi-SIM; rate limiting | React/Next, NestJS, **MongoDB**, Kotlin app; REST + webhooks; multi-device | Termux + Rust + Python proxy + ngrok; REST send only |
| What it does not do | No DLR guarantees beyond phone-reported status; needs Google services on the device | Docs say **not recommended for high-volume batch** (carrier restrictions) | Same SIM limits | No inbound webhooks documented; not production grade |
| Laravel path | HTTP API + webhook → add to `SmsInboundWebhookController::PROVIDERS` + a driver | Same; local mode needs the phone reachable from the server (VPN/tunnel) | Same | Not recommended |
| Verdict | **Eliminate** for the white-label product (AGPL + Firebase + CockroachDB footprint) | **Best candidate** if any SIM gateway is ever piloted | Acceptable second | **Eliminate** (hobby) |

Common SIM-gateway limits (secondary sources, `UNVERIFIED` as operator facts): carriers commonly throttle to tens-to-low-hundreds of SMS per hour per SIM and block SIMs that exceed it; the sms-gate FAQ lists `RESULT_ERROR_LIMIT_EXCEEDED` for exactly this. A phone-offline or SIM-blocked failure leaves the message in limbo unless the router times out and reroutes.

## SMS — carrier-grade

**Jasmin SMS Gateway** — Apache-2.0 (VERIFIED). SMPP client/server, HTTP API, routing, AMQP (RabbitMQ) + Redis, Python/Twisted, 1.2k stars, 2,097 commits, 45 open issues (VERIFIED). It is **not** a way to avoid a carrier: it needs an SMPP bind from an aggregator or carrier. Value is only if Naara later signs direct SMPP deals. Needs a long-running server (VPS), RabbitMQ and Redis: **cannot ship on cPanel**.

Not evaluated in depth (`UNVERIFIED`, named only in the brief): **Kannel** — old WAP/SMS gateway, dropped from scope because Jasmin covers the same SMPP need with an HTTP API and a maintained Docker path.

## Voice

| | Asterisk | FreeSWITCH | Jambonz |
|---|---|---|---|
| Licence | **GPL-2.0** (dual) (VERIFIED; Sangoma) | **MPL** (VERIFIED; SignalWire is primary sponsor) | **Self-hosting needs a licence key tied to a domain**; free keys only for non-commercial and pre-revenue (VERIFIED, docs.jambonz.org/self-hosting). **Not free.** |
| Activity | 34.5k commits, 155 open issues, ARI + AMI (VERIFIED) | 36.2k commits, 896 open issues (VERIFIED) | n/a |
| Needs | VPS, SIP trunk (a paid carrier), TURN, hardening | Same | Same + licence |
| Laravel path | Thin AMI/ARI client. PHP packages are tiny: `khody2012/laravel-ami-toolkit` v0.6.0.37 (20 Jun 2026) has **82 installs, 2 stars** (VERIFIED, Packagist) — read it, then write our own thin client | Event Socket client (not researched) | HTTP/WebSocket API |

Dropped with reasons: **FreePBX/FusionPBX** (admin GUIs for a PBX we would not offer to customers), **Kamailio/OpenSIPS** (carrier-scale SIP routing, overkill at our volume), `yousefkadah/freepbx-laravel-integrator` (not read).
Browser calling (JsSIP/SIP.js over WSS to Asterisk/FreeSWITCH) is viable in principle but needs TURN/STUN, TLS certs and a VPS; **we already built exactly that need for N2N** (coturn + Reverb, `docs/N2N-CALLS.md`) and Twilio's browser dialer for PSTN calls. Not researched further (`UNVERIFIED`).
