<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Chat status extras (MASTER-ONLY): one reaction per viewer per post, and the people an owner hides their status from. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_status_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('status_id')->constrained('n2n_status_posts')->cascadeOnDelete();
            $t->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $t->string('emoji', 16);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['status_id', 'viewer_id']);
        });
        Schema::create('n2n_status_hidden', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $t->unique(['owner_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_status_hidden');
        Schema::dropIfExists('n2n_status_reactions');
    }
};
