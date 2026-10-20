<?php

namespace App\Support\Permissions;

use Spatie\Permission\DefaultTeamResolver;

/**
 * Business Suite Prompt 13 §2. Every permission check that predates the
 * Business Suite (super_admin/admin/staff/user/merchant, StaffService, all
 * of it) runs with no team context set — it never calls
 * setPermissionsTeamId(). Spatie's own DefaultTeamResolver would leave the
 * ambient team id as `null`, but `model_has_roles`/`model_has_permissions`
 * can't carry `null` on a composite-primary-key column (MySQL forces PK
 * columns NOT NULL), so this app's retrofit migration
 * (2026_09_22_110003_add_teams_to_permission_tables) stamps every
 * pre-existing pivot row with the sentinel `team_id = 0` instead — a value
 * no real `wallet_groups.id` can ever have, since auto-increment starts at
 * 1. Defaulting the ambient team id to `0` here (instead of Spatie's `null`)
 * keeps every one of those pre-existing checks matching those stamped rows,
 * with no code changes anywhere outside the Business Suite. Only code that
 * explicitly calls setPermissionsTeamId($walletGroup) — the business
 * workspace's own role checks — ever sees a non-zero team id.
 */
class SentinelTeamResolver extends DefaultTeamResolver
{
    protected int|string|null $teamId = 0;
}
