<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** `blocker` no longer receives anything from `blocked`; the blocked person is never told (N2N, MASTER-ONLY). */
class Block extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_blocks';

    protected $fillable = ['blocker_id', 'blocked_id', 'created_at'];
}
