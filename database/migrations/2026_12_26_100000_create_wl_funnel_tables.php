<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** White Label purchase funnel (MASTER-ONLY, Funnel blueprint Phases 1 and 3): the instance (soft gate with an ID) and its append-only event log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl_funnel_instances', function (Blueprint $t) {
            $t->id();
            $t->string('public_code', 20)->unique();             // BS-XXXXXXXXXC / EX-XXXXXXXXXC, immutable
            $t->string('plan_key_at_landing', 24);
            $t->string('origin', 16)->default('landing');
            $t->json('utm')->nullable();
            $t->string('visitor_id', 64)->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 32)->default('landing_clicked')->index();
            $t->unsignedTinyInteger('draft_step')->default(1);
            $t->json('draft')->nullable();                        // non-sensitive wizard answers only
            $t->string('selected_plan_key', 24)->nullable();
            $t->string('hosting_choice', 32)->nullable();
            $t->timestamp('draft_saved_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('wl_funnel_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instance_id')->constrained('wl_funnel_instances')->cascadeOnDelete();
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32);
            $t->string('actor', 16)->default('system');            // system | user | admin
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['instance_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl_funnel_events');
        Schema::dropIfExists('wl_funnel_instances');
    }
};
