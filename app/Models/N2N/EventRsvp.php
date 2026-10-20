<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One member's answer to an event (B13, MASTER-ONLY). */
class EventRsvp extends Model
{
    public const GOING = 'going';

    public const MAYBE = 'maybe';

    public const DECLINED = 'declined';

    public const PENDING = 'no_response';

    protected $table = 'n2n_event_rsvps';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'reminded_at' => 'datetime'];
    }
}
