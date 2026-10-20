<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Travel Handbook Store (MASTER-ONLY): per-title merchandising and unlock controls. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handbooks', function (Blueprint $t) {
            $t->boolean('is_featured')->default(false)->after('sort_order');        // admin pick for the landing page's Popular shelf
            $t->boolean('unlock_via_esim')->default(true)->after('is_featured');    // a matching eSIM unlocks it
            $t->boolean('unlock_via_pro')->default(true)->after('unlock_via_esim'); // a Naara Pro slot may be spent on it
            $t->boolean('allow_purchase')->default(true)->after('unlock_via_pro');  // can be bought directly
            $t->json('extra_unlock_countries')->nullable()->after('allow_purchase'); // extra eSIM countries that also unlock it
            $t->index(['status', 'is_featured']);
        });
        Schema::table('handbook_esim_picks', function (Blueprint $t) {
            $t->unsignedTinyInteger('picks_allowed')->default(1)->after('country_codes');   // taken from the admin setting when the eSIM was bought
            $t->json('chosen_ids')->nullable()->after('picks_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('handbook_esim_picks', function (Blueprint $t) {
            $t->dropColumn(['picks_allowed', 'chosen_ids']);
        });
        Schema::table('handbooks', function (Blueprint $t) {
            $t->dropIndex(['status', 'is_featured']);
            $t->dropColumn(['is_featured', 'unlock_via_esim', 'unlock_via_pro', 'allow_purchase', 'extra_unlock_countries']);
        });
    }
};
