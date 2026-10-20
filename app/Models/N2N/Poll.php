<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** N2N poll data (MASTER-ONLY). */
class Poll extends Model
{
    public $timestamps = true;

    protected $table = 'n2n_polls';

    protected $fillable = ['message_id', 'question', 'multiple', 'closed_at'];

    protected function casts(): array
    {
        return ['multiple' => 'boolean', 'closed_at' => 'datetime'];
    }
}
