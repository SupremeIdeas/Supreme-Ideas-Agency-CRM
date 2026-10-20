<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content/billing/hot-menu blueprint Phase F2 — the Basic-to-Extended
 * balance/installment upgrade path. Distinct from the existing immediate
 * payBalanceAndUpgrade(): a merchant proposes a timeframe
 * (upgrade_proposed_days, informational only), an admin sets the actual
 * ENFORCED deadline (upgrade_deadline_at — the merchant's proposal is never
 * unilaterally binding), and a scheduled command attempts the charge once
 * it's due.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->foreignId('upgrade_target_plan_id')->nullable()->after('license_plan_id')
                ->constrained('white_label_license_plans')->nullOnDelete();
            $table->decimal('upgrade_balance_due', 10, 2)->nullable()->after('upgrade_target_plan_id');
            $table->unsignedSmallInteger('upgrade_proposed_days')->nullable()->after('upgrade_balance_due');
            $table->timestamp('upgrade_deadline_at')->nullable()->after('upgrade_proposed_days');
            $table->timestamp('upgrade_notice_sent_at')->nullable()->after('upgrade_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgrade_target_plan_id');
            $table->dropColumn(['upgrade_balance_due', 'upgrade_proposed_days', 'upgrade_deadline_at', 'upgrade_notice_sent_at']);
        });
    }
};
