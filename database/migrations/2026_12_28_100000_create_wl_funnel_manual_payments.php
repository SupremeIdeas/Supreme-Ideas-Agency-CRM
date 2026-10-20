<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** White Label funnel manual payments (Funnel blueprint 5.1, MASTER-ONLY): one row per payment attempt, with the locked quote, the proof and the review outcome. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl_funnel_manual_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funnel_instance_id')->constrained('wl_funnel_instances')->cascadeOnDelete();
            $t->foreignId('account_id')->nullable()->constrained('manual_payment_accounts')->nullOnDelete();
            $t->string('plan_key', 24);
            $t->string('currency', 8);
            $t->string('network', 24)->nullable();
            $t->decimal('price_usd', 12, 2);
            $t->decimal('expected_amount', 18, 2);
            $t->decimal('rate', 18, 8);
            $t->string('rate_source', 16);
            $t->timestamp('rate_as_of');
            $t->timestamp('locked_until');
            $t->string('reference_code', 40)->unique();
            $t->string('status', 20)->default('awaiting_transfer')->index();
            $t->string('payer_name', 120)->nullable();
            $t->string('payer_bank', 120)->nullable();
            $t->timestamp('paid_at_claimed')->nullable();
            $t->string('tx_hash', 120)->nullable()->unique();
            $t->json('proof')->nullable();                 // [{disk,key,sha256,mime,bytes}]
            $t->json('flags')->nullable();                 // automatic checks: duplicate_image, duplicate_tx, late, ...
            $t->unsignedSmallInteger('proof_attempts')->default(0);
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('reject_reason', 300)->nullable();
            $t->decimal('approved_amount', 18, 2)->nullable();
            $t->foreignId('white_label_instance_id')->nullable()->constrained('white_label_instances')->nullOnDelete();
            $t->timestamp('issued_at')->nullable();
            $t->timestamps();
            $t->index(['funnel_instance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl_funnel_manual_payments');
    }
};
