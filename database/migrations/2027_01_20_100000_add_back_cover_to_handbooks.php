<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Travel Handbook Store (MASTER-ONLY): the back cover (about the book and the Naara Family), closed into every EPUB and PDF the reader and the buyer receive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handbooks', function (Blueprint $t) {
            $t->string('back_cover_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('handbooks', function (Blueprint $t) {
            $t->dropColumn('back_cover_path');
        });
    }
};
