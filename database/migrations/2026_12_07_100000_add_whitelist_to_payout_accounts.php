<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crypto payout destinations must be whitelisted in the provider dashboard (NOWPayments only pays whitelisted
 * wallet addresses). We track where each address is in that hand-off: pending -> exported (CSV given to admin)
 * -> whitelisted (admin confirms it is live in the dashboard). NULL = not a whitelist-gated destination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->string('whitelist_status', 16)->nullable()->after('provider_status')->index();
            $table->timestamp('whitelist_exported_at')->nullable()->after('whitelist_status');
            $table->timestamp('whitelisted_at')->nullable()->after('whitelist_exported_at');
        });
    }

    public function down(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['whitelist_status', 'whitelist_exported_at', 'whitelisted_at']);
        });
    }
};
