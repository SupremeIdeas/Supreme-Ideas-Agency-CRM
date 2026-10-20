# Provider live test, 2026-10-18 (read-only, no keys stored in the repo)

Only GET/balance/catalogue calls. No purchases, SMS, calls or charges.

| Provider | Result | Note |
|---|---|---|
| Paystack (live) | OK | Balance endpoint answers; NGN balance 0. |
| eSIM Go | OK | Organisation endpoint answers for the Naara organisation. |
| Quibity | OK without sandbox | The key is a LIVE key. With `x-sandbox: on` the API answers 401, so set `QUIBITY_SANDBOX=false`. |
| Zendit (prod) | OK | Balance 0 USD; `/vouchers/offers` lists 6361 offers in the shape the client parses. |
| Bitrefill | FIXED | The v2 API rejects Basic auth ("basic_auth_not_supported"); a personal access token as Bearer works (`/ping`, `/products`). Client, config, admin key form and tests now use `BITREFILL_API_KEY`. |
| Twilio | OK | Balance answers. |
| Telnyx | OK | Balance answers. |
| 5sim | OK | Profile answers; balance 0, rating 96. |
| Getatext | OK | Header is `Auth: <key>` (what the client sends); balance and prices answer. |
| Africa's Talking | OK (sandbox) | Sandbox user data answers. |
| HeroSMS | KEY REJECTED | `BAD_KEY` on `getBalance` with the supplied key. Regenerate or confirm the key at the HeroSMS panel. |
| MontyMobile | NOT INTEGRATED | No client in the platform and no API docs supplied; needs the base URL / docs before anything can be built. |
| Airalo, eSIM Go webhook secret, SMS-Activate | NOT SUPPLIED | |

Funding needed before any real order test: Paystack balance, Zendit balance, 5sim balance, Getatext ($1.87), Telnyx ($5), Twilio ($15.50).
