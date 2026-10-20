<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One billable speaking interval (N2N Batch 11, MASTER-ONLY): from appointment to un-appointment, on the server clock. */
class CallInterval extends Model
{
    protected $table = 'n2n_call_participant_intervals';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_at' => 'datetime', 'to_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
