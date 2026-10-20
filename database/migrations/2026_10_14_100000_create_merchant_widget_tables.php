<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Widgets (merchant embed + WordPress loader) — MASTER-ONLY. docs/build-specs/NAARA-MERCHANT-WIDGETS-BLUEPRINT.md §4.
 * Table names carry `merchant_widget` so App\Support\MasterOnlyModules can see (and refuse to ship) them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_widget_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->string('public_key')->unique();            // wk_live_… — safe to expose; identifies, never authorises on its own (§5.1)
            $t->string('secret_hash');                     // sha256 of the one-time secret (WordPress activation ping only)
            $t->json('allowed_domains');                   // exact hosts, no wildcards in v1 (§13.4)
            $t->json('product_scopes');                    // subset of esim|number|giftcard
            $t->json('theme')->nullable();
            $t->unsignedInteger('catalogue_rate_per_min')->nullable();
            $t->unsignedInteger('checkout_rate_per_min')->nullable();
            $t->string('status')->default('active');       // active | revoked
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoked_reason')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('merchant_widget_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_widget_key_id')->nullable()->constrained('merchant_widget_keys')->nullOnDelete();
            $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('buyer_email')->nullable();
            $t->string('kind');                            // esim | giftcard (number is scoped but not yet purchasable — see PROGRESS)
            $t->unsignedBigInteger('product_id');
            $t->decimal('face_value', 18, 4)->nullable();  // gift cards
            $t->json('buyer_fields')->nullable();          // gift-card delivery fields the shopper filled in (recipient email etc.)
            $t->string('reference')->unique();             // merchantwidget:{public_key}:{idempotency_key} — the idempotency boundary
            $t->string('session_token_hash')->nullable()->unique(); // sha256 of the single-use hosted-checkout token
            $t->timestamp('session_expires_at')->nullable();
            $t->timestamp('session_used_at')->nullable();
            $t->string('status')->default('pending_payment'); // pending_payment | fulfilling | completed | failed | refunded | held
            $t->decimal('retail_price_usd', 18, 4);
            $t->decimal('merchant_price_usd', 18, 4);
            $t->string('currency', 3)->default('USD');
            $t->string('payment_gateway')->nullable();
            $t->string('payment_reference')->nullable()->index(); // provider reference — links the credited top-up back here (§7.1 step 6)
            $t->foreignId('esim_order_id')->nullable()->constrained('esim_orders')->nullOnDelete();
            $t->foreignId('gift_card_order_id')->nullable()->constrained('gift_card_orders')->nullOnDelete();
            $t->json('result')->nullable();                // masked delivery payload only
            $t->string('failure_reason')->nullable();
            $t->string('origin_domain')->nullable();
            $t->timestamps();
        });

        Schema::create('merchant_widget_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('merchant_widget_key_id')->index();
            $t->string('event_type');                      // impression | catalogue_view | checkout_opened | checkout_abandoned
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();     // append-only log: no updated_at, outside the money transaction
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_widget_events');
        Schema::dropIfExists('merchant_widget_orders');
        Schema::dropIfExists('merchant_widget_keys');
    }
};
