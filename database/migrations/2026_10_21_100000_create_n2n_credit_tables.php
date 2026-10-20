<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N Batch 5 (MASTER-ONLY): the usage-credit economy. A SEPARATE ledger from loyalty NaaraCredit (credit_ledger / user_wallets), so usage
 * credit can never reach checkout redemption or withdrawal: those code paths do not know these tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_usage_wallets', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->decimal('shareable', 14, 2)->default(0);       // purchased + subscription grant: may be shared onward
            $t->decimal('nonshareable', 14, 2)->default(0);    // received as a gift: spendable, never re-shareable (blocks farming rings)
            $t->timestamps();
        });

        Schema::create('n2n_usage_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('bucket', 16);                           // shareable | nonshareable | loyalty (loyalty rows mirror a CreditService spend)
            $t->string('type', 24);                             // purchase grant share_out share_in gift_escrow gift_refund gift_claim consume hold hold_release refund
            $t->decimal('amount', 14, 2);                       // signed: + adds credit, - removes it
            $t->decimal('balance_after', 14, 2)->nullable();
            $t->string('reference', 120);
            $t->unsignedBigInteger('counterparty_user_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'bucket', 'reference']);     // the idempotency boundary
            $t->index(['user_id', 'id']);
            $t->index(['type', 'created_at']);
        });

        Schema::create('n2n_usage_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 120)->unique();
            $t->decimal('amount', 14, 2);
            $t->json('parts');                                  // [{bucket, cents}] in the order they were taken
            $t->string('status', 12)->default('held');          // held | settled | released
            $t->decimal('settled_amount', 14, 2)->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('n2n_credit_gifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->string('recipient_kind', 8);                    // email | phone
            $t->string('recipient_hash', 64)->index();          // hmac of the normalised identifier; the clear value is never stored
            $t->string('recipient_masked', 60);
            $t->decimal('amount', 14, 2);
            $t->string('status', 12)->default('pending');       // pending | claimed | refunded | cancelled
            $t->timestamp('expires_at')->index();
            $t->string('idempotency_key', 64);
            $t->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->timestamps();
            $t->unique(['sender_id', 'idempotency_key']);
            $t->index(['status', 'expires_at']);
        });

        Schema::table('brand_subscription_plans', function (Blueprint $t) {
            $t->boolean('is_pro')->default(false);
            $t->decimal('n2n_credit_grant', 14, 2)->default(0);  // credits granted into usage_shareable on activation/renewal
        });
    }

    public function down(): void
    {
        Schema::table('brand_subscription_plans', fn (Blueprint $t) => $t->dropColumn(['is_pro', 'n2n_credit_grant']));
        Schema::dropIfExists('n2n_credit_gifts');
        Schema::dropIfExists('n2n_usage_holds');
        Schema::dropIfExists('n2n_usage_ledger');
        Schema::dropIfExists('n2n_usage_wallets');
    }
};
