<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A merchant's paid (or admin-granted) access to Naara Widgets (MASTER-ONLY). */
class MerchantWidgetAccessPurchase extends Model
{
    public const WALLET = 'wallet';

    public const GRANT = 'grant';

    protected $fillable = ['merchant_id', 'user_id', 'amount_usd', 'source', 'reference', 'granted_at'];

    protected function casts(): array
    {
        return ['amount_usd' => 'decimal:2', 'granted_at' => 'datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
