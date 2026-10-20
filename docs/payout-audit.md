# Payout audit (Engineering v3, Phase 2.0)

Date: 2026-10-11. Source of truth is the code; every row below was read, not assumed.

## Gateways that can pay out

| Gateway | Class | Interfaces | How Payout Health finds it |
|---|---|---|---|
| Paystack | `Services/Payouts/PaystackPayoutGateway` | PayoutGateway, DeclaresCapabilities, SupportsStatusLookup, ReportsBalance | registry (`PayoutService::providerNames()`) |
| Flutterwave | `FlutterwavePayoutGateway` | PayoutGateway, DeclaresCapabilities, lookup | registry |
| Stripe Connect | `StripePayoutGateway` | PayoutGateway, DeclaresCapabilities | registry |
| PayPal | `PayPalPayoutGateway` | PayoutGateway, DeclaresCapabilities | registry |
| Cryptomus | `CryptomusPayoutGateway` | PayoutGateway, DeclaresCapabilities | registry |
| Manual external | `ManualExternalPayoutGateway` | PayoutGateway | registry (never counted as automatic) |
| Grey | `PayoutRails/grey` (extension) | + ExplainsAvailability, lookup, balances | registry once the rail loads |
| NOWPayments | `PayoutRails/nowpayments` (extension) | + ExplainsAvailability, lookup, balances | registry once the rail loads |
| Payoneer, Stripe Global | planned (`config/payouts.php`) | | shown as "not installed" |

## Payment gateways with no payout implementation (gap list for the owner)

`CoinPaymentsGateway`, `BinancePayGateway`, `PayssionGateway` have no payout class. Whether each provider offers a payout / mass-withdrawal API is **UNVERIFIED** (the blueprint requires checking the provider's current docs, which this audit did not do). Nothing is routed to them. Implement or consciously skip once the docs are read. Stripe, Paystack, Flutterwave, PayPal, Cryptomus have both a payment and a payout class; NOWPayments gained a payout rail (this session).

## Contradictions found and fixed

1. `AutomationStatus` iterated the literal list `paystack, flutterwave, stripe, paypal, cryptomus`: updater-delivered rails (Grey, NOWPayments, a future Payoneer) were never evaluated, so "at least one payout provider is configured" could read wrong. Now derived from the registry via `GatewayStatusResolver`.
2. "Configured" was `gatewayFor() !== null`, which merged six causes. `GatewayStatusResolver` now returns a reason (`no_key`, `key_present_env_mismatch`, `gateway_unavailable`, `breaker_tripped`, `extension_not_installed`, `extension_problem`, `disabled_by_admin`) with the one-line fix.
3. `RailAdvisor` collapsed everything into "temporarily unavailable". Only a tripped breaker says that now; a rail that is not set up says "not open yet" (all 14 locales). Members never see keys, modes or internals.
4. Saving the same bank account twice created two rows. Now a friendly "You have already saved that account."

## One money-moving core (confirmed)

Every withdrawal type calls `PayoutService::createRequest()`: user (`WithdrawalService`), merchant, staff, referral, partner, platform (`PlatformWithdrawalService`). Only `PayoutService::send()` calls a gateway. No second core exists.

## Hand-kept provider lists left on purpose

`RailAdvisor::FAST_RAILS` / `STATIC_ORDER`, `PayoutRailRegistry::RAILS` are the user-facing RAIL identifiers (paystack, flutterwave, stripe_connect, global), not gateway lists, so `naara:verify payouts` no longer scans RailAdvisor. `PayoutWebhookController::PROVIDERS` is the core set; extensions are admitted through `PayoutRailExtensions::installed()`.

## UNVERIFIED (owner / sandbox needed)

- Country and rail coverage per provider is not yet copied from each provider's official docs into versioned config with `source_url` and `verified_on`. Until that is done, the capability probe (provider list vs enabled corridors) is the guard: a mismatch is flagged, not routed.
- Sandbox end-to-end loops per gateway need sandbox keys.
- Live micro-payout probe (decision 1 in the blueprint) is not built; default "yes, capped, owner's own account" awaits owner confirmation.

## New check buttons

Admin, Payouts, Health: per-gateway Test and Check corridors, Run all probes, Simulate corridor. `php artisan naara:verify payouts` runs: registry-derived lists, every enabled corridor has a ready gateway, probes are recent and passing.
