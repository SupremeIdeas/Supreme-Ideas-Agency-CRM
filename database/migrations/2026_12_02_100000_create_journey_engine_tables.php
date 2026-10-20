<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journey Engine J0/J1 (intent-led onboarding and the one-primary-card engine). Everything here stores COUNTS AND STATES about a user's own account, never message
 * bodies, transcripts or contacts (blueprint principle 8). Erasure and export reach these tables through the journey_* prefix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intent', 30);
            $table->string('source', 12)->default('chosen');          // chosen | inferred | admin
            $table->decimal('confidence', 4, 3)->nullable();
            $table->timestamp('confirmed_at')->nullable();             // an inferred intent is only a suggestion until the user confirms it
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'intent']);
        });

        Schema::create('journey_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('stages')->nullable();                        // lifecycle stage per intent
            $table->json('signals_cache')->nullable();
            $table->string('country', 3)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('device_class', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('journey_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key', 60);
            $table->string('subject_key', 80)->default('');             // what it is about (an eSIM id, a number id); '' = the account
            $table->string('priority', 12);
            $table->string('state', 12)->default('shown');              // shown | clicked | dismissed | snoozed | converted | expired
            $table->json('evidence')->nullable();
            $table->unsignedSmallInteger('shown_count')->default(0);
            $table->unsignedSmallInteger('dismiss_count')->default(0);
            $table->timestamp('first_shown_at')->nullable();
            $table->timestamp('last_shown_at')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('snooze_until')->nullable();
            $table->boolean('holdout')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'rule_key', 'subject_key']);
            $table->index(['user_id', 'state']);
        });

        Schema::create('journey_channel_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);                              // in_app | push | email | whatsapp
            $table->string('category', 24);                             // guardian | suggestions
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('source', 24)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'channel', 'category']);
        });
    }

    public function down(): void
    {
        foreach (['journey_channel_consents', 'journey_cards', 'journey_profiles', 'user_intents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
