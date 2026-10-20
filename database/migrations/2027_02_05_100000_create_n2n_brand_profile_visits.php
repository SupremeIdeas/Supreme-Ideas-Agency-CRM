<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MASTER-ONLY: who looked at a brand profile (one row per visitor per day) + the brand's own chat switch + a visitor's privacy choice. Idempotent (MySQL DDL is not transactional). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('n2n_brand_profile_visits')) {
            Schema::create('n2n_brand_profile_visits', function (Blueprint $t) {
                $t->id();
                $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
                $t->foreignId('visitor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('visitor_key', 48);                 // u<id> for a member, g<sha1> for a signed-out visitor (never the raw IP)
                $t->date('visited_on');
                $t->unsignedInteger('hits')->default(1);
                $t->timestamp('first_at')->nullable();
                $t->timestamp('last_at')->nullable();
                $t->unique(['brand_partner_id', 'visitor_key', 'visited_on'], 'n2nbv_brand_key_day_uq');
                $t->index(['brand_partner_id', 'last_at'], 'n2nbv_brand_last_idx');
            });
        }
        if (! Schema::hasColumn('brand_partners', 'chat_mode')) {
            Schema::table('brand_partners', fn (Blueprint $t) => $t->string('chat_mode', 12)->default('open'));   // open | connections
        }
        if (! Schema::hasColumn('users', 'n2n_private_visits')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('n2n_private_visits')->default(false));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_brand_profile_visits');
        if (Schema::hasColumn('brand_partners', 'chat_mode')) {
            Schema::table('brand_partners', fn (Blueprint $t) => $t->dropColumn('chat_mode'));
        }
        if (Schema::hasColumn('users', 'n2n_private_visits')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('n2n_private_visits'));
        }
    }
};
