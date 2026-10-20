<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A returning shopper's proof of email on one merchant's widget (MASTER-ONLY): a short emailed code, then a long random token. Only hashes are stored. */
class MerchantWidgetShopperSession extends Model
{
    protected $fillable = ['merchant_id', 'email', 'code_hash', 'code_expires_at', 'attempts', 'token_hash', 'token_expires_at', 'last_used_at', 'ip'];

    protected $hidden = ['code_hash', 'token_hash'];

    protected function casts(): array
    {
        return ['code_expires_at' => 'datetime', 'token_expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }
}
