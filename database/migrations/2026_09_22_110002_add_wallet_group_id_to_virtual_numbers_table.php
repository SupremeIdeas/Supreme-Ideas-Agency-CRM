<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Suite Prompt 13 §1.3. Nullable so every existing row and every
     * individual-consumer purchase is unaffected — NULL means "owned by
     * user_id directly," exactly as today. Other resource tables (call
     * flows, inbox) get this same column only in the prompt that actually
     * uses it (14, 16) — not added speculatively here.
     */
    public function up(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->foreignId('wallet_group_id')->nullable()->after('user_id')
                ->constrained('wallet_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_group_id');
        });
    }
};
