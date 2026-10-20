<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each video says which way it was filmed (so full screen opens the right way round) and whether a self-hosted file may be saved. MASTER ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_marketing_video_sections') && ! Schema::hasColumn('master_marketing_video_sections', 'orientation')) {
            Schema::table('master_marketing_video_sections', function (Blueprint $t) {
                $t->string('orientation', 10)->default('landscape');
                $t->boolean('protected')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_marketing_video_sections', 'orientation')) {
            Schema::table('master_marketing_video_sections', fn (Blueprint $t) => $t->dropColumn(['orientation', 'protected']));
        }
    }
};
