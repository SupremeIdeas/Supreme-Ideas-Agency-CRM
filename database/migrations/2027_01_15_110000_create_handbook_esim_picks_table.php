<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Travel Handbook Store (MASTER-ONLY): a regional or global eSIM earns ONE free handbook from the countries it covers; the pick is the person's to make. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handbook_esim_picks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('esim_order_id')->unique();
            $t->json('country_codes');
            $t->foreignId('handbook_id')->nullable()->constrained('handbooks')->nullOnDelete();
            $t->timestamp('picked_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'picked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handbook_esim_picks');
    }
};
