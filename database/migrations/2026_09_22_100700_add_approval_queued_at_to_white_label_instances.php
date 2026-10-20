<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oversight/Withdrawal blueprint Phase E3 — marks the moment an admin's
 * approval was handed to ProcessWhiteLabelApprovalJob, so the "system-
 * processed approvals" review window can list every instance that went
 * through the async charge+activate path (successful or not), separate
 * from instances issued directly (issueNewLicense/approveInstance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->timestamp('approval_queued_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn('approval_queued_at');
        });
    }
};
