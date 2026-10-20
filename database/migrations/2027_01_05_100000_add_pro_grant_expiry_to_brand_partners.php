<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An admin Naara Pro grant can now be time-limited (MASTER-ONLY). NULL keeps meaning "lifetime", so every existing grant is unchanged. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->timestamp('pro_grant_expires_at')->nullable()->after('pro_granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->dropColumn('pro_grant_expires_at');
        });
    }
};
