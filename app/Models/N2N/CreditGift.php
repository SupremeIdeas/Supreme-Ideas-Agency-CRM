<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A gift of usage credit to someone who is not on Naara yet, held in escrow for up to 24h (MASTER-ONLY). */
class CreditGift extends Model
{
    public const PENDING = 'pending';

    public const CLAIMED = 'claimed';

    public const REFUNDED = 'refunded';

    public const CANCELLED = 'cancelled';

    protected $table = 'n2n_credit_gifts';

    protected $fillable = [
        'sender_id', 'recipient_kind', 'recipient_hash', 'recipient_masked', 'amount', 'status', 'expires_at', 'idempotency_key',
        'claimed_by_user_id', 'claimed_at', 'refunded_at',
    ];

    protected $hidden = ['recipient_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'datetime', 'claimed_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
