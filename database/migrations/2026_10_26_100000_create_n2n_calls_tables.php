<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N Batch 10 (MASTER-ONLY): 1:1 voice calls. The SERVER clock (answered_at, ended_at) is the only timing authority. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $t->string('state', 12)->default('ringing');               // ringing active ended missed declined busy failed
            $t->boolean('suppressed')->default(false);                 // blocked / not allowed / do-not-disturb: rings for the caller, reaches nobody
            $t->string('end_reason', 24)->nullable();                  // hangup declined no_answer cancelled credit peer_lost busy failed
            $t->unsignedInteger('rate_cents')->default(0);             // per-minute rate frozen at call start
            $t->unsignedSmallInteger('increment_s')->default(60);      // billing increment frozen at call start
            $t->unsignedSmallInteger('hold_count')->default(0);        // rolling holds taken: refs call:{id}:h0..h{n-1}
            $t->unsignedInteger('hold_cents_each')->default(0);
            $t->timestamp('ring_expires_at')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->timestamp('ends_at')->nullable();                      // when the credit cover runs out (set only if an extension failed)
            $t->timestamp('ended_at')->nullable();
            $t->timestamp('caller_beat_at')->nullable();
            $t->timestamp('callee_beat_at')->nullable();
            $t->unsignedInteger('billed_seconds')->default(0);
            $t->unsignedInteger('billed_cents')->default(0);
            $t->boolean('relay_used')->nullable();                     // reported by the client at the end: measured relay share
            $t->timestamps();
            $t->index(['caller_id', 'state']);
            $t->index(['callee_id', 'state']);
            $t->index(['state', 'ring_expires_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('n2n_who_can_call', 12)->default('connections');   // connections | everyone | nobody (Pro brands may always be called by default)
            $t->timestamp('n2n_dnd_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['n2n_who_can_call', 'n2n_dnd_until']));
        Schema::dropIfExists('n2n_calls');
    }
};
