<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brand-Growth Phase 6 (MASTER-ONLY): "this person arrived from a brand's Business Card". One row per person and brand; never a silent follow. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_intents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('brand_partner_id')->constrained('brand_partners')->cascadeOnDelete();
            $t->string('source', 16)->default('card');
            $t->string('status', 12)->default('pending');   // pending | connected | dismissed
            $t->timestamp('prompted_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'brand_partner_id']);
            $t->index(['brand_partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_intents');
    }
};
