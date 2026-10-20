<?php

namespace App\Support;

use App\Models\User;
use App\Models\WalletGroup;

/**
 * Business Suite Prompt 13 §4 — the workspace switcher. Session-scoped only:
 * switching never re-authenticates, never touches any other session/device
 * (§4.2), and every write action while a business group is active still
 * resolves through WorkspaceRoleService + WalletGroupService before ever
 * touching WalletService (§4.4) — this class only answers "which group, if
 * any, is active for this browser session right now."
 */
class Workspace
{
    private const SESSION_KEY = 'active_wallet_group_id';

    /** The active WalletGroup for this session, or null (= Personal). */
    public static function active(): ?WalletGroup
    {
        $id = session(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $group = WalletGroup::find($id);
        if (! $group || ! self::belongsTo($user, $group)) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        return $group;
    }

    public static function isBusinessActive(): bool
    {
        return (bool) self::active()?->isBusiness();
    }

    /** Every WalletGroup this user can switch into: owned + accepted memberships. */
    public static function switchableFor(User $user): \Illuminate\Support\Collection
    {
        $owned = $user->ownedWalletGroups()->get();
        $memberOf = $user->walletGroupMemberships()
            ->whereNotNull('accepted_at')
            ->with('walletGroup')
            ->get()
            ->pluck('walletGroup')
            ->filter();

        return $owned->concat($memberOf)->unique('id')->values();
    }

    public static function belongsTo(User $user, WalletGroup $group): bool
    {
        if ($group->owner_user_id === $user->id) {
            return true;
        }

        return $group->members()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /** Set the active group for this session only. Null = switch to Personal. */
    public static function setActive(?WalletGroup $group): void
    {
        if ($group === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $group->id]);
    }
}
