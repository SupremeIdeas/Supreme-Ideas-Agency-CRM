<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admin-editable notices shown at the top of Naara chat (N2N, MASTER-ONLY), with per-member seen/dismissed tracking. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_chat_notices', function (Blueprint $t) {
            $t->id();
            $t->string('title', 80);
            $t->string('body', 280);
            $t->string('tone', 12)->default('info');      // info | warn
            $t->boolean('active')->default(true);
            $t->boolean('dismissible')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['active', 'starts_at', 'ends_at']);
        });
        Schema::create('n2n_chat_notice_seen', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notice_id')->constrained('n2n_chat_notices')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('seen_at')->nullable();
            $t->timestamp('dismissed_at')->nullable();
            $t->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_chat_notice_seen');
        Schema::dropIfExists('n2n_chat_notices');
    }
};
