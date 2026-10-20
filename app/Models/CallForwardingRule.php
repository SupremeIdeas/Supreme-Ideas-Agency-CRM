<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A call-forwarding rule for a permanent NaaraSim number (Live Voice — Part A).
 * The voice webhook resolves the inbound call's destination to this rule and
 * dials the target. Supplier masking still applies — the user only ever sees
 * their NaaraSim number and their own forward target.
 */
class CallForwardingRule extends Model
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'user_id', 'virtual_number_id', 'twilio_number', 'forward_to_number',
        'fallback_number', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function virtualNumber(): BelongsTo
    {
        return $this->belongsTo(VirtualNumber::class);
    }

    /**
     * Rules owned by one voice provider. A rule written before providers were tracked (no linked number) belongs to Twilio,
     * the only voice provider that existed then.
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where(function (Builder $q) use ($provider) {
            if ($provider === 'twilio') {
                $q->whereNull('virtual_number_id');
            }
            $q->orWhereHas('virtualNumber', fn ($n) => $n->where('provider', $provider));
        });
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
