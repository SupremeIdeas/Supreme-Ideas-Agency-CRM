<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Pro verified badge. A brand is "Naara Pro" while it holds an ACTIVE paid listing subscription (derived, never stored — so a cancelled or
 * lapsed subscription can't leave a stale badge) OR while an admin has granted it complimentary Pro (these columns). A grant lets the user list
 * their brand without paying and earns the same badge; revoking it removes the badge again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $table) {
            $table->timestamp('pro_granted_at')->nullable()->after('current_plan_id');
            $table->foreignId('pro_granted_by')->nullable()->after('pro_granted_at')->constrained('users')->nullOnDelete();
            $table->string('pro_grant_note', 191)->nullable()->after('pro_granted_by');
            $table->index('pro_granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $table) {
            $table->dropIndex(['pro_granted_at']);
            $table->dropConstrainedForeignId('pro_granted_by');
            $table->dropColumn(['pro_granted_at', 'pro_grant_note']);
        });
    }
};
