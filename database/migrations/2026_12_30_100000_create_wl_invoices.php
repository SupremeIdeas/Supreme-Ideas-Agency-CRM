<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** White Label invoices, receipts, proformas and credit notes (Funnel Phase 6B, MASTER-ONLY). Issued documents are immutable snapshots; numbers are gapless per series and year. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wl_invoice_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('series', 12);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('last_number')->default(0);
            $t->unique(['series', 'year']);
        });
        Schema::create('wl_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('doc_type', 12);                         // invoice | receipt | credit_note | proforma
            $t->string('series', 12);
            $t->string('number', 60)->unique();
            $t->string('source_key', 80)->unique();             // idempotency: mp:{id}:invoice, mp:{id}:receipt, mp:{id}:balance ...
            $t->foreignId('funnel_instance_id')->nullable()->constrained('wl_funnel_instances')->nullOnDelete();
            $t->foreignId('white_label_instance_id')->nullable()->constrained('white_label_instances')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('parent_invoice_id')->nullable()->constrained('wl_invoices')->nullOnDelete();
            $t->string('status', 16)->default('issued');       // issued | partially_paid | paid | overdue | void
            $t->string('currency', 3)->default('USD');
            $t->decimal('subtotal', 12, 2);
            $t->decimal('tax_total', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->decimal('amount_paid', 12, 2)->default(0);
            $t->decimal('balance_due', 12, 2)->default(0);
            $t->timestamp('issued_at');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('public_token_hash', 64)->nullable()->unique();
            $t->json('seller_snapshot');
            $t->json('buyer_snapshot');
            $t->json('lines');
            $t->json('payments')->nullable();
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('emailed_at')->nullable();
            $t->timestamps();
            $t->index(['doc_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wl_invoices');
        Schema::dropIfExists('wl_invoice_sequences');
    }
};
