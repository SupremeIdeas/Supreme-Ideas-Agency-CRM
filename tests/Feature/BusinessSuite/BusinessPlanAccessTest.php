<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Admin\BusinessPlanCapabilities;
use App\Livewire\Business;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Business\BusinessAccess;
use App\Services\Wallet\WalletGroupService;
use App\Services\Wallet\WalletService;
use App\Support\Business\BusinessPlanFeatures;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSuiteSettings;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NaaraBusiness Suite entitlements (MASTER-ONLY): Business Suite features assigned to Naara Pro plans, the gate that enforces them, per-plan limits,
 * and the rule that a lapsed plan pauses access without deleting anything. This test runs the PRODUCTION default ("Naara Pro plans only"); every other
 * Business test runs with the Suite open to everyone (see EnablesBusinessSuite).
 */
class BusinessPlanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        BusinessSuiteSettings::set(true);
        foreach (['call_flows', 'inbox', 'recording'] as $m) {
            Setting::setValue(BusinessSettings::MODULE.$m, true, 'business');
        }
        Setting::setValue(BusinessSettings::MODULE.'ai_voice', false, 'business');
        Setting::setValue(BusinessSettings::ACCESS.'model', 'pro', 'business');
    }

    private function plan(array $caps, string $name = 'Business', float $price = 49): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create([
            'name' => $name, 'price_usd_per_month' => $price, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 10,
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

    private function lapse(User $u): void
    {
        $brand = BrandPartner::where('owner_user_id', $u->id)->firstOrFail();
        $brand->subscription->forceFill(['status' => BrandSubscription::CANCELLED])->save();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_feature_is_assignable_only_while_its_module_is_switched_on(): void
    {
        $this->assertEqualsCanonicalizing(['workspace', 'call_flows', 'inbox', 'recording'], BusinessPlanFeatures::assignable());

        Setting::setValue(BusinessSettings::MODULE.'inbox', false, 'business');
        $this->assertNotContains('inbox', BusinessPlanFeatures::assignable());

        BusinessSuiteSettings::set(false);
        $this->assertSame([], BusinessPlanFeatures::assignable());
    }

    public function test_choices_are_made_consistent_and_clamped(): void
    {
        $this->assertSame(['workspace', 'recording', 'intelligence'], BusinessPlanFeatures::normalize(['intelligence']));
        $this->assertSame(['workspace', 'inbox'], BusinessPlanFeatures::normalize(['inbox', 'nonsense']));

        $c = BusinessPlanFeatures::clean(['features' => ['inbox'], 'monthly_credits' => 9999999, 'limits' => ['seats' => '12', 'numbers' => 0, 'evil' => 5], 'premium_skins' => 1]);
        $this->assertSame(100000, $c['monthly_credits']);
        $this->assertSame(['seats' => 12], $c['limits']);
        $this->assertTrue($c['premium_skins']);
        $this->assertSame(['workspace', 'inbox'], $c['features']);
    }

    public function test_the_admin_assigns_features_to_a_plan_and_a_switched_off_module_keeps_its_assignment(): void
    {
        $plan = $this->plan(['features' => ['workspace', 'ai_voice'], 'monthly_credits' => 0]);   // ai_voice module is off today

        Livewire::actingAs($this->admin())->test(BusinessPlanCapabilities::class, ['planId' => $plan->id])
            ->set('features', ['workspace', 'inbox', 'ai_voice'])   // a tampered request cannot add a module that is off... and cannot remove it either
            ->set('monthlyCredits', 25)
            ->set('limits.seats', 40)
            ->call('save')->assertHasNoErrors();

        $caps = $plan->fresh()->capabilities;
        $this->assertEqualsCanonicalizing(['workspace', 'inbox', 'ai_voice'], $caps['features']);   // ai_voice kept from before, inbox newly added
        $this->assertSame(25, $caps['monthly_credits']);
        $this->assertSame(40, $caps['limits']['seats']);

        // The off module grants nothing today, but the assignment is still stored for when it comes back.
        $owner = $this->onPlan($plan);
        $this->assertFalse(app(BusinessAccess::class)->allows($owner, 'ai_voice'));
        $this->assertTrue(app(BusinessAccess::class)->allows($owner, 'inbox'));
    }

    public function test_only_an_admin_may_edit_plan_capabilities(): void
    {
        $plan = $this->plan(['features' => []]);
        Livewire::actingAs(User::factory()->create(['is_active' => true]))->test(BusinessPlanCapabilities::class, ['planId' => $plan->id])->assertStatus(403);
    }

    public function test_access_follows_the_owners_plan_and_a_lapsed_plan_pauses_it(): void
    {
        $access = app(BusinessAccess::class);
        $owner = $this->onPlan($this->plan(['features' => ['workspace', 'inbox']]));
        $nobody = User::factory()->create(['is_active' => true]);

        $this->assertTrue($access->allows($owner, 'inbox'));
        $this->assertFalse($access->allows($owner, 'recording'));    // plan does not include it
        $this->assertFalse($access->allows($nobody, 'inbox'));
        $this->assertSame(BusinessAccess::STATE_ACTIVE, $access->state($owner));
        $this->assertSame(BusinessAccess::STATE_NONE, $access->state($nobody));

        $this->lapse($owner);
        $this->assertFalse($access->allows($owner, 'inbox'));
        $this->assertSame(BusinessAccess::STATE_LAPSED, $access->state($owner));

        // Renewing brings it straight back.
        BrandPartner::where('owner_user_id', $owner->id)->firstOrFail()->subscription->forceFill(['status' => BrandSubscription::ACTIVE])->save();
        $this->assertTrue($access->allows($owner, 'inbox'));
    }

    public function test_an_admin_granted_pro_gets_the_features_of_the_plan_it_was_granted(): void
    {
        $member = User::factory()->create(['is_active' => true]);
        app(BrandSubscriptionService::class)->grantPro($member, $this->plan(['features' => ['workspace', 'call_flows']]), $this->admin(), 'launch partner');

        $this->assertTrue(app(BusinessAccess::class)->allows($member, 'call_flows'));
        $this->assertFalse(app(BusinessAccess::class)->allows($member, 'inbox'));
    }

    public function test_the_admin_can_open_the_suite_to_everyone(): void
    {
        $nobody = User::factory()->create(['is_active' => true]);
        $this->assertFalse(app(BusinessAccess::class)->allows($nobody, 'inbox'));

        Setting::setValue(BusinessSettings::ACCESS.'model', 'open', 'business');
        $this->assertTrue(app(BusinessAccess::class)->allows($nobody, 'inbox'));
        $this->assertSame(BusinessAccess::STATE_OPEN, app(BusinessAccess::class)->state($nobody));

        // ...but a switched-off module still grants nothing, whoever you are.
        Setting::setValue(BusinessSettings::MODULE.'inbox', false, 'business');
        $this->assertFalse(app(BusinessAccess::class)->allows($nobody, 'inbox'));
    }

    public function test_a_member_without_a_plan_sees_a_friendly_page_not_a_403_and_cannot_create_a_workspace(): void
    {
        $nobody = User::factory()->create(['is_active' => true]);

        $this->actingAs($nobody)->get('/business/workspace')->assertOk()->assertSee('part of NaaraBusiness Suite')->assertSee('See plans');

        Livewire::actingAs($nobody)->test(Business::class)->set('name', 'Acme')->call('createWorkspace')
            ->assertSee('comes with a NaaraBusiness Suite plan');
        $this->assertSame(0, WalletGroup::where('owner_user_id', $nobody->id)->count());
    }

    public function test_a_plan_owner_can_create_and_use_a_workspace(): void
    {
        $owner = $this->onPlan($this->plan(['features' => ['workspace', 'inbox']]));

        $this->actingAs($owner)->get('/business/workspace')->assertOk()->assertDontSee('part of NaaraBusiness Suite');
        Livewire::actingAs($owner)->test(Business::class)->set('name', 'Acme Ltd')->call('createWorkspace')->assertSet('error', null);
        $this->assertSame(1, WalletGroup::where('owner_user_id', $owner->id)->where('kind', 'business')->count());
    }

    public function test_a_lapsed_owner_is_told_their_data_is_safe_and_a_seat_member_is_told_to_ask_the_owner(): void
    {
        $owner = $this->onPlan($this->plan(['features' => ['workspace', 'inbox']]));
        $group = app(WalletGroupService::class)->findOrCreateOwnGroup($owner, WalletGroup::KIND_BUSINESS);
        $group->forceFill(['name' => 'Acme'])->save();
        app(\App\Services\Permissions\WorkspaceRoleService::class)->assignRole($group, $owner, BusinessRole::OWNER);

        $this->lapse($owner);

        $this->actingAs($owner)->get('/business/workspace')->assertOk()
            ->assertSee('has ended')->assertSee('still here, just paused')->assertSee('Renew a plan');
        $this->assertDatabaseHas('wallet_groups', ['id' => $group->id]);   // nothing deleted
        // Anything that is not a page load (a send, a Livewire update, JSON) is refused outright rather than half-working.
        $this->actingAs($owner)->withSession(['active_wallet_group_id' => $group->id])->postJson('/business/inbox/send', [])->assertStatus(403);

        $agent = User::factory()->create(['is_active' => true]);
        \App\Models\WalletGroupMember::create(['wallet_group_id' => $group->id, 'user_id' => $agent->id, 'role' => BusinessRole::AGENT, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        app(\App\Services\Permissions\WorkspaceRoleService::class)->assignRole($group, $agent, BusinessRole::AGENT);
        $this->actingAs($agent)->withSession(['active_wallet_group_id' => $group->id])->get('/business/workspace')
            ->assertOk()->assertSee('Ask them to check their plan')->assertDontSee('Renew a plan');
    }

    public function test_a_plan_can_raise_workspace_limits_but_never_lower_the_platform_default(): void
    {
        Setting::setValue(BusinessSettings::LIMIT.'seats', 3, 'business');
        $big = $this->onPlan($this->plan(['features' => ['workspace'], 'limits' => ['seats' => 10]], 'Big', 99));
        $small = $this->onPlan($this->plan(['features' => ['workspace'], 'limits' => ['seats' => 1]], 'Small', 19));
        $none = $this->onPlan($this->plan(['features' => ['workspace']], 'Plain', 29));
        $svc = app(WalletGroupService::class);
        $access = app(BusinessAccess::class);

        $this->assertSame(10, $access->limit($svc->findOrCreateOwnGroup($big, WalletGroup::KIND_BUSINESS), 'seats'));
        $this->assertSame(3, $access->limit($svc->findOrCreateOwnGroup($small, WalletGroup::KIND_BUSINESS), 'seats'));
        $this->assertSame(3, $access->limit($svc->findOrCreateOwnGroup($none, WalletGroup::KIND_BUSINESS), 'seats'));
        $this->assertSame(BusinessSettings::limit('flow_nodes'), $access->limit($svc->findOrCreateOwnGroup($big, WalletGroup::KIND_BUSINESS), 'flow_nodes'));   // not an overridable limit
    }

    public function test_the_admin_page_offers_the_plan_editor_and_the_access_switch(): void
    {
        $plan = $this->plan(['features' => ['workspace']]);
        $this->actingAs($this->admin())->get(route('admin.brand-directory'))->assertOk()->assertSee('NaaraBusiness Suite and Pro perks');
        $this->assertNotNull($plan);
        $this->assertArrayHasKey(BusinessSettings::ACCESS.'model', \App\Support\Business\BusinessSettingsSchema::flat());
    }
}
