# Naara Respond pricing check

Cost of one AI reply on the `fast` model class: **$0.00255** (1800 tokens in, 150 out, list prices from config/ai.php, unverified until checked against the invoice).

| Plan | Price/mo | AI included | Allowance | AI cost at 100% | % of price | Margin at 100% | Margin at expected use | Safe allowance (budget) | Status |
|---|---|---|---|---|---|---|---|---|---|
| Starter Reach | $19.00 | yes | 200 | $0.51 | 2.7% | $17.92 | $18.23 | 596 | safe |
| Growth | $49.00 | yes | 200 | $0.51 | 1% | $47.02 | $47.33 | 1537 | safe |
| Spotlight | $99.00 | yes | 200 | $0.51 | 0.5% | $95.52 | $95.83 | 3105 | safe |

Budget rule: the AI may cost at most 8% of a plan's price at full use; margin figures take off 3% card fees. Hosting, support and telephony are not in this table.

## Reading this report (2026-10-19)
- The AI assistant is **cheap on the `fast` model**: about a quarter of a cent per reply with the default assumptions. At the platform default of 200 actions a month it costs under $0.60 even on the smallest plan. The AI is not where margin is lost.
- Raising the model class to `standard` roughly doubles the per-reply cost; it still fits the 8% budget at these allowances.
- Real risks to margin are elsewhere: the **monthly NaaraCredit refill** a plan grants (value depends on how you price credits), **voice minutes and SMS** paid from the wallet (pass-through with markup, see `PRICING-DEFAULTS-REPORT.md`), card fees (assumed 3%), and support time.
- Token counts are assumptions (1,800 in / 150 out per reply). After a week of real traffic, replace them with measured values from Admin > AI costs (`respond_agent` rows in `ai_usage_log`) and re-run `naara:respond:pricing`.
- Suggested starting allowances (admin sets them per plan in Admin > Brand directory > plan capabilities > assistant allowance): Starter Reach 100, Growth 500, Spotlight 2,000 actions a month. At the fast model that is about $0.26, $1.28 and $5.10 of AI cost at full use, 1.3% / 2.6% / 5.2% of each plan's price.
- Re-run: `php artisan naara:respond:pricing --assume-granted --write=docs/RESPOND-PRICING-REPORT.md`.
