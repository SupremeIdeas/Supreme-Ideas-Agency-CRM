<?php

namespace App\Services\JourneyEngine;

use App\Models\User;

/**
 * "Who would see this?" for the Studio: a COUNT over a sample of recently active members, never a list of people. It runs the rule's own signal exactly as the engine would
 * (intent match, ownership suppression, signal firing), so the number is what would really happen, minus the daily caps and holdout.
 */
class StudioAudience
{
    public function __construct(private readonly RuleCatalog $rules = new RuleCatalog, private readonly Signals $signals = new Signals, private readonly IntentService $intents = new IntentService) {}

    /** @return array{sampled: int, matching: int} */
    public function estimate(string $ruleKey, int $sample = 500): array
    {
        $rule = $this->rules->everything()[$ruleKey] ?? null;
        if ($rule === null) {
            return ['sampled' => 0, 'matching' => 0];
        }
        $matching = 0;
        $sampled = 0;
        User::query()->where('is_active', true)->latest('id')->limit(max(1, min(2000, $sample)))->get(['id'])->each(function (User $user) use ($rule, &$matching, &$sampled) {
            $sampled++;
            $userIntents = $this->intents->for($user);
            if (! (in_array('*', $rule['intents'], true) || array_intersect($rule['intents'], $userIntents))) {
                return;
            }
            foreach ((array) $rule['owns'] as $owned) {
                if ($this->signals->owns($user, $owned)) {
                    return;
                }
            }
            if ($this->signals->{$rule['signal']}($user) !== null) {
                $matching++;
            }
        });

        return ['sampled' => $sampled, 'matching' => $matching];
    }
}
