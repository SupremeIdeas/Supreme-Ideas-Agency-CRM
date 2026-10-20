<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brand-Growth Phase 7 (MASTER-ONLY): the "follow started" record behind the return gate, plus clawback columns on the claim. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_reward_starts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handle_id')->nullable()->constrained('social_follow_handles')->cascadeOnDelete();
            $t->foreignId('brand_partner_handle_id')->nullable()->constrained('brand_partner_handles')->cascadeOnDelete();
            $t->timestamp('started_at');
            $t->string('ip_hash', 64)->nullable()->index();
            $t->string('ua_hash', 64)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'handle_id']);
            $t->unique(['user_id', 'brand_partner_handle_id']);
        });
        Schema::table('social_follow_claims', function (Blueprint $t) {
            $t->timestamp('clawed_back_at')->nullable();
            $t->foreignId('clawed_back_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('clawback_reason', 191)->nullable();
            $t->decimal('clawed_back_credits', 12, 2)->nullable();
            $t->string('ip_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('social_follow_claims', function (Blueprint $t) {
            $t->dropForeign(['clawed_back_by']);
            $t->dropIndex(['ip_hash']);
            $t->dropColumn(['clawed_back_at', 'clawed_back_by', 'clawback_reason', 'clawed_back_credits', 'ip_hash']);
        });
        Schema::dropIfExists('follow_reward_starts');
    }
};
