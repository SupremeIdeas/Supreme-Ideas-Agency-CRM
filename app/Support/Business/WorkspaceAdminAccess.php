<?php

namespace App\Support\Business;

use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;

/**
 * Who may manage a workspace's sensitive settings (MASTER-ONLY, Business Suite): call recordings (Prompt 15, consent steer §8) and connected inbox channels (Prompt 16): the workspace owner and its
 * admins, nobody else. Naara staff and platform admins have no access here (support sees metadata only, in the admin Activity tab).
 * The "agent on that call" is not covered: the platform does not record which person picked up, so nothing here can honestly tell.
 */
class WorkspaceAdminAccess
{
    public static function can(WalletGroup $group, User $user): bool
    {
        if ($group->owner_user_id === $user->id) {
            return true;
        }

        return app(WorkspaceRoleService::class)->roleFor($group, $user) === BusinessRole::ADMIN;
    }

    /** The business workspace this user may manage recordings for: the active one first, then one they own, then any they administer. */
    public static function groupFor(User $user): ?WalletGroup
    {
        $candidates = collect([Workspace::active()])->filter()
            ->concat($user->ownedWalletGroups()->where('kind', WalletGroup::KIND_BUSINESS)->get())
            ->concat(Workspace::switchableFor($user));

        return $candidates->first(fn (WalletGroup $g) => $g->isBusiness() && self::can($g, $user));
    }
}
