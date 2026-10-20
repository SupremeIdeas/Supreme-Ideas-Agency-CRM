<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Suite Prompt 14 §1 — additive tables for the Business Phone Core
 * (IVR flows, ring groups, business hours). Nothing here touches
 * `call_forwarding_rules`; an individually-owned number never has a row in
 * any of these.
 *
 * `call_flows.root_node_id` is a plain indexed column, deliberately NOT a
 * foreign key: `call_flow_nodes.call_flow_id` already points back at
 * `call_flows`, and a mutual FK pair can't be created or dropped cleanly
 * (and would make "create the flow before its first node" impossible on
 * stricter engines). The model layer owns that integrity instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_group_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Business hours');
            $table->string('timezone')->default('UTC');   // IANA name
            $table->json('windows')->nullable();            // [{day,start,end}]
            $table->json('holiday_overrides')->nullable();  // [{date,closed,windows?}]
            $table->timestamps();
        });

        Schema::create('ring_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('strategy')->default('simultaneous'); // simultaneous | sequential
            $table->timestamps();
        });

        Schema::create('ring_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ring_group_id')->constrained()->cascadeOnDelete();
            // "organization_member_id" in the prompt — Prompt 13 reconciled
            // Organization onto WalletGroup, so the member is a WalletGroupMember.
            $table->foreignId('wallet_group_member_id')->constrained('wallet_group_members')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0); // sequential ordering only
            $table->string('destination_number')->nullable();     // explicit override; else their own Naara Line
            $table->boolean('ring_in_browser')->default(false);   // also ring their in-browser dialer client
            $table->timestamps();

            $table->unique(['ring_group_id', 'wallet_group_member_id']);
        });

        Schema::create('call_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_number_id')->constrained('virtual_numbers')->cascadeOnDelete();
            $table->unsignedBigInteger('root_node_id')->nullable()->index();
            $table->string('status')->default('paused'); // active | paused
            $table->timestamps();

            // One flow per number — the webhook resolves by called number.
            $table->unique('virtual_number_id');
        });

        Schema::create('call_flow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_flow_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // menu | queue | forward | voicemail
            $table->string('label')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::table('call_events', function (Blueprint $table) {
            $table->foreignId('call_flow_id')->nullable()->after('call_forwarding_rule_id')->constrained('call_flows')->nullOnDelete();
            $table->unsignedBigInteger('call_flow_node_id')->nullable()->after('call_flow_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('call_flow_id');
            $table->dropColumn('call_flow_node_id');
        });
        Schema::dropIfExists('call_flow_nodes');
        Schema::dropIfExists('call_flows');
        Schema::dropIfExists('ring_group_members');
        Schema::dropIfExists('ring_groups');
        Schema::dropIfExists('business_hours_schedules');
    }
};
