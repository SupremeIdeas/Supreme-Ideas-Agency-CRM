<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Suite Prompt 17 (MASTER-ONLY): the AI voice agent. A workspace's agent settings, one row per handled call, and a `source` on call_recordings so an
 * AI-handled call lands in the same transcript and intelligence pipeline as a human-handled one (reporting does not fork).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_voice_agent_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->unique()->constrained('wallet_groups')->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->string('agent_name', 60)->default('Assistant');
            $t->text('persona')->nullable();                           // what the agent may say about the business (the owner's words)
            $t->string('greeting', 300)->nullable();                   // spoken first; must say it is an automated assistant
            $t->string('language', 10)->default('en-US');
            $t->foreignId('ring_group_id')->nullable()->constrained('ring_groups')->nullOnDelete();
            $t->foreignId('business_hours_schedule_id')->nullable()->constrained('business_hours_schedules')->nullOnDelete();
            $t->unsignedTinyInteger('max_clarifications')->default(3);
            $t->timestamps();
        });

        Schema::create('ai_voice_agent_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $t->foreignId('virtual_number_id')->nullable()->constrained('virtual_numbers')->nullOnDelete();
            $t->foreignId('call_flow_id')->nullable()->constrained('call_flows')->nullOnDelete();
            $t->unsignedBigInteger('call_flow_node_id')->nullable();
            $t->string('call_reference', 64)->nullable()->index();      // the CallSid, same identity scheme as call_recordings
            $t->string('from_number', 32)->nullable();
            $t->string('status', 16)->default('in_progress');           // in_progress | handed_off | ended
            $t->string('outcome', 16)->nullable();                      // qualified | booked | handed_off | no_outcome
            $t->string('handoff_reason', 32)->nullable();
            $t->string('summary', 300)->nullable();                     // one sentence for whoever picks up
            $t->json('transcript')->nullable();                         // appended to during the call
            $t->json('tool_calls')->nullable();
            $t->json('callback')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('duration_seconds')->default(0);
            // funded block, the same shape as voice_calls
            $t->decimal('retail_per_min', 10, 4)->default(0);
            $t->unsignedSmallInteger('minutes_authorized')->default(0);
            $t->decimal('amount_held', 12, 4)->default(0);
            $t->string('hold_reference', 120)->nullable()->unique();
            $t->unsignedSmallInteger('minutes_billed')->default(0);
            $t->decimal('amount_charged', 12, 4)->default(0);
            $t->decimal('refunded', 12, 4)->default(0);
            $t->timestamp('settled_at')->nullable();
            $t->foreignId('recording_id')->nullable()->constrained('call_recordings')->nullOnDelete();
            $t->timestamps();
            $t->index(['wallet_group_id', 'created_at']);
        });

        Schema::table('call_recordings', function (Blueprint $t) {
            $t->string('source', 12)->default('recording')->after('provider');   // recording | ai_agent (a transcript with no audio)
        });
    }

    public function down(): void
    {
        Schema::table('call_recordings', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::dropIfExists('ai_voice_agent_sessions');
        Schema::dropIfExists('ai_voice_agent_configs');
    }
};
