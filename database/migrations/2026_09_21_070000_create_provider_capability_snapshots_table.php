<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier 5 #14 (Provider Capability Radar, master-only). One row per
     * provider+source per successful fetch — the hash lets the scan command
     * cheaply tell "nothing changed" from "something changed" without
     * re-diffing every run.
     */
    public function up(): void
    {
        Schema::create('provider_capability_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('source_type'); // changelog | openapi | response-schema
            $table->string('content_hash', 64);
            $table->longText('raw_content');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['provider', 'source_type', 'fetched_at'], 'pcs_provider_source_type_fetched_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_capability_snapshots');
    }
};
