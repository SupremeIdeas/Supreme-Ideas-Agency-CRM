<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Admin\BusinessSuiteSettingsPage;
use App\Livewire\Business;
use App\Livewire\CallFlowBuilder;
use App\Models\AuditLog;
use App\Models\RingGroup;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Services\Wallet\WalletGroupService;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSettingsSchema;
use App\Support\Business\BusinessSuiteSettings;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** The Business Suite control panel (MASTER-ONLY): schema defaults, the admin page, and every limit/module switch actually biting. */
class BusinessControlPanelTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Queue::fake();
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role);

        return $u;
    }

    private function workspace(): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $g = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($g, $owner, BusinessRole::OWNER);

        return [$owner, $g];
    }

    public function test_every_field_has_a_default_inside_its_own_limits(): void
    {
        foreach (BusinessSettingsSchema::flat() as $key => $f) {
            $this->assertNotSame('', trim($f['help']), "$key needs help text");
            if ($f['type'] === 'int') {
                $this->assertGreaterThanOrEqual($f['min'], $f['default'], $key);
                $this->assertLessThanOrEqual($f['max'], $f['default'], $key);
            }
        }
        $this->assertSame(25, BusinessSettings::limit('seats'));
        $this->assertSame(12, BusinessSettings::call('max_hops'));
        $this->assertTrue(BusinessSettings::workspaceCreationOpen());
    }

    public function test_unknown_modules_stay_off_whatever_is_stored(): void
    {
        Setting::setValue('business.module.not_a_module', true, 'business');
        $this->assertFalse(BusinessSettings::module('not_a_module'));
        $this->assertFalse(BusinessSettings::module('integrations'));
        $this->assertTrue(BusinessSettings::module('call_flows'));
        $this->assertFalse(BusinessSettings::module('does_not_exist'));
    }

    public function test_a_module_is_never_live_while_the_suite_is_off(): void
    {
        BusinessSuiteSettings::set(false);
        $this->assertFalse(BusinessSettings::module('call_flows'));
    }

    public function test_super_admin_saves_with_confirmation_and_it_is_audited(): void
    {
        $sa = $this->user('super_admin');
        $slot = BusinessSuiteSettingsPage::slot('business.limit.seats');
        $flows = BusinessSuiteSettingsPage::slot('business.module.call_flows');

        Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)
            ->set("values.$slot", 3)->call('save')
            ->assertHasNoErrors();
        $this->assertSame(3, BusinessSettings::limit('seats'));
        $this->assertTrue(AuditLog::where('action', 'business_suite.settings_changed')->exists());

        // A switch that affects live customers needs the tick.
        Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)
            ->set("values.$flows", false)->call('save')->assertHasErrors('confirmed');
        $this->assertTrue(BusinessSettings::module('call_flows'));
        Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)
            ->set("values.$flows", false)->set('confirmed', true)->call('save')->assertHasNoErrors();
        $this->assertFalse(BusinessSettings::module('call_flows'));
    }

    public function test_out_of_range_values_are_refused(): void
    {
        $slot = BusinessSuiteSettingsPage::slot('business.call.max_hops');
        Livewire::actingAs($this->user('super_admin'))->test(BusinessSuiteSettingsPage::class)
            ->set("values.$slot", 999)->call('save')->assertHasErrors("values.$slot");
        $this->assertSame(12, BusinessSettings::call('max_hops'));
    }

    public function test_reset_puts_the_default_back(): void
    {
        $sa = $this->user('super_admin');
        Setting::setValue('business.limit.seats', 4, 'business');
        Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)
            ->call('resetField', BusinessSuiteSettingsPage::slot('business.limit.seats'));
        $this->assertSame(25, BusinessSettings::limit('seats'));
    }

    public function test_an_admin_can_look_but_not_change(): void
    {
        $slot = BusinessSuiteSettingsPage::slot('business.limit.seats');
        Livewire::actingAs($this->user('admin'))->test(BusinessSuiteSettingsPage::class)
            ->assertSee('Only a super admin can change them')
            ->set("values.$slot", 2)->call('save')->assertForbidden();
        $this->assertSame(25, BusinessSettings::limit('seats'));
    }

    public function test_the_workspaces_and_activity_tabs_render(): void
    {
        [, $g] = $this->workspace();
        Livewire::actingAs($this->user('admin'))->test(BusinessSuiteSettingsPage::class)
            ->call('setTab', 'workspaces')->assertSee($g->name)
            ->call('setTab', 'activity')->assertOk()
            ->call('setTab', 'nonsense')->assertSet('tab', 'settings');
    }

    public function test_the_seat_limit_blocks_new_seats_but_not_a_re_invite(): void
    {
        Setting::setValue('business.limit.seats', 1, 'business');
        [$owner] = $this->workspace();
        $svc = app(WalletGroupService::class);

        $svc->inviteSeat($owner, $owner, 'a@example.com', BusinessRole::AGENT, null, null);
        $svc->inviteSeat($owner, $owner, 'a@example.com', BusinessRole::VIEWER, null, null); // refresh, not a new seat

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('limit of 1 seats');
        $svc->inviteSeat($owner, $owner, 'b@example.com', BusinessRole::AGENT, null, null);
    }

    public function test_paused_sign_ups_stop_a_new_workspace_only(): void
    {
        Setting::setValue(BusinessSettings::WORKSPACE_CREATION, false, 'business');
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        Livewire::actingAs($u)->test(Business::class)->set('name', 'Acme')->call('createWorkspace')
            ->assertSee('paused for now');
        $this->assertSame(0, WalletGroup::where('owner_user_id', $u->id)->where('kind', 'business')->count());
    }

    public function test_the_ring_group_limit_bites(): void
    {
        Setting::setValue('business.limit.ring_groups', 1, 'business');
        [$owner, $g] = $this->workspace();

        $c = Livewire::actingAs($owner)->test(CallFlowBuilder::class);
        $c->set('rgName', 'Sales')->call('addRingGroup')->set('rgName', 'Support')->call('addRingGroup')
            ->assertSet('error', 'This workspace has reached its limit of 1 ring groups.');
        $this->assertSame(1, RingGroup::where('wallet_group_id', $g->id)->count());
    }

    public function test_turning_call_flows_off_shows_coming_soon_and_stops_the_builder(): void
    {
        [$owner] = $this->workspace();
        Setting::setValue('business.module.call_flows', false, 'business');

        $this->actingAs($owner)->get('/business/call-flows')->assertOk()->assertSee('Call flows is coming soon');
        $this->actingAs($owner)->getJson('/business/call-flows')->assertNotFound();
    }
}
