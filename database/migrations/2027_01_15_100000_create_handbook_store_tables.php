<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Travel Handbook Store (MASTER-ONLY): titles, series, taxonomy, editions, audiobooks, entitlements, reading state. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handbook_series', function (Blueprint $t) {
            $t->id();
            $t->string('country_code', 2)->nullable()->index();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('handbooks', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->string('catalogue_id', 20)->nullable()->unique();             // e.g. GBR-01, matches the title catalogue and the bulk import
            $t->string('country_code', 2)->nullable()->index();
            $t->string('series_slot', 3)->nullable();                          // S1 to S9
            $t->foreignId('series_id')->nullable()->constrained('handbook_series')->nullOnDelete();
            $t->string('title', 200);
            $t->string('subtitle', 240)->nullable();
            $t->text('description')->nullable();
            $t->string('language', 8)->default('en');
            $t->string('cover_path')->nullable();
            $t->string('epub_path')->nullable();
            $t->string('pdf_path')->nullable();
            $t->unsignedInteger('page_count')->nullable();
            $t->decimal('list_price_usd', 8, 2)->default(0);
            $t->string('status', 12)->default('draft')->index();              // draft | review | published | archived
            $t->date('last_verified_on')->nullable();
            $t->unsignedInteger('edition')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->string('isbn_ebook', 20)->nullable();
            $t->string('isbn_paperback', 20)->nullable();
            $t->string('isbn_hardcover', 20)->nullable();
            $t->string('sample_chapter_ref', 240)->nullable();                 // EPUB spine href of the free sample
            $t->json('toc')->nullable();                                       // extracted table of contents
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('handbook_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('name', 80);
            $t->timestamps();
        });
        Schema::create('handbook_category_handbook', function (Blueprint $t) {
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_category_id')->constrained()->cascadeOnDelete();
            $t->primary(['handbook_id', 'handbook_category_id'], 'hbk_category_handbook_pk');   // the auto name is 67 characters; MySQL allows 64
        });
        Schema::create('handbook_moods', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('name', 80);
            $t->timestamps();
        });
        Schema::create('handbook_handbook_mood', function (Blueprint $t) {
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_mood_id')->constrained()->cascadeOnDelete();
            $t->primary(['handbook_id', 'handbook_mood_id']);
        });
        Schema::create('handbook_external_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace', 24);                                     // amazon_kindle | amazon_paperback | amazon_hardcover | apple_books | google_play_books | other
            $t->string('url', 400)->nullable();
            $t->boolean('is_enabled')->default(false);
            $t->string('availability', 12)->default('coming_soon');           // coming_soon | live
            $t->timestamps();
            $t->unique(['handbook_id', 'marketplace']);
        });
        Schema::create('handbook_audiobooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('handbook_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('price_usd', 8, 2)->default(0);
            $t->boolean('stream_enabled')->default(true);
            $t->boolean('download_enabled')->default(false);
            $t->boolean('is_enabled')->default(false);
            $t->unsignedInteger('total_seconds')->default(0);
            $t->timestamps();
        });
        Schema::create('handbook_audio_tracks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audiobook_id')->constrained('handbook_audiobooks')->cascadeOnDelete();
            $t->unsignedInteger('position');
            $t->string('chapter_title', 200);
            $t->string('file_path');
            $t->unsignedInteger('seconds')->default(0);
            $t->timestamps();
        });
        Schema::create('handbook_editions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('edition');
            $t->string('epub_path')->nullable();
            $t->string('pdf_path')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['handbook_id', 'edition']);
        });
        Schema::create('handbook_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->string('level', 10);                                           // unlocked | owned
            $t->string('source', 12);                                          // esim_order | pro_slot | purchase | upgrade | admin
            $t->string('source_ref', 80)->nullable();                          // order id or payment reference
            $t->decimal('price_paid_usd', 8, 2)->default(0);                  // the price actually paid, never recomputed
            $t->timestamp('locked_at')->nullable();                            // a Pro slot unlock whose Pro term lapsed
            $t->timestamps();
            $t->unique(['user_id', 'handbook_id']);
        });
        Schema::create('handbook_audio_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('audiobook_id')->constrained('handbook_audiobooks')->cascadeOnDelete();
            $t->string('source_ref', 80)->nullable();
            $t->decimal('price_paid_usd', 8, 2)->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'audiobook_id']);
        });
        Schema::create('handbook_pro_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('pro_term', 40);                                        // sub:{brand subscription id} or grant:{brand id}
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->timestamp('used_at')->useCurrent();
            $t->unique(['user_id', 'pro_term', 'handbook_id']);
        });
        Schema::create('handbook_reading_state', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->string('locator', 400)->nullable();                            // EPUB CFI
            $t->decimal('percent', 5, 2)->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->unique(['user_id', 'handbook_id']);
        });
        Schema::create('handbook_bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->string('locator', 400);
            $t->string('label', 160)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'handbook_id']);
        });
        Schema::create('handbook_downloads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('handbook_id')->constrained()->cascadeOnDelete();
            $t->string('format', 8);                                           // epub | pdf | audio
            $t->unsignedInteger('edition')->default(1);
            $t->boolean('stamped')->default(true);
            $t->string('ip_hash', 40)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['handbook_downloads', 'handbook_bookmarks', 'handbook_reading_state', 'handbook_pro_slots', 'handbook_audio_entitlements', 'handbook_entitlements', 'handbook_editions', 'handbook_audio_tracks', 'handbook_audiobooks', 'handbook_external_links', 'handbook_handbook_mood', 'handbook_moods', 'handbook_category_handbook', 'handbook_categories', 'handbooks', 'handbook_series'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
