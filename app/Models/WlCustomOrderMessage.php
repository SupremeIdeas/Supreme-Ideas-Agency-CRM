<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a White Label custom order thread between the buyer and the admin team (MASTER-ONLY). */
class WlCustomOrderMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'author_id', 'from_admin', 'body', 'created_at'];

    protected $casts = ['from_admin' => 'boolean', 'created_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
