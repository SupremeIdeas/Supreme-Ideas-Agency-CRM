<?php

namespace App\Models\JourneyEngine;

use Illuminate\Database\Eloquent\Model;

/** A recorded push or email of a protective alert (J2). */
class JourneyDelivery extends Model
{
    protected $fillable = ['user_id', 'journey_card_id', 'channel', 'rule_key', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
