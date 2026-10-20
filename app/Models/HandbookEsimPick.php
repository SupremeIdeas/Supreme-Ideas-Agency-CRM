<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A regional/global eSIM's one free handbook, waiting for the person to choose (MASTER-ONLY). */
class HandbookEsimPick extends Model
{
    protected $fillable = ['user_id', 'esim_order_id', 'country_codes', 'picks_allowed', 'chosen_ids', 'handbook_id', 'picked_at'];

    protected function casts(): array
    {
        return ['country_codes' => 'array', 'chosen_ids' => 'array', 'picks_allowed' => 'integer', 'picked_at' => 'datetime'];
    }

    public function picksLeft(): int
    {
        return max(0, (int) $this->picks_allowed - count((array) $this->chosen_ids));
    }

    public function handbook(): BelongsTo
    {
        return $this->belongsTo(Handbook::class);
    }
}
