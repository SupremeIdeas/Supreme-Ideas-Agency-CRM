<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MASTER-ONLY. Where each signed-in member left their floating widgets (Widget Dock); one row per member, so it follows them across devices. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_marketing_dock_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('positions');
            $table->unsignedBigInteger('stamp')->default(0);   // client clock (ms) of the last move, so the newer of local and server wins
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_marketing_dock_positions');
    }
};
