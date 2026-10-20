<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Suite Prompt 15 (MASTER-ONLY): call recording, transcripts and conversation intelligence for workspace numbers.
 * Recording is OFF by default and only a workspace owner/admin can switch it on (docs/legal/CALL-RECORDING-CONSENT-STEER.md). Every recording carries
 * its consent evidence; a recording with none is deleted by the retention job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_recording_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->unique()->constrained('wallet_groups')->cascadeOnDelete();
            $t->boolean('recording_enabled')->default(false);
            $t->boolean('intelligence_enabled')->default(false);   // separate consent from recording (steer §9)
            $t->unsignedSmallInteger('retention_days')->default(30);
            $t->timestamp('legal_hold_at')->nullable();             // pauses deletion; audited
            $t->text('sensitive_numbers')->nullable();              // numbers the workspace never records (health, legal, financial hotlines), comma separated
            $t->timestamps();
        });

        Schema::table('virtual_numbers', function (Blueprint $t) {
            $t->boolean('recording_enabled')->default(false)->after('wallet_group_id');
        });

        Schema::create('call_recordings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_group_id')->constrained('wallet_groups')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // the wallet that pays (workspace owner)
            $t->foreignId('virtual_number_id')->nullable()->constrained('virtual_numbers')->nullOnDelete();
            $t->foreignId('call_flow_id')->nullable()->constrained('call_flows')->nullOnDelete();
            $t->string('call_reference', 64)->index();                          // the CallSid LogCallEventJob already keys calls by
            $t->string('provider', 20)->default('twilio');
            $t->string('provider_recording_sid', 64)->unique();
            $t->string('from_number', 32)->nullable();
            $t->string('to_number', 32)->nullable();
            $t->unsignedInteger('duration_seconds')->default(0);
            $t->string('storage_path')->nullable();                             // private disk, never a provider URL
            $t->string('status', 16)->default('received');                      // received | stored | unfunded | failed | purged
            $t->string('transcript_status', 16)->default('none');               // none | pending | processing | complete | failed | skipped
            $t->string('intelligence_status', 16)->default('none');
            $t->json('consent')->nullable();                                    // announcement version, regime, countries, caller action, timestamps
            $t->json('billing')->nullable();                                    // wallet references per step (never provider cost)
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('purged_at')->nullable();
            $t->timestamps();
            $t->index(['wallet_group_id', 'created_at']);
            $t->index(['status', 'expires_at']);
        });

        Schema::create('call_transcripts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_recording_id')->unique()->constrained('call_recordings')->cascadeOnDelete();
            $t->longText('transcript_text');
            $t->string('transcript_source', 24)->default('elevenlabs');          // kept as a field so a provider swap needs no schema change
            $t->timestamps();
        });

        Schema::create('call_intelligence_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_recording_id')->unique()->constrained('call_recordings')->cascadeOnDelete();
            $t->string('sentiment', 12)->nullable();                            // positive | neutral | negative
            $t->decimal('talk_listen_ratio', 6, 3)->nullable();                 // null unless the source gives speaker data: never fabricated
            $t->json('keywords')->nullable();
            $t->text('coaching_notes')->nullable();
            $t->text('summary_text')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_intelligence_summaries');
        Schema::dropIfExists('call_transcripts');
        Schema::dropIfExists('call_recordings');
        Schema::table('virtual_numbers', fn (Blueprint $t) => $t->dropColumn('recording_enabled'));
        Schema::dropIfExists('call_recording_settings');
    }
};
