<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** N2N poll data (MASTER-ONLY). */
class PollVote extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_poll_votes';

    protected $fillable = ['poll_id', 'option_id', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
