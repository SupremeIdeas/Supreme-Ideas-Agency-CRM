<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person's access to a handbook (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookEntitlement extends Model
{
    protected $table = 'handbook_entitlements';

    protected $fillable = ['user_id', 'handbook_id', 'level', 'source', 'source_ref', 'price_paid_usd', 'locked_at'];

    protected function casts(): array
    {
        return ['price_paid_usd' => 'decimal:2', 'locked_at' => 'datetime'];
    }

    public const UNLOCKED = 'unlocked';

    public const OWNED = 'owned';

    public function handbook(): BelongsTo
    {
        return $this->belongsTo(Handbook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
