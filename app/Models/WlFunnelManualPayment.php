<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One manual payment attempt in the White Label funnel (MASTER-ONLY). */
class WlFunnelManualPayment extends Model
{
    public const AWAITING = 'awaiting_transfer';

    public const REVIEW = 'under_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const UNDERPAID = 'underpaid';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    public const PURPOSE_LICENSE = 'license';

    public const PURPOSE_BALANCE = 'balance';

    /** @var list<string> payments that still count as "the buyer's current one" */
    public const OPEN = [self::AWAITING, self::REVIEW, self::UNDERPAID];

    protected $fillable = ['funnel_instance_id', 'method', 'purpose', 'wl_invoice_id', 'account_id', 'plan_key', 'currency', 'network', 'price_usd', 'expected_amount', 'rate', 'rate_source', 'rate_as_of', 'locked_until', 'reference_code', 'status', 'payer_name', 'payer_bank', 'paid_at_claimed', 'tx_hash', 'proof', 'flags', 'proof_attempts', 'reviewer_id', 'reviewed_at', 'reject_reason', 'approved_amount', 'white_label_instance_id', 'issued_at'];

    protected $hidden = ['proof'];

    protected function casts(): array
    {
        return ['proof' => 'array', 'flags' => 'array', 'rate_as_of' => 'datetime', 'locked_until' => 'datetime', 'paid_at_claimed' => 'datetime', 'reviewed_at' => 'datetime', 'issued_at' => 'datetime',
            'price_usd' => 'decimal:2', 'expected_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'rate' => 'decimal:8'];
    }

    public function isBalance(): bool
    {
        return $this->purpose === self::PURPOSE_BALANCE;
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WlFunnelInstance::class, 'funnel_instance_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentAccount::class, 'account_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
