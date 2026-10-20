<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brand Connect (Brand-Growth Phase 6, MASTER-ONLY): who connected, the owner's decision, and whether the welcome message went out. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_connect_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('accepted')->index();     // pending | accepted | declined
            $t->string('intro_status', 12)->default('none');            // none | queued | sent | no_credit | blocked | failed
            $t->timestamp('decided_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('conversation_id')->nullable();
            $t->timestamps();
            $t->unique(['brand_partner_id', 'user_id']);
        });
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->string('connect_mode', 10)->default('open');            // open: connecting is instant | approve: the owner accepts or declines each one
            $t->boolean('intro_enabled')->default(false);
            $t->string('intro_text', 240)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', fn (Blueprint $t) => $t->dropColumn(['connect_mode', 'intro_enabled', 'intro_text']));
        Schema::dropIfExists('brand_connect_requests');
    }
};
