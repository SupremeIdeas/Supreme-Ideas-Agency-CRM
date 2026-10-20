<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Chat status posts (MASTER-ONLY): 24-hour text/photo/video/voice updates by Naara Pro members. Short-lived by design; the purge job deletes the row AND its stored object. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_status_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 8);                       // text | image | video | voice
            $t->string('body', 700)->nullable();         // the text, or a caption
            $t->string('bg', 7)->nullable();             // text status background, #RRGGBB
            $t->string('font', 12)->nullable();          // text status font style key
            $t->string('disk', 16)->nullable();
            $t->string('storage_key', 190)->nullable();
            $t->string('mime', 40)->nullable();
            $t->unsignedInteger('bytes')->default(0);
            $t->unsignedSmallInteger('duration_s')->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamp('purge_failed_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('n2n_status_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('status_id')->constrained('n2n_status_posts')->cascadeOnDelete();
            $t->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('viewed_at')->useCurrent();
            $t->unique(['status_id', 'viewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_status_views');
        Schema::dropIfExists('n2n_status_posts');
    }
};
