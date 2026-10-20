<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bootstrap manifest: what `naara:bootstrap` created, with a checksum of the value it wrote, so a re-run can tell an untouched default from one the admin edited
 * and never overwrites the latter. MASTER-ONLY tooling (see CLAUDE.md white-label discipline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bootstrap_manifest', function (Blueprint $table) {
            $table->id();
            $table->string('step', 40);
            $table->string('kind', 30);
            $table->string('item', 190);
            $table->string('checksum', 40);
            $table->timestamp('admin_modified_at')->nullable();
            $table->timestamps();
            $table->unique(['step', 'kind', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bootstrap_manifest');
    }
};
