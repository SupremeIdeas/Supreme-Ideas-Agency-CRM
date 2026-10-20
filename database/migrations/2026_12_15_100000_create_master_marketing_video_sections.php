<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_marketing_video_sections', function (Blueprint $t) {
            $t->id();
            $t->string('slot', 80);                       // see App\Support\MasterMarketing\VideoSlots
            $t->string('title', 140);
            $t->string('description', 300)->nullable();
            $t->string('source_type', 12)->default('url'); // upload | url (YouTube / Vimeo / .mp4 link)
            $t->string('video_url', 500);
            $t->string('poster_url', 500)->nullable();
            $t->boolean('autoplay')->default(false);
            $t->boolean('loop')->default(false);
            $t->boolean('is_visible')->default(true);
            $t->unsignedInteger('position')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['slot', 'is_visible', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_marketing_video_sections');
    }
};
