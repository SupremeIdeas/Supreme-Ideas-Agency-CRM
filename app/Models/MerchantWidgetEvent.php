<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only widget funnel event (MASTER-ONLY, blueprint §4.4). No updated_at on purpose. */
class MerchantWidgetEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['merchant_widget_key_id', 'event_type', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
