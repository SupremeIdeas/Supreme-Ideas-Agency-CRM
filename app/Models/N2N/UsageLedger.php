<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One append-only usage-credit movement (MASTER-ONLY). */
class UsageLedger extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_usage_ledger';

    protected $fillable = ['user_id', 'bucket', 'type', 'amount', 'balance_after', 'reference', 'counterparty_user_id', 'meta'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_after' => 'decimal:2', 'meta' => 'array', 'created_at' => 'datetime'];
    }
}
