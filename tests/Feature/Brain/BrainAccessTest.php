<?php

namespace Tests\Feature\Brain;

use App\Livewire\Admin\Brain\Prompts;
use App\Livewire\Admin\BusinessPlanCapabilities;
use App\Livewire\Brain\Chat;
use App\Models\Brain\BrainMessage;
use App\Models\Brain\BrainPrompt;
use App\Models\Brain\BrainThread;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Wallet\WalletService;
use App\Support\Brain\BrainAccess;
use App\Support\Brain\BrainPrompts;
use App\Support\Brain\BrainSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** NaaraBrain access follows Naara Pro (master-only), and the seeded ideas library that gives members ideas. */
class BrainAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.api_key' => 'sk-test', 'ai.backoff_ms' => 0]);
        Setting::setValue(BrainSettings::FLAG, true, 'brain');
        Http::fake(['*/messages' => Http::response(['model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => 'ok']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
    }

    private function plan(array $caps = [], string $name = 'Business'): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create([
            'name' => $name, 'price_usd_per_month' => 49, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 10,
            'video_previews_allowed' => 0, 'is_active' => true, 'sort_order' => 1, 'capabilities' => $caps,
        ]);
    }

    private function onPlan(BrandSubscriptionPlan $plan): User
    {
        $u = User::factory()->create(['is_active' => true]);
        app(WalletService::class)->credit($u, 500, 'USD');
        app(BrandSubscriptionService::class)->subscribe($u, $plan);

        return $u;
    }

    public function test_free_members_get_the_free_ceiling_and_the_lightest_depth(): void
    {
        $u = User::factory()->create();

        $this->assertSame(BrainAccess::TIER_FREE, BrainAccess::tier($u));
        $this->assertSame(10, BrainAccess::dailyLimit($u));
        $this->assertSame(['quick'], BrainAccess::depths($u));
        $this->assertSame('quick', BrainAccess::effectiveDepth($u, 'deep'));
        $this->assertSame('Free', BrainAccess::label($u));
    }

    public function test_a_plan_without_its_own_numbers_gets_the_pro_defaults(): void
    {
        $u = $this->onPlan($this->plan([], 'Starter'));

        $this->assertSame(BrainAccess::TIER_PRO, BrainAccess::tier($u));
        $this->assertSame(100, BrainAccess::dailyLimit($u));
        $this->assertSame(['quick', 'balanced', 'deep'], BrainAccess::depths($u));
        $this->assertSame('Starter', BrainAccess::label($u));
    }

    public function test_a_bigger_plan_can_set_more_questions_and_fewer_or_more_depths(): void
    {
        $small = $this->onPlan($this->plan(['brain' => ['daily' => 40, 'depths' => ['quick', 'balanced']]], 'Small'));
        $big = $this->onPlan($this->plan(['brain' => ['daily' => 500]], 'Big'));

        $this->assertSame(40, BrainAccess::dailyLimit($small));
        $this->assertSame(['quick', 'balanced'], BrainAccess::depths($small));
        $this->assertSame('balanced', BrainAccess::effectiveDepth($small, 'deep'));   // clamps to the best allowed at or below
        $this->assertSame(500, BrainAccess::dailyLimit($big));
        $this->assertSame(['quick', 'balanced', 'deep'], BrainAccess::depths($big));  // none set = the Pro default
    }

    public function test_a_lapsed_plan_falls_back_to_free_and_keeps_the_data(): void
    {
        $u = $this->onPlan($this->plan(['brain' => ['daily' => 500]]));
        $this->assertSame(500, BrainAccess::dailyLimit($u));

        BrandPartner::where('owner_user_id', $u->id)->firstOrFail()->subscription->forceFill(['status' => BrandSubscription::CANCELLED])->save();
        \App\Services\N2N\Media\NaaraPro::bust($u->id);

        $this->assertSame(BrainAccess::TIER_FREE, BrainAccess::tier($u->fresh()));
        $this->assertSame(10, BrainAccess::dailyLimit($u->fresh()));
    }

    public function test_admins_are_never_limited(): void
    {
        $this->seed(RoleSeeder::class);
        $a = User::factory()->create();
        $a->assignRole('admin');

        $this->assertNull(BrainAccess::dailyLimit($a));
        $this->assertSame(['quick', 'balanced', 'deep'], BrainAccess::depths($a));
    }

    public function test_when_free_access_is_off_free_members_are_invited_to_upgrade_and_pro_members_still_ask(): void
    {
        Setting::setValue('brain.free_access', false, 'brain');
        $free = User::factory()->create();
        $pro = $this->onPlan($this->plan());

        $this->actingAs($free);
        Livewire::test(Chat::class)->call('send', 'hello')->assertSee(__('brain.pro_only'))->assertSee(__('brain.upgrade'));
        $this->assertSame(0, BrainMessage::count());

        $this->actingAs($pro);
        Livewire::test(Chat::class)->call('send', 'hello');
        $this->assertSame(1, BrainMessage::where('role', 'user')->count());
    }

    public function test_the_free_ceiling_blocks_with_an_upgrade_link_but_a_plan_raises_it(): void
    {
        Setting::setValue('brain.daily_messages', 1, 'brain');
        $free = User::factory()->create();
        $pro = $this->onPlan($this->plan(['brain' => ['daily' => 3]]));

        $this->actingAs($free);
        Livewire::test(Chat::class)->call('send', 'one')->call('send', 'two')->assertSee(__('brain.upgrade'));
        $this->assertSame(1, BrainMessage::where('user_id', $free->id)->where('role', 'user')->count());

        $this->actingAs($pro);
        Livewire::test(Chat::class)->call('send', 'a')->set('threadId', null)->call('send', 'b');
        $this->assertSame(2, BrainMessage::where('user_id', $pro->id)->where('role', 'user')->count());
    }

    public function test_a_locked_depth_cannot_be_chosen_and_an_old_choice_runs_at_what_the_plan_allows(): void
    {
        config(['ai.models.fast' => 'claude-haiku-4-5-20251001']);
        $free = User::factory()->create();
        $t = BrainThread::create(['user_id' => $free->id, 'title' => 'x', 'depth' => 'deep']);   // e.g. chosen while on a plan that has since lapsed
        $this->actingAs($free);

        Livewire::test(Chat::class, ['threadId' => $t->id])->call('setDepth', 'deep')->assertSee(__('brain.depth_locked'))->call('send', 'hi');

        $this->assertSame('deep', $t->fresh()->depth);   // untouched
        Http::assertSent(fn ($r) => $r->data()['model'] === 'claude-haiku-4-5-20251001');
    }

    public function test_the_sidebar_shows_the_plan_and_the_meter(): void
    {
        $u = $this->onPlan($this->plan(['brain' => ['daily' => 40]], 'Growth'));
        $this->actingAs($u);

        Livewire::test(Chat::class)->assertSee('Growth')->assertSee('0 of 40 today');
    }

    public function test_the_plan_editor_saves_brain_numbers_and_ignores_junk(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $plan = $this->plan();
        $this->actingAs($admin);

        Livewire::test(BusinessPlanCapabilities::class, ['planId' => $plan->id])->set('brainDaily', 250)->set('brainDepths', ['quick', 'deep'])->call('save')->assertHasNoErrors();
        $this->assertSame(['daily' => 250, 'depths' => ['quick', 'deep']], $plan->fresh()->capabilities['brain']);

        Livewire::test(BusinessPlanCapabilities::class, ['planId' => $plan->id])->set('brainDepths', ['nope'])->call('save')->assertHasErrors('brainDepths.0');
        Livewire::test(BusinessPlanCapabilities::class, ['planId' => $plan->id])->set('brainDaily', '')->set('brainDepths', [])->call('save');
        $this->assertArrayNotHasKey('brain', $plan->fresh()->capabilities);   // blank = the Pro defaults
    }

    // -------------------------------------------------------------- the ideas library

    public function test_the_library_is_seeded_with_at_least_fifty_centre_lines_and_many_ideas(): void
    {
        $this->assertGreaterThanOrEqual(50, BrainPrompt::where('kind', 'tagline')->count());
        $this->assertGreaterThanOrEqual(60, BrainPrompt::where('kind', 'suggestion')->count());
        $this->assertGreaterThanOrEqual(15, BrainPrompt::where('kind', 'capability')->count());
        $this->assertEmpty(array_diff(BrainPrompt::where('kind', 'suggestion')->pluck('pack')->unique()->all(), array_keys(BrainPrompt::PACKS)), 'every idea belongs to a known area');
        // Honesty: anything that needs the member's own account data is "Soon", never offered as working.
        $this->assertSame(0, BrainPrompt::where('kind', 'suggestion')->where('live', true)->where(fn ($q) => $q->where('text', 'like', '%my balance%')->orWhere('text', 'like', 'Who spent%')->orWhere('text', 'like', 'Summarize my last call%'))->count());
    }

    public function test_reseeding_never_duplicates_or_overwrites_admin_edits(): void
    {
        $row = BrainPrompt::where('kind', 'tagline')->first();
        $row->update(['text' => 'Edited by the admin', 'active' => false]);
        $before = BrainPrompt::count();

        (new \Database\Seeders\BrainPromptSeeder)->run();

        $this->assertSame($before, BrainPrompt::count());
        $this->assertSame('Edited by the admin', $row->fresh()->text);
        $this->assertFalse($row->fresh()->active);
    }

    public function test_the_centre_line_changes_between_visits_and_never_repeats_the_last_one(): void
    {
        $lines = BrainPrompt::where('kind', 'tagline')->pluck('text')->all();
        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $seen[BrainPrompts::tagline($i ? end($seen) : null)] = true;
        }
        $this->assertGreaterThan(5, count($seen), 'the line rotates');
        $this->assertEmpty(array_diff(array_keys($seen), $lines));
        for ($i = 0; $i < 40; $i++) {
            $this->assertNotSame($lines[0], BrainPrompts::tagline($lines[0]));
        }
    }

    public function test_the_page_shows_a_library_line_and_working_chips_from_different_areas(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u);

        $c = Livewire::test(Chat::class);
        $this->assertContains($c->get('tagline'), BrainPrompt::where('kind', 'tagline')->pluck('text')->all());
        $chips = $c->get('chips');
        $this->assertGreaterThanOrEqual(4, count($chips));
        $live = BrainPrompt::where('kind', 'suggestion')->where('live', true)->pluck('text')->all();
        foreach ($chips as $chip) {
            $this->assertContains($chip, $live, 'a chip only offers something that works today');
        }
        $packs = BrainPrompt::whereIn('text', $chips)->pluck('pack')->all();
        $this->assertSame(count($packs), count(array_unique($packs)), 'each chip comes from a different area');
    }

    public function test_the_idea_browser_lists_every_area_and_asks_on_tap(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $c = Livewire::test(Chat::class)->call('toggleIdeas')->assertSee(__('brain.ideas_title'))->assertSee('Trips and data')->assertSee('Business phone')->assertSee(__('brain.soon'));
        $c->call('ask', 'What is an eSIM, and how is it different from a physical SIM?')->assertSet('ideasOpen', false);

        $this->assertSame(1, BrainMessage::where('role', 'user')->count());
    }

    public function test_the_admin_can_add_edit_switch_off_and_delete_ideas(): void
    {
        $this->seed(RoleSeeder::class);
        $a = User::factory()->create();
        $a->assignRole('super_admin');
        $this->actingAs($a);

        $c = Livewire::test(Prompts::class)->call('setKind', 'tagline')->set('newText', 'A brand new centre line')->call('add')->assertHasNoErrors();
        $row = BrainPrompt::where('text', 'A brand new centre line')->firstOrFail();
        $c->call('toggle', $row->id, 'active');
        $this->assertFalse($row->fresh()->active);
        $c->call('startEdit', $row->id)->set('editText', 'Reworded line')->call('saveEdit');
        $this->assertSame('Reworded line', $row->fresh()->text);
        $c->call('delete', $row->id);
        $this->assertNull(BrainPrompt::find($row->id));
        $c->call('toggle', $row->id + 999, 'bogus');   // junk field and missing row are ignored

        Livewire::actingAs(User::factory()->create())->test(Prompts::class)->assertForbidden();
    }
}
