<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N scheduled messages (MASTER-ONLY): text queued for later delivery in an existing conversation. Nothing is charged and nothing is visible to the
 * other person until the due time, when it is sent through the ordinary MessageService (so blocks, privacy, link filter, rate limits and the credit meter
 * all apply at delivery). `client_id` makes the queueing idempotent; the delivery id is derived from the row, so a crashed worker can never double-send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_scheduled_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('conversation_id')->constrained('n2n_conversations')->cascadeOnDelete();
            $t->string('client_id', 64);
            $t->text('body');
            $t->unsignedBigInteger('reply_to_id')->nullable();
            $t->timestamp('send_at');
            $t->string('status', 12)->default('pending');          // pending | sending | sent | failed | cancelled
            $t->string('fail_reason', 20)->nullable();             // credit | undelivered | error
            $t->unsignedBigInteger('message_id')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('claimed_at')->nullable();
            $t->timestamps();
            $t->unique(['sender_id', 'client_id']);
            $t->index(['status', 'send_at']);
            $t->index(['sender_id', 'conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_scheduled_messages');
    }
};
