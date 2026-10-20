<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Private admin notes on a White Label purchase (MASTER-ONLY): "called, will pay Friday". Never shown to the buyer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl_funnel_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funnel_instance_id')->constrained('wl_funnel_instances')->cascadeOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('body', 500);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['funnel_instance_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl_funnel_notes');
    }
};
