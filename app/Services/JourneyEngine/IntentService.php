<?php

namespace App\Services\JourneyEngine;

use App\Models\JourneyEngine\UserIntent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Intent capture (J0). The list is fixed by config; free text and entry sources can only SUGGEST intents from it, and a suggestion never becomes active until the user
 * confirms it. No AI is involved: the mapper is keyword-based and deterministic, so onboarding works with every key absent.
 */
class IntentService
{
    /** @return array<string, array<string, string>> */
    public function catalog(): array
    {
        return (array) config('journey_engine.intents', []);
    }

    /** Active, confirmed intents. @return list<string> */
    public function for(User $user): array
    {
        return UserIntent::where('user_id', $user->id)->whereNull('removed_at')->whereNotNull('confirmed_at')->pluck('intent')->all();
    }

    public function hasChosen(User $user): bool
    {
        return UserIntent::where('user_id', $user->id)->whereNotNull('confirmed_at')->exists();
    }

    /** Replace the user's chosen intents with this set (anything not in the catalog is ignored). @param list<string> $intents */
    public function choose(User $user, array $intents, string $source = 'chosen'): void
    {
        $valid = array_values(array_intersect(array_unique($intents), array_keys($this->catalog())));
        UserIntent::where('user_id', $user->id)->whereNotIn('intent', $valid)->whereNull('removed_at')->update(['removed_at' => now()]);
        foreach ($valid as $i) {
            UserIntent::updateOrCreate(['user_id' => $user->id, 'intent' => $i], ['source' => $source, 'confirmed_at' => now(), 'removed_at' => null]);
        }
    }

    /** Record a SUGGESTION (never active until confirmed). */
    public function suggest(User $user, string $intent, string $source, float $confidence): void
    {
        if (! isset($this->catalog()[$intent])) {
            return;
        }
        UserIntent::firstOrCreate(['user_id' => $user->id, 'intent' => $intent], ['source' => $source, 'confidence' => $confidence]);
    }

    /** @return list<string> intents a sentence points at (several allowed), in catalog order */
    public function fromText(string $text): array
    {
        $t = ' '.mb_strtolower($text).' ';
        $hits = [];
        foreach ((array) config('journey_engine.keywords', []) as $intent => $words) {
            foreach ($words as $w) {
                if (str_contains($t, mb_strtolower($w))) {
                    $hits[] = $intent;

                    break;
                }
            }
        }

        return $hits ?: [];
    }

    /** A landing page / UTM source word to an intent, or null. */
    public function fromEntrySource(?string $source): ?string
    {
        $source = strtolower(trim((string) $source));

        return (config('journey_engine.entry_sources')[$source] ?? null);
    }

    /** @return Collection<int, UserIntent> suggestions waiting for a yes/no */
    public function pending(User $user): Collection
    {
        return UserIntent::where('user_id', $user->id)->whereNull('confirmed_at')->whereNull('removed_at')->get();
    }
}
