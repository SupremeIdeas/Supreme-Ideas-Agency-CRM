<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business Card (Brand-Growth Phase 5, MASTER-ONLY): a human-readable address and a short code beside the permanent Naara ID address. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->string('card_slug', 30)->nullable()->unique();
            $t->string('card_code', 8)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->dropUnique(['card_slug']);
            $t->dropUnique(['card_code']);
            $t->dropColumn(['card_slug', 'card_code']);
        });
    }
};
