<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business Suite Prompt 18 (MASTER-ONLY): a key can be bound to one workspace, and a workspace's integrations subscribe to signed webhooks. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->foreignId('wallet_group_id')->nullable()->after('owner_user_id')->constrained('wallet_groups')->nullOnDelete();
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('target_url', 500);
            $table->text('secret');
            $table->string('status', 12)->default('active');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->string('last_error', 200)->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_group_id', 'event_type', 'status'], 'webhook_subs_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');

        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropForeign(['wallet_group_id']);
            $table->dropColumn('wallet_group_id');
        });
    }
};
