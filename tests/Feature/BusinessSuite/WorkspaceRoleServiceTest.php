<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Permissions\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WorkspaceRoleServiceTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BusinessRoleSeeder']);
    }

    private function service(): WorkspaceRoleService
    {
        return app(WorkspaceRoleService::class);
    }

    private function group(User $owner): WalletGroup
    {
        return WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);
    }

    public function test_assigning_a_role_grants_its_permissions_and_updates_the_denormalized_column(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $agent = User::factory()->create();
        $member = WalletGroupMember::create([
            'wallet_group_id' => $group->id, 'user_id' => $agent->id, 'invited_at' => now(), 'accepted_at' => now(),
        ]);

        $this->service()->assignRole($group, $agent, BusinessRole::AGENT);

        $this->assertTrue($this->service()->hasPermission($group, $agent, Permissions::USE_WORKSPACE));
        $this->assertFalse($this->service()->hasPermission($group, $agent, Permissions::MANAGE_SEATS));
        $this->assertSame('agent', $member->fresh()->role);
    }

    public function test_viewer_role_has_no_spend_permission(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $viewer = User::factory()->create();

        $this->service()->assignRole($group, $viewer, BusinessRole::VIEWER);

        $this->assertTrue($this->service()->hasPermission($group, $viewer, Permissions::VIEW_REPORTS));
        $this->assertFalse($this->service()->hasPermission($group, $viewer, Permissions::SPEND));
    }

    public function test_removing_a_role_revokes_every_permission_in_that_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $admin = User::factory()->create();
        $this->service()->assignRole($group, $admin, BusinessRole::ADMIN);

        $this->service()->removeRole($group, $admin);

        $this->assertFalse($this->service()->hasPermission($group, $admin, Permissions::MANAGE_SEATS));
    }

    public function test_a_role_in_one_group_never_leaks_permissions_into_another(): void
    {
        $owner = User::factory()->create();
        $groupA = $this->group($owner);
        $groupB = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Beta', 'kind' => 'business']);
        $user = User::factory()->create();

        $this->service()->assignRole($groupA, $user, BusinessRole::OWNER);

        $this->assertTrue($this->service()->hasPermission($groupA, $user, Permissions::MANAGE_GROUP));
        $this->assertFalse($this->service()->hasPermission($groupB, $user, Permissions::MANAGE_GROUP));
    }

    public function test_role_checks_never_leak_the_ambient_team_context_afterward(): void
    {
        $owner = User::factory()->create();
        $group = $this->group($owner);
        $admin = User::factory()->create();
        $this->service()->assignRole($group, $admin, BusinessRole::ADMIN);

        // A totally unrelated ambient (non-business) check, run right after a
        // business permission check, must not be scoped to $group's team id.
        $admin->assignRole('admin');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }
}
