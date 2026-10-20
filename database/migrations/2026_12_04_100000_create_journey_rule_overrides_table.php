<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Journey Engine J3: admin edits to the shipped rules. The rule itself (signal, route, evidence fields) stays in code; this table only holds what an admin may safely change. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_rule_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 48)->unique();
            $table->boolean('enabled')->default(true);
            $table->string('priority', 16)->nullable();      // only within the allowed set for the rule's class (see RuleCatalog::allowedPriorities)
            $table->json('intents')->nullable();             // null = the shipped intents
            $table->json('copy')->nullable();                // {title, body, cta, why}: plain text, escaped on render, null = shipped copy
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_rule_overrides');
    }
};
