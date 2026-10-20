<?php

namespace App\Console\Commands\Bootstrap;

use App\Models\Coupon;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Services\Pricing\DiscountMarginGuard;
use App\Services\Pricing\PricingEngine;
use App\Services\Bootstrap\Steps\JourneyGoalsStep;
use Illuminate\Console\Command;

/**
 * Bootstrap Phase 3 report: writes docs/PRICING-DEFAULTS-REPORT.md from the LIVE pricing settings and the real PricingEngine / DiscountMarginGuard, over a grid of sample costs.
 * It uses sample costs on purpose (a fresh install has no catalogue yet); re-run it after the first catalogue sync for real SKUs. Nothing here is market data.
 */
class BootstrapPricingReportCommand extends Command
{
    protected $signature = 'naara:bootstrap:pricing-report {--out=docs/PRICING-DEFAULTS-REPORT.md}';

    protected $description = 'Margin and coupon-cost report for the current pricing defaults (sample costs; read-only)';

    public function handle(PricingEngine $engine, DiscountMarginGuard $guard): int
    {
        $md = ['# PRICING-DEFAULTS-REPORT (generated)', '', '> `php artisan naara:bootstrap:pricing-report` on '.now()->toDateString().'. Uses the live settings and the real PricingEngine, over SAMPLE wholesale costs (a fresh install has no catalogue). Margins are the platform default, labelled DEFAULT (tune); `pricing.market.*` is illustrative, not scraped data. Re-run after the first catalogue sync.', ''];

        $md[] = '## 1. Markups and floors in force';
        $md[] = '';
        $md[] = '| Setting | Value |';
        $md[] = '|---|---|';
        foreach (Setting::query()->where('group', 'pricing')->orderBy('key')->get() as $s) {
            $v = $s->value['value'] ?? null;
            $md[] = '| `'.$s->key.'` | '.(is_scalar($v) ? $v : json_encode($v)).' |';
        }
        $md[] = '';

        $min = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $md[] = '## 2. eSIM (default markup + MarginGuard floor of cost + $'.number_format($min, 2).')';
        $md[] = '';
        $md[] = '| Wholesale cost | Retail | Profit | Margin on retail | Guard hit |';
        $md[] = '|---|---|---|---|---|';
        foreach ([0.5, 1.2, 3.0, 8.0, 20.0, 60.0] as $cost) {
            $plan = new EsimPlan(['cost_price_usd' => $cost, 'provider' => 'esimgo']);
            $retail = $engine->calculateRetail($plan, false);
            $md[] = sprintf('| $%.2f | $%.2f | $%.2f | %.0f%% | %s |', $cost, $retail, $retail - $cost, $retail > 0 ? ($retail - $cost) / $retail * 100 : 0, $retail <= $cost + $min + 0.0001 ? 'floor' : 'no');
        }
        $md[] = '';

        $md[] = '## 3. SMS / OTP numbers (per provider markup + per-SMS floor)';
        $md[] = '';
        $md[] = '| Provider | Cost | Retail | Profit | Margin on retail |';
        $md[] = '|---|---|---|---|---|';
        foreach (['getatext', 'fivesim', 'twilio', 'telnyx'] as $p) {
            foreach ([0.01, 0.05, 0.30] as $cost) {
                $r = $engine->calculateSmsRetail($cost, $p);
                $md[] = sprintf('| %s | $%.3f | $%.4f | $%.4f | %.0f%% |', $p, $cost, $r, $r - $cost, $r > 0 ? ($r - $cost) / $r * 100 : 0);
            }
        }
        $md[] = '';
        $md[] = '## 4. Voice minutes';
        $md[] = '';
        $md[] = '| Cost/min | Retail/min | Margin on retail |';
        $md[] = '|---|---|---|';
        foreach ([0.007, 0.014, 0.05] as $cost) {
            $r = $engine->calculateVoiceRetail($cost, 'twilio');
            $md[] = sprintf('| $%.3f | $%.4f | %.0f%% |', $cost, $r, $r > 0 ? ($r - $cost) / $r * 100 : 0);
        }
        $md[] = '';

        $md[] = '## 5. Coupons at their MAXIMUM discount (the floor wins)';
        $md[] = '';
        $md[] = 'CouponEngine clamps every discounted price to `DiscountMarginGuard::floor()`, so a coupon can only ever consume part of the admin margin (cap `pricing.discount_margin_cap_pct`) and never goes below cost + minimum profit. Cost to the platform of one redemption on a $20-cost eSIM:';
        $md[] = '';
        $md[] = '| Coupon | Discount | Active | Price before | Price after (clamped) | Platform cost of one redemption |';
        $md[] = '|---|---|---|---|---|---|';
        $plan = new EsimPlan(['cost_price_usd' => 20.0, 'provider' => 'esimgo']);
        $retail = $engine->calculateRetail($plan, false);
        $floor = $guard->floor(20.0, $retail - 20.0);
        foreach (Coupon::query()->orderBy('code')->get() as $c) {
            $after = max(round($retail * (1 - (float) $c->percent_off / 100), 2), $floor);
            $md[] = sprintf('| `%s` | %.0f%% (%s) | %s | $%.2f | $%.2f | $%.2f |', $c->code, $c->percent_off, $c->applies_to, $c->is_active ? 'yes' : 'draft', $retail, $after, $retail - $after);
        }
        $md[] = '';

        $step = new JourneyGoalsStep;
        $md[] = '## 6. Reward liability (My Journey starter ladder, all goals INACTIVE until you switch them on)';
        $md[] = '';
        $per = (float) Setting::getValue('credits.per_usd', 100);
        $md[] = sprintf('Most one user can ever earn from the ladder: **%d credits = $%.2f** at `credits.per_usd` = %d. Budget cap (`journey.reward_budget_credits`): %d credits. Credits are redeemable only up to `credits.max_redeem_pct` of a checkout and never below the discount floor.', $step->lifetimeLiability(), $per > 0 ? $step->lifetimeLiability() / $per : 0, $per, (float) Setting::getValue(JourneyGoalsStep::BUDGET_KEY, JourneyGoalsStep::DEFAULT_BUDGET));
        $md[] = '';
        $md[] = '## 7. Not done here (needs the owner or real data)';
        $md[] = '';
        $md[] = '- Real catalogue audit (every SKU): run again after `esim:sync` and the number catalogue sync.';
        $md[] = '- Tax rates: deliberately not invented; configure per market with your accountant (TaxRates admin).';
        $md[] = '- Competitiveness band: needs real competitor data.';

        $out = base_path((string) $this->option('out'));
        file_put_contents($out, implode("\n", $md)."\n");
        $this->info('Wrote '.str_replace(base_path().'/', '', $out));

        return self::SUCCESS;
    }
}
