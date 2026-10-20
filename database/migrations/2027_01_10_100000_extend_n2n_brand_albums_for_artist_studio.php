<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Premium album studio (N2N, MASTER-ONLY): a cover, the distributor and its smart link, a release date, a publish switch and an order. Albums that already existed stay published. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_brand_albums', function (Blueprint $t) {
            $t->string('cover_path', 300)->nullable()->after('description');
            $t->string('distributor', 24)->nullable()->after('cover_path');
            $t->string('smart_url', 300)->nullable()->after('distributor');
            $t->date('release_date')->nullable()->after('smart_url');
            $t->timestamp('published_at')->nullable()->after('release_date');
            $t->unsignedSmallInteger('sort_order')->default(0)->after('published_at');
        });
        DB::table('n2n_brand_albums')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('n2n_brand_albums', function (Blueprint $t) {
            $t->dropColumn(['cover_path', 'distributor', 'smart_url', 'release_date', 'published_at', 'sort_order']);
        });
    }
};
