<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Daily Business Card counters per brand: page views, QR scans and contact-card saves (MASTER-ONLY). Counts only, no visitor data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesscard_stats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_partner_id')->constrained('brand_partners')->cascadeOnDelete();
            $t->date('day');
            $t->string('metric', 12);   // view | qr_scan | vcard
            $t->unsignedInteger('total')->default(0);
            $t->unique(['brand_partner_id', 'day', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesscard_stats');
    }
};
