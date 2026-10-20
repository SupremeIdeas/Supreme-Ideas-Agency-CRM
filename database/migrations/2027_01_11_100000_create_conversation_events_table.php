<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The unified conversation timeline (Brand-Growth Phase 9, MASTER-ONLY): one row per person per message or call, whatever channel it travelled on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('peer_key', 64);                // Naara ID or E.164: the other side, in one stable spelling
            $t->string('channel', 16);                 // n2n | sms | call
            $t->string('kind', 16)->default('message'); // message | call
            $t->string('direction', 4);                // in | out
            $t->string('status', 24)->default('sent');
            $t->string('summary', 240)->nullable();
            $t->string('source_type', 16);             // n2n_message | outbound_sms | inbound_sms | voice_call
            $t->unsignedBigInteger('source_id');
            $t->timestamp('occurred_at')->index();
            $t->timestamps();
            $t->unique(['user_id', 'source_type', 'source_id']);   // exactly one event per person per source row: replays and the reconcile sweep are idempotent
            $t->index(['user_id', 'peer_key', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_events');
    }
};
