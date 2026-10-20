<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One NaaraBrain inbox-assistant action in a business workspace (MASTER-ONLY): what was asked, the suggestion, and what it cost, so allowance and margin are exact. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_brain_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();            // who pressed the button
            $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();      // whose plan pays for it (the workspace owner)
            $t->string('channel', 16);
            $t->string('counterpart', 191);
            $t->string('action', 16);                                                     // draft | summary | tasks
            $t->string('status', 12)->default('pending');                                 // pending | done | error
            $t->text('output')->nullable();
            $t->string('model', 80)->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('cost_usd', 12, 6)->default(0);
            $t->timestamps();
            $t->index(['owner_user_id', 'created_at']);
            $t->index(['wallet_group_id', 'channel', 'counterpart']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_brain_runs');
    }
};
