<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N moderation (MASTER-ONLY): who reviewed a report and what they did, and a per-member chat restriction staff can set and lift. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_reports', function (Blueprint $t) {
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('staff_note', 500)->nullable();
            $t->string('action', 24)->nullable();                   // dismissed | warned | restricted_24h | restricted_7d | restricted_permanent
        });
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('n2n_restricted_until')->nullable();      // can read, react and receive; cannot send messages or place calls until then
            $t->string('n2n_restricted_reason', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['n2n_restricted_until', 'n2n_restricted_reason']));
        Schema::table('n2n_reports', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['reviewed_at', 'staff_note', 'action']);
        });
    }
};
