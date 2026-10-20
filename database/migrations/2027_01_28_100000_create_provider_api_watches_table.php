<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_api_watches', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->unique();
            $table->string('base_url', 255);
            $table->string('pinned_version', 40)->nullable();       // what this install is built against, read from the configured base URL
            $table->string('observed_version', 80)->nullable();     // a version the provider itself reported in a response header
            $table->json('newer_versions')->nullable();             // candidate versions that now answer differently from a nonsense version
            $table->string('deprecation', 255)->nullable();         // Deprecation / Sunset / Warning header text
            $table->string('status', 20)->default('ok');            // ok | newer_available | deprecated | changed | unreachable
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('signature', 40)->nullable();            // fingerprint of the findings, so an acknowledged finding stays quiet until it changes
            $table->string('acknowledged_signature', 40)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_api_watches');
    }
};
