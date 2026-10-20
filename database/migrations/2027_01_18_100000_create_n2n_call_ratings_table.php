<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N after-call rating (MASTER-ONLY): one row per person per call: the stars they gave (or that they dismissed the sheet) and whether they opened the Trustpilot invitation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_call_ratings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('call_id');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('stars')->nullable();       // 1..5; null = dismissed with "Not now"
            $t->boolean('trustpilot_opened')->default(false);   // they pressed the Trustpilot button (we never see what they wrote there)
            $t->boolean('video')->default(false);
            $t->timestamps();
            $t->unique(['call_id', 'user_id']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_call_ratings');
    }
};
