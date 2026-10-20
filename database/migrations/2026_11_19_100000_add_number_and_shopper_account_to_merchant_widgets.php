<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Widgets (MASTER-ONLY): verification numbers can be bought through a widget, and a returning shopper gets a small "my orders" area on the merchant's own site.
 * Table names carry `merchant_widget` so App\Support\MasterOnlyModules sees (and refuses to ship) them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_widget_orders', function (Blueprint $t) {
            $t->string('product_ref', 160)->nullable()->after('product_id');          // number orders: otp:<service>:<country>
            $t->unsignedBigInteger('sms_order_id')->nullable()->index()->after('gift_card_order_id');
            $t->timestamp('earned_at')->nullable();                                    // number orders: the merchant margin is accrued once the code arrives
        });

        // A shopper proves their email with a short code, then holds a long random token. Only hashes are stored.
        Schema::create('merchant_widget_shopper_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('code_hash')->nullable();
            $t->timestamp('code_expires_at')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('token_hash')->nullable()->unique();
            $t->timestamp('token_expires_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['merchant_id', 'email']);
        });

        // Export is instant; deletion is a request an admin approves (account deletion is never self-serve).
        Schema::create('merchant_widget_data_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('kind', 16);                         // export | delete
            $t->string('status', 16)->default('open');      // open | done
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_widget_data_requests');
        Schema::dropIfExists('merchant_widget_shopper_sessions');
        Schema::table('merchant_widget_orders', function (Blueprint $t) {
            $t->dropColumn(['product_ref', 'sms_order_id', 'earned_at']);
        });
    }
};
