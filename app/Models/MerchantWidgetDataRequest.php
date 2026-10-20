<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shopper's data request made from the widget (MASTER-ONLY): an export (instant, logged) or a deletion (an admin approves: deletion is never self-serve). */
class MerchantWidgetDataRequest extends Model
{
    public const EXPORT = 'export';

    public const DELETE = 'delete';

    protected $fillable = ['merchant_id', 'email', 'kind', 'status', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
