<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The dashboard "connect with this brand" popup is shown at most 3 times, a day apart (Brand-Growth Phase 6, MASTER-ONLY). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_intents', function (Blueprint $t) {
            $t->unsignedTinyInteger('popup_count')->default(0)->after('prompted_at');
            $t->timestamp('popup_last_at')->nullable()->after('popup_count');
        });
    }

    public function down(): void
    {
        Schema::table('connection_intents', function (Blueprint $t) {
            $t->dropColumn(['popup_count', 'popup_last_at']);
        });
    }
};
