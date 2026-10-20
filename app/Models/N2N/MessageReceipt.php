<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** Delivered / read marker for one recipient of one message (N2N, MASTER-ONLY). */
class MessageReceipt extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_message_receipts';

    protected $fillable = ['message_id', 'user_id', 'delivered_at', 'read_at'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
