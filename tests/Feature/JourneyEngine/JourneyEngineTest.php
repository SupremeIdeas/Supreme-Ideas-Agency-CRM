<?php

namespace Tests\Feature\JourneyEngine;

use App\Livewire\JourneyEngine\TodayStack;
use App\Models\CallEvent;
use App\Models\EsimOrder;
use App\Models\JourneyEngine\JourneyCard;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\JourneyEngine\CardEngine;
use App\Services\JourneyEngine\IntentService;
use App\Services\JourneyEngine\RuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Journey Engine J0/J1: intents are a fixed list, cards need evidence, protective alerts outrank suggestions, and nothing reads private content. */
class JourneyEngineTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $intents = []): User
    {
        $u = User::factory()->create(['is_active' => true]);
        Setting::setValue('journey.holdout_pct', 0);                   // keep tests deterministic unless a test asks for the holdout
        if ($intents) {
            (new IntentService)->choose($u, $intents);
        }

        return $u;
    }

    private function esim(User $u, array $over = []): EsimOrder
    {
        return EsimOrder::create($over + ['user_id' => $u->id, 'provider' => 'esimgo', 'status' => 'active', 'activated_at' => now()->subDay(), 'expires_at' => now()->addDays(10), 'price_charged' => 5, 'wholesale_cost' => 3, 'currency' => 'USD']);
    }

    private function usage(EsimOrder $o, int $used, int $total = 1000): void
    {
        DB::table('esim_usage_snapshots')->insert(['esim_order_id' => $o->id, 'user_id' => $o->user_id, 'data_total_mb' => $total, 'data_remaining_mb' => $total - $used, 'data_used_mb' => $used, 'captured_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_every_shipped_rule_is_valid_and_an_opportunity_cannot_lack_evidence(): void
    {
        $this->assertSame([], (new RuleCatalog)->validate());

        config(['journey_engine.rules.bad' => ['name' => 'x', 'signal' => 'missedCalls', 'priority' => 'opportunity', 'intents' => ['line'], 'route' => 'dashboard', 'copy' => 'missed_calls']]);
        $this->assertStringContainsString('bad: an opportunity rule must declare evidence_fields', implode("\n", (new RuleCatalog)->validate()));
    }

    public function test_intents_come_from_a_fixed_list_and_suggestions_need_confirmation(): void
    {
        $svc = new IntentService;
        $u = $this->user();
        $svc->choose($u, ['travel', 'made_up', 'verify']);
        $this->assertEqualsCanonicalizing(['travel', 'verify'], $svc->for($u));

        $this->assertSame(['travel', 'verify'], $svc->fromText('I am moving to Canada next month and need a code for my bank'));
        $this->assertSame('travel', $svc->fromEntrySource('esim'));
        $this->assertNull($svc->fromEntrySource('random'));

        $svc->suggest($u, 'earn', 'inferred', 0.6);
        $this->assertNotContains('earn', $svc->for($u), 'an inferred intent is not active until the user confirms it');
    }

    public function test_low_data_fires_with_evidence_and_turns_critical_then_resolves_when_topped_up(): void
    {
        $u = $this->user(['travel']);
        $o = $this->esim($u);
        $this->usage($o, 850);

        $r = (new CardEngine)->evaluate($u);
        $this->assertSame('low_data', $r['primary']->rule_key);
        $this->assertSame('important', $r['primary']->priority);
        $this->assertSame(85, $r['primary']->evidence['percent_used']);
        $this->assertStringContainsString('85%', (new CardEngine)->render($r['primary'])['why']);

        $this->usage($o, 970);
        $this->assertSame('critical', (new CardEngine)->evaluate($u)['primary']->priority);

        DB::table('esim_usage_snapshots')->delete();
        $this->usage($o, 100);
        $this->assertNull((new CardEngine)->evaluate($u)['primary'], 'a resolved alert disappears');
        $this->assertSame(JourneyCard::EXPIRED, JourneyCard::where('rule_key', 'low_data')->first()->state);
    }

    public function test_a_protective_alert_outranks_a_suggestion_and_only_one_suggestion_shows(): void
    {
        $u = $this->user(['line']);
        $this->esim($u, ['expires_at' => now()->addHours(5)]);                       // critical expiry
        foreach (range(1, 4) as $i) {
            CallEvent::create(['user_id' => $u->id, 'status' => 'no-answer']);        // suggestion: missed calls
        }
        SmsOrder::create(['user_id' => $u->id, 'provider' => 'x', 'status' => 'completed', 'completed_at' => now()]);   // second suggestion: referral

        $r = (new CardEngine)->evaluate($u);
        $this->assertSame('esim_expiring', $r['primary']->rule_key);
        $this->assertSame('critical', $r['primary']->priority);
        $this->assertCount(1, array_filter([$r['primary'], ...$r['secondary']], fn ($c) => $c->priority === 'opportunity'), 'one suggestion at a time');
    }

    public function test_a_suggestion_needs_a_matching_intent_and_respects_holdout_caps_and_dismissal(): void
    {
        $u = $this->user(['travel']);                                                // not a line/business user
        foreach (range(1, 4) as $i) {
            CallEvent::create(['user_id' => $u->id, 'status' => 'no-answer']);
        }
        $this->assertNull((new CardEngine)->evaluate($u)['primary'], 'no intent match, no missed-calls suggestion');

        (new IntentService)->choose($u, ['line']);
        $first = (new CardEngine)->evaluate($u)['primary'];
        $this->assertSame('missed_calls', $first->rule_key);

        (new CardEngine)->dismiss($first);
        $this->assertNull((new CardEngine)->evaluate($u)['primary'], 'dismissed means cooled down');

        Setting::setValue('journey.holdout_pct', 100);
        $h = $this->user(['line']);
        Setting::setValue('journey.holdout_pct', 100);
        foreach (range(1, 4) as $i) {
            CallEvent::create(['user_id' => $h->id, 'status' => 'no-answer']);
        }
        $this->assertTrue((new CardEngine)->inHoldout($h));
        $this->assertNull((new CardEngine)->evaluate($h)['primary'], 'holdout users get no suggestions');
    }

    public function test_a_critical_alert_dismissed_comes_back_and_a_number_renewal_without_funds_is_critical(): void
    {
        $u = $this->user();
        VirtualNumber::create(['user_id' => $u->id, 'provider' => 'twilio', 'phone_number' => '+15550001', 'monthly_cost' => 1, 'monthly_retail' => 4.5, 'status' => 'active', 'next_billing_date' => now()->addDays(2)->toDateString()]);

        $r = (new CardEngine)->evaluate($u);
        $this->assertSame('number_renewal', $r['primary']->rule_key);
        $this->assertSame('critical', $r['primary']->priority);

        (new CardEngine)->snooze($r['primary'], 24);
        $this->assertNull((new CardEngine)->evaluate($u)['primary'], 'snoozed for a day');
        JourneyCard::query()->update(['snooze_until' => now()->subMinute(), 'updated_at' => now()->subDays(2)]);
        $this->assertNotNull((new CardEngine)->evaluate($u)['primary'], 'it returns after the snooze');
    }

    public function test_the_signal_library_never_reads_private_content(): void
    {
        $src = strtolower((string) file_get_contents(app_path('Services/JourneyEngine/Signals.php')));
        // Strip comments so the doc block that EXPLAINS the rule does not trip it.
        $code = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $src);
        foreach (['inbound_messages', 'inboundmessage', 'transcript', 'voicemail', 'outbound_messages', 'support_messages', 'n2n_messages', 'contacts', '->body', '->message'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "Signals must not touch {$forbidden}");
        }
    }

    public function test_the_today_stack_chooses_intents_and_only_ever_acts_on_the_users_own_cards(): void
    {
        $u = $this->user();
        Livewire::actingAs($u)->test(TodayStack::class)
            ->assertSee('What brings you to Naara?')
            ->set('text', 'I want to earn a commission and travel')->call('understand')
            ->assertSet('selected', ['travel', 'earn'])
            ->call('toggle', 'earn')->assertSet('selected', ['travel'])
            ->call('save')->assertSet('chooser', false);
        $this->assertSame(['travel'], (new IntentService)->for($u));

        $o = $this->esim($u);
        $this->usage($o, 900);
        $c = Livewire::actingAs($u)->test(TodayStack::class)->assertSee('Your data is running low')->assertSee('Why am I seeing this?');

        $other = $this->user();
        $mine = JourneyCard::where('user_id', $u->id)->first();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($other)->test(TodayStack::class)->call('dismiss', $mine->id);
    }

    public function test_the_engine_can_be_switched_off_by_the_admin(): void
    {
        $u = $this->user(['travel']);
        $this->usage($this->esim($u), 900);
        Setting::setValue('journey.enabled', false);
        Livewire::actingAs($u)->test(TodayStack::class)->assertDontSee('Your data is running low');
    }
}
