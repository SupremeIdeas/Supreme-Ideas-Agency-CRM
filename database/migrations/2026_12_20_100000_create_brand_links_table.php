<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained('brand_partners')->cascadeOnDelete();
            $t->string('label', 40);
            $t->string('url', 500);                       // as entered
            $t->string('normalized_url', 500);
            $t->string('host', 253);
            $t->string('registrable_domain', 253);
            $t->string('tld', 63);
            $t->string('subdomain', 253)->nullable();
            $t->unsignedTinyInteger('resemblance_score')->default(0);
            $t->string('status', 12)->default('pending'); // pending | approved | rejected | suspended
            $t->string('reject_reason', 300)->nullable();
            $t->boolean('review_requested')->default(false);
            $t->timestamp('last_checked_at')->nullable();
            $t->unsignedSmallInteger('last_http_status')->nullable();
            $t->string('final_host', 253)->nullable();
            $t->string('safe_browsing_status', 10)->nullable();
            $t->unsignedSmallInteger('report_count')->default(0);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['brand_partner_id', 'status']);
            $t->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_links');
    }
};
