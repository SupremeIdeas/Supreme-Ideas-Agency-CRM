<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Planner, Phase C (N2N, MASTER-ONLY): a tiny private follow-up list ("remind me to call John"). Not a Tasks app: one line, an optional due time, a star, a done tick,
 * tied to a person the member has a chat with. Strictly private: the other person never sees it, is never told, and it is erased with the member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('about_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('conversation_id')->nullable()->constrained('n2n_conversations')->nullOnDelete();
            $t->string('title', 120);
            $t->timestamp('due_at')->nullable();
            $t->boolean('starred')->default(false);
            $t->timestamp('done_at')->nullable();
            $t->timestamp('reminded_at')->nullable();      // exactly-once marker, claimed before anything is sent
            $t->timestamps();
            $t->index(['user_id', 'done_at', 'due_at']);
            $t->index(['due_at', 'reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_followups');
    }
};
