<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Widgets paid access (MASTER-ONLY). One row per merchant: the merchant bought (or was granted) the right to create widget keys,
 * copy the snippet and download the plugin. The unique merchant_id is the idempotency boundary against a double click.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_widget_access_purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('amount_usd', 12, 2)->default(0);   // price at the moment of purchase; 0 for an admin grant
            $t->string('source', 20)->default('wallet');    // wallet | grant
            $t->string('reference')->unique();
            $t->timestamp('granted_at')->useCurrent();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_widget_access_purchases');
    }
};
