# Buyability + orchestration proof at zero balance (2026-10-18)

Every supplier balance is 0, so no order could be completed. This proves the OTHER half: each item is real, priced, accepted
by the supplier's own validation, and refused ONLY for lack of funds; and that our orchestration then refunds the buyer.
Live keys, throw-away scratch database, scratch wallet. No money moved anywhere.

## Supplier side (raw API)

| Supplier | Call | Answer | Meaning |
|---|---|---|---|
| eSIM Go | `POST /v2.5/orders` `type:validate` for `esim_1GB_7D_NG_V2` | `subTotal 3.34`, `valid:false` | bundle exists and is priced; invalid only because org balance is 0 |
| eSIM Go | real order `esim_1GB_7D_AT_V2` | 400 `Insufficient balance: 0.00 USD. Total is: 1.17 USD` | accepted, refused for funds |
| Quibity | real order plan 19 | 400 `Not enough credit` (`price 2.3`) | accepted, refused for funds |
| Zendit eSIM | real order `ESIM-AT-7D-1GB-NOROAM` | "wallet has insufficient funds" | accepted, refused for funds |
| Zendit gift | `POST /vouchers/purchases` `220_LV_10` | transaction created then `FAILED / INSUFFICIENT_FUNDS` (cost 11.60 USD) | offer valid; Zendit answers asynchronously, our poll reads the terminal state |
| Bitrefill gift | `POST /v2/invoices` `steam-usa` 20 | `balance_too_low` | product + value valid; funds only |
| Bitrefill top-up | `gosmart-usa` 25 | `missing_param: phone_number` | product valid, needs the recipient phone (our checkout collects it as a required field) |

Not testable until funded or keyed: Airalo (no credentials), Monty/Gigs/1GLOBAL eSIM (partner-gated, no keys), Africa's Talking
airtime, number providers (read-only by owner rule; their purchase calls spend real money immediately).

## Our side (orchestration, scratch wallet funded with 50.00)

| Flow | Sequence observed in the wallet ledger |
|---|---|
| eSIM Go plan | debit 1.67 (50.00 to 48.33), provider refuses, refund 1.67 (back to 50.00), admin alert queued |
| Quibity plan | debit 4.16, refuse, refund 4.16 |
| Zendit eSIM plan | debit 1.73, refuse, refund 1.73 |
| Zendit gift card (Amazon, 7.81 charged) | order row `processing`, provider fails, order `failed`, wallet 50.00 to 50.00 |

Net: the buyer is never left charged for something undelivered, the refund is idempotent (`refund:{ref}`), and the order row is
written before the provider is called. Cost price is never in any payload.

## What "buyable" still needs from the owner

Fund at least one supplier per lane (suggested floor: eSIM Go 20 USD, Zendit 20 USD, Bitrefill small BTC top-up), then one real
5-dollar order per lane; the code path above is otherwise proven.
