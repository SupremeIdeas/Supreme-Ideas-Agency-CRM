# NOWPayments crypto payouts (master only)

Stablecoin payouts to wallets the member saved, through the NOWPayments Mass Payouts API. Delivered as a
payout-rail extension (`app/PayoutRails/nowpayments/`).

Source of truth: the NOWPayments Postman collection, "Mass payouts" (`documenter.getpostman.com/view/7907941/2s93JusNJt`).

## The flow the API requires (from the docs)

1. `POST /v1/auth` with the dashboard email + password -> a JWT valid for 5 minutes (cached 4).
2. `POST /v1/payout/validate-address` (`x-api-key`) before anything is saved.
3. `POST /v1/payout` (`x-api-key` + `Authorization: Bearer <JWT>`), body `{withdrawals:[{address, currency, amount, unique_external_id, ipn_callback_url, ...}]}`. `amount` has at most 6 decimals.
4. `POST /v1/payout/{batch-id}/verify` with the 2FA code. Mandatory by default; an unverified payout is auto-rejected after an hour. With the dashboard's authenticator secret saved in Provider Keys the code is generated automatically.
5. Status by IPN (`x-nowpayments-sig` = HMAC-SHA512 of the key-sorted JSON body with the IPN secret) or `GET /v1/payout/{id}`.

NOWPayments also requires the server IP AND every destination wallet to be on the dashboard whitelist (Settings, Whitelist).

## The whitelist hand-off (what the admin does)

1. A member adds a wallet (Wallet, Withdraw, "Crypto wallet"). Shape is checked per network, NOWPayments confirms it with validate-address, and it is saved as `pending`. If NOWPayments cannot be reached, nothing is saved.
2. Admin, Payouts, Payout health, "Crypto wallet whitelist": "Download new as CSV". Those addresses become `exported`.
3. Add them in the NOWPayments dashboard (Settings, Whitelist).
4. Back on the page: "Mark selected as whitelisted" (or "Mark all downloaded").
5. Until then the Guardian WAITS (re-checks every 30 minutes) any payout to that address. It is not refused and not refunded. The gateway also refuses a non-whitelisted address as a second line.

CSV columns: `account_id, network, currency, address, memo, label, added_at`. NOWPayments' own bulk-import format is not in the docs we have; the file is plain CSV you can paste or reshape.

## Status mapping

| NOWPayments | Ours | Why |
|---|---|---|
| creating / waiting / processing / sending | processing | in flight |
| finished | paid | final |
| rejected | failed (refund) | final |
| failed | processing + admin alert | NOT final; NOWPayments may resume it. Their docs: never recreate until finished/rejected |

`unique_external_id` = our `wireReference()`. If the create answer is lost, the reconciler scans recent payouts for it; only a complete scan may say "not found".

## Scope on purpose

- Stablecoins only (USDT on Tron/Ethereum/BNB Smart Chain/Solana, USDC on Ethereum). They are USD-pegged, so the USD payout amount is the coin amount and no price conversion or rate risk sits between balance and wallet. Network tickers live in `app/Support/CryptoPayoutNetworks.php`; a wrong ticker fails closed at validate-address.
- Float: stablecoin balances are summed into USD for the float tracker.

## Set-up (sandbox first)

1. Provider Keys: NOWPayments API key, IPN secret, payout dashboard email + password, and (recommended) the 2FA secret. Base URL `https://api-sandbox.nowpayments.io` for sandbox. IPN endpoint for payouts: `https://<site>/webhooks/payouts/nowpayments`.
2. In the NOWPayments dashboard whitelist this server's IP.
3. Test connection (reads `/v1/balance` and checks the payout login).
4. `php artisan payouts:sync-corridors nowpayments --countries=NG,GH,KE` (DISABLED corridors), then enable one in Payout settings.
5. Add a test wallet, run the whitelist hand-off, request a small payout, approve, watch it finish.

## UNVERIFIED until a sandbox payout proves it

- The payout IPN body: we assume it mirrors the payout status object (`id`, `status`, `unique_external_id`). If it differs, status lookup still settles the payout.
- Who pays the network fee (`fee_paid_by` is returned but not documented).
- The sandbox host name and the exact ticker spellings other than `usdttrc20` (which appears in the docs). Wrong tickers are rejected by validate-address, never paid.
- Whether NOWPayments dedupes on `unique_external_id`. We do not rely on it: the payout engine sends at most once per claim.
