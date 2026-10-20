<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier 5 #14 (Provider Capability Radar, master-only). A human-reviewed
     * proposal generated from a real detected provider-capability change —
     * never auto-implemented, auto-deployed, or treated as verified fact.
     * `source_snapshot_id` links back to the literal evidence that triggered
     * it, so a reviewer can verify the claim themselves.
     */
    public function up(): void
    {
        Schema::create('capability_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->foreignId('source_snapshot_id')->constrained('provider_capability_snapshots')->cascadeOnDelete();
            $table->longText('raw_diff');
            $table->text('summary');
            $table->text('capability_added');
            $table->text('relevance_to_naara');
            $table->text('proposed_feature');
            $table->string('estimated_effort'); // small | medium | large
            $table->string('confidence'); // high | medium | low
            $table->text('requires_human_verification_because');
            $table->string('status')->default('new'); // new | reviewing | accepted | deferred | dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->text('dismissed_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('deferred_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_proposals');
    }
};
