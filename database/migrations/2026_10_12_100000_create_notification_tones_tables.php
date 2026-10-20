<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The admin's tone library. Exactly one default per (category, slot); the service enforces it inside a transaction.
        Schema::create('notification_tones', function (Blueprint $t) {
            $t->id();
            $t->string('category', 24);            // ringtone | message | credit_alert | push | reward
            $t->string('slot', 24)->default('default'); // message: general | naara | naaraline; everything else: default
            $t->string('label', 80);
            $t->string('file_path');
            $t->string('disk', 24);
            $t->string('mime', 40);
            $t->unsignedInteger('size_bytes');
            $t->unsignedSmallInteger('duration_ms')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_builtin')->default(false);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['category', 'slot', 'is_default']);
        });

        // A member's own choice per (category, slot) key, plus the two global switches. `tones` is {"message.naara": 12, ...}.
        Schema::create('user_sound_prefs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('tones')->nullable();
            $t->boolean('sound_enabled')->default(true);
            $t->boolean('vibrate_enabled')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sound_prefs');
        Schema::dropIfExists('notification_tones');
    }
};
