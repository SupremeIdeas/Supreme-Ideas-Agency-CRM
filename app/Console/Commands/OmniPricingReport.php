<?php

namespace App\Console\Commands;

use App\Models\BrandSubscriptionPlan;
use App\Services\Omni\RespondPricing;
use Illuminate\Console\Command;

/** Prints (and optionally saves) the Naara Respond margin check for every Naara Pro plan (MASTER-ONLY). Pure arithmetic over the model price table. */
class OmniPricingReport extends Command
{
    protected $signature = 'naara:respond:pricing {--write= : Save the Markdown report to this path} {--assume-granted : Judge every plan as if it included the AI assistant}';

    protected $description = 'Check that every Naara Pro plan keeps its margin with the AI assistant on';

    public function handle(RespondPricing $pricing): int
    {
        $rows = $pricing->report(BrandSubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(), (bool) $this->option('assume-granted'));
        $per = $pricing->costPerReply();
        $md = "# Naara Respond pricing check\n\nCost of one AI reply on the `".config('omni.agent_model_class')."` model class: **\$".number_format($per, 5)."** (".config('omni.pricing.tokens_in_per_reply').' tokens in, '.config('omni.pricing.tokens_out_per_reply')." out, list prices from config/ai.php, unverified until checked against the invoice).\n\n";
        $md .= "| Plan | Price/mo | AI included | Allowance | AI cost at 100% | % of price | Margin at 100% | Margin at expected use | Safe allowance (budget) | Status |\n|---|---|---|---|---|---|---|---|---|---|\n";
        foreach ($rows as $r) {
            $md .= '| '.$r['plan'].' | $'.number_format($r['price'], 2).' | '.($r['grants_ai'] ? 'yes' : 'no').' | '.$r['allowance'].' | $'.number_format($r['ai_cost_full'], 2).' | '.($r['ai_pct_full'] ?? 'n/a').'% | $'.number_format($r['margin_full'], 2).' | $'.number_format($r['margin_expected'], 2).' | '.$r['safe_allowance'].' | '.$r['status']." |\n";
        }
        $md .= "\nBudget rule: the AI may cost at most ".config('omni.pricing.ai_budget_pct').'% of a plan\'s price at full use; margin figures take off '.config('omni.pricing.payment_fee_pct')."% card fees. Hosting, support and telephony are not in this table.\n";
        $this->line($md);
        if ($path = $this->option('write')) {
            file_put_contents($path, $md);
            $this->info("Saved to $path");
        }

        return $rows !== [] && collect($rows)->contains(fn ($r) => $r['status'] === 'over budget') ? self::FAILURE : self::SUCCESS;
    }
}
