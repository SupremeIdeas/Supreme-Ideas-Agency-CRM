<?php

namespace Tests\Feature\Omni;

use App\Models\BrandSubscriptionPlan;
use App\Services\Omni\RespondPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The plan margin check: arithmetic is right, a plan without the AI costs nothing, and an over-generous allowance is flagged. */
class RespondPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_per_reply_follows_the_model_price_table(): void
    {
        config(['omni.agent_model_class' => 'fast', 'ai.models.fast' => 'claude-haiku-4-5-20251001', 'omni.pricing.tokens_in_per_reply' => 1000000, 'omni.pricing.tokens_out_per_reply' => 1000000]);
        $this->assertEqualsWithDelta(6.0, app(RespondPricing::class)->costPerReply(), 0.0001, '$1 in + $5 out per million tokens');
    }

    public function test_plans_are_judged_against_the_ai_budget(): void
    {
        config(['omni.agent_model_class' => 'fast', 'ai.models.fast' => 'claude-haiku-4-5-20251001', 'omni.pricing.tokens_in_per_reply' => 1800, 'omni.pricing.tokens_out_per_reply' => 150,
            'omni.pricing.ai_budget_pct' => 8, 'omni.pricing.payment_fee_pct' => 3, 'omni.pricing.expected_use_pct' => 40]);
        $per = app(RespondPricing::class)->costPerReply();
        $this->assertEqualsWithDelta(0.00255, $per, 0.00001, '1800 x $1/M + 150 x $5/M');
        $none = BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => 19, 'handles_included' => 1, 'is_active' => true, 'capabilities' => ['features' => ['workspace', 'inbox']]]);
        $fair = BrandSubscriptionPlan::create(['name' => 'Growth', 'price_usd_per_month' => 49, 'handles_included' => 3, 'is_active' => true, 'capabilities' => ['features' => ['workspace', 'inbox', 'brain_assist'], 'brain_business' => ['monthly_actions' => 500]]]);
        $greedy = BrandSubscriptionPlan::create(['name' => 'Greedy', 'price_usd_per_month' => 19, 'handles_included' => 1, 'is_active' => true, 'capabilities' => ['features' => ['workspace', 'inbox', 'brain_assist'], 'brain_business' => ['monthly_actions' => 5000]]]);
        $rows = collect(app(RespondPricing::class)->report([$none, $fair, $greedy]))->keyBy('plan');
        $this->assertSame('none', $rows['Starter']['status']);
        $this->assertSame(0, $rows['Starter']['allowance']);
        $this->assertSame('safe', $rows['Growth']['status']);
        $this->assertEqualsWithDelta(500 * $per, $rows['Growth']['ai_cost_full'], 0.001);
        $this->assertGreaterThan(0, $rows['Growth']['margin_full']);
        $this->assertSame('over budget', $rows['Greedy']['status']);
        $this->assertSame((int) floor(19 * 0.08 / $per), $rows['Greedy']['safe_allowance']);
    }

    public function test_the_command_fails_when_a_plan_is_over_budget(): void
    {
        BrandSubscriptionPlan::create(['name' => 'Greedy', 'price_usd_per_month' => 19, 'handles_included' => 1, 'is_active' => true, 'sort_order' => 1, 'capabilities' => ['features' => ['workspace', 'inbox', 'brain_assist'], 'brain_business' => ['monthly_actions' => 50000]]]);
        $this->artisan('naara:respond:pricing')->assertFailed();
    }
}
