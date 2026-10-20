<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** White Label custom orders (Funnel Phase 8, MASTER-ONLY): a buyer describes a custom build, an admin quotes it, the buyer accepts and pays. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl_custom_orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('requested')->index();
            $t->string('brand_name', 120);
            $t->string('contact_email', 190);
            $t->string('contact_whatsapp', 40)->nullable();
            $t->json('modules')->nullable();
            $t->string('budget_band', 20)->nullable();
            $t->string('timeline', 20)->nullable();
            $t->text('brief');
            $t->string('reference_link', 300)->nullable();
            $t->decimal('quote_total_usd', 12, 2)->nullable();
            $t->json('quote_items')->nullable();
            $t->text('quote_note')->nullable();
            $t->timestamp('quote_valid_until')->nullable();
            $t->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('quoted_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('paid_via', 12)->nullable();
            $t->string('paid_reference', 120)->nullable();
            $t->decimal('paid_amount_usd', 12, 2)->nullable();
            $t->string('decline_reason', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('wl_custom_order_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('wl_custom_orders')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('from_admin')->default(false);
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl_custom_order_messages');
        Schema::dropIfExists('wl_custom_orders');
    }
};
