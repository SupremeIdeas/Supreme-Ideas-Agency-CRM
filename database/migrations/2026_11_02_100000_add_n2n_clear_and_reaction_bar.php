<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N private history controls and the personal reaction bar (MASTER-ONLY). "Clear" / "Delete chat" / "Delete for me" are per-member VIEW filters:
 * they never touch what the other person sees and never remove stored rows (moderation, legal hold and erasure keep working on the real data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_participants', function (Blueprint $t) {
            $t->unsignedBigInteger('cleared_before_id')->default(0);   // this member sees only messages with a greater id
            $t->timestamp('cleared_at')->nullable();
            $t->timestamp('removed_at')->nullable();                   // "Delete chat": hidden from the list until a newer message arrives
        });
        Schema::create('n2n_message_hides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('message_id')->constrained('n2n_messages')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'message_id']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->json('n2n_reaction_bar')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('n2n_reaction_bar'));
        Schema::dropIfExists('n2n_message_hides');
        Schema::table('n2n_participants', fn (Blueprint $t) => $t->dropColumn(['cleared_before_id', 'cleared_at', 'removed_at']));
    }
};
