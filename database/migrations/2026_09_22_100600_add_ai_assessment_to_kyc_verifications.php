<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oversight/Withdrawal blueprint Phase E2 — Claude's advisory cross-check on
 * an escalated (pending, manual-review) KYC submission. Purely additive
 * metadata for the human reviewer; approve/reject stay exactly as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->json('ai_assessment')->nullable()->after('checks');
            $table->timestamp('ai_assessed_at')->nullable()->after('ai_assessment');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->dropColumn(['ai_assessment', 'ai_assessed_at']);
        });
    }
};
