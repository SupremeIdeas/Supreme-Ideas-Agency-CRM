<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oversight/Withdrawal blueprint Phase C2 — a tamper-evident record of the
 * domain a license was FIRST activated against. `activation_domain` is
 * plain text for admin readability; `activation_domain_hash` is the
 * fingerprint set once at first activation and never overwritten, so a
 * later activation from a different domain can be detected by comparing
 * against it rather than trusting whatever the fork most recently reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->string('activation_domain')->nullable()->after('current_platform_version');
            $table->string('activation_domain_hash')->nullable()->after('activation_domain');
            $table->timestamp('activation_domain_mismatch_at')->nullable()->after('activation_domain_hash');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn(['activation_domain', 'activation_domain_hash', 'activation_domain_mismatch_at']);
        });
    }
};
