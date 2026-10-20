<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private admin note on a White Label purchase (MASTER-ONLY). */
class WlFunnelNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['funnel_instance_id', 'admin_id', 'body', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
