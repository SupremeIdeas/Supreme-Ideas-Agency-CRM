<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The loudness wave drawn in the video progress bar (96 points). MASTER ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_marketing_video_sections') && ! Schema::hasColumn('master_marketing_video_sections', 'wave')) {
            Schema::table('master_marketing_video_sections', fn (Blueprint $t) => $t->json('wave')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_marketing_video_sections', 'wave')) {
            Schema::table('master_marketing_video_sections', fn (Blueprint $t) => $t->dropColumn('wave'));
        }
    }
};
