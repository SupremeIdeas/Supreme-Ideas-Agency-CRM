<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Planner (B13, N2N, MASTER-ONLY): an event is planned inside a 1:1 chat and appears there as an event card (a message of type `event`, so cost, blocks,
 * disappearing and moderation all apply as for any message). Times are stored in UTC with the creator's IANA timezone for display. `n2n_event_rsvps` has one row
 * per member; `reminded_at` is the exactly-once marker for the reminder (set by compare-and-set before anything is sent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('n2n_conversations')->cascadeOnDelete();
            $t->foreignId('message_id')->nullable()->unique()->constrained('n2n_messages')->cascadeOnDelete();
            $t->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 80);
            $t->string('description', 500)->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('timezone', 64)->default('UTC');
            $t->unsignedSmallInteger('reminder_minutes')->default(15);
            $t->unsignedSmallInteger('sequence')->default(0);        // bumped on every edit so calendar apps replace the old copy
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['starts_at', 'cancelled_at']);
            $t->index(['conversation_id', 'starts_at']);
            $t->index(['creator_id']);
        });
        Schema::create('n2n_event_rsvps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('n2n_events')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('status', 12)->default('no_response');          // going | maybe | declined | no_response
            $t->timestamp('responded_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
            $t->index(['user_id', 'reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_event_rsvps');
        Schema::dropIfExists('n2n_events');
    }
};
