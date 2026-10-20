<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White Label manual payments (Funnel blueprint 5.1). Receiving accounts are ADMIN-EDITABLE data, never code: the owner
 * can replace them (e.g. with the Grey business account details) at any time. Field values are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 8);                    // USD GBP EUR USDT USDC ...
            $table->string('kind', 12)->default('bank');      // bank | crypto
            $table->string('network', 24)->nullable();        // crypto only: TRC20 BEP20 Solana ...
            $table->string('label');
            $table->text('fields');                           // encrypted:array  [{label, value}, ...]
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamp('test_confirmed_at')->nullable(); // an owner test transfer/transaction was sent and seen
            $table->string('test_note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['currency', 'enabled', 'sort']);
        });

        // Every change to a receiving account (anti address-swap): who, when, why, and a fingerprint of the old/new values.
        Schema::create('manual_payment_account_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('manual_payment_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 24);                     // created | fields_changed | enabled | disabled | test_confirmed | deleted
            $table->string('reason', 255)->nullable();
            $table->string('before_hash', 64)->nullable();
            $table->string('after_hash', 64)->nullable();
            $table->timestamps();
        });

        // Rates are a snapshot with an as_of, not a feed (blueprint section 2.4). source: grey_live | manual.
        Schema::create('manual_payment_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 8);
            $table->string('to_currency', 8)->default('USD');
            $table->decimal('rate', 18, 8);                   // net value of 1 unit of from_currency, in to_currency
            $table->string('source', 16);
            $table->timestamp('as_of');
            $table->string('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['from_currency', 'to_currency', 'source', 'as_of'], 'mpr_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_rates');
        Schema::dropIfExists('manual_payment_account_changes');
        Schema::dropIfExists('manual_payment_accounts');
    }
};
