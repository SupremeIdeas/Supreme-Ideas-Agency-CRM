<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\WlInvoice;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cancels a White Label invoice by issuing a credit note (MASTER-ONLY). The original is never edited or deleted: it is marked void (unpaid) or credited (paid) and a gapless CN document
 * mirrors it with negative amounts. This does NOT move money or touch the licence: refunds and licence decisions stay a deliberate admin action. Idempotent per invoice.
 */
class WlInvoiceVoider
{
    public function creditNote(WlInvoice $inv, string $reason, ?int $adminId = null): WlInvoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Say why this document is being cancelled.');
        }

        return DB::transaction(function () use ($inv, $reason, $adminId) {
            $inv = WlInvoice::query()->whereKey($inv->id)->lockForUpdate()->firstOrFail();
            if ($inv->doc_type !== WlInvoice::INVOICE) {
                throw new \InvalidArgumentException('Only an invoice can be cancelled with a credit note.');
            }
            if ($existing = WlInvoice::query()->where('source_key', 'cn:'.$inv->id)->first()) {
                return $existing;
            }
            if (! in_array($inv->status, ['issued', 'paid'], true)) {
                throw new \InvalidArgumentException('This invoice is already cancelled.');
            }
            $lines = array_map(fn ($l) => array_merge($l, ['unit_price' => -1 * (float) $l['unit_price'], 'amount' => -1 * (float) $l['amount']]), (array) $inv->lines);
            $wasPaid = $inv->status === 'paid';
            $cn = WlInvoice::query()->create([
                'doc_type' => WlInvoice::CREDIT_NOTE, 'series' => 'CN', 'number' => WlInvoiceNumbering::next('CN'), 'source_key' => 'cn:'.$inv->id,
                'funnel_instance_id' => $inv->funnel_instance_id, 'white_label_instance_id' => $inv->white_label_instance_id, 'user_id' => $inv->user_id, 'parent_invoice_id' => $inv->id,
                'status' => 'paid', 'currency' => $inv->currency, 'subtotal' => -1 * (float) $inv->subtotal, 'tax_total' => -1 * (float) $inv->tax_total, 'total' => -1 * (float) $inv->total,
                'amount_paid' => 0, 'balance_due' => 0, 'issued_at' => now(), 'public_token_hash' => WlInvoice::hashToken(Str::random(48)),
                'seller_snapshot' => $inv->seller_snapshot, 'buyer_snapshot' => $inv->buyer_snapshot, 'lines' => $lines, 'payments' => [],
                'terms' => $inv->terms, 'notes' => 'Credit note for '.$inv->number.'. Reason: '.$reason,
                'meta' => array_merge((array) $inv->meta, ['reason' => $reason, 'credited_invoice' => $inv->number, 'was_paid' => $wasPaid, 'refund_handled_separately' => $wasPaid]),
            ]);
            $inv->forceFill(['status' => 'void', 'voided_at' => now(), 'balance_due' => 0])->save();
            Auditor::log('wl.invoice_voided', WlInvoice::class, $inv->id, ['credit_note' => $cn->number, 'reason' => mb_substr($reason, 0, 200), 'was_paid' => $wasPaid, 'by' => $adminId]);

            return $cn;
        });
    }
}
