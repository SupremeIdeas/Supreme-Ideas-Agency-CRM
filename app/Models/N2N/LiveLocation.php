<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** The latest position of a live-location message (N2N, MASTER-ONLY). Coordinates are nulled when sharing ends. */
class LiveLocation extends Model
{
    protected $table = 'n2n_live_locations';

    protected $guarded = [];

    protected $hidden = ['lat', 'lng'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'stopped_at' => 'datetime', 'last_update_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }

    public function active(): bool
    {
        return $this->stopped_at === null && $this->expires_at->isFuture();
    }
}
