<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N polls (MASTER-ONLY): a poll is a message of type `poll`; options and votes live here. Votes are visible to the two members of the chat only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_polls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->unique()->constrained('n2n_messages')->cascadeOnDelete();
            $t->string('question', 200);
            $t->boolean('multiple')->default(false);
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('n2n_poll_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained('n2n_polls')->cascadeOnDelete();
            $t->unsignedTinyInteger('position');
            $t->string('text', 100);
        });
        Schema::create('n2n_poll_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained('n2n_polls')->cascadeOnDelete();
            $t->foreignId('option_id')->constrained('n2n_poll_options')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['option_id', 'user_id']);
            $t->index(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_poll_votes');
        Schema::dropIfExists('n2n_poll_options');
        Schema::dropIfExists('n2n_polls');
    }
};
