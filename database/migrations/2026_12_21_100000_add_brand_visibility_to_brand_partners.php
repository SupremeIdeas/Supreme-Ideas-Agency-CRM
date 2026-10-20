<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->timestamp('published_at')->nullable()->after('is_active');     // the CONTENT state: the owner pressed Publish
            $t->unsignedTinyInteger('gate_version')->default(1)->after('published_at'); // which content gate this brand was created under
            $t->string('logo_path', 500)->nullable()->after('hero_image_path'); // the profile photo (the new gate asks for one)
        });

        // Grandfather: every brand that is already listed (or was, and is only paused or switched off) counts as published under the OLD gate, so the
        // new gate can never take a live listing away. They get a gentle "complete your profile" nudge instead.
        DB::table('brand_partners')->whereIn('listing_status', ['active', 'paused_billing', 'disabled'])->whereNull('published_at')->update(['published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->dropColumn(['published_at', 'gate_version', 'logo_path']);
        });
    }
};
