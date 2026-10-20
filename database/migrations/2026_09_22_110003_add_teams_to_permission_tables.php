<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Suite Prompt 13 §2: retrofits spatie/laravel-permission's
     * team-scoped roles onto an app that has run with `teams => false` since
     * its first migration, without touching any of the existing ambient
     * staff/admin permission checks (super_admin/admin/staff/user/merchant),
     * which run with no team context at all today.
     *
     * Spatie's own official retrofit stub (add_teams_fields.php.stub) adds
     * `team_id` to `model_has_roles`/`model_has_permissions` with
     * `->default('1')`, because MySQL forces every composite PRIMARY KEY
     * column to be NOT NULL — a plain nullable column matching Spatie's own
     * ambient default of `null` is structurally impossible there. Defaulting
     * to `1` risks colliding with a real `wallet_groups.id` and silently
     * misattributing ambient checks to whatever business workspace happens
     * to own id 1. Instead every ambient/pre-existing pivot row here is
     * stamped with the sentinel `team_id = 0` — an id no `wallet_groups` row
     * can ever have (auto-increment starts at 1) — paired with
     * App\Support\Permissions\SentinelTeamResolver, whose default ambient
     * team id is `0` instead of Spatie's built-in `null`. Every existing
     * permission check therefore keeps matching team_id = 0 exactly as
     * before this migration, with zero code changes anywhere outside the
     * Business Suite.
     *
     * `roles.team_id` is only part of a UNIQUE index (never a primary key),
     * so it stays genuinely nullable: the 5 pre-existing global roles get
     * the same `0` sentinel (matching the ambient default), while the 4 new
     * business roles (seeded separately, see BusinessRoleSeeder) get a true
     * `NULL` team_id — confirmed against HasRoles::roles(), which matches a
     * role via `whereNull($teamField)->orWhere($teamField, $ambientTeamId)`,
     * so one NULL-team role definition is correctly reusable across every
     * distinct wallet_group_id via the team-scoped PIVOT row alone.
     *
     * Defensive `hasColumn` guards throughout: the base permission-tables
     * migration (2026_07_12_141333) branches its own DDL on
     * `config('permission.teams')` *at the moment it runs* — on a real prod
     * database it ran months ago while that flag was still false, so this
     * migration is the one adding `team_id` there. But on a brand-new
     * database built by running every migration from scratch after this
     * flag flipped to true (e.g. the sqlite `:memory:` test database
     * RefreshDatabase builds for every test), the base migration itself
     * already creates `team_id` — on those tables this migration then has
     * nothing structural left to do beyond the sentinel backfill.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
            });

            DB::table('roles')->update(['team_id' => 0]);

            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique(['team_id', 'name', 'guard_name']);
                $table->index('team_id', 'roles_team_foreign_key_index');
            });
        } else {
            DB::table('roles')->whereNull('team_id')->update(['team_id' => 0]);
        }

        $this->addTeamToPivot('model_has_permissions', 'permission_id', 'permissions', 'model_has_permissions_permission_model_type_primary');
        $this->addTeamToPivot('model_has_roles', 'role_id', 'roles', 'model_has_roles_role_model_type_primary');

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Add `team_id` to one of Spatie's pivot tables and fold it into the
     * composite primary key. On MySQL the PRIMARY KEY is the only index
     * backing the `{role,permission}_id` foreign key, so `DROP PRIMARY KEY`
     * is refused (errno 150) while that FK exists — Spatie's own stub drops
     * and re-creates it around the key change, so we do the same. (sqlite,
     * which the test suite runs on, doesn't enforce this, which is how the
     * original version of this migration passed every test and still failed
     * on a real MySQL database.) Three separate statements-groups so each
     * DDL step is independently re-runnable.
     */
    private function addTeamToPivot(string $table, string $fkColumn, string $referenced, string $primaryName): void
    {
        if (Schema::hasColumn($table, 'team_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($fkColumn) {
            $t->dropForeign([$fkColumn]);
        });

        Schema::table($table, function (Blueprint $t) use ($table, $fkColumn, $primaryName) {
            $t->dropPrimary($primaryName);
            $t->unsignedBigInteger('team_id')->default(0)->after($fkColumn);
            $t->primary(['team_id', $fkColumn, 'model_id', 'model_type'], $primaryName);
            $t->index('team_id', $table.'_team_foreign_key_index');
        });

        Schema::table($table, function (Blueprint $t) use ($fkColumn, $referenced) {
            $t->foreign($fkColumn)->references('id')->on($referenced)->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->removeTeamFromPivot('model_has_roles', 'role_id', 'roles', 'model_has_roles_role_model_type_primary');
        $this->removeTeamFromPivot('model_has_permissions', 'permission_id', 'permissions', 'model_has_permissions_permission_model_type_primary');

        if (Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('roles_team_foreign_key_index');
                $table->dropUnique(['team_id', 'name', 'guard_name']);
                $table->unique(['name', 'guard_name']);
                $table->dropColumn('team_id');
            });
        }
    }

    private function removeTeamFromPivot(string $table, string $fkColumn, string $referenced, string $primaryName): void
    {
        if (! Schema::hasColumn($table, 'team_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($fkColumn) {
            $t->dropForeign([$fkColumn]);
        });

        Schema::table($table, function (Blueprint $t) use ($table, $fkColumn, $primaryName) {
            $t->dropIndex($table.'_team_foreign_key_index');
            $t->dropPrimary($primaryName);
            $t->dropColumn('team_id');
            $t->primary([$fkColumn, 'model_id', 'model_type'], $primaryName);
        });

        Schema::table($table, function (Blueprint $t) use ($fkColumn, $referenced) {
            $t->foreign($fkColumn)->references('id')->on($referenced)->onDelete('cascade');
        });
    }
};
