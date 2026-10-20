<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content/billing/hot-menu blueprint Phase G — one row per (admin, hot-menu
 * section) recording when that admin last opened it. Badge counts (App\Support\
 * AdminHotMenu) count qualifying records created after last_viewed_at, mirroring
 * NotificationCenter's own unread-since-last-check pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_section_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section_key');
            $table->timestamp('last_viewed_at');
            $table->timestamps();

            $table->unique(['user_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_section_views');
    }
};
