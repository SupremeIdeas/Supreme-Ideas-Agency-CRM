<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the inspector found inside each uploaded installer (CPU, contents, signing), shown on its admin card. MASTER ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_marketing_installers') && ! Schema::hasColumn('master_marketing_installers', 'checks')) {
            Schema::table('master_marketing_installers', fn (Blueprint $t) => $t->json('checks')->nullable()->after('sha256'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_marketing_installers', 'checks')) {
            Schema::table('master_marketing_installers', fn (Blueprint $t) => $t->dropColumn('checks'));
        }
    }
};
