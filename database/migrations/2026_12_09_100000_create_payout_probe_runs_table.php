<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Evidence of every payout gateway probe (Engineering v3, Phase 2.3). Never stores secrets, balances or personal data. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_probe_runs')) {
            return;
        }
        Schema::create('payout_probe_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->index();
            $table->string('tier', 16);               // auth | capability
            $table->string('status', 12);             // ok | warn | fail | skipped
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('summary', 255);
            $table->json('checks')->nullable();       // [{label, ok}]
            $table->json('mismatches')->nullable();   // registry vs provider disagreements
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['provider', 'tier', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_probe_runs');
    }
};
