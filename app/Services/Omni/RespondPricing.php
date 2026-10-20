<?php

namespace App\Services\Omni;

use App\Models\BrandSubscriptionPlan;
use App\Services\AI\ClaudeGateway;
use App\Support\Business\BusinessSettings;

/**
 * Does each Naara Pro plan still make money once the AI assistant is on? (MASTER-ONLY.) Multiplies the measured-or-assumed tokens per AI reply by the model price
 * table (the same table the admin AI cost view uses), against what each plan earns and the monthly allowance it grants. Pure arithmetic, no provider call.
 * Replace the token assumptions in config/omni.php with measured numbers from ai_usage_log once real traffic exists.
 */
class RespondPricing
{
    public function __construct(private readonly ClaudeGateway $gateway)
    {
    }

    /** USD for one AI reply on the configured model class. */
    public function costPerReply(): float
    {
        $model = $this->gateway->model((string) config('omni.agent_model_class', 'fast'));

        return $this->gateway->cost($model, (int) config('omni.pricing.tokens_in_per_reply'), (int) config('omni.pricing.tokens_out_per_reply'));
    }

    /**
     * @param iterable<BrandSubscriptionPlan> $plans
     * @param bool $assumeGranted judge every plan as if it included the assistant (to size the allowance before granting it)
     *
     * @return list<array<string,mixed>>
     */
    public function report(iterable $plans, bool $assumeGranted = false): array
    {
        $per = $this->costPerReply();
        $budget = (float) config('omni.pricing.ai_budget_pct') / 100;
        $fee = (float) config('omni.pricing.payment_fee_pct') / 100;
        $expected = (float) config('omni.pricing.expected_use_pct') / 100;
        $rows = [];
        foreach ($plans as $p) {
            $price = (float) $p->price_usd_per_month;
            $grants = $assumeGranted || $p->grantsFeature('brain_assist');
            $own = (int) ($p->capabilities['brain_business']['monthly_actions'] ?? 0);
            $allowance = $grants ? ($own > 0 ? $own : (int) BusinessSettings::brain('monthly_actions')) : 0;
            $full = $allowance * $per;
            $safe = $per > 0 ? (int) floor($price * $budget / $per) : 0;
            $rows[] = [
                'plan' => $p->name, 'price' => $price, 'grants_ai' => $grants, 'allowance' => $allowance, 'cost_per_reply' => $per,
                'ai_cost_full' => round($full, 4), 'ai_pct_full' => $price > 0 ? round($full / $price * 100, 1) : null,
                'margin_full' => round($price * (1 - $fee) - $full, 2), 'margin_expected' => round($price * (1 - $fee) - $full * $expected, 2),
                'safe_allowance' => $safe, 'status' => ! $grants ? 'none' : ($allowance <= $safe ? 'safe' : 'over budget'),
            ];
        }

        return $rows;
    }
}
