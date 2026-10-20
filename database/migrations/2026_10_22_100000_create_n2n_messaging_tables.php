<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 6 (MASTER-ONLY): user-to-user conversations. SEPARATE from the Naara Line SMS threads (message_threads), which stay as they are.
 * Encryption: message bodies rely on host/disk-level encryption at rest and TLS in transit, NOT an app-layer cast, so abuse reports and
 * the safety review can read an excerpt. The UI therefore says "encrypted in transit and at rest", never "end-to-end".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('type', 12)->default('direct');            // direct now; group later (the model does not block it)
            $t->string('status', 12)->default('active');          // request | active
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('pair_key', 40)->nullable()->unique();     // "min:max" for a direct chat: one conversation per pair
            $t->unsignedBigInteger('last_message_id')->nullable();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('n2n_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('n2n_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('last_read_message_id')->nullable();
            $t->timestamp('muted_until')->nullable();             // B12
            $t->timestamp('archived_at')->nullable();             // B12
            $t->timestamp('pinned_at')->nullable();               // B12
            $t->json('theme_json')->nullable();                   // B12, per-viewer
            $t->timestamps();
            $t->unique(['conversation_id', 'user_id']);
            $t->index('user_id');
        });

        Schema::create('n2n_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('n2n_conversations')->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->string('client_id', 64);                          // idempotency: one message per (sender, client_id)
            $t->string('type', 12)->default('text');              // text | system
            $t->text('body');
            $t->unsignedBigInteger('reply_to_id')->nullable();
            $t->timestamp('deliver_at')->nullable();
            $t->decimal('cost_credits', 14, 2)->default(0);
            $t->string('ledger_ref', 120)->nullable();
            $t->boolean('suppressed')->default(false);            // blocked / not-allowed sender: looks delivered, reaches nobody
            $t->timestamp('deleted_for_all_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['sender_id', 'client_id']);
            $t->index(['conversation_id', 'id']);
        });

        Schema::create('n2n_message_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('n2n_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->unique(['message_id', 'user_id']);
        });

        Schema::create('n2n_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['blocker_id', 'blocked_id']);
        });

        Schema::create('n2n_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('reported_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('conversation_id')->nullable();
            $t->unsignedBigInteger('message_id')->nullable();
            $t->string('reason', 40);                             // spam | abuse | scam | other
            $t->text('note')->nullable();
            $t->json('excerpt')->nullable();                      // the last few messages FROM the reported person, captured at report time
            $t->string('status', 12)->default('open');            // open | reviewed | actioned | dismissed
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('n2n_who_can_message', 12)->default('everyone');   // everyone | connections | nobody
            $t->boolean('n2n_read_receipts')->default(true);
            $t->boolean('n2n_typing_indicator')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['n2n_who_can_message', 'n2n_read_receipts', 'n2n_typing_indicator']));
        Schema::dropIfExists('n2n_reports');
        Schema::dropIfExists('n2n_blocks');
        Schema::dropIfExists('n2n_message_receipts');
        Schema::dropIfExists('n2n_messages');
        Schema::dropIfExists('n2n_participants');
        Schema::dropIfExists('n2n_conversations');
    }
};
