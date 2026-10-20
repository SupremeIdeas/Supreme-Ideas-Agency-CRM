<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A server-side cached provider thumbnail, so a profile page makes no third-party request before the visitor taps play (N2N, MASTER-ONLY). */
class Thumbnail extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_thumbnails';

    protected $fillable = ['provider', 'provider_id', 'disk', 'storage_key', 'status', 'fetched_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
