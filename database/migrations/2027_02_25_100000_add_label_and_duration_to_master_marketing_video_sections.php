<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The hero video card shows a small label chip ("Product tour") and a length ("2:30"). MASTER ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_marketing_video_sections') && ! Schema::hasColumn('master_marketing_video_sections', 'label')) {
            Schema::table('master_marketing_video_sections', function (Blueprint $t) {
                $t->string('label', 40)->nullable();
                $t->string('duration_label', 12)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_marketing_video_sections', 'label')) {
            Schema::table('master_marketing_video_sections', fn (Blueprint $t) => $t->dropColumn(['label', 'duration_label']));
        }
    }
};
