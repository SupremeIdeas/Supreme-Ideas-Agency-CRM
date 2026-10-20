<?php

namespace App\Services\Permissions;

use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Support\Permissions\BusinessRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Business Suite Prompt 13 §2. The ONLY place in the app that ever touches
 * `PermissionRegistrar::setPermissionsTeamId()` for a business workspace —
 * every call here runs inside a try/finally that puts the ambient team id
 * back to the sentinel `0` (App\Support\Permissions\SentinelTeamResolver's
 * default) before returning, so a business-role check never leaks into any
 * unrelated staff/admin permission check running later in the same request.
 *
 * `WalletGroupMember.role` is kept in sync as a denormalized display copy
 * only — every actual authorization decision re-derives from the real
 * Spatie team-scoped assignment, never from that column.
 */
class WorkspaceRoleService
{
    public function __construct(private PermissionRegistrar $registrar)
    {
    }

    /** Grant (replacing any existing) business role to a member of this WalletGroup. */
    public function assignRole(WalletGroup $group, User $user, string $shortRoleName): void
    {
        if (! in_array($shortRoleName, BusinessRole::all(), true)) {
            throw new \InvalidArgumentException("Unknown business role [{$shortRoleName}].");
        }

        $this->forgetCachedRoles($user);
        $this->withTeam($group, function () use ($user, $shortRoleName) {
            $user->syncRoles([BusinessRole::spatieName($shortRoleName)]);
        });
        $this->forgetCachedRoles($user);

        WalletGroupMember::where('wallet_group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['role' => $shortRoleName]);
    }

    /** Revoke every business role this user holds in this WalletGroup. */
    public function removeRole(WalletGroup $group, User $user): void
    {
        $this->forgetCachedRoles($user);
        $this->withTeam($group, function () use ($user) {
            $user->syncRoles([]);
        });
        $this->forgetCachedRoles($user);

        WalletGroupMember::where('wallet_group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['role' => null]);
    }

    public function hasPermission(WalletGroup $group, User $user, string $permission): bool
    {
        $this->forgetCachedRoles($user);
        $result = $this->withTeam($group, fn () => $user->hasPermissionTo($permission, BusinessRole::GUARD));
        $this->forgetCachedRoles($user);

        return $result;
    }

    public function roleFor(WalletGroup $group, User $user): ?string
    {
        $this->forgetCachedRoles($user);
        $result = $this->withTeam($group, function () use ($user) {
            $spatieName = $user->roles()->where('guard_name', BusinessRole::GUARD)->value('name');

            return $spatieName ? str_replace('business_', '', $spatieName) : null;
        });
        $this->forgetCachedRoles($user);

        return $result;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withTeam(WalletGroup $group, \Closure $callback): mixed
    {
        $previous = $this->registrar->getPermissionsTeamId();

        try {
            $this->registrar->setPermissionsTeamId($group->id);

            return $callback();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * Spatie's own `getPermissionsViaRoles()` uses `loadMissing('roles', ...)`
     * — if the SAME `$user` instance was already checked against a different
     * WalletGroup earlier in the request, its `roles`/`permissions`
     * relations are cached from that unrelated team context, and
     * `loadMissing` would silently reuse that stale, wrongly-scoped result
     * instead of re-querying under the new team id. Every entry point above
     * that reads or writes role state must drop that cache first.
     */
    private function forgetCachedRoles(User $user): void
    {
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
    }
}
