<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Suite Prompt 13 §1: generalizes Prompt 11 §3's WalletGroup into
     * the Business Suite's "Organization" concept rather than duplicating it
     * with a new table. `kind` defaults to `family` so every row Prompt 11
     * already created stays valid with zero data migration.
     */
    public function up(): void
    {
        Schema::table('wallet_groups', function (Blueprint $table) {
            $table->enum('kind', ['family', 'business'])->default('family')->after('name');
            $table->string('business_email')->nullable()->after('kind');
            $table->timestamp('business_email_verified_at')->nullable()->after('business_email');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_groups', function (Blueprint $table) {
            $table->dropColumn(['kind', 'business_email', 'business_email_verified_at']);
        });
    }
};
