<?php

namespace App\Support\Permissions;

/**
 * Business Suite permission strings, scoped per wallet_group_id via
 * team-scoped spatie/laravel-permission (see WorkspaceRoleService). Prompts
 * 14-19 route every business-suite authorization check through these same
 * four — they never invent a second mechanism (Prompt 13 §2.4).
 */
final class Permissions
{
    public const MANAGE_GROUP = 'business.manage_group';

    public const MANAGE_SEATS = 'business.manage_seats';

    public const MANAGE_NUMBERS = 'business.manage_numbers';

    public const USE_WORKSPACE = 'business.use_workspace';

    public const VIEW_REPORTS = 'business.view_reports';

    public const SPEND = 'business.spend';

    public static function all(): array
    {
        return [
            self::MANAGE_GROUP,
            self::MANAGE_SEATS,
            self::MANAGE_NUMBERS,
            self::USE_WORKSPACE,
            self::VIEW_REPORTS,
            self::SPEND,
        ];
    }
}
