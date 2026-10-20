<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A manual payment can now be for the licence itself or for the balance to Extended (Funnel Phase 7, MASTER-ONLY). Every existing row stays a licence payment. */
return new class extends Migration
{
    // MySQL limits identifiers to 64 characters; the auto-generated name for this index is 65, so it is named explicitly. The migration is also safe to
    // re-run after a failed attempt (MySQL DDL is not transactional: the columns may already exist when the index step failed).
    private const INDEX = 'wlfmp_instance_purpose_status_idx';

    public function up(): void
    {
        Schema::table('wl_funnel_manual_payments', function (Blueprint $t) {
            if (! Schema::hasColumn('wl_funnel_manual_payments', 'purpose')) {
                $t->string('purpose', 10)->default('license')->after('method');
            }
            if (! Schema::hasColumn('wl_funnel_manual_payments', 'wl_invoice_id')) {
                $t->unsignedBigInteger('wl_invoice_id')->nullable()->after('purpose');
            }
        });
        if (! collect(Schema::getIndexes('wl_funnel_manual_payments'))->contains(fn ($i) => $i['name'] === self::INDEX)) {
            Schema::table('wl_funnel_manual_payments', function (Blueprint $t) {
                $t->index(['funnel_instance_id', 'purpose', 'status'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::table('wl_funnel_manual_payments', function (Blueprint $t) {
            $t->dropIndex(self::INDEX);
            $t->dropColumn(['purpose', 'wl_invoice_id']);
        });
    }
};
