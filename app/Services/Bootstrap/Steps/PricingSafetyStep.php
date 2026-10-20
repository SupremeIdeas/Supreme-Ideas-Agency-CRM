<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Coupon;
use App\Models\Setting;
use App\Services\Bootstrap\BootstrapStep;
use App\Services\Pricing\DiscountMarginGuard;

/**
 * Bootstrap Phase 3 (verification only): proves the money floors actually protect the shipped defaults. It creates nothing: the pricing defaults already come from
 * PricingSettingsSeeder and the registry, and PricingEngine/MarginGuard clamp every sale. This step FAILS LOUDLY if any default could let a sale reach cost + floor.
 */
class PricingSafetyStep implements BootstrapStep
{
    public function name(): string
    {
        return 'pricing-safety';
    }

    public function plan(string $profile): array
    {
        return [];
    }

    public function apply(string $profile): array
    {
        return ['created' => 0, 'preserved' => 0, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];

        foreach (['pricing.minimum_profit_usd' => 0.50, 'pricing.sms_min_profit' => 0.01, 'pricing.giftcard_min_profit' => 0.25] as $key => $fallback) {
            if ((float) Setting::getValue($key, $fallback) <= 0) {
                $fail[] = "{$key} must be above 0 (a zero profit floor lets a sale reach cost)";
            }
        }

        $cap = (float) Setting::getValue('pricing.discount_margin_cap_pct', 30);
        if ($cap < 0 || $cap > 100) {
            $fail[] = "pricing.discount_margin_cap_pct is {$cap}; it must be between 0 and 100";
        }

        foreach (Setting::query()->where('key', 'like', 'pricing.%markup%')->get() as $s) {
            $v = $s->value['value'] ?? null;
            if (! is_numeric($v) || (float) $v < 0) {
                $fail[] = "{$s->key} is not a non-negative number";
            }
        }

        // The discount floor must always sit above cost + the minimum profit, for any cost and any admin margin.
        $guard = new DiscountMarginGuard;
        $min = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        foreach ([0.5, 2.0, 10.0, 80.0] as $cost) {
            foreach ([0.0, 0.5, 4.0, 30.0] as $margin) {
                if ($guard->floor($cost, $margin) < $cost + $min - 0.0001) {
                    $fail[] = "discount floor for cost {$cost} / margin {$margin} falls below cost + minimum profit";
                }
            }
        }

        foreach (Coupon::query()->where('is_active', true)->get() as $c) {
            if ((float) $c->percent_off <= 0 || (float) $c->percent_off > 100) {
                $fail[] = "coupon {$c->code} has an invalid percent_off";
            }
            if ((int) $c->per_user_limit < 1) {
                $fail[] = "coupon {$c->code} has no per-user limit";
            }
        }

        return $fail;
    }
}
