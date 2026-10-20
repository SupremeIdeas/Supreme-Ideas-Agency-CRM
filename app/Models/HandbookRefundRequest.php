<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer's request to be refunded for a handbook (MASTER-ONLY). Decided by a person, never automatically. */
class HandbookRefundRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = ['user_id', 'handbook_id', 'entitlement_id', 'source', 'amount_usd', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['amount_usd' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handbook(): BelongsTo
    {
        return $this->belongsTo(Handbook::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
