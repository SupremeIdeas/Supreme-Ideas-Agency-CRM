# NaaraSim — Batch 5: Usage-Credit Economy
### Master repo only. Money layer for N2N (Primitive P2). Highest-risk batch: treat every path as a ledger invariant. For Claude Sonnet + Graphify.

**Depends on:** B3 (recipient resolution). **Unblocks:** B6, B7, B10, B11.

## 0. Findings from the codebase
- `CreditService` (`app/Services/Credits/CreditService.php`): `earn/spend/spendWithdrawable/rewardReferral`, `Cache::lock("credits:{id}")` + DB transaction, `credit_ledger` rows with `balance_after`, `SCALE = 2`. USD peg from `CreditSettings::creditsToUsd/usdToCredits`.
- `quoteRedemption()` lets credits discount **eSIM/SMS checkout**, and part of the balance is **withdrawable** (referral). → Loyalty credit **must not** be reused as the N2N currency (Master §2.4).
- Wallet: `UserWallet`, `WalletTransaction` (type enum `credit|debit|refund|referral|withdrawal`, unique user+reference index from migration `2026_09_06_161643`). Prefer `debit` + an `n2n_credit_purchase:{ref}` reference over altering the enum.
- `VoiceCall` already has the **hold → settle** pattern (`amount_held`, `minutes_authorized`, `settled_at`); copy the shape.
- `Referral` (`reward_pct`, `rewarded`) + `ReferralRewardService` already implement first-purchase commission.
- `PricingEngine`, `DiscountMarginGuard`, `PricingArchitect` exist for margin math.
- `WalletGroup` shares the *owner's wallet* by member cap; it is not the credit-share feature.

## Phase A — Usage bucket
1. Add `bucket` (`loyalty` | `usage`) to `credit_ledger`; on `user_wallets` add `usage_shareable` and `usage_nonshareable` (decimal, scale 2). Purchased + subscription-grant → shareable. Gifted-in + loyalty spent on N2N → non-shareable (Master D4b: blocks credit-farming rings).
2. Spend order for N2N: loyalty first, then non-shareable, then shareable. The usage bucket is **never** redeemable at checkout and **never** withdrawable — enforce in `quoteRedemption` and the payout services with tests.
3. Invariant test: sum of ledger rows per bucket = wallet columns, always.

## Phase B — Buy credit from wallet
1. Debit wallet, credit `usage_shareable` at the peg (`usdToCredits`), atomic, idempotent by reference. One-way: **no conversion back to wallet money**. Refund policy is an explicit admin/legal decision; default = non-refundable except gift-return to sender.
2. Native-iOS flag `credits.purchase_enabled_ios` default off (Master D9; read `docs/APP-STORE-PAYMENTS-COMPLIANCE.md`).
3. Verification: double-submit buys once; insufficient wallet fails cleanly; ledger and wallet reconcile.

## Phase C — Share credit
1. Recipient picked by **Naara ID, username, verified phone or email** through `IdentityResolver` with live validation (debounced, rate-limited). Extend the existing wallet-share recipient input to accept the same identifiers.
2. Registered recipient → atomic transfer (paired ledger rows, same reference) into the recipient's `usage_nonshareable`.
3. Only shareable credit can be shared. Caps: per-recipient, per-day, minimum sender account age, minimum/maximum amount — all admin settings.
4. Verification: concurrent shares cannot overspend; sharing to self is rejected; received gift credit cannot be re-shared.

## Phase D — Pending gift for unregistered email/phone (24h escrow)
1. `credit_gifts`: sender, recipient identifier (normalized; store hashed + masked display), amount, status `pending|claimed|refunded|cancelled`, `expires_at = now+24h`, idempotency key, referral link. Sender is debited **immediately**; funds sit in escrow, not in any balance.
2. Invite carries the **sender's referral link only**. Claim succeeds only if the person registers **through that link within the window and verifies the same email/phone** the gift was addressed to (prevents link-forwarding theft). Credit goes to the new user's `usage_nonshareable`; `Referral` row created so the existing first-purchase commission applies unchanged.
3. Expiry job `credits:expire-gifts` (every minute): `UPDATE … WHERE status='pending' AND expires_at <= now()` guarded by affected-row count + lock, so refund happens **exactly once**, back to the sender's usage bucket. Register with `JobHeartbeat`. Works from a 1-minute cPanel cron.
4. Claim-vs-expiry race resolved by row lock; whichever wins, the other is a no-op.
5. Verification: register at minute 23:59 → claimed; at 24:01 → already refunded; wrong email → not claimed; job run twice → one refund; kill the job mid-run and re-run.

## Phase E — Pricing and metering (server-authoritative)
1. `N2NPricing`: `messageCost()` (setting, default 0.5), `mediaCost(bytes, type)` = `ceil2(MB × admin $/MB × markup × usdToCredits)`, `callRatePerMinute()` from a USD setting, `speakerRate()` = same. **Admin $/MB is derived from the real storage bill and reviewed monthly** (Master §2.7); show a worked example in the admin panel. Enforce a minimum-profit floor with the existing margin machinery.
2. `UsageMeter`: `charge()` (instant, idempotent), `hold()`, `settle()`, `release()`. Exception `InsufficientUsageCredit` mapped to a UI that offers **Buy** and **Ask a friend to share**.
3. Put the admin controls as a tab in the existing pricing-architect screen (no new nav item).
4. Verification: a 0.5 charge is exact at scale 2; hold/settle refunds the unused part; client-supplied sizes/durations are ignored.

## Phase F — Subscription grant and UI
1. Add `is_pro`, `n2n_credit_grant` to `BrandSubscriptionPlan`; on activation/renewal grant into `usage_shareable`, idempotent by `sub:{id}:{period}`. Grant expiry is an admin setting (default: none) — **flag** to the owner as a liability decision.
2. Upgrade the NaaraCredit page: both balances, buy, share, pending gifts with countdown, history.
3. Low-balance and credit-received events publish `credit_alert` tone events (B2).

## Phase G — Accounting and abuse
1. Admin report: outstanding usage-credit liability, pending gifts, consumed credit → platform earning. Ask an accountant how consumption is recognized; do not decide it in code.
2. Velocity monitors: gift rings, new accounts receiving from many senders, repeated refunds.

## Constraints
- Every mutation: idempotency key + `Cache::lock` + DB transaction; never trust client amounts.
- Decimal math only; no float comparisons on money.
- Erasure and legal hold cover `credit_gifts`.

## Deliverable
Ledger invariant test results, the gift-expiry race tests, the derived $/MB worked example (with the source of the storage figure marked UNVERIFIED if not confirmed from the actual bill), and the liability report.
