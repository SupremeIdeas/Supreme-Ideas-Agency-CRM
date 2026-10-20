<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelManualPayment as P;
use App\Models\WlInvoice;
use App\Support\Auditor;
use App\Support\BrandSettings;
use App\Support\Notifications\Notify;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues White Label documents (Funnel Phase 6B, MASTER-ONLY). One approved manual payment yields an Invoice (paid), a Receipt and, when the plan leaves a balance to Extended, a Balance Invoice
 * (issued, with a due date). Idempotent per source key, numbers are gapless, and every document renders from its own immutable snapshot so later price, address or logo edits never rewrite history.
 * Approval never depends on this: it runs after the licence is issued, and anything that fails here is retried by `wl-invoices:retry` and flagged by `naara:verify wl`.
 */
class WlInvoiceIssuer
{
    /** @return array{invoice: WlInvoice, receipt: WlInvoice, balance: ?WlInvoice} */
    public function forApprovedPayment(P $pay): array
    {
        $pay->loadMissing('instance.user');
        if ($pay->isBalance() || $pay->status !== P::APPROVED || $pay->white_label_instance_id === null) {
            throw new \InvalidArgumentException('Documents are only issued for an approved payment with a licence.');
        }
        $tokens = [];
        $docs = DB::transaction(function () use ($pay, &$tokens) {
            $inst = $pay->instance;
            $plan = WhiteLabelLicensePlan::query()->where('key', $pay->plan_key)->first();
            $seller = $this->sellerSnapshot();
            $buyer = $this->buyerSnapshot($inst);
            $tax = WlInvoiceSettings::tax();
            $priceUsd = (float) $pay->price_usd;
            $usdValue = round((float) $pay->approved_amount * (float) $pay->rate, 2);

            $lines = [['kind' => 'license', 'description' => ($plan?->name ?? $pay->plan_key).' White Label licence', 'plan_key' => $pay->plan_key, 'qty' => 1, 'unit_price' => $priceUsd, 'amount' => $priceUsd]];
            if ($usdValue + 0.00001 < $priceUsd) {
                $adj = round($usdValue - $priceUsd, 2);   // a shortfall inside the accepted tolerance: the invoice settles at what actually arrived
                $lines[] = ['kind' => 'adjustment', 'description' => 'Bank fee or rounding adjustment accepted', 'plan_key' => null, 'qty' => 1, 'unit_price' => $adj, 'amount' => $adj];
            }
            $subtotal = round(array_sum(array_column($lines, 'amount')), 2);
            $taxTotal = round($subtotal * $tax['rate'] / 100, 2);
            $total = round($subtotal + $taxTotal, 2);
            $applied = [['method' => 'manual', 'paid_currency' => $pay->currency, 'paid_amount' => (float) $pay->approved_amount, 'rate_used' => (float) $pay->rate, 'usd_value' => $usdValue, 'tx_hash' => $pay->tx_hash, 'reference' => $pay->reference_code, 'applied_at' => now()->toIso8601String()]];

            $invoice = $this->make($pay, 'mp:'.$pay->id.':invoice', WlInvoice::INVOICE, 'INV', $seller, $buyer, $lines, $subtotal, $taxTotal, $total, $total, 'paid', null, $applied, $tokens);
            $receipt = $this->make($pay, 'mp:'.$pay->id.':receipt', WlInvoice::RECEIPT, 'RCT', $seller, $buyer, [['kind' => 'receipt', 'description' => 'Payment received for invoice '.$invoice->number, 'plan_key' => null, 'qty' => 1, 'unit_price' => $usdValue, 'amount' => $usdValue]], $usdValue, 0.0, $usdValue, $usdValue, 'paid', $invoice->id, $applied, $tokens);

            $balance = null;
            $owed = $plan ? (float) WhiteLabelLicensePlan::balanceToExtended($plan) : 0.0;
            if ($owed > 0) {
                $due = WhiteLabelInstance::query()->find($pay->white_label_instance_id)?->upgrade_deadline_at ?? now()->addDays(WlInvoiceSettings::paymentTermsDays());
                $bLines = [['kind' => 'balance', 'description' => 'Balance to upgrade '.($plan->name ?? $pay->plan_key).' to Extended', 'plan_key' => $pay->plan_key, 'qty' => 1, 'unit_price' => $owed, 'amount' => $owed]];
                $bTax = round($owed * $tax['rate'] / 100, 2);
                $balance = $this->make($pay, 'mp:'.$pay->id.':balance', WlInvoice::INVOICE, 'INV', $seller, $buyer, $bLines, $owed, $bTax, round($owed + $bTax, 2), 0.0, 'issued', $invoice->id, [], $tokens, $due);
            }

            return compact('invoice', 'receipt', 'balance');
        });

        foreach (array_filter($docs) as $d) {
            if ($d->emailed_at === null && WlInvoiceSettings::autoSend() && $d->user) {
                $token = $tokens[$d->id] ?? $this->newPublicToken($d);
                Notify::event($d->user, $d->doc_type === WlInvoice::RECEIPT ? 'wl.receipt_issued' : 'wl.invoice_issued', ['number' => $d->number, 'url' => route('wl-invoice.show', $token), 'due' => $d->due_at?->format('Y-m-d') ?? '', 'balance' => number_format((float) $d->balance_due, 2)], 'wl-doc-'.$d->id);
                $d->forceFill(['emailed_at' => now()])->save();
            }
        }

        return $docs;
    }

    /**
     * A proforma for the payment the buyer is about to make (Funnel Phase 6B, MASTER-ONLY): what they will pay, in which currency, with which reference, quoted at the locked rate. Not a tax invoice; the
     * invoice and receipt are issued only when an admin approves the payment. Idempotent per payment, numbered in its own PRO series, and rendered from its own snapshot like every other document.
     */
    public function proformaForPayment(P $pay): WlInvoice
    {
        $pay->loadMissing('instance.user');
        if ($pay->isBalance() || $pay->instance === null) {
            throw new \InvalidArgumentException('A proforma is only issued for a licence payment.');
        }
        $tokens = [];
        $inv = DB::transaction(function () use ($pay, &$tokens) {
            $plan = WhiteLabelLicensePlan::query()->where('key', $pay->plan_key)->first();
            $tax = WlInvoiceSettings::tax();
            $price = (float) $pay->price_usd;
            $lines = [['kind' => 'license', 'description' => ($plan?->name ?? $pay->plan_key).' White Label licence', 'plan_key' => $pay->plan_key, 'qty' => 1, 'unit_price' => $price, 'amount' => $price]];
            $taxTotal = round($price * $tax['rate'] / 100, 2);
            $total = round($price + $taxTotal, 2);
            $seller = $this->sellerSnapshot();

            return $this->make($pay, 'mp:'.$pay->id.':proforma', WlInvoice::PROFORMA, 'PRO', $seller, $this->buyerSnapshot($pay->instance), $lines, $price, $taxTotal, $total, 0.0, 'issued', null, [], $tokens, $pay->locked_until);
        });
        if ($inv->wasRecentlyCreated) {
            $how = $pay->currency === 'USD' ? number_format((float) $pay->expected_amount, 2).' USD' : number_format((float) $pay->expected_amount, in_array($pay->currency, ['USDT', 'USDC'], true) ? 2 : 2).' '.$pay->currency.($pay->network ? ' on '.$pay->network : '').' (quoted at 1 '.$pay->currency.' = '.rtrim(rtrim(number_format((float) $pay->rate, 6, '.', ''), '0'), '.').' USD)';
            $inv->forceFill([
                'terms' => 'This is a proforma: it is not a tax invoice and nothing is owed until you pay. Pay '.$how.' using payment reference '.$pay->reference_code.'.'.($pay->locked_until ? ' The quote is locked until '.$pay->locked_until->format('Y-m-d H:i').' UTC.' : '').' Your invoice and receipt are issued once the payment is approved.',
                'meta' => array_merge((array) $inv->meta, ['expected_amount' => (float) $pay->expected_amount, 'currency' => $pay->currency, 'network' => $pay->network, 'rate' => (float) $pay->rate]),
            ])->save();
        }

        return $inv;
    }

    /** The balance to Extended was paid by transfer: mark the balance invoice paid and issue a receipt. Idempotent per payment. */
    public function forBalancePayment(P $pay): WlInvoice
    {
        $pay->loadMissing('instance.user');
        $tokens = [];
        $receipt = DB::transaction(function () use ($pay, &$tokens) {
            $inv = WlInvoice::query()->whereKey($pay->wl_invoice_id)->lockForUpdate()->firstOrFail();
            $usdValue = min(round((float) $pay->approved_amount * (float) $pay->rate, 2), (float) $inv->total);
            if ($inv->status !== 'paid') {
                $applied = [['method' => 'manual', 'paid_currency' => $pay->currency, 'paid_amount' => (float) $pay->approved_amount, 'rate_used' => (float) $pay->rate, 'usd_value' => $usdValue, 'tx_hash' => $pay->tx_hash, 'reference' => $pay->reference_code, 'applied_at' => now()->toIso8601String()]];
                $inv->forceFill(['status' => 'paid', 'amount_paid' => $inv->total, 'balance_due' => 0, 'paid_at' => now(), 'payments' => array_merge((array) $inv->payments, $applied)])->save();
            }
            $applied = (array) $inv->payments;

            return $this->make($pay, 'mp:'.$pay->id.':receipt', WlInvoice::RECEIPT, 'RCT', (array) $inv->seller_snapshot, (array) $inv->buyer_snapshot, [['kind' => 'receipt', 'description' => 'Payment received for invoice '.$inv->number, 'plan_key' => null, 'qty' => 1, 'unit_price' => $usdValue, 'amount' => $usdValue]], $usdValue, 0.0, $usdValue, $usdValue, 'paid', $inv->id, $applied, $tokens);
        });
        if ($receipt->emailed_at === null && WlInvoiceSettings::autoSend() && $receipt->user) {
            $token = $tokens[$receipt->id] ?? $this->newPublicToken($receipt);
            Notify::event($receipt->user, 'wl.receipt_issued', ['number' => $receipt->number, 'url' => route('wl-invoice.show', $token)], 'wl-doc-'.$receipt->id);
            $receipt->forceFill(['emailed_at' => now()])->save();
        }

        return $receipt;
    }

    /** @param  array<string,string>  $tokens filled with the raw public token of each NEW document (only the hash is stored) */
    private function make(P $pay, string $key, string $type, string $series, array $seller, array $buyer, array $lines, float $subtotal, float $tax, float $total, float $paid, string $status, ?int $parent, array $payments, array &$tokens, $due = null): WlInvoice
    {
        if ($existing = WlInvoice::query()->where('source_key', $key)->first()) {
            return $existing;
        }
        $token = Str::random(48);
        $inv = WlInvoice::query()->create([
            'doc_type' => $type, 'series' => $series, 'number' => WlInvoiceNumbering::next($series), 'source_key' => $key, 'funnel_instance_id' => $pay->funnel_instance_id, 'white_label_instance_id' => $pay->white_label_instance_id,
            'user_id' => $pay->instance->user_id, 'parent_invoice_id' => $parent, 'status' => $status, 'currency' => 'USD', 'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total, 'amount_paid' => $paid, 'balance_due' => round($total - $paid, 2),
            'issued_at' => now(), 'due_at' => $due ?? ($type === WlInvoice::INVOICE && $paid < $total ? now()->addDays(WlInvoiceSettings::paymentTermsDays()) : null), 'paid_at' => $status === 'paid' ? now() : null, 'public_token_hash' => WlInvoice::hashToken($token),
            'seller_snapshot' => $seller, 'buyer_snapshot' => $buyer, 'lines' => $lines, 'payments' => $payments, 'terms' => $seller['terms'] ?: null, 'notes' => $seller['notes'] ?: null,
            'meta' => ['funnel_code' => $pay->instance->public_code, 'reference' => $pay->reference_code],
        ]);
        $tokens[$inv->id] = $token;
        Auditor::log('wl.invoice_issued', WlInvoice::class, $inv->id, ['number' => $inv->number, 'type' => $type, 'total' => $total]);

        return $inv;
    }

    /** A fresh public link token (the old one stops working). Only the hash is stored. */
    public function newPublicToken(WlInvoice $inv): string
    {
        $token = Str::random(48);
        $inv->forceFill(['public_token_hash' => WlInvoice::hashToken($token)])->save();

        return $token;
    }

    /** The logos and seller text as they are at issue time, copied into the document (so changing them later never alters it). */
    private function sellerSnapshot(): array
    {
        $s = WlInvoiceSettings::seller();
        $s['logos'] = array_values(array_filter([WlInvoiceLogos::dataUri('family') ?? WlInvoiceLogos::dataUri('product'), WlInvoiceLogos::dataUri('agency')]));

        return $s;
    }

    private function buyerSnapshot($inst): array
    {
        $d = (array) $inst->draft;

        return ['name' => (string) ($d['billing_name'] ?? $inst->user?->name), 'address' => (string) ($d['billing_address'] ?? ''), 'country' => (string) ($d['billing_country'] ?? ''), 'tax_id' => (string) ($d['billing_tax_id'] ?? ''), 'email' => (string) ($d['billing_email'] ?? $inst->user?->email), 'brand' => (string) ($d['brand_name'] ?? ''), 'funnel_code' => $inst->public_code];
    }

    /** Render a stored document to PDF (from its snapshot only; no remote fetches). */
    public function pdf(WlInvoice $inv): string
    {
        return Pdf::setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])->loadView('pdf.wl-invoice', ['inv' => $inv, 'brand' => BrandSettings::name()])->output();
    }
}
