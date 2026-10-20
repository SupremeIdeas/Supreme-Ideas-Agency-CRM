<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One White Label invoice, receipt, proforma or credit note (MASTER-ONLY). Immutable after issue: it renders from its own snapshots. */
class WlInvoice extends Model
{
    public const INVOICE = 'invoice';

    public const RECEIPT = 'receipt';

    public const PROFORMA = 'proforma';

    public const CREDIT_NOTE = 'credit_note';

    protected $fillable = ['doc_type', 'series', 'number', 'source_key', 'funnel_instance_id', 'white_label_instance_id', 'user_id', 'parent_invoice_id', 'status', 'currency', 'subtotal', 'tax_total', 'total', 'amount_paid', 'balance_due', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'public_token_hash', 'seller_snapshot', 'buyer_snapshot', 'lines', 'payments', 'terms', 'notes', 'meta', 'emailed_at'];

    protected $hidden = ['public_token_hash'];

    protected function casts(): array
    {
        return ['seller_snapshot' => 'array', 'buyer_snapshot' => 'array', 'lines' => 'array', 'payments' => 'array', 'meta' => 'array', 'issued_at' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime', 'emailed_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance_due' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    public function isFiscal(): bool
    {
        return in_array($this->doc_type, [self::INVOICE, self::CREDIT_NOTE], true);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
