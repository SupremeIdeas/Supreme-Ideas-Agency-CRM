<?php

namespace App\Livewire\JourneyEngine;

use App\Models\JourneyEngine\JourneyCard;
use App\Models\JourneyEngine\JourneyProfile;
use App\Models\Setting;
use App\Services\JourneyEngine\CardEngine;
use App\Services\JourneyEngine\IntentService;
use Livewire\Component;

/**
 * The dashboard "Today" stack (Journey Engine J1): at most ONE primary card plus two secondary ones, each with a "Why am I seeing this?" that shows its evidence, plus the
 * first-run "What brings you to Naara?" chooser. Everything is the user's own data; nothing here needs an AI key.
 */
class TodayStack extends Component
{
    /** @var list<string> */
    public array $selected = [];

    public string $text = '';

    public bool $chooser = false;

    public ?string $notice = null;

    public function mount(IntentService $intents): void
    {
        $user = auth()->user();
        $this->selected = $intents->for($user);
        $skipped = (JourneyProfile::where('user_id', $user->id)->first()?->stages['intent_skipped_at'] ?? null);
        $this->chooser = ! $intents->hasChosen($user) && ! ($skipped && now()->parse($skipped)->gt(now()->subDays(7)));
    }

    public function toggle(string $intent, IntentService $intents): void
    {
        if (! isset($intents->catalog()[$intent])) {
            return;
        }
        $this->selected = in_array($intent, $this->selected, true) ? array_values(array_diff($this->selected, [$intent])) : [...$this->selected, $intent];
    }

    /** Maps the free-text answer onto the fixed list; shows them as selected so the user confirms with one tap. */
    public function understand(IntentService $intents): void
    {
        $this->validate(['text' => 'nullable|string|max:300']);
        $found = $intents->fromText($this->text);
        $this->selected = array_values(array_unique([...$this->selected, ...$found]));
        $this->notice = $found ? __('journey_engine.intent.suggested') : null;
    }

    public function save(IntentService $intents): void
    {
        $intents->choose(auth()->user(), $this->selected ?: ['unsure']);
        $this->chooser = false;
        $this->notice = __('journey_engine.intent.saved');
    }

    /** The pop-up was closed by the X, Esc or the backdrop: that is "Skip for now" (a real Continue saves first, so it never reaches here). */
    public function updatedChooser(bool $open): void
    {
        if (! $open) {
            $this->skip();
        }
    }

    public function skip(): void
    {
        $p = JourneyProfile::firstOrNew(['user_id' => auth()->id()]);
        $p->stages = array_merge((array) $p->stages, ['intent_skipped_at' => now()->toIso8601String()]);
        $p->save();
        $this->chooser = false;
    }

    public function dismiss(int $id, CardEngine $engine): void
    {
        $engine->dismiss($this->card($id));
    }

    public function snooze(int $id, CardEngine $engine): void
    {
        $engine->snooze($this->card($id), 24);
    }

    public function open(int $id, CardEngine $engine)
    {
        $card = $this->card($id);
        $engine->click($card);

        return redirect($engine->render($card)['url']);
    }

    private function card(int $id): JourneyCard
    {
        return JourneyCard::where('user_id', auth()->id())->findOrFail($id);   // only ever the signed-in user's own card
    }

    public function render(CardEngine $engine, IntentService $intents)
    {
        if (! (bool) Setting::getValue('journey.enabled', true)) {
            return view('livewire.journey-engine.today-stack', ['off' => true, 'primary' => null, 'secondary' => [], 'attention' => 0, 'catalog' => [], 'chooser' => false, 'selected' => []]);
        }

        $result = $this->chooser ? ['primary' => null, 'secondary' => [], 'attention' => 0] : $engine->evaluate(auth()->user());
        $mapped = fn (JourneyCard $c) => ['id' => $c->id] + $engine->render($c) + ['evidence' => (array) $c->evidence];

        return view('livewire.journey-engine.today-stack', [
            'off' => false,
            'chooser' => $this->chooser,
            'catalog' => $intents->catalog(),
            'selected' => $this->selected,
            'primary' => $result['primary'] ? $mapped($result['primary']) : null,
            'secondary' => array_map($mapped, $result['secondary']),
            'attention' => $result['attention'],
        ]);
    }
}
