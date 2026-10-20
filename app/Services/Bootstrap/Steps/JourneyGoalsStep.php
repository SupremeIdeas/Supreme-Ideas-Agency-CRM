<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Bootstrap\BootstrapEntry;
use App\Models\JourneyGoal;
use App\Models\Setting;
use App\Services\Bootstrap\BootstrapStep;
use App\Services\Journey\JourneyGoalService;

/**
 * Bootstrap Phase 4: the starter "My Journey" ladder, built ONLY from metrics JourneyGoalService already computes. Goals ship INACTIVE (a reward is a liability, so the owner
 * reviews the budget and switches them on: owner decision 3). Rewards are tiny and every lifetime total is checked against `journey.reward_budget_credits`.
 * Values are hypotheses (DEFAULT, tune), not market data. Goals needing metrics that do not exist yet (username, contacts, N2N...) are added with their features.
 */
class JourneyGoalsStep implements BootstrapStep
{
    public const BUDGET_KEY = 'journey.reward_budget_credits';

    public const DEFAULT_BUDGET = 5000;

    public function name(): string
    {
        return 'journey-goals';
    }

    /** @return list<array{title: string, description: string, metric: string, target: float, reward: float, icon: string, audience: string}> */
    public function ladder(): array
    {
        $g = fn (string $title, string $description, string $metric, float $target, float $reward, string $icon, string $audience = JourneyGoal::AUDIENCE_ALL) => compact('title', 'description', 'metric', 'target', 'reward', 'icon', 'audience');

        return [
            $g('First eSIM', 'Buy and install your first eSIM plan.', 'esim_purchases', 1, 50, 'sim'),
            $g('Regular traveller', 'Buy 3 eSIM plans.', 'esim_purchases', 3, 100, 'sim'),
            $g('Frequent flyer', 'Buy 10 eSIM plans.', 'esim_purchases', 10, 250, 'sim'),
            $g('First number', 'Get your first Naara number.', 'number_purchases', 1, 50, 'hash'),
            $g('Number collector', 'Get 3 numbers.', 'number_purchases', 3, 100, 'hash'),
            $g('Number pro', 'Get 10 numbers.', 'number_purchases', 10, 250, 'hash'),
            $g('Two countries', 'Connect in 2 different countries.', 'countries_reached', 2, 50, 'globe'),
            $g('Five countries', 'Connect in 5 different countries.', 'countries_reached', 5, 100, 'globe'),
            $g('Ten countries', 'Connect in 10 different countries.', 'countries_reached', 10, 250, 'globe'),
            $g('Three-day streak', 'Check in 3 days in a row.', 'checkin_streak_days', 3, 20, 'zap'),
            $g('One-week streak', 'Check in 7 days in a row.', 'checkin_streak_days', 7, 50, 'zap'),
            $g('One-month streak', 'Check in 30 days in a row.', 'checkin_streak_days', 30, 200, 'zap'),
            $g('First referral', 'Refer a friend who joins.', 'referrals_made', 1, 100, 'users'),
            $g('Five referrals', 'Refer 5 friends who join.', 'referrals_made', 5, 250, 'users'),
            $g('Twenty referrals', 'Refer 20 friends who join.', 'referrals_made', 20, 500, 'users'),
            $g('Spend $50', 'Reach $50 in total spend.', 'total_spend_usd', 50, 100, 'wallet'),
            $g('Spend $200', 'Reach $200 in total spend.', 'total_spend_usd', 200, 250, 'wallet'),
            $g('Spend $1,000', 'Reach $1,000 in total spend.', 'total_spend_usd', 1000, 500, 'wallet'),
            $g('First client', 'Connect your first client.', 'merchant_clients_connected', 1, 100, 'users', JourneyGoal::AUDIENCE_MERCHANT),
            $g('First paid invoice', 'Get your first invoice paid.', 'merchant_invoices_paid', 1, 100, 'receipt', JourneyGoal::AUDIENCE_MERCHANT),
            $g('Ten paid invoices', 'Get 10 invoices paid.', 'merchant_invoices_paid', 10, 250, 'receipt', JourneyGoal::AUDIENCE_MERCHANT),
        ];
    }

    /** Total credits a user could ever earn from the starter ladder, across all audiences. */
    public function lifetimeLiability(): float
    {
        return array_sum(array_column($this->ladder(), 'reward'));
    }

    public function plan(string $profile): array
    {
        $rows = [];
        foreach ($this->ladder() as $g) {
            if (! JourneyGoal::where('title', $g['title'])->exists()) {
                $rows[] = ['kind' => 'journey-goal', 'item' => $g['title'], 'note' => "{$g['metric']} >= {$g['target']} -> {$g['reward']} credits (INACTIVE draft)"];
            }
        }

        return $rows;
    }

    public function apply(string $profile): array
    {
        if (! Setting::query()->where('key', self::BUDGET_KEY)->exists()) {
            Setting::setValue(self::BUDGET_KEY, self::DEFAULT_BUDGET, 'journey', 'Most credits a user can ever earn from the starter ladder (DEFAULT, tune)', isPublic: false);
        }
        $created = $preserved = 0;
        foreach ($this->ladder() as $g) {
            if (JourneyGoal::where('title', $g['title'])->exists()) {
                $preserved++;

                continue;
            }
            JourneyGoal::create([
                'title' => $g['title'], 'description' => $g['description'], 'metric' => $g['metric'], 'target' => $g['target'], 'period_type' => JourneyGoal::PERIOD_LIFETIME,
                'reward_credits' => $g['reward'], 'audience' => $g['audience'], 'icon' => $g['icon'], 'is_active' => false,
            ]);
            BootstrapEntry::updateOrCreate(['step' => $this->name(), 'kind' => 'journey-goal', 'item' => $g['title']], ['checksum' => BootstrapEntry::checksum([$g['metric'], $g['target'], $g['reward']])]);
            $created++;
        }

        return ['created' => $created, 'preserved' => $preserved, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];
        foreach ($this->ladder() as $g) {
            if (! array_key_exists($g['metric'], JourneyGoalService::METRICS)) {
                $fail[] = "goal '{$g['title']}' uses metric {$g['metric']} which JourneyGoalService does not compute";
            }
            if (! JourneyGoal::where('title', $g['title'])->exists()) {
                $fail[] = "goal '{$g['title']}' is missing (run naara:bootstrap)";
            }
        }
        $budget = (float) Setting::getValue(self::BUDGET_KEY, self::DEFAULT_BUDGET);
        if ($this->lifetimeLiability() > $budget) {
            $fail[] = 'the starter ladder can pay '.$this->lifetimeLiability()." credits per user, above the {$budget} budget (".self::BUDGET_KEY.')';
        }

        return $fail;
    }
}
