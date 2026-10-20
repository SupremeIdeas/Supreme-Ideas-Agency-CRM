<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Suite Prompt 13 §1.2 + §5.2. `role`/`invited_by_user_id`/
     * `status` are exactly what the prompt specifies (`status` defaults to
     * `active` so every family-plan member Prompt 11 already created stays
     * valid). `spend_cap_usd`/`spend_cap_ngn` already exist and are reused
     * as-is for seat caps — nothing duplicated here.
     *
     * `user_id` nullable + `invited_email` are NOT in the prompt's own §1
     * list, but §5.2 requires inviting an email with no existing NaaraSim
     * account yet ("the invite leads to the same Fortify registration
     * flow... after registering, they're attached to the pending
     * WalletGroupMember row") — the schema as specified in §1 alone (user_id
     * NOT NULL) cannot represent that pending state, so this closes that one
     * gap the later section depends on.
     */
    public function up(): void
    {
        Schema::table('wallet_group_members', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('invited_email')->nullable()->after('user_id');
            $table->string('role')->nullable()->after('invited_email');
            $table->foreignId('invited_by_user_id')->nullable()->after('role')
                ->constrained('users')->nullOnDelete();
            $table->enum('status', ['invited', 'active', 'removed'])->default('active')->after('invited_by_user_id');
        });

        Schema::table('wallet_group_members', function (Blueprint $table) {
            $table->unique(['wallet_group_id', 'invited_email']);
        });

        // The `default('active')` above is only correct for rows that had
        // already been accepted — a pending Prompt-11 invite (accepted_at
        // still null) is not "active," it's "invited." Correct those back
        // rather than let the blanket default misrepresent them.
        DB::table('wallet_group_members')->whereNull('accepted_at')->update(['status' => 'invited']);
    }

    public function down(): void
    {
        Schema::table('wallet_group_members', function (Blueprint $table) {
            $table->dropUnique(['wallet_group_id', 'invited_email']);
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropColumn(['invited_email', 'role', 'status']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
