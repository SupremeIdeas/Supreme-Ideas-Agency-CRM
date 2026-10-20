<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Brand-Growth Phase 6 (MASTER-ONLY). */
class ConnectionIntent extends Model
{
    public const PENDING = 'pending';

    public const CONNECTED = 'connected';

    public const DISMISSED = 'dismissed';

    protected $fillable = ['user_id', 'brand_partner_id', 'source', 'status', 'prompted_at', 'popup_count', 'popup_last_at', 'resolved_at'];

    protected $casts = ['prompted_at' => 'datetime', 'popup_last_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }
}
