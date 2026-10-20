<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** follower -> followed (N2N Batch 9, MASTER-ONLY). Mutual rows are a connection; see App\Services\N2N\Connections\Connections. */
class Follow extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_follows';

    protected $fillable = ['follower_id', 'followed_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
