<?php

namespace App\Services\JourneyEngine;

use App\Models\JourneyEngine\JourneyRuleOverride;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Loads and VALIDATES the rules (config/journey_engine.php). A rule that could mislead is refused rather than shown: an opportunity must name its evidence, the signal must
 * exist, the route must exist, the priority must be a known class. Validation runs in tests and in `validate()`. The Studio (J3) layers admin edits (switch off, priority within its class, intents, copy) over the shipped rules without touching the signal, route or evidence.
 */
class RuleCatalog
{
    /** @var array<string, JourneyRuleOverride>|null */
    private ?array $overrides = null;

    /** The rules that are switched on, with the admin's Studio edits applied. @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return array_filter($this->everything(), fn ($r) => $r['enabled']);
    }

    /** Every shipped rule INCLUDING switched-off ones (what the Studio lists). @return array<string, array<string, mixed>> */
    public function everything(): array
    {
        $out = [];
        foreach ((array) config('journey_engine.rules', []) as $key => $spec) {
            $rule = $spec + ['key' => $key, 'intents' => ['*'], 'owns' => []];
            $rule['shipped'] = ['priority' => $rule['priority'], 'intents' => $rule['intents']];
            $rule['enabled'] = true;
            $rule['copy_override'] = [];
            if ($o = ($this->overrides()[$key] ?? null)) {
                $rule['enabled'] = (bool) $o->enabled;
                if ($o->priority && in_array($o->priority, self::allowedPriorities($rule['shipped']['priority']), true)) {
                    $rule['priority'] = $o->priority;
                    $rule['priority_overridden'] = true;       // an explicit admin choice beats the level a signal would pick on its own (e.g. 95% data used)
                }
                if (! empty($o->intents)) {
                    $rule['intents'] = array_values((array) $o->intents);
                }
                $rule['copy_override'] = array_filter((array) $o->copy, fn ($v) => is_string($v) && trim($v) !== '');
            }
            $out[$key] = $rule;
        }

        return $out;
    }

    /** @return array<string, JourneyRuleOverride> */
    private function overrides(): array
    {
        if ($this->overrides === null) {
            try {
                $this->overrides = Schema::hasTable('journey_rule_overrides') ? JourneyRuleOverride::all()->keyBy('rule_key')->all() : [];
            } catch (\Throwable) {
                $this->overrides = [];
            }
        }

        return $this->overrides;
    }

    /**
     * The priorities an admin may move a rule to. A protective alert stays protective (critical or important) and a suggestion stays a suggestion (it may be demoted to a
     * plain FYI): an admin can never promote a suggestion above the suggestion caps or demote a safety alert into the noise.
     *
     * @return list<string>
     */
    public static function allowedPriorities(string $shipped): array
    {
        return in_array($shipped, ['critical', 'important'], true) ? ['critical', 'important'] : ['opportunity', 'fyi'];
    }

    /**
     * Problems with a proposed Studio edit (empty = acceptable). The rule's signal, route and evidence stay in code: only enabled, priority, intents and copy can change.
     *
     * @param  array{priority?: ?string, intents?: array, copy?: array} $edit
     * @return list<string>
     */
    public function validateEdit(string $key, array $edit): array
    {
        $rule = $this->everything()[$key] ?? null;
        if ($rule === null) {
            return ["{$key}: unknown rule"];
        }
        $problems = [];
        $priority = $edit['priority'] ?? null;
        if ($priority !== null && $priority !== '' && ! in_array($priority, self::allowedPriorities($rule['shipped']['priority']), true)) {
            $problems[] = 'Priority must be one of: '.implode(', ', self::allowedPriorities($rule['shipped']['priority'])).' for this kind of rule.';
        }
        $known = array_keys((array) config('journey_engine.intents', []));
        foreach ((array) ($edit['intents'] ?? []) as $i) {
            if ($i !== '*' && ! in_array($i, $known, true)) {
                $problems[] = "Unknown intent '{$i}'.";
            }
        }
        $limits = ['title' => 80, 'body' => 240, 'cta' => 32, 'why' => 240];
        $allowed = array_map(fn ($f) => ':'.$f, (array) ($rule['evidence_fields'] ?? []));
        foreach ((array) ($edit['copy'] ?? []) as $field => $text) {
            if (! isset($limits[$field]) || $text === null || $text === '') {
                continue;
            }
            if (! is_string($text) || mb_strlen($text) > $limits[$field]) {
                $problems[] = ucfirst($field).' is too long (max '.$limits[$field].' characters).';
            }
            if ($text !== strip_tags((string) $text)) {
                $problems[] = ucfirst($field).' must be plain text (no HTML).';
            }
            if (preg_match_all('/:([a-z_]+)/', (string) $text, $m)) {
                foreach ($m[0] as $ph) {
                    if (! in_array($ph, $allowed, true)) {
                        $problems[] = ucfirst($field)." uses {$ph}, which this rule does not provide. Available: ".implode(' ', $allowed ?: ['none']).'.';
                    }
                }
            }
        }

        return $problems;
    }

    /** @return list<string> problems, empty when every rule is valid */
    public function validate(): array
    {
        $problems = [];
        $priorities = array_keys((array) config('journey_engine.priorities', []));
        $intents = array_keys((array) config('journey_engine.intents', []));
        foreach ($this->everything() as $key => $r) {
            if (! in_array($r['priority'] ?? '', $priorities, true)) {
                $problems[] = "{$key}: unknown priority";
            }
            if (($r['priority'] ?? '') === 'opportunity' && empty($r['evidence_fields'])) {
                $problems[] = "{$key}: an opportunity rule must declare evidence_fields (no card without a reason)";
            }
            if (! method_exists(Signals::class, (string) ($r['signal'] ?? ''))) {
                $problems[] = "{$key}: signal '".($r['signal'] ?? '')."' does not exist on Signals";
            }
            if (! Route::has((string) ($r['route'] ?? ''))) {
                $problems[] = "{$key}: route '".($r['route'] ?? '')."' does not exist";
            }
            foreach ((array) ($r['intents'] ?? []) as $i) {
                if ($i !== '*' && ! in_array($i, $intents, true)) {
                    $problems[] = "{$key}: unknown intent '{$i}'";
                }
            }
            if (! is_string(__("journey_engine.cards.{$r['copy']}.title")) || str_starts_with((string) __("journey_engine.cards.{$r['copy']}.title"), 'journey_engine.')) {
                $problems[] = "{$key}: missing copy 'journey_engine.cards.{$r['copy']}.title'";
            }
        }

        return $problems;
    }
}
