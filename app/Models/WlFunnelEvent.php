<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only funnel transition log (MASTER-ONLY): the analytics source and the audit trail. */
class WlFunnelEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['instance_id', 'from_status', 'to_status', 'actor', 'meta', 'created_at'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
}
