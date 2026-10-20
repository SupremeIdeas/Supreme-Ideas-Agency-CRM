# Provider live-key test pass (2026-10-13)

Run against the owner's real provider accounts from a scratch database. **No key is stored anywhere in the repository.** Live keys were used for read-only calls only (balances, price and bank lookups, account resolution); nothing was bought, sent, called or charged on a live key.

| Layer | What was exercised | Result |
|---|---|---|
| Paystack (test key) bank list | `PaystackBankResolver::banks('NG')` and the Withdraw screen | 289 banks, PalmPay (`999991`) present |
| Paystack add payout account | Withdraw screen, PalmPay 7089997083 | Saved as FRANK CHARLES EBUBEDIKE, bank PalmPay, verified, default, masked |
| Paystack (live key, read-only) | resolve PalmPay, bank list, balance | Name resolves identically, 289 banks, balance NGN 0 |
| Paystack card payment | `initialize` (redirect URL issued), then a real TEST card charge (4084...) with our metadata, `verify`, then the signed `charge.success` webhook to `/webhooks/payments/paystack` | Wallet credited once (NGN 1,000 = USD 0.67); a replay credits nothing; a bad signature is 401. The hosted checkout page itself sits behind a Cloudflare bot check, so it cannot be driven by a headless browser |
| Paystack payouts | `payouts:sandbox-check paystack --send` | 8 of 8 PASS (recipient, transfer, lookup by our reference, duplicate reference refused) |
| eSIM Go (sandbox) | catalogue, balance, order body, full catalogue sync | Found and fixed 4 real bugs (below); 5,434 bundles now sync |
| Quibity | catalogue | **Not verifiable**: `esim.sm/api/reseller/v1` does not exist; the supplied token is a consumer-app session token that `api.quibity.com` answers with "unsupported version of the app". Need Quibity's reseller API documentation |
| Airalo | none | No credentials supplied |
| 5sim | balance, price | Works (balance 0). ISO country codes are rejected by 5sim, callers must pass its slugs |
| HeroSMS | balance | `BAD_KEY` 401 for the key as supplied and with the `14376U` prefix stripped: re-copy the key from the HeroSMS profile |
| Getatext | balance, price list, US routing | Balance works (USD 0.77); price lookup was broken (below), now routes US to Getatext |
| Twilio / Telnyx | read-only: monthly cost, number search, outbound SMS price | Work. Telnyx number search returns masked numbers |
| Money path, eSIM | Checkout buy with a real eSIM Go sandbox order at USD 0 balance | Debit USD 4.34, provider refused, refund USD 4.34 recorded atomically, buyer told |
| Money path, numbers | GetNumber order (5sim, Nigeria, USD 0 balance) | Debit 0.4434, provider refused, refund recorded, no order row |
| Webhooks | 34 forged unsigned or badly signed POSTs across payment, payout, SMS, voice, KYC, app-build and WhatsApp endpoints | All rejected (401, 403 or 404); no wallet change |
| Public surface | 372 GET requests as guest, member and admin | 0 server errors |
| Security spot checks | admin path, dotfiles, vendor, headers, login throttle | Admin and dotfiles hidden; CSP, nosniff, frame and referrer headers present; login throttled after 5 attempts |
| `naara:verify` | every area | Only environment warnings remain (see below) |

## Bugs found and fixed
1. **eSIM Go catalogue stopped at page 1**: 50 of 5,434 bundles were imported. Now walks every page.
2. **Every eSIM Go order would have been rejected** ("Order must contain Order Items"): the body was not the v2.5 shape. Now `type: transaction`, `Order: [...]`, `assign: true`, and the new eSIM's ICCID and SM-DP+ details are read back so the buyer gets a working QR and LPA string. The post-order assignment read follows the v2.5 documentation and could not be exercised without a funded account: watch the first live order.
3. **eSIM Go balance always read 0** (the API nests it under `organisations[0]`), so the low-balance alert could never fire.
4. **Unlimited eSIM Go bundles were stored as `data_mb = -1`**; the platform's convention is `null`, which the fair-usage disclosure and failover matching rely on.
5. **Getatext could never serve a number**: its price list is `{status, errors, prices: [...]}` and the code read the top level, so every service looked unavailable and the US lane owner was always skipped for 5sim. Now reads `prices`; US Google, Telegram and Facebook route to Getatext again (live prices USD 0.66, 1.00, 0.60). Covered by `GetatextPriceLookupTest`.
6. Wallet top-up swallowed the provider's error silently; it is now logged.

## Owner items from this pass
- HeroSMS key invalid. Quibity reseller documentation needed. Airalo credentials not supplied.
- eSIM Go: fund the account and place one small order, then check the eSIM arrives with a QR; set `ESIMGO_WEBHOOK_SECRET` and the callback URL in the eSIM Go portal.
- Paystack: set the live keys only in the production `.env` or Admin > API keys; the three keys pasted into the chat should be rotated, since a chat transcript is not a safe place for live credentials.
- Environment (not code): configure a TURN relay for calls, a real mail transport, `upload_max_filesize` of at least 8 MB, run `naara-id:backfill`, and run all payout probes once in Admin > Payouts > Health.
