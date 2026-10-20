<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business Suite inbox composer parity (MASTER-ONLY): messages can carry media/voice/location/contact/poll, and can be scheduled. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->string('attachment_kind', 16)->nullable()->after('attachment_url');
            $table->string('attachment_name', 190)->nullable()->after('attachment_kind');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
        });

        Schema::create('scheduled_channel_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 80)->nullable();
            $table->json('payload');
            $table->timestamp('send_at');
            $table->string('status', 12)->default('pending');
            $table->string('error', 200)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_channel_messages');

        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_kind', 'attachment_name', 'attachment_mime']);
        });
    }
};
