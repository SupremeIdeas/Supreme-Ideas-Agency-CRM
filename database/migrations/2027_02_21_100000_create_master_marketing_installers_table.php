<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Desktop and browser installers (Windows, macOS, Linux, Chrome extension) the owner uploads in Admin and users download from /download. MASTER ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_marketing_installers', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 24)->unique();          // windows | macos | linux | chrome
            $table->string('version', 32)->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();           // on the public media disk
            $table->string('external_url', 2048)->nullable();  // used instead of a file (release asset, store listing, separate-branch raw link)
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('min_os', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_marketing_installers');
    }
};
