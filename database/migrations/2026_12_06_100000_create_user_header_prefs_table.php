<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-account choice of which shortcuts sit in the dashboard header (at most three) and whether a merchant sees the merchant panel button. Nothing is added to `users`. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_header_prefs')) {
            Schema::create('user_header_prefs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->json('items')->nullable();            // null = the defaults; otherwise the chosen keys in order
                $table->boolean('merchant_button')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_header_prefs');
    }
};
