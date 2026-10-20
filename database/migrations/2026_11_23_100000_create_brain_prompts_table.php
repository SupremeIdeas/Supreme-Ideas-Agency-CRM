<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NaaraBrain content library (MASTER-ONLY): the rotating centre lines, the quick-question ideas and the capability list, so the admin can tune them without a deploy. Seeded below. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brain_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->unique();          // kind:slug, so a re-seed updates instead of duplicating
            $t->string('kind', 16)->index();           // tagline | suggestion | capability
            $t->string('pack', 24)->default('general')->index();
            $t->string('text', 240);                   // the line shown (tagline), the question sent (suggestion), or the capability title
            $t->string('detail', 280)->nullable();     // capability: one line on what it does
            $t->boolean('live')->default(true);        // works today (true) or is on the roadmap and shown as "Soon" (false)
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        if (class_exists(\Database\Seeders\BrainPromptSeeder::class)) {
            (new \Database\Seeders\BrainPromptSeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brain_prompts');
    }
};
