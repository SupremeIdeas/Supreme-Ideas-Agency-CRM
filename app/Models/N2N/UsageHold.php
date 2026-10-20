<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** Credit earmarked for a metered action that settles later, e.g. a call (MASTER-ONLY). */
class UsageHold extends Model
{
    protected $table = 'n2n_usage_holds';

    protected $fillable = ['user_id', 'reference', 'amount', 'parts', 'status', 'settled_amount', 'settled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'settled_amount' => 'decimal:2', 'parts' => 'array', 'settled_at' => 'datetime'];
    }
}
