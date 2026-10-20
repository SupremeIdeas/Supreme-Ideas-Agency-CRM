<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content/billing/hot-menu blueprint Phase E — the manifest's own changelog
 * text, carried onto the attempt row so it survives past the (async) apply and
 * is still available afterward to seed a "What's New" entry. Previously this
 * text was only ever shown transiently in the pre-apply preview.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_update_attempts', function (Blueprint $table) {
            $table->text('changelog')->nullable()->after('to_version');
        });
    }

    public function down(): void
    {
        Schema::table('platform_update_attempts', function (Blueprint $table) {
            $table->dropColumn('changelog');
        });
    }
};
