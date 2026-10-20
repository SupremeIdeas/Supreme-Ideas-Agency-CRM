<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 12, Tier 1 (MASTER-ONLY): per-member conversation power features. Everything here belongs to ONE member's view of a chat and is
 * never visible to the other person: stars, the pinned message, "delete for me" on media, and (on the existing participant row) mute,
 * archive, pin and theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_participants', function (Blueprint $t) {
            $t->unsignedBigInteger('pinned_message_id')->nullable()->after('pinned_at');   // this member's pinned message in the chat
        });

        Schema::create('n2n_stars', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('message_id')->constrained('n2n_messages')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'message_id']);
        });

        Schema::create('n2n_attachment_hides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('attachment_id')->constrained('n2n_attachments')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_attachment_hides');
        Schema::dropIfExists('n2n_stars');
        Schema::table('n2n_participants', fn (Blueprint $t) => $t->dropColumn('pinned_message_id'));
    }
};
