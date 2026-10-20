<?php

namespace Tests\Feature\JourneyEngine;

use App\Livewire\JourneyEngine\TodayStack;
use App\Models\JourneyEngine\JourneyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "What brings you to Naara?" is its own pop-up with check cards; closing it any way but Continue is "Skip for now". */
class JourneyIntentPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_member_gets_the_popup_open_with_a_check_card_per_intent(): void
    {
        $u = User::factory()->create();
        $c = Livewire::actingAs($u)->test(TodayStack::class)->assertSet('chooser', true);
        $c->assertSeeHtml('role="checkbox"')->assertSeeHtml('ns-intent__box')->assertSee(__('journey_engine.intent.title'));
        $this->assertGreaterThanOrEqual(6, substr_count($c->html(), 'role="checkbox"'));
    }

    public function test_ticking_cards_and_continuing_saves_and_closes(): void
    {
        $u = User::factory()->create();
        $c = Livewire::actingAs($u)->test(TodayStack::class);
        $first = array_key_first(config('journey_engine.intents'));
        $c->call('toggle', $first)->assertSet('selected', [$first])->assertSeeHtml('aria-checked="true"');
        $c->call('save')->assertSet('chooser', false);
        $this->assertSame([$first], app(\App\Services\JourneyEngine\IntentService::class)->for($u));
    }

    public function test_closing_the_popup_counts_as_skip_for_now_and_it_stays_away_for_a_week(): void
    {
        $u = User::factory()->create();
        Livewire::actingAs($u)->test(TodayStack::class)->set('chooser', false);
        $this->assertNotNull(JourneyProfile::where('user_id', $u->id)->first()?->stages['intent_skipped_at'] ?? null);
        Livewire::actingAs($u)->test(TodayStack::class)->assertSet('chooser', false);
    }
}
