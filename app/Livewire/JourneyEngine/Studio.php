<?php

namespace App\Livewire\JourneyEngine;

use App\Models\JourneyEngine\JourneyRuleOverride;
use App\Services\JourneyEngine\RuleCatalog;
use App\Services\JourneyEngine\StudioAudience;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Journey Studio (J3): admin control over the shipped Journey rules. An admin can switch a rule off, move its priority within its class, change who it applies to and rewrite its
 * words, and see how many members it would reach. The signal, route and evidence stay in code, so Studio cannot create a rule that reads private content, promotes a
 * suggestion above the caps, or shows a card without a reason. Every change is audited.
 */
#[Layout('components.layouts.admin')]
class Studio extends Component
{
    public ?string $editing = null;

    public ?string $priority = null;

    /** @var list<string> */
    public array $intents = [];

    public bool $everyone = false;

    /** @var array{title: string, body: string, cta: string, why: string} */
    public array $copy = ['title' => '', 'body' => '', 'cta' => '', 'why' => ''];

    /** @var list<string> */
    public array $problems = [];

    public ?string $saved = null;

    /** @var array<string, array{sampled: int, matching: int}> */
    public array $audience = [];

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
    }

    public function edit(string $key, RuleCatalog $rules): void
    {
        $this->authorizeAdmin();
        $rule = $rules->everything()[$key] ?? null;
        if ($rule === null) {
            return;
        }
        $this->editing = $key;
        $this->problems = [];
        $this->saved = null;
        $this->priority = $rule['priority'];
        $this->everyone = in_array('*', $rule['intents'], true);
        $this->intents = array_values(array_diff($rule['intents'], ['*']));
        $over = $rule['copy_override'];
        $this->copy = ['title' => $over['title'] ?? '', 'body' => $over['body'] ?? '', 'cta' => $over['cta'] ?? '', 'why' => $over['why'] ?? ''];
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->problems = [];
    }

    public function save(RuleCatalog $rules): void
    {
        $this->authorizeAdmin();
        if ($this->editing === null) {
            return;
        }
        $intents = $this->everyone ? ['*'] : array_values($this->intents);
        $edit = ['priority' => $this->priority, 'intents' => $intents, 'copy' => $this->copy];
        $this->problems = $rules->validateEdit($this->editing, $edit);
        if ($intents === []) {
            $this->problems[] = 'Choose at least one audience, or "Everyone".';
        }
        if ($this->problems) {
            return;
        }
        $shipped = $rules->everything()[$this->editing]['shipped'];
        $override = JourneyRuleOverride::firstOrNew(['rule_key' => $this->editing]);
        $override->fill([
            'enabled' => $override->exists ? $override->enabled : true,
            'priority' => $this->priority === $shipped['priority'] ? null : $this->priority,
            'intents' => $intents === $shipped['intents'] ? null : $intents,
            'copy' => array_filter(array_map(fn ($v) => trim((string) $v), $this->copy)) ?: null,
            'updated_by' => Auth::id(),
        ])->save();
        Auditor::log('journey.rule_edited', JourneyRuleOverride::class, $override->id, ['rule' => $this->editing]);
        $this->saved = 'Saved. It applies from the next time a member opens their dashboard.';
        $this->editing = null;
    }

    public function toggle(string $key, RuleCatalog $rules): void
    {
        $this->authorizeAdmin();
        if (! isset($rules->everything()[$key])) {
            return;
        }
        $override = JourneyRuleOverride::firstOrNew(['rule_key' => $key]);
        $override->enabled = $override->exists ? ! $override->enabled : false;
        $override->updated_by = Auth::id();
        $override->save();
        Auditor::log($override->enabled ? 'journey.rule_enabled' : 'journey.rule_disabled', JourneyRuleOverride::class, $override->id, ['rule' => $key]);
    }

    public function resetRule(string $key): void
    {
        $this->authorizeAdmin();
        JourneyRuleOverride::where('rule_key', $key)->get()->each->delete();
        Auditor::log('journey.rule_reset', JourneyRuleOverride::class, null, ['rule' => $key]);
        $this->editing = null;
        $this->saved = 'Back to the shipped rule.';
    }

    public function estimate(string $key, StudioAudience $studio): void
    {
        $this->authorizeAdmin();
        $limiter = 'journey-studio-estimate:'.Auth::id();
        if (RateLimiter::tooManyAttempts($limiter, 6)) {
            $this->saved = 'That estimate scans real accounts, so it is limited. Try again in a minute.';

            return;
        }
        RateLimiter::hit($limiter, 60);
        $this->audience[$key] = $studio->estimate($key);
    }

    public function render(RuleCatalog $rules)
    {
        return view('livewire.journey-engine.studio', [
            'rules' => $rules->everything(),
            'catalog' => (array) config('journey_engine.intents'),
            'problemsInCatalog' => $rules->validate(),
        ]);
    }
}
