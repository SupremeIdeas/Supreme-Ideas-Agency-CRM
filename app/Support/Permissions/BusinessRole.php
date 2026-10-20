<?php

namespace App\Support\Permissions;

/**
 * Business Suite Prompt 13 §2. The four business-workspace roles, named
 * exactly as the prompt specifies ('owner', 'admin', 'agent', 'viewer') for
 * display and for `wallet_group_members.role` — but stored in Spatie's
 * `roles` table under a `business_` prefixed name, because this app already
 * has a global staff role literally named `admin` (see RoleSeeder). Without
 * the prefix, `Role::findByName('admin')` would match both the staff role
 * (team_id = 0) and this one (team_id = null) once teams are enabled —
 * confirmed against Role::findByParam(), which always applies
 * `whereNull(team_id) OR team_id = $ambient` regardless of which row a
 * caller intended. The prefix makes the two namespaces unambiguous.
 */
final class BusinessRole
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const AGENT = 'agent';

    public const VIEWER = 'viewer';

    public const GUARD = 'web';

    public static function all(): array
    {
        return [self::OWNER, self::ADMIN, self::AGENT, self::VIEWER];
    }

    /** The name this role is actually stored/checked under in Spatie's tables. */
    public static function spatieName(string $shortName): string
    {
        return "business_{$shortName}";
    }

    /** Permissions granted to each role — Prompt 13 §2.2's own descriptions. */
    public static function permissionsFor(string $shortName): array
    {
        return match ($shortName) {
            self::OWNER => [
                Permissions::MANAGE_GROUP,
                Permissions::MANAGE_SEATS,
                Permissions::MANAGE_NUMBERS,
                Permissions::USE_WORKSPACE,
                Permissions::VIEW_REPORTS,
                Permissions::SPEND,
            ],
            self::ADMIN => [
                Permissions::MANAGE_SEATS,
                Permissions::MANAGE_NUMBERS,
                Permissions::USE_WORKSPACE,
                Permissions::VIEW_REPORTS,
                Permissions::SPEND,
            ],
            self::AGENT => [
                Permissions::USE_WORKSPACE,
                Permissions::VIEW_REPORTS,
                Permissions::SPEND,
            ],
            self::VIEWER => [
                Permissions::VIEW_REPORTS,
            ],
            default => throw new \InvalidArgumentException("Unknown business role [{$shortName}]."),
        };
    }
}
