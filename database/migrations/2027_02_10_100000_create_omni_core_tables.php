<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Respond omni core (MASTER-ONLY, blueprint section 3.1). One workspace (wallet group) owns its contacts, conversations and messages; every table carries
 * workspace_id first so isolation is an index prefix, not an afterthought. Idempotent (MySQL DDL is not transactional); index names kept under 64 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('channel_connections', 'public_id')) {
            Schema::table('channel_connections', fn (Blueprint $t) => $t->uuid('public_id')->nullable()->unique('chconn_public_id_uq'));
        }
        if (! Schema::hasTable('omni_contacts')) {
            Schema::create('omni_contacts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('name', 160)->nullable();
                $t->string('email', 190)->nullable();
                $t->string('phone', 32)->nullable();                 // E.164
                $t->string('stage', 24)->default('new');             // new | lead | customer | lost
                $t->json('tags')->nullable();
                $t->unsignedBigInteger('merged_into_id')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'phone'], 'omni_contacts_ws_phone_idx');
                $t->index(['workspace_id', 'email'], 'omni_contacts_ws_email_idx');
            });
        }
        if (! Schema::hasTable('omni_identities')) {
            Schema::create('omni_identities', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('contact_id');
                $t->string('channel', 24);
                $t->string('external_id', 190);                      // the customer's id on that channel (chat id, wa_id, IGSID, email)
                $t->timestamps();
                $t->unique(['workspace_id', 'channel', 'external_id'], 'omni_identity_uq');
                $t->index('contact_id', 'omni_identity_contact_idx');
            });
        }
        if (! Schema::hasTable('omni_conversations')) {
            Schema::create('omni_conversations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('contact_id');
                $t->unsignedBigInteger('connection_id')->nullable();
                $t->string('channel', 24);
                $t->string('status', 16)->default('open');           // open | pending | resolved
                $t->unsignedBigInteger('assignee_id')->nullable();
                $t->string('last_preview', 160)->nullable();
                $t->timestamp('last_message_at')->nullable();
                $t->timestamp('last_inbound_at')->nullable();        // drives the 24-hour service window
                $t->unsignedInteger('unread')->default(0);
                $t->timestamps();
                $t->index(['workspace_id', 'status', 'last_message_at'], 'omni_conv_ws_status_idx');
                $t->index(['workspace_id', 'contact_id', 'channel'], 'omni_conv_ws_contact_idx');
            });
        }
        if (! Schema::hasTable('omni_messages')) {
            Schema::create('omni_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('conversation_id');
                $t->string('direction', 3);                          // in | out
                $t->string('status', 12)->default('received');       // received | queued | sent | delivered | read | failed
                $t->string('provider_message_id', 190)->nullable();
                $t->string('idempotency_key', 64)->nullable();       // outbound: one send per key
                $t->text('body')->nullable();
                $t->json('media')->nullable();
                $t->string('error', 240)->nullable();
                $t->unsignedBigInteger('author_id')->nullable();     // the teammate (or null for the assistant/system)
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'conversation_id', 'id'], 'omni_msg_thread_idx');
                $t->unique(['conversation_id', 'direction', 'provider_message_id'], 'omni_msg_provider_uq');
                $t->unique(['conversation_id', 'idempotency_key'], 'omni_msg_idem_uq');
            });
        }
        if (! Schema::hasTable('omni_raw_events')) {
            Schema::create('omni_raw_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('connection_id');
                $t->string('provider_event_id', 190);                // unique per connection: a replayed webhook is a no-op
                $t->json('payload');
                $t->string('status', 12)->default('pending');        // pending | done | failed
                $t->string('error', 240)->nullable();
                $t->timestamp('received_at')->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->unique(['connection_id', 'provider_event_id'], 'omni_raw_event_uq');
                $t->index(['status', 'id'], 'omni_raw_status_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['omni_raw_events', 'omni_messages', 'omni_conversations', 'omni_identities', 'omni_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
        if (Schema::hasColumn('channel_connections', 'public_id')) {
            Schema::table('channel_connections', function (Blueprint $t) {
                $t->dropUnique('chconn_public_id_uq');
                $t->dropColumn('public_id');
            });
        }
    }
};
