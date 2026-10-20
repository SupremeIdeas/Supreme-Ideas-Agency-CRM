<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Respond automation (MASTER-ONLY, blueprint sections 5 to 11): knowledge base, AI agent settings and run log, flows, broadcasts, outbound webhooks.
 * Every table carries workspace_id first. Idempotent (MySQL DDL is not transactional); index names stay under 64 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('omni_contacts', 'marketing_opt_in')) {
            Schema::table('omni_contacts', function (Blueprint $t) {
                $t->boolean('marketing_opt_in')->default(false);
                $t->timestamp('opted_out_at')->nullable();
            });
        }
        if (! Schema::hasTable('omni_kb_sources')) {
            Schema::create('omni_kb_sources', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('kind', 8);                               // faq | text | url
                $t->string('title', 160);
                $t->string('url', 500)->nullable();
                $t->text('content')->nullable();
                $t->string('status', 12)->default('ready');         // ready | fetching | failed
                $t->string('error', 240)->nullable();
                $t->timestamp('fetched_at')->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'kind'], 'omni_kbs_ws_kind_idx');
            });
        }
        if (! Schema::hasTable('omni_kb_chunks')) {
            Schema::create('omni_kb_chunks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('source_id');
                $t->unsignedInteger('ord')->default(0);
                $t->text('content');
                $t->index(['workspace_id', 'source_id'], 'omni_kbc_ws_src_idx');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE omni_kb_chunks ADD FULLTEXT omni_kbc_ft (content)');
            }
        }
        if (! Schema::hasTable('omni_agent_settings')) {
            Schema::create('omni_agent_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->unique('omni_agent_ws_uq');
                $t->string('mode', 8)->default('off');               // off | suggest | auto
                $t->string('tone', 16)->default('friendly');         // friendly | formal | short
                $t->string('business_summary', 1000)->nullable();
                $t->json('handoff_words')->nullable();
                $t->unsignedInteger('daily_cap')->default(200);      // AI replies per day; a hard stop on cost
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('omni_agent_runs')) {
            Schema::create('omni_agent_runs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('conversation_id');
                $t->unsignedBigInteger('message_id')->nullable();
                $t->string('mode', 8);
                $t->string('outcome', 12);                           // answered | suggested | handoff | refused | capped | error
                $t->text('reply')->nullable();
                $t->json('sources')->nullable();
                $t->unsignedInteger('tokens_in')->default(0);
                $t->unsignedInteger('tokens_out')->default(0);
                $t->string('note', 160)->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'conversation_id'], 'omni_run_ws_conv_idx');
                $t->index(['workspace_id', 'created_at'], 'omni_run_ws_created_idx');
            });
        }
        if (! Schema::hasTable('omni_flows')) {
            Schema::create('omni_flows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('name', 120);
                $t->string('trigger_type', 24);                      // new_conversation | keyword | outside_hours
                $t->string('trigger_value', 120)->nullable();
                $t->json('steps');
                $t->string('status', 8)->default('draft');           // draft | live
                $t->unsignedInteger('version')->default(1);
                $t->unsignedInteger('runs_count')->default(0);
                $t->timestamps();
                $t->index(['workspace_id', 'status'], 'omni_flows_ws_status_idx');
            });
        }
        if (! Schema::hasTable('omni_flow_runs')) {
            Schema::create('omni_flow_runs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('flow_id');
                $t->unsignedBigInteger('conversation_id')->nullable();
                $t->boolean('is_test')->default(false);
                $t->string('status', 10)->default('running');        // running | done | stopped | failed
                $t->json('trace')->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'flow_id'], 'omni_fr_ws_flow_idx');
                $t->index(['workspace_id', 'conversation_id', 'created_at'], 'omni_fr_ws_conv_idx');
            });
        }
        if (! Schema::hasTable('omni_broadcasts')) {
            Schema::create('omni_broadcasts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('name', 120);
                $t->unsignedBigInteger('connection_id');
                $t->string('body', 1000);
                $t->json('segment')->nullable();                     // {tag?, stage?}
                $t->string('status', 10)->default('draft');         // draft | scheduled | sending | done | cancelled
                $t->timestamp('scheduled_at')->nullable();
                $t->unsignedInteger('per_minute')->default(30);
                $t->json('stats')->nullable();
                $t->timestamps();
                $t->index(['workspace_id', 'status'], 'omni_bc_ws_status_idx');
            });
        }
        if (! Schema::hasTable('omni_broadcast_recipients')) {
            Schema::create('omni_broadcast_recipients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('broadcast_id');
                $t->unsignedBigInteger('contact_id');
                $t->string('status', 10)->default('queued');        // queued | sent | failed | skipped
                $t->string('reason', 160)->nullable();
                $t->timestamps();
                $t->unique(['broadcast_id', 'contact_id'], 'omni_bcr_uq');
                $t->index(['workspace_id', 'broadcast_id', 'status'], 'omni_bcr_ws_idx');
            });
        }
        if (! Schema::hasTable('omni_webhooks')) {
            Schema::create('omni_webhooks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->string('url', 500);
                $t->string('secret', 80);
                $t->json('events');                                  // message.received | message.sent | conversation.resolved
                $t->boolean('active')->default(true);
                $t->unsignedSmallInteger('last_status')->nullable();
                $t->timestamp('last_at')->nullable();
                $t->unsignedInteger('failures')->default(0);
                $t->timestamps();
                $t->index(['workspace_id', 'active'], 'omni_wh_ws_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['omni_webhooks', 'omni_broadcast_recipients', 'omni_broadcasts', 'omni_flow_runs', 'omni_flows', 'omni_agent_runs', 'omni_agent_settings', 'omni_kb_chunks', 'omni_kb_sources'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
