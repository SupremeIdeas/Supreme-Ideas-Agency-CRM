<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone a workspace member paired with the native softphone (MASTER-ONLY). No push token and no secret is stored here. */
class BusinessSoftphoneDevice extends Model
{
    /** A phone that has not asked for a token in this many days is probably offline for good: its push registration may have gone stale. */
    public const STALE_DAYS = 14;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['push_ready' => 'boolean', 'paired_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStale(): bool
    {
        return $this->last_seen_at === null || $this->last_seen_at->lt(now()->subDays(self::STALE_DAYS));
    }
}
