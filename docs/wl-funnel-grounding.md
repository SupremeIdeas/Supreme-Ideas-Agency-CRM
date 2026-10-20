# White Label funnel: Phase 0 grounding report

Date: 2026-10-11. Repo: master only (the funnel uses the License Surgery models, which forks must never receive).
Legend: ✅ confirmed in code, ◐ exists but differs from the blueprint or is partial, ✗ missing.

## 0.1 What the blueprint says exists

| Item | Status | Finding |
|---|---|---|
| `WhiteLabelInstance`, `WhiteLabelLicensePlan`, `WhiteLabelLicensePayment`, `WhiteLabelProjectIntake`, `WhiteLabelApiLog`, `WhiteLabelGuideLink` | ✅ | All present in `app/Models`. |
| Plan keys `basic, medium, extended, extended_v2` | ✅ | `WhiteLabelLicensePlan::KEY_*`; `WhiteLabelContent::PLAN_KEYS`. |
| Plan prices | ◐ | Seeder: basic 1500, medium 2500, extended 5000, extended_v2 7500. The blueprint says Extended is about 2500. **Owner to confirm** (the DB rows are the truth; the funnel reads them and never hard-codes a price). |
| `MerchantWhiteLabel` (route `merchant.white-label`) | ✅ | `requestLicense`, `payBalance`, `proposeUpgrade`, `submitIntake`, balance/total-due via `WhiteLabelInstance::balanceToExtended()` / `totalDueUsd()`. |
| `requestLicense` gate | ◐ | Requires an ACTIVE merchant that is V2, plus the hosting disclaimer; creates an instance with `acquisition_method = merchant_self_service`. The funnel must satisfy these through a new service, not by loosening the check. |
| `MerchantUpgradeService::grant()` | ◐ | Admin-only (`abort_unless admin`) and PERMANENT: `markUpgraded()` sets `tier = v2` with no expiry. A time-boxed preview needs a new grant record plus a revoke job that reverts only what the funnel granted. **Do not reuse `grant()` for visitors.** |
| `selfUpgrade()` | ✅ | Charges `MerchantSettings::upgradePriceUsd()` from the wallet (idempotent reference `merchant_v2_upgrade:{id}`). Not used by the funnel. |
| `BecomeMerchant` / `MerchantJoin` | ◐ | Becoming a merchant needs membership unlock (spend minimum, enrollment fee, or referral target), optional KYB (L3), then `apply()` and an admin `approve()`. `MerchantJoin` is a storefront-invite page, unrelated. "Default merchant requirements" for funnel users is therefore an **owner decision** (see below). |
| `WhiteLabelRegistry` (admin) + intake PDF | ✅ | Route `admin.white-label`, `admin.white-label.intake.pdf`. |
| Landing `/white-label` | ✅ | `LandingController::whiteLabel`, route `white-label.landing`. No `/white-label/start` yet. |
| `white-label:upgrade-installments` | ✅ | Scheduled daily 06:05. |
| `white-label:check-deploy-timelines` | ◐ | Real name is `whitelabel:intake-deploy-check` (no hyphen, different suffix). The funnel's regression tests must target the real name. |
| `WalletService::debit(... reference ...)` | ✅ | Idempotent; used by upgrade paths. |
| `barryvdh/laravel-dompdf`, `endroid/qr-code` | ✅ | In `composer.json`; `resources/views/pdf/white-label-project-intake.blade.php` is the pattern. |
| Invoice number sequence | ✅ (absent) | None exists; Phase 6B builds it. |
| Turnstile / captcha | ✅ | `App\Support\Turnstile`, `SecuritySettings`. |
| Notification catalog | ✅ | `config/notification_catalog.php`. |
| Guided Tours (Engineering v3 Phase 10) | ✗ | Not built. The preview tour (Phase 2) depends on it, or on a small tour component built for the funnel. |
| `NaaraId` generator | ◐ | `App\Services\N2N\Identity\NaaraId` has no prefix parameter, and N2N is its own master-only module. The funnel gets a sibling `FunnelCode` (prefix `BS-` / `EX-`, Crockford body + check symbol) and does not import N2N code. |
| FX / rate code | ◐ | `CurrencyService` and payout `FxGuard` exist for payouts; manual payment quotes need their own `manual_payment_rates` table with `as_of` (blueprint 5.3). |
| Feature flags | ✅ | `FeatureFlags::FEATURES`. Added in this phase: `wl_funnel`, `wl_manual_payments`, `wl_custom_orders`, `wl_preview_unlock`, all default OFF. |

## 0.2 Crypto receiving addresses (verified independently, no trust in the pack's own claim)

All four QR images in the pack were decoded with zxing-cpp at 2x scale and compared with `seed/manual-payment-accounts.seed.json`:

| Coin / network | QR decodes to seed address | Checksum |
|---|---|---|
| USDT TRC20 | yes | base58check, 0x41 prefix: valid |
| USDT BEP20 | yes | EIP-55: valid |
| USDC BEP20 | yes | EIP-55: valid |
| USDC Solana | yes | base58 to 32 bytes: valid |

Still owed before any payer sees an address: (a) a PHP/CI verifier `naara:verify wl-payments` (TRON and Solana base58 are easy; EIP-55 needs keccak-256, which is NOT in PHP core or `vendor/`, so it must be implemented or a small package approved), (b) QR decoding stays an out-of-band script (no PHP QR decoder is installed), (c) the owner's test transaction on every network (blueprint 5.6). Addresses stay `enabled = false` until then.

## 0.3 Rates

Confirmed by eye from the owner's captures: GBP to USD 1.3036 (USD to GBP 0.74595978), EUR to USD 1.1075 (USD to EUR 0.88157489). They are not exact inverses, so the blueprint's conservative-direction rule applies. USDT/USDC to USD 0.998, USD to NGN 1342.01 and EUR to NGN 1495.07 still to be read from their screenshots when Phase 4 builds the rate table. `USD to PTS` is an internal points pair and is excluded from payments.

## 0.4 Seeding manual payment accounts

Not done yet, on purpose. The seed file contains a personal bank account and wallet addresses and **must not be committed**. Plan: an admin-only seeder/command that takes the seed path at run time (`php artisan wl:seed-manual-accounts --file=...`), stores fields encrypted, loads crypto rows disabled, and writes an audit entry. Needs the Phase 5.1 tables first.

## 0.5 Feature flags

Added, all default OFF (see above). Money-adjacent flags stay off until the Phase 5.6 checklist is complete.

## Decisions needed from the owner before Phase 1

1. **Merchant requirements for funnel visitors.** Today an active merchant needs the programme unlock, optionally KYB, and an admin approval. A visitor who is "unlocked by the funnel" has none of that. Options: (a) create the Merchant row as ACTIVE with an audited reason `funnel` (fastest, lets a stranger act as a merchant for the preview window), (b) create it in a limited `funnel_preview` state that can see the purchase flow but cannot sell, invite or earn (safer, recommended). The blueprint assumes (a); this is a money and abuse question.
2. **Plan prices** (Extended: 5000 or 2500?).
3. Funnel ID prefix mapping, preview durations, EUR manual payments, NGN manual account: blueprint defaults apply unless changed (decisions 2 to 6).

## Gate 0 status

| Gate item | Status |
|---|---|
| Grounding report complete | ✅ (this file) |
| Four QR images decode to seed addresses and pass checksums | ✅ (manual run); ✗ PHP/CI verifier not built yet |
| Rate fixtures loaded | ◐ (two needed pairs confirmed; table does not exist yet) |
| Seed loaded behind admin auth | ✗ (needs Phase 5.1 tables) |
| Feature flags | ✅ |

Gate 0 is NOT passed. Phase 1 should not start until the owner answers the merchant-requirements question.
