<?php

namespace App\Models\JourneyEngine;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing a user is here for (travel, verify, line...). An inferred intent stays a suggestion until confirmed_at is set. */
class UserIntent extends Model
{
    protected $fillable = ['user_id', 'intent', 'source', 'confidence', 'confirmed_at', 'removed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'removed_at' => 'datetime', 'confidence' => 'decimal:3'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
