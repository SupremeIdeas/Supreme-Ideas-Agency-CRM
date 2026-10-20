<?php

namespace App\Models\JourneyEngine;

use Illuminate\Database\Eloquent\Model;

/** Per-channel, per-category consent. A channel with no granted row is OFF: nothing is pushed, emailed or messaged without it (in-app is always allowed). */
class JourneyChannelConsent extends Model
{
    protected $fillable = ['user_id', 'channel', 'category', 'granted_at', 'revoked_at', 'source'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function active(): bool
    {
        return $this->granted_at !== null && $this->revoked_at === null;
    }
}
