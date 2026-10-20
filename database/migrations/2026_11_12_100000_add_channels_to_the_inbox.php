<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Suite Prompt 16 (MASTER-ONLY): the inbox that already exists (inbound_messages, outbound_messages, message_threads) gains a CHANNEL
 * dimension (sms | whatsapp | telegram | instagram) and an optional workspace, so a team can share the conversations of its own connected channels.
 *
 * Every existing row is an SMS: the column default backfills them (a single metadata change on MySQL 8, no row rewrite). Widening the thread
 * uniqueness from (user, counterpart) to (user, channel, counterpart) can never create a collision, because every existing row shares channel 'sms';
 * the new index is added BEFORE the old one is dropped so the user_id foreign key always has an index to lean on.
 *
 * channel_connections holds a workspace's OWN channel accounts (its WhatsApp number, Telegram bot, Instagram page). Credentials are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['inbound_messages', 'outbound_messages', 'message_threads'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('channel', 16)->default('sms')->after('user_id');
                $t->foreignId('wallet_group_id')->nullable()->after('channel')->constrained('wallet_groups')->nullOnDelete();
                if ($table === 'message_threads') {
                    $t->timestamp('opted_out_at')->nullable();      // the person said /stop or STOP: nothing is sent to them until they message again
                    $t->index(['wallet_group_id', 'channel', 'last_at'], 'message_threads_workspace_idx');
                } else {
                    $t->index(['wallet_group_id', 'channel'], $table.'_workspace_idx');
                }
            });
        }

        // A reply on WhatsApp / Telegram / Instagram is not sent from one of our numbers, so the line is optional on an outbound row.
        Schema::table('outbound_messages', fn (Blueprint $t) => $t->unsignedBigInteger('virtual_number_id')->nullable()->change());

        Schema::table('message_threads', function (Blueprint $t) {
            // counterpart_number is channel-relative: a phone number for sms/whatsapp, a chat id for telegram, a scoped user id for instagram.
            $t->unique(['user_id', 'channel', 'counterpart_number'], 'message_threads_user_channel_counterpart_unique');
        });
        Schema::table('message_threads', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'counterpart_number']);
        });

        Schema::create('channel_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $t->string('channel', 16);                                   // whatsapp | telegram | instagram
            $t->string('external_id', 64);                               // WhatsApp phone_number_id, Telegram bot id, Instagram account id
            $t->string('label', 80)->nullable();
            $t->text('credentials');                                     // encrypted array: tokens, never shown again after saving
            $t->string('webhook_secret', 64)->nullable();                // Telegram secret_token the bot echoes back
            $t->string('status', 12)->default('active');                 // active | paused | error
            $t->string('last_error', 200)->nullable();
            $t->timestamp('last_event_at')->nullable();
            $t->timestamps();
            $t->unique(['channel', 'external_id']);
            $t->index(['wallet_group_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');

        Schema::table('message_threads', function (Blueprint $t) {
            $t->dropUnique('message_threads_user_channel_counterpart_unique');
        });
        Schema::table('message_threads', fn (Blueprint $t) => $t->unique(['user_id', 'counterpart_number']));

        // Channel replies have no line; they go with the channel columns.
        \Illuminate\Support\Facades\DB::table('outbound_messages')->whereNull('virtual_number_id')->delete();
        Schema::table('outbound_messages', fn (Blueprint $t) => $t->unsignedBigInteger('virtual_number_id')->nullable(false)->change());

        foreach (['inbound_messages', 'outbound_messages', 'message_threads'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                // The foreign key leans on the workspace index, so it goes first; dropping the column then removes it from the index.
                if ($table === 'message_threads') {
                    $t->dropColumn('opted_out_at');
                }
                $t->dropForeign(['wallet_group_id']);
                $t->dropIndex($table === 'message_threads' ? 'message_threads_workspace_idx' : $table.'_workspace_idx');
                $t->dropColumn(['wallet_group_id', 'channel']);
            });
        }
    }
};
