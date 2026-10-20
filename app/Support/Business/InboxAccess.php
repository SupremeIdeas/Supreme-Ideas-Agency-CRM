<?php

namespace App\Support\Business;

use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\Permissions;
use App\Support\Workspace;

/** Which business workspace's shared inbox a person may use right now (MASTER-ONLY): the active one, when the inbox module is live and they hold the permission. */
class InboxAccess
{
    public static function workspaceFor(?User $user): ?WalletGroup
    {
        if (! $user || ! BusinessSettings::module('inbox')) {
            return null;
        }
        $ws = Workspace::active();
        if (! $ws || ! $ws->isBusiness()) {
            return null;
        }
        // The owner's plan must include the inbox. A lapsed plan hides it (the conversations are kept) and brings it back on renewal.
        if (! app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($ws, 'inbox')) {
            return null;
        }

        return $ws->owner_user_id === $user->id || app(WorkspaceRoleService::class)->hasPermission($ws, $user, Permissions::USE_WORKSPACE) ? $ws : null;
    }
}
