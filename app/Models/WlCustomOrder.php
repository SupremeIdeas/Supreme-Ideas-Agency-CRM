<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A White Label custom order (MASTER-ONLY): request, then admin quote, then accept, then paid. */
class WlCustomOrder extends Model
{
    public const REQUESTED = 'requested';

    public const QUOTED = 'quoted';

    public const ACCEPTED = 'accepted';

    public const PAID = 'paid';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** @var list<string> orders that still wait on someone */
    public const OPEN = [self::REQUESTED, self::QUOTED, self::ACCEPTED];

    protected $fillable = ['reference', 'user_id', 'status', 'brand_name', 'contact_email', 'contact_whatsapp', 'modules', 'budget_band', 'timeline', 'brief', 'reference_link', 'quote_total_usd', 'quote_items', 'quote_note', 'quote_valid_until', 'quoted_by', 'quoted_at', 'accepted_at', 'paid_at', 'paid_via', 'paid_reference', 'paid_amount_usd', 'decline_reason'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'quote_items' => 'array', 'quote_total_usd' => 'decimal:2', 'paid_amount_usd' => 'decimal:2',
            'quote_valid_until' => 'datetime', 'quoted_at' => 'datetime', 'accepted_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WlCustomOrderMessage::class, 'order_id')->orderBy('id');
    }

    public function quoteExpired(): bool
    {
        return $this->status === self::QUOTED && $this->quote_valid_until !== null && $this->quote_valid_until->isPast();
    }
}
