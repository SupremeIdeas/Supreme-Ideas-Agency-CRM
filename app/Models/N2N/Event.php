<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A planned event in a 1:1 Naara chat (B13, MASTER-ONLY). Times are UTC. */
class Event extends Model
{
    protected $table = 'n2n_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class, 'event_id');
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->addHour();
    }
}
