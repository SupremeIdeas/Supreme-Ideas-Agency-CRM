<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentAccountChange extends Model
{
    protected $fillable = ['account_id', 'user_id', 'action', 'reason', 'before_hash', 'after_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
