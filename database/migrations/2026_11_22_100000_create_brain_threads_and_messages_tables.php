<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NaaraBrain Sprint 1 small schema, first half (MASTER-ONLY): conversations and messages. Runs, automations and approvals arrive in the sprint that needs them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brain_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 120)->nullable();
            $t->string('depth', 16)->default('balanced');
            $t->string('length', 16)->default('normal');   // concise | normal | detailed
            $t->boolean('pinned')->default(false);
            $t->timestamp('archived_at')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'archived_at', 'last_message_at']);
        });

        Schema::create('brain_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained('brain_threads')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 12);                          // user | assistant
            $t->longText('body');
            $t->string('status', 12)->default('done');       // pending | done | error
            $t->string('model', 80)->nullable();
            $t->timestamps();
            $t->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brain_messages');
        Schema::dropIfExists('brain_threads');
    }
};
