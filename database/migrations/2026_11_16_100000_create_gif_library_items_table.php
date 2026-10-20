<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The composer's GIF and sticker library: shelves of GIFs every chat composer can offer. The shipped set is registered here so a fresh install has it straight away. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gif_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 24);
            $table->string('name', 120);
            $table->string('path', 255);                       // relative to public/images/gifs (disk null) or to the media disk
            $table->string('disk', 24)->nullable();            // null = the shipped files under public/images/gifs
            $table->string('source', 16)->default('seed');     // seed | upload | url
            $table->string('source_url', 500)->nullable();
            $table->string('license', 160)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index(['category', 'is_active', 'sort']);
        });

        if (class_exists(\App\Services\Gifs\GifLibrary::class)) {
            app(\App\Services\Gifs\GifLibrary::class)->syncSeeds();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gif_library_items');
    }
};
