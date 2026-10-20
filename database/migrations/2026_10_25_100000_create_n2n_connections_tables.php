<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 9 (MASTER-ONLY): user->user follow graph (brand-owner follow-back reuses it; brand_partner_followers stays the user->brand
 * page follow), Updates seen state and reactions, invite bookkeeping, message quote context, and the brand's Updates privacy setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['follower_id', 'followed_id']);
            $t->index('followed_id');
        });

        Schema::create('n2n_update_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->timestamp('last_seen_at');                              // seen = last_seen_at >= the brand's latest gallery change
            $t->unsignedBigInteger('last_item_id')->nullable();
            $t->unique(['viewer_id', 'brand_partner_id']);
        });

        Schema::create('n2n_update_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('item_id');                          // brand_partner_images.id
            $t->string('emoji', 16);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['viewer_id', 'item_id']);                       // one reaction per viewer per item (changing it replaces it)
        });

        Schema::create('n2n_invites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $t->string('kind', 8);                                      // email | phone | link
            $t->string('target_hash', 64)->nullable();                  // keyed hash only: the clear address is never stored
            $t->timestamp('created_at')->useCurrent();
            $t->index(['inviter_id', 'created_at']);
        });

        Schema::table('n2n_messages', function (Blueprint $t) {
            $t->json('quote_json')->nullable();                         // context of what this message replies to (an Update)
        });
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->string('n2n_updates_visibility', 12)->default('connections');   // connections | nobody
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', fn (Blueprint $t) => $t->dropColumn('n2n_updates_visibility'));
        Schema::table('n2n_messages', fn (Blueprint $t) => $t->dropColumn('quote_json'));
        foreach (['n2n_invites', 'n2n_update_reactions', 'n2n_update_views', 'n2n_follows'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
