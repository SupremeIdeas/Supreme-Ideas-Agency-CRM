<?php

namespace Tests\Feature\JourneyEngine;

use App\Livewire\JourneyEngine\Studio;
use App\Models\EsimOrder;
use App\Models\JourneyEngine\JourneyCard;
use App\Models\JourneyEngine\JourneyRuleOverride;
use App\Models\Setting;
use App\Models\User;
use App\Services\JourneyEngine\CardEngine;
use App\Services\JourneyEngine\RuleCatalog;
use App\Services\JourneyEngine\StudioAudience;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Journey Engine J3: the Studio edits shipped rules within safe limits and can never create a risky rule. */
class JourneyStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $a = User::factory()->create(['is_active' => true]);
        $a->assignRole('super_admin');

        return $a;
    }

    private function lowDataUser(): User
    {
        Setting::setValue('journey.holdout_pct', 0);
        $u = User::factory()->create(['is_active' => true]);
        $o = EsimOrder::create(['user_id' => $u->id, 'provider' => 'esimgo', 'status' => 'active', 'activated_at' => now()->subDay(), 'expires_at' => now()->addDays(10), 'price_charged' => 5, 'wholesale_cost' => 3, 'currency' => 'USD']);
        DB::table('esim_usage_snapshots')->insert(['esim_order_id' => $o->id, 'user_id' => $u->id, 'data_total_mb' => 1000, 'data_remaining_mb' => 100, 'data_used_mb' => 900, 'captured_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    public function test_only_a_super_admin_can_open_the_studio(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('admin');
        Livewire::actingAs($staff)->test(Studio::class)->assertForbidden();
        Livewire::actingAs($this->admin())->test(Studio::class)->assertOk()->assertSee('Data running low')->assertSee('Protective alert');
    }

    public function test_a_switched_off_rule_never_fires_and_switching_it_back_on_restores_it(): void
    {
        $u = $this->lowDataUser();
        $this->assertSame('low_data', (new CardEngine)->evaluate($u, false)['primary']->rule_key);

        $c = Livewire::actingAs($this->admin())->test(Studio::class)->call('toggle', 'low_data');
        $this->assertNull((new CardEngine)->evaluate($u, false)['primary']?->rule_key === 'low_data' ? 'x' : null);
        $this->assertArrayNotHasKey('low_data', (new RuleCatalog)->all());
        $this->assertArrayHasKey('low_data', (new RuleCatalog)->everything());

        $c->call('toggle', 'low_data');
        $this->assertArrayHasKey('low_data', (new RuleCatalog)->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'journey.rule_disabled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'journey.rule_enabled']);
    }

    public function test_priority_can_only_move_within_its_class(): void
    {
        $rules = new RuleCatalog;
        $this->assertSame([], $rules->validateEdit('low_data', ['priority' => 'critical']));
        $this->assertNotEmpty($rules->validateEdit('low_data', ['priority' => 'opportunity']), 'a safety alert cannot be demoted into the noise');
        $this->assertNotEmpty($rules->validateEdit('missed_calls', ['priority' => 'critical']), 'a suggestion can never be promoted above the suggestion caps');
        $this->assertSame([], $rules->validateEdit('missed_calls', ['priority' => 'fyi']));
    }

    public function test_copy_is_plain_text_limited_and_may_only_use_the_rules_own_placeholders(): void
    {
        $rules = new RuleCatalog;
        $this->assertSame([], $rules->validateEdit('low_data', ['copy' => ['body' => 'You have used :percent_used% and :mb_left MB is left.']]));
        $this->assertStringContainsString(':secret', implode(' ', $rules->validateEdit('low_data', ['copy' => ['body' => 'Hi :secret']])));
        $this->assertNotEmpty($rules->validateEdit('low_data', ['copy' => ['title' => '<b>Hi</b>']]));
        $this->assertNotEmpty($rules->validateEdit('low_data', ['copy' => ['title' => str_repeat('x', 81)]]));
        $this->assertNotEmpty($rules->validateEdit('low_data', ['intents' => ['made_up']]));
    }

    public function test_saved_copy_and_priority_reach_the_card_the_member_sees(): void
    {
        $u = $this->lowDataUser();
        Livewire::actingAs($this->admin())->test(Studio::class)->call('edit', 'low_data')
            ->set('priority', 'critical')->set('copy.title', 'Heads up: :percent_used% used')->set('copy.body', '')->call('save')
            ->assertSet('problems', [])->assertSee('Saved');

        $engine = new CardEngine;
        $card = $engine->evaluate($u)['primary'];
        $r = $engine->render($card);

        $this->assertSame('critical', $card->priority);
        $this->assertSame('Heads up: 90% used', $r['title']);
        $this->assertNotSame('', $r['body'], 'an untouched field keeps the shipped wording');
        $this->assertSame(JourneyRuleOverride::where('rule_key', 'low_data')->first()->copy['title'], 'Heads up: :percent_used% used');
    }

    public function test_an_invalid_edit_is_refused_with_reasons_and_changes_nothing(): void
    {
        Livewire::actingAs($this->admin())->test(Studio::class)->call('edit', 'missed_calls')
            ->set('priority', 'critical')->set('copy.body', '<script>x</script>')->call('save')
            ->assertSee('Priority must be one of')->assertSee('plain text');

        $this->assertSame(0, JourneyRuleOverride::count());
    }

    public function test_reset_returns_to_the_shipped_rule(): void
    {
        $c = Livewire::actingAs($this->admin())->test(Studio::class);
        $c->call('edit', 'low_data')->set('copy.title', 'Custom')->call('save');
        $this->assertSame(1, JourneyRuleOverride::count());

        $c->call('resetRule', 'low_data');
        $this->assertSame(0, JourneyRuleOverride::count());
        $this->assertSame('', (new RuleCatalog)->everything()['low_data']['copy_override']['title'] ?? '');
    }

    public function test_the_audience_estimate_is_a_count_not_a_list(): void
    {
        $this->lowDataUser();
        User::factory()->count(2)->create(['is_active' => true]);

        $r = (new StudioAudience)->estimate('low_data');

        $this->assertSame(['sampled', 'matching'], array_keys($r));
        $this->assertSame(1, $r['matching']);
        $this->assertSame(3, $r['sampled']);
        Livewire::actingAs($this->admin())->test(Studio::class)->call('estimate', 'low_data')->assertSee('would see this right now');
    }

    public function test_every_rule_stays_valid_with_the_studio_in_place(): void
    {
        $this->assertSame([], (new RuleCatalog)->validate());
        $this->assertSame(0, JourneyCard::count());
    }

    public function test_brand_flows_fire_on_the_members_own_brand_state_and_stated_intent_only(): void
    {
        Setting::setValue('journey.holdout_pct', 0);
        $engine = new CardEngine;

        // Intent "brand", no listing: a suggestion that says why.
        $u = User::factory()->create(['is_active' => true]);
        (new \App\Services\JourneyEngine\IntentService)->choose($u, ['brand']);
        $card = $engine->evaluate($u, false)['primary'];
        $this->assertSame('brand_get_listed', $card->rule_key);
        $this->assertStringContainsString('chose to grow your brand', $engine->render($card)['why']);

        // No stated intent: nothing is suggested.
        $other = User::factory()->create(['is_active' => true]);
        $this->assertNull($engine->evaluate($other, false)['primary']);

        // Owning a listing suppresses "get listed"; a paused listing is a protective alert for anyone who owns one.
        $brand = \App\Models\BrandPartner::create(['owner_user_id' => $u->id, 'brand_name' => 'Lagos Beats', 'listing_status' => \App\Models\BrandPartner::STATUS_PAUSED, 'is_active' => true]);
        $card = $engine->evaluate($u, false)['primary'];
        $this->assertSame('brand_listing_paused', $card->rule_key);
        $this->assertSame('important', $card->priority);
        $this->assertStringContainsString('Lagos Beats', $engine->render($card)['body']);

        // Pending setup for over a day nudges, but only members who chose the brand intent.
        $brand->update(['listing_status' => \App\Models\BrandPartner::STATUS_PENDING]);
        $brand->forceFill(['created_at' => now()->subHours(30)])->save();
        $this->assertSame('brand_setup_incomplete', $engine->evaluate($u, false)['primary']->rule_key);
        $this->assertSame([], (new RuleCatalog)->validate());
    }
}
