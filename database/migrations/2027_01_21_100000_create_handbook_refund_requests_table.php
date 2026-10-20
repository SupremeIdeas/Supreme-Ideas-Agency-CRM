<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Travel Handbook Store (MASTER-ONLY): a buyer's refund request. Never automatic: a person on the team approves or declines it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handbook_refund_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('entitlement_id')->nullable();
            $t->string('source', 16);                                  // purchase | upgrade: what the money bought
            $t->decimal('amount_usd', 12, 2);                          // the price paid, snapshotted when asked
            $t->text('reason')->nullable();
            $t->string('status', 12)->default('pending');              // pending | approved | declined
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->index(['user_id', 'handbook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handbook_refund_requests');
    }
};
