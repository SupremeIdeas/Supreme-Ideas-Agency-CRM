<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One person's after-call answer (MASTER-ONLY). */
class CallRating extends Model
{
    protected $table = 'n2n_call_ratings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['trustpilot_opened' => 'boolean', 'video' => 'boolean', 'stars' => 'integer'];
    }
}
