<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 11 (MASTER-ONLY, VPS-only): group calls through a self-hosted SFU. A room has a host, up to 5 speakers chosen by the host (the host
 * counts as one when speaking) and any number of listeners up to the admin cap. Speaking is billed per appointment interval from the speaker's
 * own credit; listening is free. Times here are the SERVER's: no client ever reports time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_group_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 80);
            $t->string('room', 64)->unique();                       // the SFU room name; random, never reused
            $t->unsignedSmallInteger('capacity');                    // listeners + speakers in the room (admin-capped at creation)
            $t->unsignedTinyInteger('max_speakers')->default(5);
            $t->string('state', 10)->default('open');                // open | closed
            $t->string('end_reason', 30)->nullable();                // closed_by_host | host_left | idle | credit | admin
            $t->timestamp('started_at')->useCurrent();
            $t->timestamp('ended_at')->nullable();
            $t->timestamp('last_active_at')->nullable();             // last time anybody beat: the sweeper closes an empty room
            $t->timestamps();
            $t->index(['state', 'host_id']);
        });

        Schema::create('n2n_call_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_call_id')->constrained('n2n_group_calls')->cascadeOnDelete();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();                  // sha256 of the secret in the URL; the URL token is never stored in the clear
            $t->text('token_enc');                                   // the same token encrypted, so the host can copy the link again
            $t->boolean('requires_approval')->default(false);
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->timestamps();
        });

        Schema::create('n2n_call_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_call_id')->constrained('n2n_group_calls')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10)->default('listener');             // host | speaker | listener
            $t->boolean('speaking')->default(false);                 // appointed to speak right now = the billable state (NOT the mute state)
            $t->timestamp('pending_at')->nullable();                 // waiting for host approval (link requires it)
            $t->timestamp('joined_at')->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamp('banned_at')->nullable();                  // removed by the host: cannot rejoin this call, with this link or any other
            $t->timestamp('last_beat_at')->nullable();
            $t->timestamps();
            $t->unique(['group_call_id', 'user_id']);
            $t->index(['group_call_id', 'left_at']);
        });

        Schema::create('n2n_call_participant_intervals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_call_id')->constrained('n2n_group_calls')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10);                                  // host | speaker (listeners are never an interval)
            $t->timestamp('from_at');
            $t->timestamp('to_at')->nullable();                      // null while open
            $t->timestamp('ends_at')->nullable();                    // set when the next credit slice could not be held: the moment cover runs out
            $t->unsignedSmallInteger('hold_count')->default(0);
            $t->unsignedInteger('hold_cents_each')->default(0);
            $t->unsignedInteger('rate_cents')->default(0);
            $t->unsignedSmallInteger('increment_s')->default(60);
            $t->unsignedInteger('billed_seconds')->default(0);
            $t->unsignedInteger('billed_cents')->default(0);
            $t->string('end_reason', 30)->nullable();                // demoted | left | removed | closed | lost | credit
            $t->timestamps();
            $t->index(['group_call_id', 'user_id', 'to_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_call_participant_intervals');
        Schema::dropIfExists('n2n_call_participants');
        Schema::dropIfExists('n2n_call_links');
        Schema::dropIfExists('n2n_group_calls');
    }
};
