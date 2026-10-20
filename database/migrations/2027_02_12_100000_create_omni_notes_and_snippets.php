<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Respond team operations (MASTER-ONLY, blueprint section 8): private internal notes on a conversation and the workspace's saved replies. Idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('omni_notes')) {
            Schema::create('omni_notes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('conversation_id');
                $t->unsignedBigInteger('author_id')->nullable();
                $t->string('body', 2000);
                $t->timestamps();
                $t->index(['workspace_id', 'conversation_id'], 'omni_notes_ws_conv_idx');
            });
        }
        if (! Schema::hasTable('omni_snippets')) {
            Schema::create('omni_snippets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('title', 80);
                $t->string('body', 2000);
                $t->timestamps();
                $t->index(['workspace_id', 'title'], 'omni_snippets_ws_title_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omni_snippets');
        Schema::dropIfExists('omni_notes');
    }
};
