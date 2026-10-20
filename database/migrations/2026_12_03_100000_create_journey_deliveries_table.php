<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Journey Engine J2: one row per alert actually pushed or emailed, so a repeat, a daily cap and "why did I get this" are all answerable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journey_card_id')->nullable()->constrained('journey_cards')->nullOnDelete();
            $table->string('channel', 16);                     // push | email
            $table->string('rule_key', 48);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['user_id', 'sent_at']);
            $table->index(['user_id', 'journey_card_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_deliveries');
    }
};
