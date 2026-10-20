<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use App\Support\Permissions\BusinessRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Business Suite Prompt 13 §2. This is the load-bearing regression test: it
 * proves the `team_id = 0` sentinel retrofit does not disturb a single
 * existing, non-business-suite permission check, and that the 4 new
 * business roles (`business_owner`/etc., team_id = null) never collide with
 * the pre-existing staff roles of the same short name (`admin`).
 */
class TeamPermissionsRetrofitTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BusinessRoleSeeder']);
    }

    public function test_ambient_staff_roles_are_stamped_with_the_sentinel_team_id(): void
    {
        $rows = Role::whereIn('name', ['super_admin', 'admin', 'staff', 'user', 'merchant'])->get();

        $this->assertCount(5, $rows);
        foreach ($rows as $role) {
            $this->assertSame(0, $role->team_id, "role [{$role->name}] should carry the ambient sentinel team_id");
        }
    }

    public function test_business_roles_carry_a_null_team_id_and_do_not_collide_with_staff_admin(): void
    {
        $bizAdmin = Role::where('name', 'business_admin')->first();
        $staffAdmin = Role::where('name', 'admin')->first();

        $this->assertNotNull($bizAdmin);
        $this->assertNotNull($staffAdmin);
        $this->assertNull($bizAdmin->team_id);
        $this->assertSame(0, $staffAdmin->team_id);
        $this->assertNotSame($bizAdmin->id, $staffAdmin->id);
    }

    public function test_existing_ambient_role_checks_are_unaffected_by_teams_being_enabled(): void
    {
        $admin = User::factory()->create();
        $plainUser = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertFalse($plainUser->fresh()->hasRole('admin'));
    }

    public function test_a_business_role_assignment_is_scoped_to_its_own_wallet_group(): void
    {
        $owner = User::factory()->create();
        $groupA = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'A', 'kind' => 'business']);
        $groupB = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'B', 'kind' => 'business']);
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($groupA->id);
        $owner->assignRole(BusinessRole::spatieName(BusinessRole::OWNER));

        $registrar->setPermissionsTeamId($groupA->id);
        $this->assertTrue($owner->fresh()->hasRole('business_owner'));

        $registrar->setPermissionsTeamId($groupB->id);
        $this->assertFalse($owner->fresh()->hasRole('business_owner'));

        // Resetting to the ambient sentinel must not leak the business role either.
        $registrar->setPermissionsTeamId(0);
        $this->assertFalse($owner->fresh()->hasRole('business_owner'));
    }
}
