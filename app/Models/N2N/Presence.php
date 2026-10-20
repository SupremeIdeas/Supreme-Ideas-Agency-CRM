<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** When a user was last seen active (N2N, MASTER-ONLY). */
class Presence extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'n2n_presence';

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
