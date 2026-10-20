<?php

namespace Database\Seeders;

use App\Support\Permissions\BusinessRole;
use App\Support\Permissions\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Business Suite Prompt 13 §2. Seeds the 4 global business-workspace role
 * definitions with an explicit `team_id = null` — deliberately bypassing
 * Spatie's ambient `getPermissionsTeamId()` stamp (which RoleSeeder relies
 * on for the pre-existing staff roles) so ONE shared "owner"/"admin"/
 * "agent"/"viewer" role definition can be assigned via a team-scoped pivot
 * row to any number of distinct wallet_group_ids — confirmed against
 * HasRoles::roles(), which matches a role definition via
 * `whereNull(team_id) OR team_id = $ambient`. Actually granting one of
 * these to a member of a specific WalletGroup is WorkspaceRoleService's job,
 * not this seeder's — this only defines the 4 roles and their permissions,
 * exactly once, regardless of how many business workspaces ever exist.
 */
class BusinessRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, BusinessRole::GUARD);
        }

        foreach (BusinessRole::all() as $shortName) {
            $spatieName = BusinessRole::spatieName($shortName);

            $role = Role::where('name', $spatieName)
                ->where('guard_name', BusinessRole::GUARD)
                ->whereNull('team_id')
                ->first();

            if (! $role) {
                $role = Role::create([
                    'name' => $spatieName,
                    'guard_name' => BusinessRole::GUARD,
                    'team_id' => null,
                ]);
            }

            $role->syncPermissions(BusinessRole::permissionsFor($shortName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
