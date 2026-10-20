<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** A webhook body stored exactly as received, before any processing (MASTER-ONLY). */
class OmniRawEvent extends Model
{
    public $timestamps = false;

    protected $table = 'omni_raw_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
