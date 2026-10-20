<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Admin-managed shelves for the composer's GIF and sticker library, with a per-category choice of which chats show it. The nine shipped shelves are created here. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gif_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 24)->unique();
            $table->string('name', 60);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_builtin')->default(false);
            $table->json('surfaces')->nullable();      // null = every chat; otherwise a list of surface keys (config gif_library.surfaces)
            $table->timestamps();
        });

        $now = now();
        foreach (array_values((array) config('gif_library.categories')) as $i => $slug) {
            DB::table('gif_categories')->insert([
                'slug' => $slug, 'name' => trans('gifs.cat.'.$slug, [], 'en'), 'sort' => $i, 'is_active' => true, 'is_builtin' => true,
                'surfaces' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gif_categories');
    }
};
