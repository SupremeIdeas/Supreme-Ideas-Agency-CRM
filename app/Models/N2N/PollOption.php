<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** N2N poll data (MASTER-ONLY). */
class PollOption extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_poll_options';

    protected $fillable = ['poll_id', 'position', 'text'];
}
