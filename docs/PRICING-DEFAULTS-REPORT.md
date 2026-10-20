# PRICING-DEFAULTS-REPORT (generated)

> `php artisan naara:bootstrap:pricing-report` on 2026-10-06. Uses the live settings and the real PricingEngine, over SAMPLE wholesale costs (a fresh install has no catalogue). Margins are the platform default, labelled DEFAULT (tune); `pricing.market.*` is illustrative, not scraped data. Re-run after the first catalogue sync.

## 1. Markups and floors in force

| Setting | Value |
|---|---|
| `pricing.architect.thin_margin_pct` | 15 |
| `pricing.currency_display` | USD |
| `pricing.default_markup_pct` | 30 |
| `pricing.developer_markup_pct` | 10 |
| `pricing.developer_sms_markup_pct` | 15 |
| `pricing.discount_margin_cap_pct` | 30 |
| `pricing.esim_global_markup_pct` | 40 |
| `pricing.esim_local_markup_pct` | 30 |
| `pricing.esim_regional_markup_pct` | 35 |
| `pricing.giftcard_markup_pct` | 8 |
| `pricing.giftcard_min_profit` | 0.25 |
| `pricing.low_balance_alert.esimgo` | 100 |
| `pricing.low_balance_alert.fivesim` | 10 |
| `pricing.low_balance_alert.getatext` | 10 |
| `pricing.market.band_pct` | 15 |
| `pricing.market.base_usd` | 0.99 |
| `pricing.market.per_day_usd` | 0.08 |
| `pricing.market.per_gb_usd` | 2.5 |
| `pricing.merchant_discount_admin_pct` | 20 |
| `pricing.merchant_discount_merchant_pct` | 10 |
| `pricing.minimum_profit_usd` | 0.5 |
| `pricing.mms_send_cost.telnyx` | 0.01 |
| `pricing.mms_send_cost.twilio` | 0.02 |
| `pricing.ngn_rate_source` | auto |
| `pricing.public_mode` | auto |
| `pricing.sms_markup_pct.fivesim` | 55 |
| `pricing.sms_markup_pct.getatext` | 50 |
| `pricing.sms_markup_pct.smsactivate` | 55 |
| `pricing.sms_markup_pct.telnyx` | 45 |
| `pricing.sms_markup_pct.twilio` | 45 |
| `pricing.sms_min_profit` | 0.01 |
| `pricing.sms_send_cost.telnyx` | 0.004 |
| `pricing.sms_send_cost.twilio` | 0.0079 |
| `pricing.voice_markup_pct.twilio` | 40 |
| `pricing.voice_min_profit` | 0.02 |
| `voice.max_call_minutes` | 60 |

## 2. eSIM (default markup + MarginGuard floor of cost + $0.50)

| Wholesale cost | Retail | Profit | Margin on retail | Guard hit |
|---|---|---|---|---|
| $0.50 | $1.00 | $0.50 | 50% | floor |
| $1.20 | $1.70 | $0.50 | 29% | floor |
| $3.00 | $3.90 | $0.90 | 23% | no |
| $8.00 | $10.40 | $2.40 | 23% | no |
| $20.00 | $26.00 | $6.00 | 23% | no |
| $60.00 | $78.00 | $18.00 | 23% | no |

## 3. SMS / OTP numbers (per provider markup + per-SMS floor)

| Provider | Cost | Retail | Profit | Margin on retail |
|---|---|---|---|---|
| getatext | $0.010 | $0.0200 | $0.0100 | 50% |
| getatext | $0.050 | $0.0750 | $0.0250 | 33% |
| getatext | $0.300 | $0.4500 | $0.1500 | 33% |
| fivesim | $0.010 | $0.0200 | $0.0100 | 50% |
| fivesim | $0.050 | $0.0775 | $0.0275 | 35% |
| fivesim | $0.300 | $0.4650 | $0.1650 | 35% |
| twilio | $0.010 | $0.0200 | $0.0100 | 50% |
| twilio | $0.050 | $0.0725 | $0.0225 | 31% |
| twilio | $0.300 | $0.4350 | $0.1350 | 31% |
| telnyx | $0.010 | $0.0200 | $0.0100 | 50% |
| telnyx | $0.050 | $0.0725 | $0.0225 | 31% |
| telnyx | $0.300 | $0.4350 | $0.1350 | 31% |

## 4. Voice minutes

| Cost/min | Retail/min | Margin on retail |
|---|---|---|
| $0.007 | $0.0270 | 74% |
| $0.014 | $0.0340 | 59% |
| $0.050 | $0.0700 | 29% |

## 5. Coupons at their MAXIMUM discount (the floor wins)

CouponEngine clamps every discounted price to `DiscountMarginGuard::floor()`, so a coupon can only ever consume part of the admin margin (cap `pricing.discount_margin_cap_pct`) and never goes below cost + minimum profit. Cost to the platform of one redemption on a $20-cost eSIM:

| Coupon | Discount | Active | Price before | Price after (clamped) | Platform cost of one redemption |
|---|---|---|---|---|---|
| `BLACKFRIDAY` | 15% (all) | draft | $26.00 | $24.20 | $1.80 |
| `COMEBACK15` | 15% (all) | yes | $26.00 | $24.20 | $1.80 |
| `EASTER` | 10% (all) | draft | $26.00 | $24.20 | $1.80 |
| `FIRSTESIM` | 10% (esim) | draft | $26.00 | $24.20 | $1.80 |
| `INDEPENDENCE` | 10% (all) | draft | $26.00 | $24.20 | $1.80 |
| `NEWYEAR` | 10% (all) | draft | $26.00 | $24.20 | $1.80 |
| `WELCOME10` | 10% (all) | yes | $26.00 | $24.20 | $1.80 |

## 6. Reward liability (My Journey starter ladder, all goals INACTIVE until you switch them on)

Most one user can ever earn from the ladder: **3620 credits = $14.48** at `credits.per_usd` = 250. Budget cap (`journey.reward_budget_credits`): 5000 credits. Credits are redeemable only up to `credits.max_redeem_pct` of a checkout and never below the discount floor.

## 7. Not done here (needs the owner or real data)

- Real catalogue audit (every SKU): run again after `esim:sync` and the number catalogue sync.
- Tax rates: deliberately not invented; configure per market with your accountant (TaxRates admin).
- Competitiveness band: needs real competitor data.
