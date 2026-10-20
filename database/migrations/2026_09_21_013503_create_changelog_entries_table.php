<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content/billing/hot-menu blueprint Phase E — the platform's own public
 * release history: what changed, in user-facing language, one row per shipped
 * version. Append-only by design (the admin UI never edits or deletes a row,
 * only creates new ones) — surfaced on the in-app "What's New" screen and as a
 * small contextual appendix in a user's own data export.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->date('released_at');
            $table->string('summary', 500); // short, user-facing headline of what changed
            $table->text('notes')->nullable(); // fuller detail, optional
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('released_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
