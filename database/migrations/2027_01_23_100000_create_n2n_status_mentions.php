<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Chat status mentions (MASTER-ONLY): who a status post tagged. Rows go with the post (24 hours) or with either member's erasure. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_status_mentions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('status_id')->constrained('n2n_status_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['status_id', 'user_id']);
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_status_mentions');
    }
};
