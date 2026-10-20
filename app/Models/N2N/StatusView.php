<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** Which status posts a viewer has opened (MASTER-ONLY). */
class StatusView extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_status_views';

    protected $fillable = ['status_id', 'viewer_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
