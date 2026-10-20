<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gestures blueprint (MASTER-ONLY, N2N): per-member gesture preferences and privacy-safe gesture counters (no user id, no content). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->json('n2n_gesture_prefs')->nullable();
        });
        Schema::create('n2n_gesture_stats', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('gesture', 24);
            $t->string('outcome', 16);          // fired | committed | cancelled | fallback
            $t->string('device', 8)->default('touch');   // touch | mouse
            $t->unsignedBigInteger('n')->default(0);
            $t->unique(['day', 'gesture', 'outcome', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_gesture_stats');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('n2n_gesture_prefs');
        });
    }
};
