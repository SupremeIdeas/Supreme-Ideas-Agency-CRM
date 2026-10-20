<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 8 (MASTER-ONLY): richer directory profiles (general vs music), embeds, albums, display-only social links, and the local
 * (non-brand) member profile. brand_partner_handles is NOT touched: those rows carry Follow-Hunt credit_reward and verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $t) {
            $t->string('profile_type', 8)->default('general');          // general | music
            $t->timestamp('music_requested_at')->nullable();
            $t->timestamp('music_verified_at')->nullable();             // music limits apply ONLY once an admin confirmed
            $t->unsignedBigInteger('music_verified_by')->nullable();
        });

        Schema::create('n2n_brand_music_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 24);
            $t->string('url', 300);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('hidden_at')->nullable();                      // moderation: report -> hide
            $t->timestamp('created_at')->useCurrent();
            $t->index('brand_partner_id');
        });

        Schema::create('n2n_brand_embeds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 16);                                  // youtube vimeo spotify soundcloud bandcamp apple
            $t->string('provider_id', 190);                              // ONLY the parsed id/path: raw HTML is never stored
            $t->string('kind', 8)->default('video');                     // video | audio
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('hidden_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['brand_partner_id', 'provider', 'provider_id']);
        });

        Schema::create('n2n_brand_albums', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->string('title', 120);
            $t->string('slug', 80);
            $t->text('description')->nullable();
            $t->timestamp('hidden_at')->nullable();
            $t->timestamps();
            $t->unique(['brand_partner_id', 'slug']);
        });

        Schema::create('n2n_brand_album_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('album_id')->constrained('n2n_brand_albums')->cascadeOnDelete();   // album delete cascades
            $t->string('platform', 24);
            $t->string('url', 300);
            $t->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('n2n_brand_social_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 24);
            $t->string('url', 300);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('hidden_at')->nullable();
        });

        Schema::create('n2n_thumbnails', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 16);
            $t->string('provider_id', 190);
            $t->string('disk', 40)->nullable();
            $t->string('storage_key', 160)->nullable();
            $t->string('status', 8)->default('pending');                 // pending | ok | failed
            $t->timestamp('fetched_at')->nullable();
            $t->unique(['provider', 'provider_id']);
        });

        Schema::create('n2n_profile_pictures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('slot');                             // 1 or 2: two lightweight pictures
            $t->string('disk', 40);
            $t->string('storage_key', 160);
            $t->unsignedInteger('bytes');
            $t->timestamps();
            $t->unique(['user_id', 'slot']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('n2n_profile_visibility', 12)->default('connections');   // public | connections | private
            $t->json('n2n_profile_reveal')->nullable();                          // for `private`: which of naara_id/email/phone/images/bio/birthday to show
            $t->boolean('n2n_dob_show_year')->default(false);                    // birthday shows day and month only unless the member opts in
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['n2n_profile_visibility', 'n2n_profile_reveal', 'n2n_dob_show_year']));
        foreach (['n2n_profile_pictures', 'n2n_thumbnails', 'n2n_brand_social_links', 'n2n_brand_album_links', 'n2n_brand_albums', 'n2n_brand_embeds', 'n2n_brand_music_links'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('brand_partners', fn (Blueprint $t) => $t->dropColumn(['profile_type', 'music_requested_at', 'music_verified_at', 'music_verified_by']));
    }
};
