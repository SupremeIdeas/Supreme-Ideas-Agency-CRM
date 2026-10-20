<?php

namespace App\Support\Brain;

use App\Models\Brain\BrainPrompt;
use Illuminate\Support\Collection;
use Throwable;

/**
 * What NaaraBrain shows to give people ideas (MASTER-ONLY): the centre line that changes on each visit, a few quick questions, the full idea browser and the
 * capability list. Everything comes from the admin-editable library (brain_prompts); if the table is empty or missing, the lang defaults keep the page whole.
 */
class BrainPrompts
{
    /** One active centre line, different from the last one this visitor saw. */
    public static function tagline(?string $avoid = null): string
    {
        $lines = self::rows(BrainPrompt::TAGLINE)->pluck('text')->all();
        $lines = $lines === [] ? [__('brain.tagline')] : $lines;
        $pool = array_values(array_filter($lines, fn ($l) => $l !== $avoid)) ?: $lines;

        return $pool[array_rand($pool)];
    }

    /**
     * A few quick questions that WORK TODAY, each from a different area so one visit shows range.
     *
     * @return list<string>
     */
    public static function chips(int $n = 4): array
    {
        $byPack = self::rows(BrainPrompt::SUGGESTION)->where('live', true)->groupBy('pack')->map(fn (Collection $c) => $c->shuffle());
        $packs = $byPack->keys()->shuffle()->take($n);
        $out = $packs->map(fn ($p) => $byPack[$p]->first()->text)->values()->all();

        return $out !== [] ? $out : [__('brain.suggest_1'), __('brain.suggest_2'), __('brain.suggest_3')];
    }

    /**
     * The idea browser: every area with its questions, working-today ones first, the rest marked "Soon".
     *
     * @return array<string, array{name: string, items: list<array{text: string, live: bool}>}>
     */
    public static function ideas(): array
    {
        $rows = self::rows(BrainPrompt::SUGGESTION)->groupBy('pack');
        $out = [];
        foreach (BrainPrompt::PACKS as $key => $name) {
            if (! $rows->has($key)) {
                continue;
            }
            $items = $rows[$key]->sortByDesc('live')->values()->map(fn ($r) => ['text' => (string) $r->text, 'live' => (bool) $r->live])->all();
            $out[$key] = ['name' => $name, 'items' => $items];
        }

        return $out;
    }

    /** @return list<array{pack: string, title: string, detail: string, live: bool}> */
    public static function capabilities(): array
    {
        return self::rows(BrainPrompt::CAPABILITY)->sortByDesc('live')->values()->map(fn ($r) => ['pack' => (string) $r->pack, 'title' => (string) $r->text, 'detail' => (string) $r->detail, 'live' => (bool) $r->live])->all();
    }

    /** @return Collection<int, BrainPrompt> */
    private static function rows(string $kind): Collection
    {
        try {
            return BrainPrompt::query()->where('kind', $kind)->where('active', true)->orderBy('sort')->get();
        } catch (Throwable) {
            return collect();
        }
    }
}
