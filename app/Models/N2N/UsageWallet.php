<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A member's N2N usage-credit balances (MASTER-ONLY). Never redeemable at checkout, never withdrawable. */
class UsageWallet extends Model
{
    public $incrementing = false;

    protected $table = 'n2n_usage_wallets';

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'shareable', 'nonshareable'];

    protected function casts(): array
    {
        return ['shareable' => 'decimal:2', 'nonshareable' => 'decimal:2'];
    }
}
